<?php

namespace WP_Smart_Image_Resize;

final class Quota
{
    /**
     * Credit limit.
     */
    const QUOTA = 150;

    /**
     * Warn before exceeding quota threshold.
     */
    const QUTA_EXCEEDING_THRESHOLD = 100;

    /**
     * Get initial credits.
     *
     * 1 credit =  1 image attachment ( unlimited thumbnails ).
     */
    public static function get_initial_credits()
    {
        return self::QUOTA;
    }

    /**
     * Incredement credit for the given image attachment.
     *
     * @param int $image_id
     *
     * @return void
     */
    public static function consume($image_id)
    {
        $processed_images = self::get_processed_images();

        if (isset($processed_images[$image_id])) {
            $processed_images[$image_id]++;
        } else {
            $processed_images[$image_id] = 1;
        }

        update_option('wp_sir_processed_attachments', $processed_images);
    }

    /**
     * Get proceeded images array.
     *
     * @return array
     */
    public static function get_processed_images()
    {
        return (array) get_option('wp_sir_processed_attachments', []);
    }

    /**
     * Determine whether the quota is exceeded.
     *
     * @return bool
     */
    public static function isExceeded()
    {
        return self::get_consumed() >= self::QUOTA;
    }

    /**
     * Get the total of consumed credits.
     * @return int
     */
    public static function get_consumed()
    {
        return count(self::get_processed_images());
    }

    /**
     * Check if the quora is exceeding soon.
     * @return bool
     */
    public static function is_exceeding_soon()
    {
        $consumed = self::get_consumed();

        return $consumed > self::QUTA_EXCEEDING_THRESHOLD && $consumed < self::QUOTA;
    }

    public static function show_quota_status()
    {
        $consumed   = self::get_consumed();
        $total      = self::get_initial_credits();
        $pct        = min( 100, (int) round( ( $consumed / $total ) * 100 ) );
        $exceeded   = self::isExceeded();
        $exceeding  = self::is_exceeding_soon();

        if ( $exceeded ) {
            $state_class = 'wp-sir-quota--exceeded';
            $bar_class   = 'wp-sir-quota__bar-fill--exceeded';
            $label_icon  = '<span class="dashicons dashicons-warning" aria-hidden="true"></span>';
        } elseif ( $exceeding ) {
            $state_class = 'wp-sir-quota--warning';
            $bar_class   = 'wp-sir-quota__bar-fill--warning';
            $label_icon  = '<span class="dashicons dashicons-info" aria-hidden="true"></span>';
        } else {
            $state_class = '';
            $bar_class   = '';
            $label_icon  = '';
        }

        $image_label = $consumed === 1
            ? esc_html__( '1 image', 'wp-smart-image-resize' )
            : sprintf( esc_html__( '%d images', 'wp-smart-image-resize' ), $consumed );

        ob_start();
        ?>
        <div class="wp-sir-quota <?php echo esc_attr( $state_class ); ?>">

            <div class="wp-sir-quota__bar-wrap" role="progressbar"
                 aria-valuenow="<?php echo esc_attr( $pct ); ?>"
                 aria-valuemin="0" aria-valuemax="100"
                 aria-label="<?php esc_attr_e( 'Image quota used', 'wp-smart-image-resize' ); ?>">
                <div class="wp-sir-quota__bar-fill <?php echo esc_attr( $bar_class ); ?>"
                     style="width:<?php echo esc_attr( $pct ); ?>%"></div>
            </div>

            <div class="wp-sir-quota__meta">
                <span class="wp-sir-quota__count">
                    <?php echo $label_icon; ?>
                    <?php
                    printf(
                        /* translators: 1: number of images processed, 2: total quota */
                        esc_html__( '%1$s of %2$s processed', 'wp-smart-image-resize' ),
                        '<strong>' . esc_html( $image_label ) . '</strong>',
                        '<strong>' . esc_html( $total ) . '</strong>'
                    );
                    ?>
                    <span class="wp-sir-help-tip"
                          title="<?php esc_attr_e( 'To view processed images, apply the filter "Smart Resize: Processed" in your media library.', 'wp-smart-image-resize' ); ?>"></span>
                </span>
                <a class="wp-sir-quota__upgrade"
                   href="https://sirplugin.com/#pricing?utm_source=wp&utm_medium=plugin&utm_campaign=unlimited_images"
                   target="_blank">
                    <?php esc_html_e( 'Get unlimited →', 'wp-smart-image-resize' ); ?>
                </a>
            </div>

            <?php if ( $exceeded ) : ?>
            <p class="wp-sir-quota__notice wp-sir-quota__notice--error">
                <?php esc_html_e( 'You\'ve reached your image limit. Upgrade to Pro to keep processing images.', 'wp-smart-image-resize' ); ?>
            </p>
            <?php elseif ( $exceeding ) : ?>
            <p class="wp-sir-quota__notice wp-sir-quota__notice--warning">
                <?php esc_html_e( 'You\'re approaching your limit. Upgrade to Pro for unlimited images.', 'wp-smart-image-resize' ); ?>
            </p>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }
}
