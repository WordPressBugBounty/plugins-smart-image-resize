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
        <a href="?page=wp-smart-image-resize&tab=bulk-regenerate"
           class="nav-tab <?php echo $current_tab === 'bulk-regenerate' ? 'nav-tab-active' : '' ?>">Bulk Regenerate Images</a>
         
           
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
                    submit_button(null, 'primary', 'submit', true, array('style' => 'margin-right: 10px;'));
                    submit_button('Save and Bulk Regenerate', 'secondary', 'submit_and_bulk_resize', true);
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
                        <li><i class="dashicons dashicons-yes" style="color: #2271b1;"></i> <strong>Coming Soon:</strong> Convert and Display AVIF images & AI background removal integration</li>
                        <li><i class="dashicons dashicons-yes" style="color: #2271b1;"></i> <strong>Priority Support:</strong> Get fast, dedicated assistance</li>
                        <li><i class="dashicons dashicons-yes" style="color: #2271b1;"></i> <strong>Future-Proof:</strong> All upcoming features included</li>
                    </ul>
                    <div style="text-align: center; padding: 5px 12px 15px;">
                        <a href="https://sirplugin.com?utm_source=wordpress&utm_medium=plugin&utm_campaign=sidebar" target="_blank" class="button button-primary" style="width: 100%; text-align: center; font-weight: 600; padding: 8px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                           Upgrade to Pro Now!
                        </a>
                        <p style="font-size: 12px; color: #646970; margin: 10px 0 0;">14-Day Money Back Guarantee</p>
                        <div class="wpsirTestimonialBox">
                    <span>⭐️⭐️⭐️⭐️⭐️</span>
                    <p>"I downloaded the free version and after 3 minutes I bought the PRO version. The plugin is EXCELLENT! For a year I didn't know what to do with WooCommerce photos, because we have 30,000 imported products with different photos."</p>
                    <div><a href="https://wordpress.org/support/topic/excellent-8052" target="_blank">- @prokurent on WordPress.org</a></div>
                </div>
                    </div>
               
                </div>
                
            </div>
            </div>
        </div>

    <?php endif;
    if ( $current_tab === 'bulk-regenerate' ):
        ?>
        <div class="wp-sir-bulk-regenerate" >
        <h2><span>Bulk Regenerate Images</span></h2>
            <p style="margin-bottom:5px">Use these steps to update your existing images according to your settings.</p>
            <ol>
                <?php 
                
                if(defined('RETHUMBIFY_VERSION')):?>
                    
                    <li>Go to <a href="<?php echo admin_url('tools.php?page=rethumbify') ?>">Tools → Rethumbify</a></li>
                    <li>Click the <b>Start Regeneration</b> button to start regenerating images</li>
                    <?php 
                    elseif(in_array('regenerate-thumbnails/regenerate-thumbnails.php',
                    apply_filters('active_plugins', get_option('active_plugins')))): ?>
                    <li>
                       Go to <a href="<?php echo admin_url() ?>tools.php?page=regenerate-thumbnails">Tools → Regenerate
                            Thumbnails</a>    
                    </li>
                    <li>
                        Click the <b>Regenerate Thumbnails for All Attachments</b> button to start regenerating images
                    </li>
                    <?php else: ?>
                    <li>Install <a
                                href="<?php echo admin_url( 'plugin-install.php?s=Regenerate+Thumbnails&tab=search&type=term' ) ?>">Regenerate
                            Thumbnails</a> plugin. </li>
                   
                   <li>Go to <a href="<?php echo admin_url() ?>tools.php?page=regenerate-thumbnails">Tools → Regenerate
                            Thumbnails</a>.   
                    </li>
                    <li>
                        Click the <b>Regenerate Thumbnails for All Attachments</b> button to start regenerating images
                    </li>
                <?php endif; ?>
            </ol>
            <div class="notice notice-info inline" style="margin-top: 10px; padding: 10px;">
                <p>
                    <span class="dashicons dashicons-info" style="color: #00a0d2; margin-right: 5px;"></span>
                    If you still see old images, try clearing all caches including your browser cache, caching plugin cache, and Cloudflare cache. This ensures the newly resized images are displayed properly.
                </p>
            </div>
          
        </div>
    <?php endif; ?>
    
    <?php if ($current_tab === 'help'): ?>
        <?php $this->render_help_tab(); ?>
    <?php endif; ?>

</div>


