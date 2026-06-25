<?php
/**
 * Bulk Image Processor template.
 *
 * @package WP_Smart_Image_Resize
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wp-sir-bulk-wrap" id="wp-sir-bulk-wrap">

    <div class="wp-sir-layout">

        <!-- ── MAIN COLUMN ─────────────────────────────────────── -->
        <div class="wp-sir-main">

            <!-- ── Status card ──────────────────────────────────────────────────── -->
            <div class="wp-sir-card wp-sir-bulk-card" id="wp-sir-bulk-status-card">

                <!-- Idle (count rendered server-side from cache) -->
                <div class="wp-sir-bulk-state" id="wp-sir-state-idle">
                    <div class="wp-sir-bulk-idle-icon" aria-hidden="true">
                        <span class="dashicons dashicons-images-alt2"></span>
                    </div>
                    <h2 class="wp-sir-bulk-title"><?php esc_html_e( 'Resize Existing Images', 'wp-smart-image-resize' ); ?></h2>
                    <p class="wp-sir-bulk-desc">
                        <?php esc_html_e( 'Resize and optimize your existing images. New uploads are handled automatically.', 'wp-smart-image-resize' ); ?>
                    </p>
                    <?php
                    $cached_count = get_transient( \WP_Smart_Image_Resize\Bulk_Processor::CACHE_COUNT_KEY );
                    if ( false === $cached_count ) {
                        $count_text = __( 'Checking image count…', 'wp-smart-image-resize' );
                        $btn_disabled = true;
                    } elseif ( (int) $cached_count > 0 ) {
                        $count_text = sprintf(
                            _n( '%s image ready to be processed.', '%s images ready to be processed.', (int) $cached_count, 'wp-smart-image-resize' ),
                            number_format_i18n( (int) $cached_count )
                        );
                        $btn_disabled = false;
                    } else {
                        $count_text = __( 'All your images are already up to date.', 'wp-smart-image-resize' );
                        $btn_disabled = true;
                    }
                    ?>
                    <p class="wp-sir-bulk-idle-text" id="wp-sir-idle-message">
                        <?php echo esc_html( $count_text ); ?>
                        <a href="#" class="wp-sir-refresh-count" id="wp-sir-refresh-count" title="<?php esc_attr_e( 'Refresh count', 'wp-smart-image-resize' ); ?>"><span class="dashicons dashicons-update"></span></a>
                    </p>
                    <button type="button" class="button button-primary wp-sir-bulk-btn wp-sir-bulk-btn--start" id="wp-sir-bulk-start" <?php echo $btn_disabled ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
                        <?php esc_html_e( 'Start Processing', 'wp-smart-image-resize' ); ?>
                    </button>
                    <p class="wp-sir-bulk-idle-hint" id="wp-sir-idle-hint">
                        <?php printf(
                            esc_html__( 'Want to customize? %sOpen Settings%s', 'wp-smart-image-resize' ),
                            '<a href="' . esc_url( admin_url( 'admin.php?page=' . WP_SIR_NAME . '&tab=general' ) ) . '">',
                            '</a>'
                        ); ?>
                    </p>
                </div>

                <!-- Running / paused -->
                <div class="wp-sir-bulk-state" id="wp-sir-state-active" style="display:none">
                    <div class="wp-sir-bulk-progress-header">
                        <span class="wp-sir-bulk-status-label" id="wp-sir-status-label">
                            <?php esc_html_e( 'Processing…', 'wp-smart-image-resize' ); ?>
                        </span>
                        <span class="wp-sir-bulk-counts">
                            <span id="wp-sir-done-count">0</span>
                            <span class="wp-sir-bulk-counts__sep">/</span>
                            <span id="wp-sir-total-count">0</span>
                            <?php esc_html_e( 'images', 'wp-smart-image-resize' ); ?>
                        </span>
                    </div>
                    <div class="wp-sir-bulk-progress-bar-wrap" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="wp-sir-progress-bar-wrap">
                        <div class="wp-sir-bulk-progress-bar" id="wp-sir-progress-bar"></div>
                    </div>
                    <p class="wp-sir-bulk-percent" id="wp-sir-percent-label">0%</p>
                    <div class="wp-sir-bulk-actions">
                        <button type="button" class="button button-secondary wp-sir-bulk-btn" id="wp-sir-bulk-pause">
                            <span class="dashicons dashicons-controls-pause" aria-hidden="true"></span>
                            <?php esc_html_e( 'Pause', 'wp-smart-image-resize' ); ?>
                        </button>
                        <button type="button" class="button button-primary wp-sir-bulk-btn" id="wp-sir-bulk-resume">
                            <span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
                            <?php esc_html_e( 'Resume', 'wp-smart-image-resize' ); ?>
                        </button>
                        <button type="button" class="button button-link-delete wp-sir-bulk-btn wp-sir-bulk-btn--abort" id="wp-sir-bulk-abort">
                            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                            <?php esc_html_e( 'Abort', 'wp-smart-image-resize' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Done -->
                <div class="wp-sir-bulk-state" id="wp-sir-state-done" style="display:none">
                    <div class="wp-sir-bulk-done-icon" aria-hidden="true">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <h3><?php esc_html_e( 'All done!', 'wp-smart-image-resize' ); ?></h3>
                    <p id="wp-sir-done-summary" class="wp-sir-bulk-done-summary"></p>
                    <div class="wp-sir-bulk-actions">
                        <button type="button" class="button button-secondary wp-sir-bulk-btn" id="wp-sir-bulk-restart">
                            <span class="dashicons dashicons-update" aria-hidden="true"></span>
                            <?php esc_html_e( 'Process Again', 'wp-smart-image-resize' ); ?>
                        </button>
                    </div>
                </div>

            </div><!-- /.wp-sir-bulk-card -->

            <!-- ── Error log ────────────────────────────────────────────────────── -->
            <div class="wp-sir-bulk-error-log" id="wp-sir-error-log" style="display:none">
                <div class="wp-sir-bulk-error-log-header" id="wp-sir-error-log-toggle" role="button" tabindex="0" aria-expanded="false" aria-controls="wp-sir-error-log-body">
                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                    <strong id="wp-sir-error-log-title"><?php esc_html_e( 'Skipped images', 'wp-smart-image-resize' ); ?></strong>
                    <span class="wp-sir-error-log-chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                </div>
                <div class="wp-sir-bulk-error-log-body" id="wp-sir-error-log-body" style="display:none">
                    <table class="wp-sir-error-table">
                        <thead><tr>
                            <th><?php esc_html_e( 'File', 'wp-smart-image-resize' ); ?></th>
                            <th><?php esc_html_e( 'Reason', 'wp-smart-image-resize' ); ?></th>
                        </tr></thead>
                        <tbody id="wp-sir-error-table-body"></tbody>
                    </table>
                </div>
            </div>

            <!-- ── Tips ─────────────────────────────────────────────────────────── -->
            <div class="wp-sir-bulk-tips">
                <h4 class="wp-sir-bulk-tips__heading">
                    <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                    <?php esc_html_e( 'Good to know', 'wp-smart-image-resize' ); ?>
                </h4>
                <ul class="wp-sir-bulk-tips__list">
                    <li><?php esc_html_e( 'New images you upload will be resized automatically — no need to run this again unless you change settings.', 'wp-smart-image-resize' ); ?></li>
                    <li><?php esc_html_e( 'You can pause and resume at any time — progress is saved automatically.', 'wp-smart-image-resize' ); ?></li>
                    <li><?php esc_html_e( 'After processing, clear your browser and caching plugin cache to see updated images.', 'wp-smart-image-resize' ); ?></li>
                    <li><?php printf(
                        esc_html__( 'Having trouble? Try the %s plugin as a fallback for bulk resizing.', 'wp-smart-image-resize' ),
                        '<a href="' . esc_url( admin_url( 'plugin-install.php?s=regenerate+thumbnails&tab=search&type=term' ) ) . '" target="_blank">Regenerate Thumbnails</a>'
                    ); ?></li>
                </ul>
            </div>

        </div><!-- /.wp-sir-main -->

        <!-- ── SIDEBAR ──────────────────────────────────────────── -->
        <?php include WP_SIR_DIR . 'templates/partials/sidebar.php'; ?>

    </div><!-- /.wp-sir-layout -->

</div><!-- /.wp-sir-bulk-wrap -->
