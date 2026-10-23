<?php
/**
 * Sidebar partial — shared across settings and bulk resize pages.
 *
 * @package WP_Smart_Image_Resize
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<aside class="wp-sir-sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'wp-smart-image-resize' ); ?>">
    <div class="wpsirInfoBox">
        <div class="wpsirInfoBox-hero">
            <span class="dashicons dashicons-superhero-alt wpsirInfoBox-hero__icon" aria-hidden="true"></span>
            <h3 class="wpsirInfoBox-hero__title"><?php esc_html_e( 'Go Pro', 'wp-smart-image-resize' ); ?></h3>
            <p class="wpsirInfoBox-hero__desc"><?php esc_html_e( 'Remove limits and unlock the full toolkit.', 'wp-smart-image-resize' ); ?></p>
        </div>
        <div class="wpsirInfoBox-tracker">
            <?php
            $processed = \WP_Smart_Image_Resize\Process_Tracker::get_total_processed();
            $total     = \WP_Smart_Image_Resize\Process_Tracker::get_max_processed();
            $pct       = min( 100, (int) round( ( $processed / $total ) * 100 ) );
            $at_limit  = \WP_Smart_Image_Resize\Process_Tracker::has_reached_limit();
            ?>
            <div class="wpsirInfoBox-usage">
                <div class="wpsirInfoBox-usage__header">
                    <span class="wpsirInfoBox-usage__label"><?php esc_html_e( 'Image usage', 'wp-smart-image-resize' ); ?></span>
                    <span class="wpsirInfoBox-usage__count"><?php echo esc_html( $processed ); ?> / <?php echo esc_html( $total ); ?></span>
                </div>
                <div class="wpsirInfoBox-usage__bar">
                    <div class="wpsirInfoBox-usage__fill <?php echo $at_limit ? 'wpsirInfoBox-usage__fill--full' : ''; ?>" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
                </div>
                <?php if ( $at_limit ) : ?>
                    <span class="wpsirInfoBox-usage__note"><?php esc_html_e( 'Limit reached', 'wp-smart-image-resize' ); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <ul class="wpsirInfoBox-features">
            <li><span class="dashicons dashicons-images-alt2" aria-hidden="true"></span><?php esc_html_e( 'Unlimited images', 'wp-smart-image-resize' ); ?></li>
            <li><span class="dashicons dashicons-performance" aria-hidden="true"></span><?php esc_html_e( 'WebP & PNG → JPG', 'wp-smart-image-resize' ); ?></li>
            <li><span class="dashicons dashicons-shield" aria-hidden="true"></span><?php esc_html_e( 'Watermark protection', 'wp-smart-image-resize' ); ?></li>
            <li><span class="dashicons dashicons-sos" aria-hidden="true"></span><?php esc_html_e( 'Priority support', 'wp-smart-image-resize' ); ?></li>
        </ul>
        <div class="wpsirInfoBox-cta">
            <a href="https://sirplugin.com?utm_source=wordpress&utm_medium=plugin&utm_campaign=sidebar" target="_blank" class="wpsirInfoBox-btn">
                <?php esc_html_e( 'Upgrade Now', 'wp-smart-image-resize' ); ?>
            </a>
        </div>
    </div>
</aside><!-- /.wp-sir-sidebar -->

