<?php

namespace WP_Smart_Image_Resize;

/**
 * Built-in bulk image processor — dedicated DB tables.
 *
 * Schema
 * ──────
 * {prefix}sir_bulk_queue
 *   id          BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY
 *   position    BIGINT UNSIGNED  NOT NULL  — zero-based order, indexed
 *   image_id    BIGINT UNSIGNED  NOT NULL
 *
 * {prefix}sir_bulk_errors
 *   id          BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY
 *   image_id    BIGINT UNSIGNED  NOT NULL
 *   filename    VARCHAR(255)     NOT NULL
 *   reason      TEXT             NOT NULL
 *   created_at  DATETIME         NOT NULL
 *
 * State (status, counters, timestamps) stays in a single lightweight option
 * `wp_sir_bulk_state` — it is a tiny scalar object, never grows, and is the
 * only thing written on every batch tick.
 */
class Bulk_Processor {

    use Singleton_Trait;

    const DB_VERSION    = 1;
    const OPT_DB_VER    = 'wp_sir_bulk_db_version';
    const OPT_STATE     = 'wp_sir_bulk_state';

    // Legacy option keys from previous designs — removed on install/reset.
    const OPT_LEGACY    = 'wp_sir_bulk_progress';
    const OPT_LEGACY_Q  = 'wp_sir_bulk_queue';
    const OPT_LEGACY_E  = 'wp_sir_bulk_errors';

    const BATCH_SIZE    = 5;
    const AJAX_START    = 'wp_sir_bulk_start';
    const AJAX_PROCESS  = 'wp_sir_bulk_process';
    const AJAX_PAUSE    = 'wp_sir_bulk_pause';
    const AJAX_RESET    = 'wp_sir_bulk_reset';
    const AJAX_STATUS   = 'wp_sir_bulk_status';

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public function init() {
        add_action( 'wp_ajax_' . self::AJAX_START,   [ $this, 'ajax_start' ] );
        add_action( 'wp_ajax_' . self::AJAX_PROCESS, [ $this, 'ajax_process_batch' ] );
        add_action( 'wp_ajax_' . self::AJAX_PAUSE,   [ $this, 'ajax_pause' ] );
        add_action( 'wp_ajax_' . self::AJAX_RESET,   [ $this, 'ajax_reset' ] );
        add_action( 'wp_ajax_' . self::AJAX_STATUS,  [ $this, 'ajax_status' ] );
    }

    /**
     * Create / upgrade tables.
     * Called from the plugin activation hook and from Plugin::maybe_upgrade().
     */
    public static function install() {
        global $wpdb;

        if ( (int) get_option( self::OPT_DB_VER, 0 ) >= self::DB_VERSION ) {
            return;
        }

        $charset = $wpdb->get_charset_collate();

        $queue_table = $wpdb->prefix . 'sir_bulk_queue';
        $error_table = $wpdb->prefix . 'sir_bulk_errors';

        $sql = "
            CREATE TABLE {$queue_table} (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                position   BIGINT UNSIGNED NOT NULL,
                image_id   BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                KEY position (position)
            ) {$charset};

            CREATE TABLE {$error_table} (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                image_id   BIGINT UNSIGNED NOT NULL,
                filename   VARCHAR(255)    NOT NULL DEFAULT '',
                reason     TEXT            NOT NULL,
                created_at DATETIME        NOT NULL,
                PRIMARY KEY (id),
                KEY image_id (image_id)
            ) {$charset};
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::OPT_DB_VER, self::DB_VERSION );

