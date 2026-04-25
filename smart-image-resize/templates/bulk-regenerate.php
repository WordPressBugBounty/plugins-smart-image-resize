<?php
/**
 * Bulk Image Processor template — redesigned to match new admin UI.
 *
 * @package WP_Smart_Image_Resize
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wp-sir-bulk-wrap" id="wp-sir-bulk-wrap">

    <!-- ── Page header ──────────────────────────────────────────────────── -->
    <div class="wp-sir-bulk-page-header">
        <div class="wp-sir-bulk-page-header__icon" aria-hidden="true">
            <span class="dashicons dashicons-update"></span>
        </div>
        <div>
            <h2>
                <?php esc_html_e( 'Bulk Regenerate Images', 'wp-smart-image-resize' ); ?>
                <span class="wp-sir-beta-badge"><?php esc_html_e( 'Beta', 'wp-smart-image-resize' ); ?></span>
            </h2>
            <p class="wp-sir-bulk-page-header__desc">
                <?php esc_html_e( 'Apply your current settings to all existing images. You can pause and resume at any time — progress is saved automatically.', 'wp-smart-image-resize' ); ?>
            </p>
        </div>
    </div>

    <!-- ── Status card ──────────────────────────────────────────────────── -->
    <div class="wp-sir-card wp-sir-bulk-card" id="wp-sir-bulk-status-card">

        <!-- Idle state -->
        <div class="wp-sir-bulk-state" id="wp-sir-state-idle">
            <div class="wp-sir-bulk-idle-icon" aria-hidden="true">
                <span class="dashicons dashicons-images-alt2"></span>
            </div>
            <p class="wp-sir-bulk-idle-text">
                <?php esc_html_e( 'Ready to process. Click the button below to start.', 'wp-smart-image-resize' ); ?>
            </p>
            <button type="button" class="button button-primary wp-sir-bulk-btn wp-sir-bulk-btn--start" id="wp-sir-bulk-start">
                <span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
                <?php esc_html_e( 'Start Processing', 'wp-smart-image-resize' ); ?>
            </button>
        </div>

        <!-- Running / paused state -->
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

            <div class="wp-sir-bulk-progress-bar-wrap"
                 role="progressbar"
                 aria-valuemin="0"
                 aria-valuemax="100"
                 aria-valuenow="0"
                 id="wp-sir-progress-bar-wrap">
                <div class="wp-sir-bulk-progress-bar" id="wp-sir-progress-bar"></div>
            </div>

            <p class="wp-sir-bulk-percent" id="wp-sir-percent-label">0%</p>

            <div class="wp-sir-bulk-actions">
                <button type="button" class="button button-secondary wp-sir-bulk-btn" id="wp-sir-bulk-pause" style="display:none">
                    <span class="dashicons dashicons-controls-pause" aria-hidden="true"></span>
                    <?php esc_html_e( 'Pause', 'wp-smart-image-resize' ); ?>
                </button>
                <button type="button" class="button button-primary wp-sir-bulk-btn" id="wp-sir-bulk-resume" style="display:none">
                    <span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
                    <?php esc_html_e( 'Resume', 'wp-smart-image-resize' ); ?>
                </button>
                <button type="button" class="button button-link-delete wp-sir-bulk-btn wp-sir-bulk-btn--abort" id="wp-sir-bulk-abort" style="display:none">
                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                    <?php esc_html_e( 'Abort', 'wp-smart-image-resize' ); ?>
                </button>
            </div>

        </div>

        <!-- Done state -->
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
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WP_SIR_NAME ) ); ?>"
                   class="button button-secondary">
                    <?php esc_html_e( '← Back to Settings', 'wp-smart-image-resize' ); ?>
                </a>
            </div>
        </div>

    </div><!-- /.wp-sir-card.wp-sir-bulk-card -->

    <!-- ── Error log ────────────────────────────────────────────────────── -->
    <div class="wp-sir-bulk-error-log" id="wp-sir-error-log" style="display:none">
        <div class="wp-sir-bulk-error-log-header"
             id="wp-sir-error-log-toggle"
             role="button"
             tabindex="0"
             aria-expanded="false"
             aria-controls="wp-sir-error-log-body">
            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
            <strong id="wp-sir-error-log-title"><?php esc_html_e( 'Skipped images', 'wp-smart-image-resize' ); ?></strong>
            <span class="wp-sir-error-log-chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
        </div>
        <div class="wp-sir-bulk-error-log-body" id="wp-sir-error-log-body" style="display:none">
            <table class="wp-sir-error-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'File', 'wp-smart-image-resize' ); ?></th>
                        <th><?php esc_html_e( 'Reason', 'wp-smart-image-resize' ); ?></th>
                    </tr>
                </thead>
                <tbody id="wp-sir-error-table-body"></tbody>
            </table>
        </div>
    </div>

    <!-- ── Tips ─────────────────────────────────────────────────────────── -->
    <div class="wp-sir-bulk-tips">
        <h4 class="wp-sir-bulk-tips__heading">
            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
            <?php esc_html_e( 'Tips', 'wp-smart-image-resize' ); ?>
        </h4>
        <ul class="wp-sir-bulk-tips__list">
            <li><?php esc_html_e( 'Keep this tab open while processing. The page does not need to stay in focus.', 'wp-smart-image-resize' ); ?></li>
            <li><?php esc_html_e( 'If you close the tab, click "Resume" when you return — progress is saved.', 'wp-smart-image-resize' ); ?></li>
            <li><?php esc_html_e( 'After processing, clear your browser and caching plugin cache to see updated images.', 'wp-smart-image-resize' ); ?></li>
            <li><?php esc_html_e( 'Run this again after changing your settings to apply them to existing images.', 'wp-smart-image-resize' ); ?></li>
            <li><?php
                printf(
                    /* translators: %s: link to Regenerate Thumbnails plugin */
                    esc_html__( 'Having trouble? The %s plugin is a reliable fallback for regenerating images.', 'wp-smart-image-resize' ),
                    '<a href="' . esc_url( admin_url( 'plugin-install.php?s=regenerate+thumbnails&tab=search&type=term' ) ) . '" target="_blank">Regenerate Thumbnails</a>'
                );
            ?></li>
        </ul>
    </div>

</div><!-- /.wp-sir-bulk-wrap -->
