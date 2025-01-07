<?php

namespace WP_Smart_Image_Resize;

use ActionScheduler_Store;
use WP_Smart_Image_Resize\Background_Process_On_Post_Save;
use WP_Smart_Image_Resize\Quota;
use WP_Smart_Image_Resize\Utilities\Env;

/**
 * Class WP_Smart_Image_Resize\Settings
 *
 * @package WP_Smart_Image_Resize\Inc
 */

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WP_Smart_Image_Resize\Settings')) :
    class Admin {

        protected static $instance = null;

        /**
         * @return Admin
         */
        public static function get_instance() {
            if (is_null(static::$instance)) {
                static::$instance = new Admin;
            }

            return static::$instance;
        }

        public function init() {

            // Add plugin to WooCommerce menu.
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_filter('pre_update_option_wp_sir_settings', [$this, 'pre_update_settings']);
            // Show Woocommerce not installed notice.
            add_action('admin_notices', [$this, 'fileinfo_not_enabled']);
            add_action('admin_notices', [$this, 'phpversion_not_supported']);
            add_action('admin_notices', [$this, 'show_background_processing_notice']);
            add_action('admin_notices',[$this,  'show_settings_saved_notice']);
            add_action('admin_init', [$this, 'plugin_settings_saved']);
            
            add_action('admin_notices', [$this, 'quota_exceeding_soon']);
            add_action('admin_notices', [$this, 'quota_exceeded_notice']);

            
            // Initialise settings form.
            add_action('admin_init', [$this, 'init_settings']);

            // Add settings help tab.
            add_action('load-woocommerce_smart-image-resize', [$this, 'settings_help'], 5, 3);

            add_filter('plugin_action_links_' . WP_SIR_BASENAME, [$this, 'plugin_links']);

            add_filter('admin_footer_text', [$this, 'admin_footer_text']);

            // Add Help tab
            if (isset($_GET['page']) && $_GET['page'] === WP_SIR_NAME && isset($_GET['tab']) && $_GET['tab'] === 'help') {
                add_action('admin_enqueue_scripts', function() {
                    wp_enqueue_style('wp-sir-admin');
                    wp_enqueue_script('wp-sir-admin');
                });
            }

            // Add AJAX handler for processor switch
            add_action('wp_ajax_wp_sir_switch_processor', [$this, 'ajax_switch_processor']);
        }

        public function plugin_settings_saved(){
            if (isset($_GET['page'])  && $_GET['page'] == WP_SIR_NAME && isset($_GET['settings-updated']) && $_GET['settings-updated']) {
                add_settings_error(WP_SIR_NAME, 'settings_updated', 'Settings saved successfully. To apply these changes to existing images, please regenerate thumbnails'
                . (wp_sir_regen_thumb_active() ? ' by navigating to ' . sprintf('<a href="%s">Tools → Regenerate Thumbnails</a>.', admin_url('tools.php?page=regenerate-thumbnails')) : '.')
                , 'updated');
            }
        }

        public function show_settings_saved_notice(){

            if(isset($_GET['page'])  && $_GET['page'] == WP_SIR_NAME){
                settings_errors(WP_SIR_NAME);
            }

        }
        public function show_background_processing_notice(){
            if(! apply_filters('wp_sir_show_background_processing_notice', true)){
                return;
            }

            if(! apply_filters('wp_sir_allow_background_processing', true)){
                return;
            }
            if(! function_exists('\as_has_scheduled_action') || ! function_exists('\as_get_scheduled_actions')){
                return;
            }

            if (\as_has_scheduled_action(Background_Process_On_Post_Save::JOB_HOOK, null, Background_Process_On_Post_Save::JOB_GROUP)) {
                
                $args = [
                    'hook' => Background_Process_On_Post_Save::JOB_HOOK,
                    'group' => Background_Process_On_Post_Save::JOB_GROUP,
                    'status' => ActionScheduler_Store::STATUS_PENDING,
                    'per_page'=> -1
                ];

                $count_pending_images = count(as_get_scheduled_actions($args, 'ids'));

                if($count_pending_images > 1){
                  $pending_message =   '<i>( '.$count_pending_images.' images remaining )</i>';
                }elseif($count_pending_images === 1){
                   $pending_message =  '<i>( 1 image remaining )</i>';
                }else{
                   $pending_message =  '<i style="color:green">All done!</i>';
                }

                ?>
<div class="notice notice-info is-dismissible">
                    <p><b>Smart Image  Resize:</b> Processing recently uploaded images in the background. This may take a little while, so please be patient. <?php echo $pending_message; ?></p>
                </div>
<?php } 
        }
        

        function quota_exceeding_soon() {
            if (Quota::is_exceeding_soon()) { ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php _e(
                            'Smart Image Resize: Your are reaching your limit for re-sizing images.',
                            WP_SIR_NAME
                        ); ?>
                        <a target="_blank" href="https:/sirplugin.com/#pro?utm_source=plugin&utm_campaign=notice_limit" class="button button-default"><?php _e(
                                                                                                                                                            'Upgrade to Pro'
                                                                                                                                                        ); ?></a> for
                        unlimited images.
                    </p>
                </div>
            <?php }
        }

        function quota_exceeded_notice() {
            if (Quota::isExceeded()) { ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e(
                            'Smart Image Resize: Your have reached your limit for re-sizing images.',
                            WP_SIR_NAME
                        ); ?>
                        <a target="_blank" href="https:/sirplugin.com/#pro?utm_source=plugin&utm_campaign=notice_limit" class="button button-default"><?php _e(
                                                                                                                                                            'Upgrade to Pro'
                                                                                                                                                        ); ?></a> for
                        unlimited images.
                    </p>
                </div>
            <?php }
        }

        function admin_footer_text() {
            $screen = get_current_screen();

            if (!function_exists('get_current_screen')) {
                return;
            }
            if ($screen->id === 'woocommerce_page_wp-smart-image-resize') { ?>
                
                Please leave us a <a href="https://wordpress.org/support/plugin/smart-image-resize/reviews/">★★★★★
                    rating</a>. We appreciate your support!
                
                
            <?php }
        }

        function plugin_links($links) {

            $settings_url    = admin_url('admin.php?page=wp-smart-image-resize');
            $settings_anchor = '<a href="' . $settings_url . '">' . __('Settings') . '</a>';
            array_unshift($links, $settings_anchor);


            
            $links[] = '<a href="https://sirplugin.com/?utm_source=plugin&utm_medium=installed_plugins&utm_campaign=go_pro" target="_blank" style="font-weight:bold;color:#38b2ac">Go Pro</a>';
            

            return $links;
        }

        function pre_update_settings($newval) {
            $defaults = [
                'enable'      => 0,
                'jpg_convert' => 0,
                'enable_webp' => 0,
                'enable_trim' => 0,
                'enable_watermark' => 0,
            ];

          

            if (isset($newval['processable_images']['taxonomies'])) {
                $newval['processable_images']['taxonomies'] = (array)$newval['processable_images']['taxonomies'];
            } else {
                $newval['processable_images']['taxonomies'] = [];
            }
            
            if (isset($newval['processable_images']['post_types'])) {
                $newval['processable_images']['post_types'] = (array)$newval['processable_images']['post_types'];
            } else {
                $newval['processable_images']['post_types'] = [];
            }

            // Ensure watermark offset values default to 0
            if (!isset($newval['watermark_offset']) || !is_array($newval['watermark_offset'])) {
                $newval['watermark_offset'] = [];
            }
            $newval['watermark_offset'] = wp_parse_args($newval['watermark_offset'], [
                'x' => 0,
                'y' => 0
            ]);

            $settings = wp_parse_args($newval, $defaults);
              
              $settings['enable_watermark'] = 0;
              $settings['jpg_convert'] = 0;
              $settings['enable_webp'] = 0;
              
              return $settings;

        }


        public function fileinfo_not_enabled() {
            if (!extension_loaded('fileinfo')) : ?>
                <div class="notice notice-error  is-dismissible">
                    <p><?php _e(
                            'Smart Image Resize: PHP Fileinfo extension is not enabled, contact your hosting provider to enable it.',
                            WP_SIR_NAME
                        ); ?></p>
                </div>
            <?php endif;
        }

        public function phpversion_not_supported() {
            if (!version_compare(PHP_VERSION, '5.6.0', '>=')) : ?>
                <div class="notice notice-error  is-dismissible">
                    <p><?php _e(
                            'Smart Image Resize requires PHP 5.6.0 or greater to work correctly.',
                            WP_SIR_NAME
                        ); ?></p>
                </div>
            <?php endif;
        }

        /**
         * Add plugin submenu to WooCommerce menu.
         *
         * @return void
         */
        public function add_admin_menu() {

            $parent_slug = 'woocommerce';
            $cap         = 'manage_woocommerce';
            if (!is_plugin_active('woocommerce/woocommerce.php')) {
                $parent_slug = 'options-general.php';
                $cap         = 'manage_options';
            }

            $page_slug = add_submenu_page(
                $parent_slug,
                'Smart Image Resize',
                'Smart Image Resize',
                $cap,
                WP_SIR_NAME,
                [$this, 'settings_page']
            );

            add_action('load-' . $page_slug, [$this, 'add_settings_help']);
        }

        /**
         * Initialize settings form.
         *
         * @return void
         */
        public function init_settings() {

            register_setting(WP_SIR_NAME, 'wp_sir_settings');

            add_settings_section('wp_sir_settings_general', 'Uniformity' , null, WP_SIR_NAME, [
                'before_section' => '<div class="sir-settings-section sir-settings-general">',
                'after_section'=>'</div>',
                'title'=> '<span>Uniformity</span>'
            ]);

            add_settings_section('wp_sir_settings_advanced', 'Advanced' , null, WP_SIR_NAME, [
                'before_section' => '<div class="sir-settings-section">',
                'after_section'=>'</div>',
            ]);
            add_settings_section('wp_sir_settings_watermark', 'Watermark <a href="https://sirplugin.com"  class="_wp-link">Upgrade to PRO</a>', null, WP_SIR_NAME, [
            'before_section' => '<div class="sir-settings-section">',
             'after_section'=>'</div>'
         ]);
            add_settings_section('wp_sir_settings_optimization', 'Optimization', null, WP_SIR_NAME,[
                   'before_section' => '<div class="sir-settings-section">',
                'after_section'=>'</div>'
            ]);
           
            add_settings_field(
                'wp_sir_disable_upscale',
                __('Enable', 'wp-smart-image-resize'),
                [$this, 'settings_field_enable'],
                WP_SIR_NAME,
                'wp_sir_settings_general'
            );

            if(apply_filters('enable_experimental_features/crop_mode', false)){
                add_settings_field(
                    'wp_sir_settings_cropping_mode',
                    __('Cropping Mode<span class="wp-sir-help-tip" title="Choose how to crop or resize your images for a uniform look. (Experimental)"></span>', WP_SIR_NAME),
                    [$this, 'settings_field_cropping_mode'],
                    WP_SIR_NAME,
                    'wp_sir_settings_general'
                );
            }
    

            add_settings_field(
                'wp_sir_settings_processable_images',
                'Images',
                [$this, 'settings_field_processable_images'],
                WP_SIR_NAME,
                'wp_sir_settings_general'
            );


               // Register `Background Color` field.
               add_settings_field(
                'wp_sir_settings_bg_color',
                'Background Color',
                [$this, 'settings_field_bg_color'],
                WP_SIR_NAME,
                'wp_sir_settings_general'
            );

            
          
           
             // Register `Enable WebP format` field.
             add_settings_field(
                'wp_sir_settings_enable_trim',
                'Trim Whitespace',
                [$this, 'settings_field_enable_trim'],
                WP_SIR_NAME,
                'wp_sir_settings_general'
            );


            add_settings_field(
                'wp_sir_settings_sizes',
                __('Image Sizes', WP_SIR_NAME),
                [$this, 'settings_field_sizes'],
                WP_SIR_NAME,
                'wp_sir_settings_advanced'
            );

            add_settings_field(
                'wp_sir_disable_upscale',
                __('Disable Image Upscaling', 'wp-smart-image-resize'),
                [$this, 'settings_field_disable_upscale'],
                WP_SIR_NAME,
                'wp_sir_settings_advanced'
            );
            
            add_settings_field(
                'wp_sir_settings_watermark',
                '<span style="position:relative;">Add Watermark</span>',
                [$this, 'settings_field_watermark'],
                WP_SIR_NAME,
                'wp_sir_settings_watermark'
            );
            

         
            // Register `Image Compression` field.
            add_settings_field(
                'wp_sir_settings_image_quality',
                'Image Compression',
                [$this, 'settings_field_image_quality'],
                WP_SIR_NAME,
                'wp_sir_settings_optimization'
            );

            // Register `Convert to JPG format` field.
            add_settings_field(
                'wp_sir_settings_jpg_convert',
                'PNG-JPG Conversion',
                [$this, 'settings_field_jpg_convert'],
                WP_SIR_NAME,
                'wp_sir_settings_optimization'
            );

            // Register `Enable WebP format` field.
            add_settings_field(
                'wp_sir_settings_enable_webp',
                'Convert & Display WebP Images',
                [$this, 'settings_field_enable_webp'],
                WP_SIR_NAME,
                'wp_sir_settings_optimization'
            );

        }

        function settings_field_enable_trim() {
            $settings = \wp_sir_get_settings(); ?>
            <div class="wp-sir-trim-settings">
                <!-- Main trim toggle -->
                <label for="wp-sir-enable-trim">
                    <input type="checkbox" 
                           name="wp_sir_settings[enable_trim]" 
                           <?php checked($settings['enable_trim'], 1); ?> 
                           id="wp-sir-enable-trim" 
                           class="wp-sir-as-toggle" 
                           value="1" />
                </label>
                <p class="description">
                    <?php _e('Automatically remove unnecessary empty space around images to create a clean, uniform appearance.', 'wp-smart-image-resize'); ?>
                </p>

                <!-- Advanced trim settings container -->
                <div class="wp-sir-trim-advanced-settings" style="margin-top:15px;display:<?php echo $settings['enable_trim'] ? 'block' : 'none' ?>">
                    <!-- Tolerance setting -->
                    <div class="wp-sir-setting-group">
                        <label>
                            <span class="wp-sir-setting-title">
                                <?php _e('Color Tolerance', 'wp-smart-image-resize'); ?>
                                <span class="wp-sir-help-tip" title="<?php esc_attr_e('Higher values will trim colors that are similar but not exactly white. Use with caution as it may trim parts of your image.', 'wp-smart-image-resize'); ?>"></span>
                            </span>
                            <input type="range" 
                                   min="0" 
                                   max="100" 
                                   name="wp_sir_settings[trim_tolerance]" 
                                   value="<?php echo esc_attr($settings['trim_tolerance']); ?>"
                                   class="wp-sir-range-input"
                                   id="wp-sir-trim-tolerance"
                                   data-value-display="wp-sir-tolerance-value" />
                            <span id="wp-sir-tolerance-value"><?php echo esc_html($settings['trim_tolerance']); ?>%</span>
                        </label>
                        <p class="description wp-sir-tolerance-feedback">
                            <?php _e('Default: 3%. Increase to trim more aggressively.', 'wp-smart-image-resize'); ?>
                        </p>
                    </div>

                    <!-- Border/Feather setting -->
                    <div class="wp-sir-setting-group" style="margin-top:15px">
                        <label>
                            <span class="wp-sir-setting-title">
                                <?php _e('Preserve Border', 'wp-smart-image-resize'); ?>
                                <span class="wp-sir-help-tip" title="<?php esc_attr_e('Add a small border around the trimmed image to prevent cutting too close to the edge.', 'wp-smart-image-resize'); ?>"></span>
                            </span>
                            <input type="number" 
                                   min="0" 
                                   max="100" 
                                   name="wp_sir_settings[trim_feather]" 
                                   value="<?php echo esc_attr($settings['trim_feather']); ?>"
                                   class="small-text" /> px
                        </label>
                        <p class="description">
                            <?php _e('Set the width of the border to maintain around the image (in pixels).', 'wp-smart-image-resize'); ?>
                        </p>
                    </div>
                </div>
            </div>
           
            <script>
            jQuery(document).ready(function($) {
                // Update tolerance value display and feedback
                $('#wp-sir-trim-tolerance').on('input', function() {
                    var value = parseInt($(this).val());
                    $('#' + $(this).data('value-display')).text(value + '%');
                    var $feedback = $('.wp-sir-tolerance-feedback');
                    
                    if (value > 50) {
                        $feedback.html('<span style="color: #d63638;">Warning: High tolerance may trim parts of your image that you want to keep.</span>');
                    } else if (value > 20) {
                        $feedback.html('<span style="color: #dba617;">Caution: Moderate-high tolerance - test on sample images first.</span>');
                    } else if (value > 10) {
                        $feedback.html('Medium tolerance - will trim similar shades of white.');
                    } else {
                        $feedback.html('<?php _e('Default: 3%. Increase to trim more aggressively.', 'wp-smart-image-resize'); ?>');
                    }
                });

                // Trigger initial feedback on page load
                $('.wp-sir-range-input').trigger('input');
            });
            </script>
<?php
        }

        function settings_field_watermark() {
            $settings = \wp_sir_get_settings(); ?>
            <label for="wp-sir-enable-watermark" >
                <input type="checkbox" name="wp_sir_settings[enable_watermark]"  <?php checked($settings['enable_watermark'], 1); ?> id="wp-sir-enable-watermark" class="wp-sir-as-toggle" value="1" />
            </label>
            <div  class="wp-sir-watermark-settings" style="display:<?php echo $settings['enable_watermark'] ? 'flex': 'none' ?>">
           <div style="padding-right: 20px;">
               
           <div style="margin-top:10px">
                 Watermark <button type="button"  class="button button-small"  style="margin-top:-3px !important" id="wp-sir-open-media-uploader">Select/upload image</button> 
                <input type="hidden" name="wp_sir_settings[watermark_image]" value="<?php echo $settings['watermark_image'] ?>"
                <?php if(!empty($settings['watermark_image'])) :
                $wm = wp_get_attachment_image_src($settings['watermark_image'], 'full');
                $size= is_array($wm) ? json_encode(['w'=> $wm[1], 'h'=> $wm[2]]) : '';
                ?>
                    data-size='<?php echo $size ?>'
                    <?php endif; ?>
                >
                </div>
            <div  style="margin-top:10px">
            Size
            <div class="wp-sir-range-wrapper">
                <input name="wp_sir_settings[watermark_size]" 
                class="wp-sir-watermark-size wp-sir-range-input" type="range" 
                min="1"
                max="100"
                value="<?php echo $settings['watermark_size']; ?>"  data-value-display="wp-sir-watermark-size-value" />
                <span id="wp-sir-watermark-size-value"><?php echo $settings['watermark_size']; ?>%</span>
            </div>
        <p class="description">
            Adjust watermark size as a percentage of product image dimensions (1%–100%).
        </p>    
        </div>
          
            <div  style="margin-top:10px">
                    Opacity
            <div class="wp-sir-range-wrapper">
                <input name="wp_sir_settings[watermark_opacity]" 
                class="wp-sir-watermark-opacity wp-sir-range-input" type="range" value="<?php echo $settings['watermark_opacity']; ?>" data-value-display="wp-sir-watermark-opacity-value" />
                <span id="wp-sir-watermark-opacity-value"><?php echo $settings['watermark_opacity']; ?>%</span>
            </div>
            <p class="description">
            Set the watermark transparency. 0% is fully transparent; 100% is fully visible.
            </p>
            </div>
            <div   style="margin-top:10px">

            <label for="wp-sir-watermark-position">
            Position <select name="wp_sir_settings[watermark_position]" id="wp-sir-watermark-position" class="hidden">
                <option value="top-left" <?php selected($settings['watermark_position'], 'top-left'); ?>>Top Left</option>
                <option value="top" <?php selected($settings['watermark_position'], 'top'); ?>>Top Center</option>
                <option value="top-right" <?php selected($settings['watermark_position'], 'top-right'); ?>>Top Right</option>
                <option value="left" <?php selected($settings['watermark_position'], 'left'); ?>>Middle Left</option>
                <option value="center" <?php selected($settings['watermark_position'], 'center'); ?>>Center</option>
                <option value="right" <?php selected($settings['watermark_position'], 'right'); ?>>Middle Right</option>
                <option value="bottom-left" <?php selected($settings['watermark_position'], 'bottom-left'); ?>>Bottom Left</option>
                <option value="bottom" <?php selected($settings['watermark_position'], 'bottom'); ?>>Bottom Center</option>
                <option value="bottom-right" <?php selected($settings['watermark_position'], 'bottom-right'); ?>>Bottom Right</option>
            </select>
            </label>
            </div>

           <div class=""  style="margin-top:10px">
           <label for="wp-sir-watermark-position">
            Offset X
            <input type="number" min="0" id="wp-sir-watermark-offset-x" class="wp-sir-offset-input" name="wp_sir_settings[watermark_offset][x]" style="width:70px" value="<?php echo $settings['watermark_offset']['x'] ?>">
            </label>

            <label for="wp-sir-watermark-position">
                Offset Y
            <input type="number" min="0" id="wp-sir-watermark-offset-y" class="wp-sir-offset-input" name="wp_sir_settings[watermark_offset][y]" style="width:70px" value="<?php echo $settings['watermark_offset']['y'] ?>">

            </label>
           </div>
           <p class="description">
           Adjust the watermark's horizontal (X) and vertical (Y) position in pixels.
           </p>
           </div>
           <div>
              <div>
                 <span>Preview</span>
              <div class="wp-sir-watermark-preview-container" style="border:1px solid #ddd; position:relative; background: url(<?php echo WP_SIR_URL . '/images/watermark-preview.jpg'; ?>); width:300px; height:300px;background-size:contain;background-repeat:no-repeat;background-color:white" >
                    
                    <?php if(!empty($settings['watermark_image'])) :
                   $watermark_fullpath = get_attached_file($settings['watermark_image']);
                   if(is_readable($watermark_fullpath)):
                    $wm = wp_get_attachment_image_src($settings['watermark_image'], 'full');
                     $wm_url = wp_get_attachment_image_url($settings['watermark_image'], 'full');
                     if(!empty($wm_url)) :
                     ?>
                     <img src="<?php echo esc_url($wm_url); ?>" 
                          class="wp-sir-watermark-preview" style="position:absolute;">
                     <?php 
                     endif;
                 endif; 
                endif; ?>
                 </div>
              </div>
           </div>
            </div>
        <?php
        }

        function settings_field_processable_images() {
            $settings = \wp_sir_get_settings();

        ?>
            <div>
                <label for="wp-sir-processable-images-product" style="display: flex; align-items: center; margin-bottom: 10px">
                    <input type="checkbox" name="wp_sir_settings[processable_images][post_types][]" <?php
                                                                                                    echo in_array(
                                                                                                        'product',
                                                                                                        $settings['processable_images']['post_types'],
                                                                                                        true
                                                                                                    ) ? 'checked' : '';
                                                                                                    ?> id="wp-sir-processable-images-product"  value="product" /> <span style="display:inline-block">Product images</span>
                </label>
                <label for="wp-sir-processable-images-product-cat" style="display: flex; align-items: center">
                    <input type="checkbox" name="wp_sir_settings[processable_images][taxonomies][]" <?php echo in_array(
                                                                                                        'product_cat',
                                                                                                        $settings['processable_images']['taxonomies'],
                                                                                                        true
                                                                                                    ) ? 'checked' : ''; ?> id="wp-sir-processable-images-product-cat"  value="product_cat" /> <span style="display:inline-block">Category images</span>
                </label>
            </div>
            <p class="description">
                <?php _e('Select which images to resize.', 'wp-smart-image-resize'); ?>
            </p>
        <?php
        }

        function settings_field_jpg_convert() {
            $settings = \wp_sir_get_settings(); ?>
            <label for="wp-sir-jpg-convert">
                <input type="checkbox" name="wp_sir_settings[jpg_convert]" <?php checked($settings['jpg_convert'], 1); ?> id="wp-sir-jpg-convert" class="wp-sir-as-toggle"  disabled  value="1" />
                
                <a target="_blank" href="https://sirplugin.com?utm_source=plugin&utm_medium=upgrade&utm_campaign=jpg_convert"><?php _e(
                                                                                                                                    'Upgrade to PRO',
                                                                                                                                    WP_SIR_NAME
                                                                                                                                ); ?></a>
                
            </label>
            <p class="description">
                <?php _e(
                    "Convert PNG to JPG to reduce file size and improve page speed.",
                    WP_SIR_NAME
                ); ?>
            </p>
        <?php
        }

        function settings_field_enable_webp() {
            $settings = \wp_sir_get_settings(); ?>
            <label for="wp-sir-enable-webp">
                <input type="checkbox" name="wp_sir_settings[enable_webp]" <?php checked($settings['enable_webp'], 1); ?> id="wp-sir-enable-webp" class="wp-sir-as-toggle"  disabled  value="1" />
                
                <a target="_blank" href="https://sirplugin.com?utm_source=plugin&utm_medium=upgrade&utm_campaign=enabled_webp"><?php _e(
                                                                                                                                    'Upgrade to PRO',
                                                                                                                                    WP_SIR_NAME
                                                                                                                                ); ?></a>
                

            </label>
            <p class="description">
                <?php _e(
                    "WebP delivers up to 90% smaller file sizes than PNG with no quality loss.<br>Automatically falls back to JPEG/PNG for browsers without WebP support.",
                    WP_SIR_NAME
                ); ?>
            </p>
            
        <?php
        }

        public function settings_field_image_quality($args) {
            $settings = \wp_sir_get_settings(); ?>
            <div class="wp-sir-range-wrapper">
                <input name="wp_sir_settings[jpg_quality]" 
                       type="range" 
                       class="wp-sir-range-input" 
                       value="<?php echo absint($settings['jpg_quality']); ?>" 
                       data-value-display="wp-sir-jpg-quality-value" />
                <span id="wp-sir-jpg-quality-value"><?php echo absint($settings['jpg_quality']); ?>%</span>
            </div>
            <p class="description">
                <?php _e('Adjust image compression level. Higher values (e.g. 70-90%) apply more compression, resulting in smaller file sizes but lower image quality. Lower values (e.g. 40-60%) apply less compression, maintaining better image quality but producing larger files. Default: 0%', 'wp-smart-image-resize'); ?>
            </p>
<?php
        }


        function settings_field_cropping_mode(){
            $settings = \wp_sir_get_settings('view');
        ?>
        <div>
            <label for="wp-sir-crop-mode-scale">
                <input type="radio" 
                       id="wp-sir-crop-mode-scale"
                       value="pad" 
                       <?php echo $settings['crop_mode'] === 'pad' ? 'checked':'' ?> 
                       name="wp_sir_settings[crop_mode]">
                Scale and Add whitespace
                <span class="wp-sir-help-tip" title="Scales the image to fit the dimensions while preserving all content. If needed, adds white space to fill the remaining area. Best for when you want to keep the entire image visible."></span>
            </label>
            &nbsp;&nbsp;
            <label for="wp-sir-crop-mode-crop">
                <input type="radio"
                       id="wp-sir-crop-mode-crop" 
                       value="fill"
                       <?php echo $settings['crop_mode'] === 'fill' ? 'checked':'' ?> 
                       name="wp_sir_settings[crop_mode]">
                Fill and Crop
                <span class="wp-sir-help-tip" title="Fills the entire dimensions by trimming edges of the image as needed. Some parts of the image will be cut off to ensure the image fits perfectly without any whitespace."></span>
            </label>
        </div>
        </div>
        <?php
        }
        function settings_field_sizes() {
            $settings = \wp_sir_get_settings('view');
            $additional_sizes = wp_sir_get_additional_sizes('view');
            $enable_fit_mode_option = ! empty(_wp_sir_get_excluded_sizes(false));
            $default_sizes = _wp_sir_get_default_sizes();
        ?>

<p class="description">
                    <?php _e('Choose which image sizes WordPress should generate when uploading product images.', 'wp-smart-image-resize'); ?>
                    <?php _e("For optimal disk space usage, we've pre-selected only the essential sizes.", 'wp-smart-image-resize'); ?>
        </p>
            <div class="wp-sir-sizes-wrapper">
                <div class="wp-sir-sizes-header">
                    <div class="wp-sir-sizes-header-left">
                        <button type="button" class="button wp-sir-toggle-sizes" aria-expanded="false">
                            <span class="wp-sir-toggle-text">Customize image sizes</span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                    </div>
                    <div class="wp-sir-sizes-info">
                        <div class="wp-sir-sizes-summary">
                            <?php 
                            $selected_count = count($settings['sizes']);
                            $total_count = count($additional_sizes);
                            echo sprintf(
                                _n('%d of %d size selected', '%d of %d sizes selected', $total_count, 'wp-smart-image-resize'),
                                $selected_count,
                                $total_count
                            ); 
                            ?>
                        </div>
                        <button id="wpsirResetDefaultSizes" type="button" class="wp-sir-reset-link <?php echo $selected_count === count($default_sizes) ? 'hidden' : '' ?>">
                            <span class="dashicons dashicons-image-rotate"></span>
                            <?php _e('Reset to defaults', 'wp-smart-image-resize'); ?>
                        </button>
                    </div>
                </div>

                <!-- Wrap the sizes table in a collapsible div -->
                <div id="wp-sir-sizes-options" class="wp-sir-sizes-table-wrapper" style="display:none">
                   
                    <table id="wp-sir-sizes-selector" data-defaults="<?php echo implode(',', $default_sizes) ?>">
                        <tr>
                            <th style="padding-left:5px;padding-top:10px !important; padding-bottom:10px !important;border-bottom:1px solid #ddd;margin-bottom:0 !important;">
                                <input type="checkbox" id="wp-sir-toggle-all-sizes" <?php echo (count($additional_sizes)) === count($settings['sizes']) ? 'checked' : '' ?> /> 
                                <?php _e('Select all', 'wp-smart-image-resize'); ?>
                            </th>
                            <?php if($enable_fit_mode_option): ?>
                            <th style="padding-left:0;padding-top:10px !important; padding-bottom:10px !important;border-bottom:1px solid #ddd;margin-bottom:0 !important;text-align:center">
                                <?php _e('Use WordPress Cropping', 'wp-smart-image-resize'); ?>
                                <?php $tooltip = "Check this to apply the thumbnail cropping setting under Settings → Media"; 
                                if(wp_sir_is_woocommerce_activated()){
                                    $tooltip .= " and Appearance → Customize → WooCommerce → Product Images.";
                                }else{
                                    $tooltip .= ".";
                                }
                                ?>
                                <span class="wp-sir-help-tip" title="<?php echo esc_attr($tooltip); ?>"></span>
                            </th>
                            <?php endif; ?>
                            <?php if (wp_sir_is_woocommerce_activated()) : ?>
                                <th style="padding-left:5px;padding-top:10px !important;padding-right:10px; padding-bottom:5px !important;border-bottom:1px solid #ddd;margin-bottom:0 !important; max-width:100px">
                                    <?php _e('Width (px)', 'wp-smart-image-resize'); ?>
                                </th>
                                <th style="padding-left:0;padding-right:0;padding-top:10px !important; padding-bottom:10px !important;border-bottom:1px solid #ddd;margin-bottom:0 !important;max-width:100px">
                                    <?php _e('Height (px)', 'wp-smart-image-resize'); ?>
                                </th>
                            <?php endif; ?>
                        </tr>
                        <?php $i = 0;
                       
                        foreach ($additional_sizes as $size_name => $size_data) :
                            if (!empty($settings['size_options'][$size_name]['width'])) {
                                $size_data['width'] = $settings['size_options'][$size_name]['width'];
                            }
                            if (!empty($settings['size_options'][$size_name]['height'])) {
                                $size_data['height'] = $settings['size_options'][$size_name]['height'];
                            }
                            

                        ?>
                            <tr>
                                <td style="<?php echo wp_sir_is_woocommerce_activated() ? 'min-width:310px;' : '' ?>padding-left:5px;padding-top:10px !important; padding-bottom:10px !important;margin-bottom:0 !important; "><label title="" for="" style="display:flex;align-items:center;font-size:13px;">
                                        <label style="display: block;width:100%">
                                            <input
                                            type="checkbox" class="wpSirSelectSize" value="<?php echo $size_name ?>" <?php echo in_array($size_name, $settings['sizes']) ? 'checked' : ''; ?> name="wp_sir_settings[sizes][]"
                                            >
                                            <span><?php echo str_replace('_', ' ', ucfirst($size_name)) ?> (<?php echo $size_data['width'] . 'x' . $size_data['height'] ?>)</span>
                                            <?php if ($size_name === 'woocommerce_thumbnail') : ?>
                                                <span class="wp-sir-help-tip" title="Used in the product grids in places such as the shop page."></span>
                                            <?php endif; ?>
                                            <?php if ($size_name === 'woocommerce_single') : ?>
                                                <span class="wp-sir-help-tip" title="Used on single product pages."></span>
                                            <?php endif; ?>
                                            <?php if ($size_name === 'woocommerce_gallery_thumbnail') : ?>
                                                <span class="wp-sir-help-tip" title="Used below the main image on the single product page to switch the gallery."></span>
                                            <?php endif; ?>
                                            <?php if ($size_name === 'thumbnail' && wp_sir_is_woocommerce_activated()) : ?>
                                                <span class="wp-sir-help-tip" title="Used to preview images in the WordPress media library."></span>
                                            <?php endif; ?>
                                        </label>

                                </td>
                                 <?php if($enable_fit_mode_option): ?>
                                <td style="text-align:center;padding-left:0;padding-right:0;padding-top:5px !important; padding-bottom:5px !important;margin-bottom:0 !important;">
                                    <label>
                                        <input type="hidden" name="wp_sir_settings[size_options][<?php echo $size_name ?>][fit_mode]" value="contain">
                                        <input type="checkbox" class="wp-sir-fit-mode" name="wp_sir_settings[size_options][<?php echo $size_name ?>][fit_mode]" value="none" 
                                        <?php echo _wp_sir_exclude_size($size_name, $settings['size_options']) ? 'checked': '' ?>
                                        >
                                    </label>
                                </td>
                                <?php endif; ?>
                                <?php if (is_woocommerce_size($size_name)) : ?>

                                    <td class="wp-sir-custom-dimensions" style="padding-left:5px;padding-right:5px;padding-top:5px !important; padding-bottom:5px !important;margin-bottom:0 !important; max-width:100px">
                                        <input type="number" value="<?php echo $size_data['width'] ?>" style="width:70px" name="wp_sir_settings[size_options][<?php echo $size_name ?>][width]">
                                    </td>
                                    <td class="wp-sir-custom-dimensions" style="padding-left:0;padding-right:0;padding-top:5px !important; padding-bottom:5px !important;margin-bottom:0 !important; max-width:100px">
                                        <input type="number" value="<?php echo $size_data['height'] ?>" style="width:70px" name="wp_sir_settings[size_options][<?php echo $size_name ?>][height]">
                                    </td>

                                <?php endif; ?>



                            </tr>
                        <?php $i++;
                        endforeach; ?>
                    </table>
                </div>
            </div>
        <?php
        }



        public function settings_field_bg_color($args) {
            $settings = \wp_sir_get_settings(); ?>
            <input name="wp_sir_settings[bg_color]" value="<?php echo $settings['bg_color']; ?>" type="text" id="wpSirColorPicker" />
            <button type="button" class="button button-default button-small" id="wp-sir-clear-bg-color" style="min-height:30px">Clear</button>
            <p class="description">
                NOTE: Default background is white. Click "Clear" to keep image transparency.</p>
        <?php
        }

        public function settings_field_enable($args) {
            $settings = \wp_sir_get_settings(); ?>
            <label for="wp-sir-enable">
                <input type="checkbox" class="wp-sir-as-toggle wp-sir-as-toggle--large" name="wp_sir_settings[enable]" id="wp-sir-enable" value="1" <?php checked($settings['enable'], 1); ?> />
            </label>
            <?php
            
            echo Quota::show_quota_status();
            
            ?>
<?php
        }

        public function settings_page() {
            include_once WP_SIR_DIR . 'templates/settings.php';
        }

        function add_settings_help() {

            if (!function_exists('get_current_screen')) {
                return;
            }

            $screen = get_current_screen();

            // Add one help tab
            $screen->add_help_tab(array(
                'id'      => 'wp-sir-help-tab1',
                'title'   => esc_html__('Overview', WP_SIR_NAME),
                'content' =>
                '<p><strong>Images:</strong> Choose which images you want to process with the plugin.</p>' .
                    '<p><strong>Image Sizes:</strong> Pick the dimensions you want your images resized to.</p>' .
                    '<p><strong>Background Color:</strong> Choose the color that will fill any empty space in the resized images. For transparent backgrounds, leave this setting empty.</p>' .
                    '<p><strong>Image Compression:</strong> Reduce file sizes to speed up your website while maintaining good image quality.</p>' .
                    '<p><strong>Trim whitespace:</strong> Automatically crop away excess white borders to create consistent-looking images.</p>' .
                    '<p><strong>PNG-JPG Conversion:</strong> Transform images to JPG format for faster loading times. Only use if you don\'t need transparency.</p>' .
                    '<p><strong>Convert & Display WebP Images:</strong> Use the modern WebP format to significantly reduce file sizes while preserving image quality. Compatible with all modern browsers, with automatic fallback to standard formats.</p>'
            ));


            
            $help_sidebar = '<p><a href="https://sirplugin.com?utm_source=plugin&utm_medium=upgrade&utm_campaign=help_sidebar">Upgrade to PRO</a></p>' .
                '<p><a href="https://wordpress.org/support/plugin/smart-image-resize/" target="_blank">Report an issue</a></p>';
            
            $screen->set_help_sidebar(
                '<p><strong>' .
                    esc_html__('For more information:', WP_SIR_NAME) .
                    '</strong></p>' . $help_sidebar
            );
        }

        function settings_field_disable_upscale() {
            $settings = wp_sir_get_settings(); ?>
            <label for="wp-sir-disable-upscale">
                <input type="checkbox" 
                       name="wp_sir_settings[disable_upscale]" 
                       <?php checked($settings['disable_upscale'], 1); ?> 
                       id="wp-sir-disable-upscale" 
                       class="wp-sir-as-toggle" 
                       value="1" />
            </label>
            <p class="description">
                <?php _e('When enabled, small photos will be kept at their original size rather than stretching them larger. Empty space will be added around the image instead. This keeps your photos looking crisp and clear.', 'wp-smart-image-resize'); ?>
            </p>
            <?php
        }

        private function get_image_processor_info() {
            $info = [];
            $info['gd'] = [
                'available' => extension_loaded('gd'),
                'version' => function_exists('gd_info') ? gd_info()['GD Version'] : 'N/A'
            ];
            
            $info['imagick'] = [
                'available' => extension_loaded('imagick'),
                'version' => class_exists('Imagick') ? \Imagick::getVersion()['versionString'] : 'N/A'
            ];
            
            return $info;
        }

        private function generate_system_report() {
            global $wp_version;
            
            $report = [];
            $report['WordPress'] = $wp_version;
            $report['PHP Version'] = PHP_VERSION;
            $report['OS'] = PHP_OS;
            $report['Memory Limit'] = ini_get('memory_limit');
            $report['Max Execution Time'] = ini_get('max_execution_time');
            $report['Post Max Size'] = ini_get('post_max_size');
            $report['Upload Max Size'] = ini_get('upload_max_filesize');
            $report['Image Processing'] = $this->get_image_processor_info();
            $report['Plugin Settings'] = get_option('wp_sir_settings');
            
            return $report;
        }

        public function render_help_tab() {
            $image_processors = $this->get_image_processor_info();
            $current_processor = get_option('wp_sir_image_processor', '');
            ?>
            <div class="wp-sir-help-page">
                <div class="wp-sir-help-section">
                    <h2><?php _e('Quick Links', 'wp-smart-image-resize'); ?></h2>
                    <ul class="wp-sir-help-links">
                        <li>
                            <span class="dashicons dashicons-book"></span>
                            <a href="https://sirplugin.com/docs" target="_blank">
                                <?php _e('Documentation', 'wp-smart-image-resize'); ?>
                            </a>
                        </li>
                        <li>
                            <span class="dashicons dashicons-sos"></span>
                            <a href="https://sirplugin.com/support" target="_blank">
                                <?php _e('Contact Support', 'wp-smart-image-resize'); ?>
                            </a>
                        </li>
                        <li>
                            <span class="dashicons dashicons-warning"></span>
                            <a href="https://sirplugin.com/troubleshooting" target="_blank">
                                <?php _e('Troubleshooting Guide', 'wp-smart-image-resize'); ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="wp-sir-help-section">
                    <h2><?php _e('System Information', 'wp-smart-image-resize'); ?></h2>
                    <p class="description">
                        <?php _e('Download this information when contacting support to help us assist you better.', 'wp-smart-image-resize'); ?>
                    </p>
                    <p>
                        <button type="button" id="wp-sir-download-report" class="button button-secondary">
                            <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                            <?php _e('Download System Report', 'wp-smart-image-resize'); ?>
                        </button>
                    </p>
                </div>

                <div class="wp-sir-help-section">
                    <h2><?php _e('Image Processing', 'wp-smart-image-resize'); ?></h2>
                    <?php if ($image_processors['gd']['available'] || $image_processors['imagick']['available']) : ?>
                        <div class="wp-sir-processor-switch">
                            <label>
                                <select name="wp_sir_image_processor" id="wp-sir-processor-select">
                                    <option value="default" <?php selected($current_processor, ''); ?>>
                                        Default
                                    </option>
                                    <?php if ($image_processors['gd']['available']) : ?>
                                        <option value="gd" <?php selected($current_processor, 'gd'); ?>>
                                            GD (<?php echo esc_html($image_processors['gd']['version']); ?>)
                                        </option>
                                    <?php endif; ?>
                                    <?php if ($image_processors['imagick']['available']) : ?>
                                        <option value="imagick" <?php selected($current_processor, 'imagick'); ?>>
                                            ImageMagick (<?php echo esc_html($image_processors['imagick']['version']); ?>)
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </label>
                            <p class="description">
                                <?php _e('Select which image processing library to use. Change this only if you experience issues with image processing.', 'wp-smart-image-resize'); ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-error">
                            <p><?php _e('No image processing library available. Please contact your hosting provider.', 'wp-smart-image-resize'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <style>
            .wp-sir-help-page {
                max-width: 800px;
                margin: 20px 0;
            }
            .wp-sir-help-section {
                background: #fff;
                padding: 20px;
                margin-bottom: 20px;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .wp-sir-help-section h2 {
                margin-top: 0;
                padding-bottom: 12px;
                border-bottom: 1px solid #eee;
            }
            .wp-sir-help-links {
                margin: 0;
            }
            .wp-sir-help-links li {
                margin-bottom: 10px;
            }
            .wp-sir-help-links .dashicons {
                margin-right: 5px;
                color: #666;
            }
            .wp-sir-processor-switch select {
                min-width: 200px;
            }
            </style>

            <script>
            jQuery(function($) {
                // Handle processor change
                $('#wp-sir-processor-select').on('change', function() {
                    var processor = $(this).val();
                    $.post(ajaxurl, {
                        action: 'wp_sir_switch_processor',
                        processor: processor,
                        nonce: '<?php echo wp_create_nonce('wp_sir_switch_processor'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    });
                });

                // Handle system report download
                $('#wp-sir-download-report').on('click', function() {
                    var report = <?php echo json_encode($this->generate_system_report()); ?>;
                    var blob = new Blob([JSON.stringify(report, null, 2)], {type: 'application/json'});
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'sir-system-report.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                });
            });
            </script>
            <?php
        }

        // Add AJAX handler for processor switch
        public function ajax_switch_processor() {
            check_ajax_referer('wp_sir_switch_processor', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Unauthorized');
            }
            
            $processor = sanitize_text_field($_POST['processor']);
            if (!in_array($processor, ['gd', 'imagick', 'default'])) {
                wp_send_json_error('Invalid processor');
            }
            
            update_option('wp_sir_image_processor', ($processor == 'default' ? '' : $processor));

            wp_send_json_success();
        }
    }
endif;
