<?php

namespace WP_Smart_Image_Resize;

/**
 * Tracks how many images have been processed by the plugin.
 */
final class Process_Tracker
{
    const OPT_KEY  = 'wp_sir_processed_attachments';
    const SEAL_KEY = 'wp_sir_pt_seal';

    /**
     * Record that an image was processed.
     */
    public static function record( $image_id )
    {
        $images = self::get_processed_images();

        if ( ! isset( $images[ $image_id ] ) ) {
            $images[ $image_id ] = 1;
        } else {
            $images[ $image_id ]++;
        }

        update_option( self::OPT_KEY, $images );
        self::update_seal( count( $images ) );
    }

    /**
     * Remove an image from the processed list (used when restoring).
     */
    public static function unrecord( $image_id )
    {
        $images = self::get_processed_images();
        if ( isset( $images[ $image_id ] ) ) {
            unset( $images[ $image_id ] );
            update_option( self::OPT_KEY, $images );
            self::update_seal( count( $images ) );
        }
    }

    /**
     * Get the array of processed image IDs.
     */
    public static function get_processed_images()
    {
        return (array) get_option( self::OPT_KEY, [] );
    }

    /**
     * Total number of images processed so far.
     */
    public static function get_total_processed()
    {
        $count  = count( self::get_processed_images() );
        $sealed = self::get_sealed_count();

        return max( $count, $sealed );
    }

    /**
     * Max images the free version will process.
     */
    public static function get_max_processed()
    {
        return self::resolve_limit();
    }

    /**
     * Whether the processing limit has been reached.
     */
    public static function has_reached_limit()
    {
        return self::get_total_processed() >= self::get_max_processed();
    }

    /**
     * Whether the processing count is nearing the limit.
     */
    public static function is_nearing_limit()
    {
        $processed = self::get_total_processed();
        return $processed > 100 && $processed < self::get_max_processed();
    }

    /**
     * Render the processing status widget (settings page).
     */
    public static function render_status()
    {
        $processed  = self::get_total_processed();
        $total      = self::get_max_processed();
        $pct        = min( 100, (int) round( ( $processed / $total ) * 100 ) );
        $at_limit   = self::has_reached_limit();
        $near_limit = self::is_nearing_limit();

        if ( $at_limit ) {
            $state_class = 'wp-sir-tracker--exceeded';
            $bar_class   = 'wp-sir-tracker__bar-fill--exceeded';
            $label_icon  = '<span class="dashicons dashicons-warning" aria-hidden="true"></span>';
        } elseif ( $near_limit ) {
            $state_class = 'wp-sir-tracker--warning';
            $bar_class   = 'wp-sir-tracker__bar-fill--warning';
            $label_icon  = '<span class="dashicons dashicons-info" aria-hidden="true"></span>';
        } else {
            $state_class = '';
            $bar_class   = '';
            $label_icon  = '';
        }

        $image_label = $processed === 1
            ? esc_html__( '1 image', 'wp-smart-image-resize' )
            : sprintf( esc_html__( '%d images', 'wp-smart-image-resize' ), $processed );

        ob_start();
        ?>
        <div class="wp-sir-tracker <?php echo esc_attr( $state_class ); ?>">
            <div class="wp-sir-tracker__bar-wrap" role="progressbar"
                 aria-valuenow="<?php echo esc_attr( $pct ); ?>"
                 aria-valuemin="0" aria-valuemax="100"
                 aria-label="<?php esc_attr_e( 'Images processed', 'wp-smart-image-resize' ); ?>">
                <div class="wp-sir-tracker__bar-fill <?php echo esc_attr( $bar_class ); ?>"
                     style="width:<?php echo esc_attr( $pct ); ?>%"></div>
            </div>
            <div class="wp-sir-tracker__meta">
                <span class="wp-sir-tracker__count">
                    <?php echo $label_icon; ?>
                    <?php printf(
                        esc_html__( '%1$s of %2$s processed', 'wp-smart-image-resize' ),
                        '<strong>' . esc_html( $image_label ) . '</strong>',
                        '<strong>' . esc_html( $total ) . '</strong>'
                    ); ?>
                </span>
                <a class="wp-sir-tracker__upgrade"
                   href="https://sirplugin.com/#pricing?utm_source=wp&utm_medium=plugin&utm_campaign=unlimited_images"
                   target="_blank">
                    <?php esc_html_e( 'Get unlimited →', 'wp-smart-image-resize' ); ?>
                </a>
            </div>
            <?php if ( $at_limit ) : ?>
            <p class="wp-sir-tracker__notice wp-sir-tracker__notice--error">
                <?php esc_html_e( 'You\'ve reached your image limit. Upgrade to Pro to keep processing images.', 'wp-smart-image-resize' ); ?>
            </p>
            <?php elseif ( $near_limit ) : ?>
            <p class="wp-sir-tracker__notice wp-sir-tracker__notice--warning">
                <?php esc_html_e( 'You\'re approaching your limit. Upgrade to Pro for unlimited images.', 'wp-smart-image-resize' ); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    private static function resolve_limit()
    {
        return 0x19 * 0x06;
    }

    private static function update_seal( $count )
    {
        $encoded = base64_encode( (string) $count );
        $hash    = self::seal_hash( $encoded );
        update_option( self::SEAL_KEY, $encoded . '|' . $hash, false );
    }

    private static function get_sealed_count()
    {
        $seal = get_option( self::SEAL_KEY, '' );
        if ( empty( $seal ) || substr_count( $seal, '|' ) !== 1 ) {
            return 0;
        }

        list( $encoded, $hash ) = explode( '|', $seal );

        if ( ! hash_equals( self::seal_hash( $encoded ), $hash ) ) {
            return 0;
        }

        return (int) base64_decode( $encoded );
    }

    private static function seal_hash( $data )
    {
        $key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wp-sir-default-key';
        return hash_hmac( 'sha256', $data, $key . '_sir_pt' );
    }
}
