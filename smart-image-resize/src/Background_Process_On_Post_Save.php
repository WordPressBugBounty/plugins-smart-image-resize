<?php

namespace WP_Smart_Image_Resize;

use WP_Smart_Image_Resize\Singleton_Trait;

final class Background_Process_On_Post_Save {

    use Singleton_Trait;

    const JOB_HOOK  = 'wp_sir_process_image';
    const JOB_GROUP = 'wp_sir_process_on_post_save';

    /** Transient key — recently-processed attachment IDs (array of ints). */
    const FEEDBACK_TRANSIENT = 'wp_sir_processed_feedback';

    /** Transient key — remove.bg failures: array of ['id'=>int,'message'=>string]. */
    const BG_FAIL_TRANSIENT  = 'wp_sir_bg_removal_failed';

    /** How long (seconds) feedback transients survive. */
    const FEEDBACK_TTL = 120;

    public function init() {
        add_action( 'save_post',                          [ $this, 'maybe_queue_image' ],            10, 2 );
        add_action( 'added_post_meta',                    [ $this, 'maybe_queue_image_postmeta' ],   10, 3 );
        add_action( 'updated_post_meta',                  [ $this, 'maybe_queue_image_postmeta' ],   10, 3 );
        add_action( 'wp_sir_process_image',               [ $this, 'process_image' ] );
        add_action( 'woocommerce_save_product_variation', [ $this, 'process_product_variation_image' ] );

        // Record async feedback so admin notices can surface results.
        add_action( 'wp_sir_image_processed',   [ $this, 'record_processed_feedback' ], 10, 1 );
        add_action( 'wp_sir_bg_removal_failed', [ $this, 'record_bg_removal_failure' ],  10, 2 );
    }

    // ------------------------------------------------------------------
    // Feedback transients
    // ------------------------------------------------------------------

    public function record_processed_feedback( $image_id ) {
        $ids   = (array) get_transient( self::FEEDBACK_TRANSIENT );
        $ids[] = (int) $image_id;
        set_transient( self::FEEDBACK_TRANSIENT, array_unique( $ids ), self::FEEDBACK_TTL );
    }

    public function record_bg_removal_failure( $image_id, $error ) {
        $entries   = (array) get_transient( self::BG_FAIL_TRANSIENT );
        $entries[] = [
            'id'      => (int) $image_id,
            'message' => $error instanceof \WP_Error
                ? $error->get_error_message()
                : __( 'Unknown error', 'wp-smart-image-resize' ),
        ];
        set_transient( self::BG_FAIL_TRANSIENT, $entries, self::FEEDBACK_TTL );
    }

    // ------------------------------------------------------------------
    // Image processing
    // ------------------------------------------------------------------

    public function process_product_variation_image( $product_id ) {
        if ( ! $this->_is_background_processing_allowed() ) {
            return;
        }

        $process_post_types = \wp_sir_get_processable_post_types();

        if ( ! in_array( \get_post_type( $product_id ), $process_post_types ) ) {
            return;
        }

        $this->_push_to_queue( \get_post_thumbnail_id( $product_id ) );
    }

