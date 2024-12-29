<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://nabillemsieh.com
 * @since      1.0.0
 *
 * @package    WP_Smart_Image_Resize
 * @subpackage WP_Smart_Image_Resize/templates
 */

$current_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general';
?>
<div class="wrap">
    
    <h1>Smart Image Resize for WooCommerce

    <span style="color: #646970; font-size: 12px; margin: 5px 0 15px;">
        v<?php echo WP_SIR_VERSION; ?>
    </span>
    </h1>
    
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=wp-smart-image-resize&tab=general"
           class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : '' ?>">Settings</a>
        <a href="?page=wp-smart-image-resize&tab=regenerate_thumbnails"
           class="nav-tab <?php echo $current_tab === 'regenerate_thumbnails' ? 'nav-tab-active' : '' ?>">Regenerate
            Thumbnails</a>
        
        <a href="?page=<?php echo WP_SIR_NAME; ?>&tab=help" 
           class="nav-tab <?php echo $current_tab === 'help' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Help', 'wp-smart-image-resize'); ?>
        </a>
    </h2>

    <?php if ( $current_tab === 'general' ): ?>

        <div class="wpsirSettingsContainer">
            <div>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( WP_SIR_NAME );
                    do_settings_sections( WP_SIR_NAME );
                    submit_button();
                    ?>
                </form>
            </div>
            <div>
            <div class="sir-sidebar">
                
            <div class="wpsirInfoBox">
                    <h3>🚀 Get PRO and unlock:</h3>
                    <ul>
                        <li><i class="dashicons dashicons-yes" style="color: #2271b1;"></i> <strong>No Image Limits:</strong> Process unlimited images</li>
                        <li><i class="dashicons dashicons-yes" style="color: #2271b1;"></i> <strong>Watermarking:</strong> Protect your images from theft and establish brand presence</li>
                        <li><i class="dashicons dashicons-yes" style="color: #2271b1;"></i> <strong>PNG to JPG:</strong> Automatically convert PNG images to optimized JPGs</li>
                        <li><i class="dashicons dashicons-yes" style="color: #2271b1;"></i> <strong>WebP Support:</strong> Faster loading with next-gen formats</li>
                        <li><i class="dashicons dashicons-yes" style="color: #2271b1;"></i> <strong>Coming Soon:</strong> AVIF support & AI background removal integration</li>
                        <li><i class="dashicons dashicons-yes" style="color: #2271b1;"></i> <strong>Priority Support:</strong> Get fast, dedicated assistance</li>
                        <li><i class="dashicons dashicons-yes" style="color: #2271b1;"></i> <strong>Future-Proof:</strong> All upcoming features included</li>
                    </ul>
                    <div style="text-align: center; padding: 5px 12px 15px;">
                        <a href="https://sirplugin.com?utm_source=wordpress&utm_medium=plugin&utm_campaign=sidebar" target="_blank" class="button button-primary" style="width: 100%; text-align: center; font-weight: 600; padding: 8px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                           Upgrade to Pro Now!
                        </a>
                        <p style="font-size: 12px; color: #646970; margin: 10px 0 0;">14-Day Money Back Guarantee</p>
                    </div>
                </div>
                
            </div>
            </div>
        </div>

    <?php endif;
    if ( $current_tab === 'regenerate_thumbnails' ):
        ?>
        <div class="wp-sir-regenerate-thumbnails" style="padding:10px">
            <p style="margin-bottom:5px">Follow these steps to resize images already uploaded to match your settings.</p>
            <ol>
                <?php if ( !wp_sir_regen_thumb_active() ): ?>
                    <li>Install <a
                                href="<?php echo admin_url( 'plugin-install.php?s=Regenerate+Thumbnails&tab=search&type=term' ) ?>">Regenerate
                            Thumbnails plugin</a>.
                    </li>
                <?php endif; ?>
                <li>Navigate to
                    <?php if ( wp_sir_regen_thumb_active() ): ?>
                        <a href="<?php echo admin_url() ?>tools.php?page=regenerate-thumbnails">Tools → Regenerate
                            Thumbnails</a>
                    <?php else: ?>
                        Tools > Regenerate Thumbnails.
                    <?php endif; ?>
                </li>
                <li>Click the <b>Regenerate Thumbnails for All Attachments</b> button to start resizing</li>
            </ol>
            <p>
                <b>NOTE:</b> If you still see old images, clear all caches including your browser cache, caching plugin cache, and Cloudflare cache.
            </p>
          
        </div>
    <?php endif; ?>
    
    <?php if ($current_tab === 'help'): ?>
        <?php $this->render_help_tab(); ?>
    <?php endif; ?>

</div>