        // Remove legacy option-based data if present.
        delete_option( self::OPT_LEGACY );
        delete_option( self::OPT_LEGACY_Q );
        delete_option( self::OPT_LEGACY_E );
    }

    /**
     * Drop tables on plugin uninstall.
     * Called from uninstall.php (create that file if it doesn't exist).
     */
    public static function uninstall() {
        global $wpdb;
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sir_bulk_queue" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sir_bulk_errors" );
        // phpcs:enable
        delete_option( self::OPT_STATE );
        delete_option( self::OPT_DB_VER );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function ajax_start() {
        $this->check_request();

        $restart = ! empty( $_POST['restart'] );
        $state   = $this->get_state();

        if ( $restart || ! $state || $state['status'] === 'done' ) {
            $ids   = $this->get_processable_ids();
            $total = count( $ids );

            $this->truncate_queue();
            $this->insert_queue( $ids );
            $this->truncate_errors();

            $state = $this->make_state( 'running', $total );
            $this->save_state( $state );

            wp_send_json_success( $this->public_state( $state ) );
        }

        if ( $state['status'] === 'paused' ) {
            $state['status'] = 'running';
            $this->save_state( $state );
        }

        wp_send_json_success( $this->public_state( $state ) );
    }

    public function ajax_process_batch() {
        $this->check_request();

        $state = $this->get_state();

        if ( ! $state || $state['status'] !== 'running' ) {
            wp_send_json_error( [ 'message' => 'No active bulk process.' ] );
        }

        $offset = (int) $state['offset'];
        $batch  = $this->fetch_queue_batch( $offset, self::BATCH_SIZE );

        $batch_errors = [];

        if ( empty( $batch ) ) {
            $state['status']      = 'done';
            $state['finished_at'] = current_time( 'mysql' );
            $this->save_state( $state );
            wp_send_json_success( $this->public_state( $state ) );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        foreach ( $batch as $image_id ) {
            $image_id  = (int) $image_id;
            $file_path = get_attached_file( $image_id );
            $filename  = $file_path ? basename( $file_path ) : "ID {$image_id}";

            if ( ! $file_path || ! is_readable( $file_path ) ) {
                $reason = $file_path
                    ? sprintf( 'File not found or not readable: %s', $filename )
                    : 'No file path registered for this attachment.';

                $batch_errors[] = [ 'id' => $image_id, 'file' => $filename, 'reason' => $reason ];
                $state['error_count']++;
                $state['done']++;
                $state['offset']++;
                continue;
            }

            try {
                @set_time_limit( 60 );
                $meta = wp_generate_attachment_metadata( $image_id, $file_path );
                if ( ! empty( $meta ) && is_array( $meta ) ) {
                    wp_update_attachment_metadata( $image_id, $meta );
                } else {
                    $reason         = 'Image was skipped — it may not match your processable image settings, or the free quota has been reached.';
                    $batch_errors[] = [ 'id' => $image_id, 'file' => $filename, 'reason' => $reason ];
                    $state['error_count']++;
                }
            } catch ( \Exception $e ) {
                $batch_errors[] = [ 'id' => $image_id, 'file' => $filename, 'reason' => $e->getMessage() ];
                $state['error_count']++;
            }

            $state['done']++;
            $state['offset']++;
        }

        if ( $state['offset'] >= $state['total'] ) {
            $state['status']      = 'done';
            $state['finished_at'] = current_time( 'mysql' );
        }

        if ( ! empty( $batch_errors ) ) {
            $this->insert_errors( $batch_errors );
        }

        // Only the tiny state option is written on every tick.
        $this->save_state( $state );

        wp_send_json_success( $this->public_state( $state, $batch_errors ) );
    }

    public function ajax_pause() {
        $this->check_request();

        $state = $this->get_state();
        if ( $state && $state['status'] === 'running' ) {
            $state['status'] = 'paused';
            $this->save_state( $state );
        }

        wp_send_json_success( $this->public_state( $state ?: [] ) );
    }

    public function ajax_reset() {
        $this->check_request();
        $this->truncate_queue();
        $this->truncate_errors();
        delete_option( self::OPT_STATE );
        wp_send_json_success( [ 'status' => 'idle' ] );
    }

    public function ajax_status() {
        $this->check_request();
        $state      = $this->get_state();
        $all_errors = $state ? $this->fetch_all_errors() : [];
        wp_send_json_success( $this->public_state( $state ?: [], $all_errors ) );
    }

    // -------------------------------------------------------------------------
    // Queue table helpers
    // -------------------------------------------------------------------------

    /**
     * Bulk-insert IDs into the queue table using chunked multi-row INSERTs.
     * A single INSERT per chunk keeps query count low even for 50k images.
     *
     * @param int[] $ids
     */
    private function insert_queue( array $ids ) {
        global $wpdb;

        if ( empty( $ids ) ) {
            return;
        }

        $table      = $wpdb->prefix . 'sir_bulk_queue';
        $chunk_size = 500; // rows per INSERT statement
        $position   = 0;

        foreach ( array_chunk( $ids, $chunk_size ) as $chunk ) {
            $placeholders = [];
            $values       = [];

            foreach ( $chunk as $image_id ) {
                $placeholders[] = '(%d, %d)';
                $values[]       = $position++;
                $values[]       = (int) $image_id;
            }

            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (position, image_id) VALUES "
                    . implode( ', ', $placeholders ),
                    $values
                )
            );
            // phpcs:enable
        }
    }

    /**
     * Fetch a batch of image IDs from the queue by position range.
     * Uses a single indexed range query — O(log n + batch_size).
     *
     * @param int $offset
     * @param int $limit
     * @return int[]
     */
    private function fetch_queue_batch( int $offset, int $limit ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sir_bulk_queue';

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT image_id FROM {$table} WHERE position >= %d ORDER BY position ASC LIMIT %d",
                $offset,
                $limit
            )
        );
        // phpcs:enable

        return array_map( 'intval', $rows );
    }

    private function truncate_queue() {
        global $wpdb;
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}sir_bulk_queue" );
        // phpcs:enable
    }

    // -------------------------------------------------------------------------
    // Error table helpers
    // -------------------------------------------------------------------------

    /**
     * @param array[] $entries  Each: ['id' => int, 'file' => string, 'reason' => string]
     */
    private function insert_errors( array $entries ) {
        global $wpdb;

        $table = $wpdb->prefix . 'sir_bulk_errors';
        $now   = current_time( 'mysql' );

        foreach ( $entries as $e ) {
            $wpdb->insert(
                $table,
                [
                    'image_id'   => (int) $e['id'],
                    'filename'   => substr( (string) $e['file'],   0, 255 ),
                    'reason'     => (string) $e['reason'],
                    'created_at' => $now,
                ],
                [ '%d', '%s', '%s', '%s' ]
            );
        }
    }

    /**
     * Fetch all errors for the current run — called only on status load,
     * not on every batch tick.
     *
     * @return array[]
     */
    private function fetch_all_errors(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sir_bulk_errors';

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            "SELECT image_id AS id, filename AS file, reason FROM {$table} ORDER BY id ASC",
            ARRAY_A
        );
        // phpcs:enable

        return $rows ?: [];
    }

    private function truncate_errors() {
        global $wpdb;
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}sir_bulk_errors" );
        // phpcs:enable
    }

    // -------------------------------------------------------------------------
    // State option helpers  (tiny scalar, written every tick)
    // -------------------------------------------------------------------------

    private function get_state() {
        $data = get_option( self::OPT_STATE );
        return is_array( $data ) ? $data : null;
    }

    private function save_state( array $state ) {
        update_option( self::OPT_STATE, $state, false );
    }

    private function make_state( string $status, int $total ): array {
        return [
            'status'      => $status,
            'total'       => $total,
            'done'        => 0,
            'offset'      => 0,
            'error_count' => 0,
            'started_at'  => current_time( 'mysql' ),
            'finished_at' => null,
        ];
    }

    // -------------------------------------------------------------------------
    // Misc helpers
    // -------------------------------------------------------------------------

    private function check_request() {
        check_ajax_referer( 'wp-sir-ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }
    }

    private function get_processable_ids(): array {
        $filter = new Filters\Filter_Processable_Regenerate_Thumbnails();
        $ids    = $filter->filter_processable_images();
        return array_values( array_map( 'intval', array_unique( array_filter( $ids ) ) ) );
    }

    /**
     * Build the payload sent to the JS client.
     * The queue table is never touched here — only scalar state + new errors.
     *
     * @param array $state
     * @param array $batch_errors  Errors from this tick, or full list on status load.
     */
    private function public_state( array $state, array $batch_errors = [] ): array {
        return [
            'status'       => $state['status']      ?? 'idle',
            'total'        => $state['total']        ?? 0,
            'done'         => $state['done']         ?? 0,
            'error_count'  => $state['error_count']  ?? 0,
            'offset'       => $state['offset']       ?? 0,
            'started_at'   => $state['started_at']   ?? null,
            'finished_at'  => $state['finished_at']  ?? null,
            'new_errors'   => array_values( $batch_errors ),
        ];
    }
}