    /**
     * Run thumbnail regeneration for a single image (Action Scheduler job).
     *
     * @param int $image_id
     */
    public function process_image( $image_id ) {
        if ( ! $this->_is_background_processing_allowed() ) {
            return;
        }

        $file_path = \get_attached_file( $image_id );

        if ( ! is_readable( $file_path ) ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Force isProcessable() to return true for this image — the async job
        // is an explicit, authoritative request so the upload-context checks
        // that guard normal requests should not block it.
        $force_processable = function( $is_processable, $checked_id ) use ( $image_id ) {
            return ( (int) $checked_id === (int) $image_id ) ? true : $is_processable;
        };
        add_filter( 'wp_sir_is_image_processable', $force_processable, 10, 2 );

        // Slow/blocking calls (remove.bg API) are safe here — we're async.
        //
        // A single pass is sufficient: when background removal runs in this
        // blocking context, Image_Editor::processImage() removes the background
        // AND generates all thumbnails from the resulting cutout in the same
        // pass (maybeRemoveBackground() returns false and the pipeline continues
        // against the swapped-in cutout path).
        //
        // A second wp_generate_attachment_metadata() pass here was previously
        // used to "regenerate from the cutout", but it is redundant and
        // actively harmful: on the second pass the "already processed" restore
        // branch in processImage() fires, calls Backup::restore() on the cutout
        // (which unlinks the backup after copying), and — with PNG→JPG
        // conversion enabled — deletes the working file too, destroying the
        // backups and the original. Do NOT reintroduce a second pass.
        Image_Editor::$allow_blocking_calls = true;
        $meta = \wp_generate_attachment_metadata( $image_id, $file_path );
        Image_Editor::$allow_blocking_calls = false;
        remove_filter( 'wp_sir_is_image_processable', $force_processable, 10 );

        if ( ! empty( $meta ) && is_array( $meta ) && isset( $meta['_processed_at'] ) ) {
            \wp_update_attachment_metadata( $image_id, $meta );
        }
    }

    /**
     * Queue a single image immediately, bypassing the "already processed" guard.
     *
     * @param int $image_id
     */
    public function queue_image_now( $image_id ) {
        if ( ! $this->_is_background_processing_allowed() || ! $this->_is_as_available() ) {
            return;
        }

        $job_args = [ 'image_id' => (int) $image_id ];

        if ( \as_has_scheduled_action( self::JOB_HOOK, $job_args, self::JOB_GROUP ) ) {
            return;
        }

        \as_enqueue_async_action( self::JOB_HOOK, $job_args, self::JOB_GROUP );
    }

    /**
     * Force-reprocess a single image, clearing the processed flag first.
     * Used by the "Reprocess" media library action.
     *
     * @param int $image_id
     */
    public function reprocess_image( $image_id ) {
        $image_id = (int) $image_id;

        if ( ! $this->_is_background_processing_allowed() || ! $this->_is_as_available() ) {
            return;
        }

        delete_post_meta( $image_id, '_processed_at' );
        delete_post_meta( $image_id, '_processed_by' );

        $job_args = [ 'image_id' => $image_id ];

        if ( \as_has_scheduled_action( self::JOB_HOOK, $job_args, self::JOB_GROUP ) ) {
            \as_unschedule_action( self::JOB_HOOK, $job_args, self::JOB_GROUP );
        }

        \as_enqueue_async_action( self::JOB_HOOK, $job_args, self::JOB_GROUP );
    }

    // ------------------------------------------------------------------
    // Queue triggers
    // ------------------------------------------------------------------

    public function maybe_queue_image_postmeta( $meta_id, $object_id, $meta_key ) {
        if ( $meta_key !== '_thumbnail_id' ) {
            return;
        }

        if ( ! $this->_is_background_processing_allowed() || ! $this->_is_as_available() ) {
            return;
        }

        $postmeta = \get_metadata_by_mid( 'post', $meta_id );

        if ( ! $postmeta ) {
            return;
        }

        $post_type          = \get_post_type( $postmeta->post_id );
        $process_post_types = \wp_sir_get_processable_post_types();

        if ( ! in_array( $post_type, $process_post_types, true ) ) {
            return;
        }

        $this->_push_to_queue( $postmeta->meta_value );
    }

    public function maybe_queue_image( $post_id, $post ) {
        if ( ! $this->_is_background_processing_allowed() || ! $this->_is_as_available() ) {
            return;
        }

        $process_post_types = \wp_sir_get_processable_post_types();

        if ( ! in_array( $post->post_type, $process_post_types, true ) ) {
            return;
        }

        $image_ids = [];

        if ( $main_image_id = \get_post_thumbnail_id( $post ) ) {
            $image_ids[] = $main_image_id;
        }

        if ( $post->post_type === 'product' && function_exists( '\wc_get_product' ) ) {
            $product       = \wc_get_product( $post_id );
            $variation_ids = $product ? $product->get_children() : [];

            foreach ( $variation_ids as $variation_id ) {
                $image_ids[] = \get_post_thumbnail_id( $variation_id );
            }

            if ( method_exists( $product, 'get_gallery_image_ids' ) ) {
                $image_ids = array_merge( $image_ids, $product->get_gallery_image_ids() );
            } elseif ( method_exists( $product, 'get_gallery_attachment_ids' ) ) {
                $image_ids = array_merge( $image_ids, $product->get_gallery_attachment_ids() );
            }
        }

        $this->_push_to_queue( $image_ids );
    }

    private function _push_to_queue( $image_ids ) {
        $image_ids = array_unique( array_filter( (array) $image_ids ) );

        if ( empty( $image_ids ) ) {
            return;
        }

        foreach ( $image_ids as $image_id ) {
            if ( \get_post_meta( $image_id, '_processed_at', true ) ) {
                continue;
            }

            $job_args  = compact( 'image_id' );
            $is_queued = \as_has_scheduled_action( self::JOB_HOOK, $job_args, self::JOB_GROUP );

            if ( $is_queued ) {
                \as_unschedule_action( self::JOB_HOOK, $job_args, self::JOB_GROUP );
            }

            \as_enqueue_async_action( self::JOB_HOOK, $job_args, self::JOB_GROUP );
        }
    }

    // ------------------------------------------------------------------
    // Guards
    // ------------------------------------------------------------------

    private function _is_background_processing_allowed() {
        return apply_filters( 'wp_sir_allow_background_processing', true );
    }

    private function _is_as_available() {
        return function_exists( '\as_has_scheduled_action' )
            && function_exists( '\as_unschedule_action' )
            && function_exists( '\as_enqueue_async_action' );
    }
}

Background_Process_On_Post_Save::instance()->init();
