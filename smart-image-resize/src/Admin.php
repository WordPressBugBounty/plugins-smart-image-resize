<?php

namespace WP_Smart_Image_Resize;

use ActionScheduler_Store;
use WP_Smart_Image_Resize\Background_Process_On_Post_Save;
use WP_Smart_Image_Resize\Process_Tracker;
use WP_Smart_Image_Resize\Utilities\Env;
use \Imagick;

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

            // Show get-started notice for new installs.
            Get_Started_Notice::instance()->load();

            // Add plugin to WooCommerce menu.
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_filter('pre_update_option_wp_sir_settings', [$this, 'pre_update_settings']);
            // Show Woocommerce not installed notice.
            add_action('admin_notices', [$this, 'fileinfo_not_enabled']);
            add_action('admin_notices', [$this, 'phpversion_not_supported']);
            add_action('admin_notices', [$this, 'show_background_processing_notice']);
            // add_action('admin_notices',[$this,  'show_settings_saved_notice']);
            add_action('admin_init', [$this, 'show_settings_saved_notice']);
            
            // Handle settings form submission
            add_action('admin_init', [$this, 'handle_settings_form_submission'], 5);
            
            
            add_action('admin_notices', [$this, 'quota_exceeding_soon']);
            add_action('admin_notices', [$this, 'quota_exceeded_notice']);

            
            // Initialise settings form.
            add_action('admin_init', [$this, 'init_settings']);

            // Restore original image from backup (single image — media library).
            add_action('wp_ajax_wp_sir_restore_original',    [$this, 'ajax_restore_original']);

            // Bulk restore — all processed images, replaces the old split restore actions.
            add_action('wp_ajax_wp_sir_bulk_restore_status', [$this, 'ajax_bulk_restore_status']);
            add_action('wp_ajax_wp_sir_bulk_restore_batch',  [$this, 'ajax_bulk_restore_batch']);

            // Media library: row action (list view) + attachment fields (grid view).
            add_filter('media_row_actions',   [$this, 'media_row_restore_action'], 10, 2);
            add_filter('attachment_fields_to_edit', [$this, 'attachment_field_restore_action'], 10, 2);

            // Inline JS for the grid-view restore button.
            add_action('admin_footer', [$this, 'print_restore_original_js']);

            // Add settings help tab.
            add_action('load-woocommerce_smart-image-resize', [$this, 'settings_help'], 5, 3);

            add_filter('plugin_action_links_' . WP_SIR_BASENAME, [$this, 'plugin_links']);

            add_filter('admin_footer_text', [$this, 'admin_footer_text']);

            // Add Help tab
            $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
            if ($page === WP_SIR_NAME && $tab === 'help') {
                add_action('admin_enqueue_scripts', function() {
                    wp_enqueue_style('wp-sir-admin');
                    wp_enqueue_script('wp-sir-admin');
                });
            }

            // Add AJAX handler for processor switch
            add_action('wp_ajax_wp_sir_switch_processor', [$this, 'ajax_switch_processor']);
            
            // Add nonce to wp_sir_object
            // add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        }

        
        /**
         * Enqueue admin scripts and localize data
         */
        // public function enqueue_admin_scripts() {
        //     $screen = get_current_screen();
        //     if (!$screen || strpos($screen->id, 'wp-smart-image-resize') === false) {
        //         return;
        //     }

        //     wp_enqueue_script('wp-sir-admin');
        //     wp_localize_script('wp-sir-admin', 'wp_sir_object2', array(
        //         'nonce' => wp_create_nonce('sir_install_rt'),
        //         'ajax_url' => admin_url('admin-ajax.php')
        //     ));
        // }

        public function plugin_settings_saved(){
            $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            $settings_updated = isset($_GET['settings-updated']) ? sanitize_text_field($_GET['settings-updated']) : '';
            if ($page === WP_SIR_NAME && $settings_updated) {
                $bulk_url = admin_url( 'admin.php?page=' . WP_SIR_NAME . '&tab=bulk-regenerate' );
                add_settings_error(
                    WP_SIR_NAME,
                    'settings_updated',
                    'Settings saved successfully. To apply these changes to existing images, <a href="' . esc_url( $bulk_url ) . '">run Bulk Resize</a>.',
                    'updated'
                );
            }
        }

        public function show_settings_saved_notice(){
            $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            if($page === WP_SIR_NAME){
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
                    <p><b>Smart Image  Resize:</b> Processing recently uploaded images in the background. This may take a little while, so please be patient. <?php echo wp_kses_post($pending_message); ?></p>
                </div>
<?php } 
        }
        

        function quota_exceeding_soon() {
            if (Process_Tracker::is_nearing_limit()) { ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php esc_html_e(
                            'Smart Image Resize: Your are reaching your limit for re-sizing images.',
                            'wp-smart-image-resize'
                        ); ?>
                        <a target="_blank" href="https:/sirplugin.com/#pro?utm_source=plugin&utm_campaign=notice_limit" class="button button-default"><?php esc_html_e(
                                                                                                                                                            'Upgrade to Pro',
                                                                                                                                                            'wp-smart-image-resize'
                                                                                                                                                        ); ?></a> for
                        unlimited images.
                    </p>
                </div>
            <?php }
        }

        function quota_exceeded_notice() {
            if (Process_Tracker::has_reached_limit()) { ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e(
                            'Smart Image Resize: Your have reached your limit for re-sizing images.',
                            'wp-smart-image-resize'
                        ); ?>
                        <a target="_blank" href="https:/sirplugin.com/#pro?utm_source=plugin&utm_campaign=notice_limit" class="button button-default"><?php esc_html_e(
                                                                                                                                                            'Upgrade to Pro',
                                                                                                                                                            'wp-smart-image-resize'
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

            $settings_url    = admin_url('admin.php?page=wp-smart-image-resize&tab=general');
            $settings_anchor = '<a href="' . $settings_url . '">' . __('Settings') . '</a>';
            array_unshift($links, $settings_anchor);


            
            $links[] = '<a href="https://sirplugin.com/?utm_source=plugin&utm_medium=installed_plugins&utm_campaign=go_pro" target="_blank" style="font-weight:bold;color:#f97316">Go Pro</a>';
            

            return $links;
        }

        function pre_update_settings($newval) {
            // Ensure we have an array
            if (!is_array($newval)) {
                $newval = [];
            }

            $defaults = [
                'enable'      => 0,
                'jpg_convert' => 0,
                'enable_webp' => 0,
                // 'enable_avif' => 0,
                'enable_trim' => 0,
                'enable_watermark' => 0,
                'bg_color' => '#ffffff',
                'jpg_quality' => 0,
                'sizes' => [],
                'trim_feather' => 0,
                'trim_tolerance' => 3,
                'watermark_size' => 50,
                'watermark_image' => 0,
                'watermark_position' => 'center',
                'watermark_opacity' => 50,
                'watermark_offset' => ['x' => 0, 'y' => 0],
                'crop_mode' => 'pad',
                'disable_upscale' => 0,
                'process_original' => 0,
                'processable_images' => ['post_types' => [], 'taxonomies' => []],
                'size_options' => [],
            ];

            // Sanitize boolean/checkbox fields
            $newval['enable'] = !empty($newval['enable']) ? 1 : 0;
            $newval['jpg_convert'] = !empty($newval['jpg_convert']) ? 1 : 0;
            $newval['enable_webp'] = !empty($newval['enable_webp']) ? 1 : 0;
            $newval['enable_trim'] = !empty($newval['enable_trim']) ? 1 : 0;
            $newval['enable_watermark'] = !empty($newval['enable_watermark']) ? 1 : 0;
            $newval['disable_upscale'] = !empty($newval['disable_upscale']) ? 1 : 0;
            $newval['process_original'] = !empty($newval['process_original']) ? 1 : 0;

            // Sanitize color field
            if (isset($newval['bg_color'])) {
                $newval['bg_color'] = sanitize_hex_color($newval['bg_color']);
                if (empty($newval['bg_color'])) {
                    $newval['bg_color'] = '';
                }
            }

            // Sanitize numeric fields
            if (isset($newval['jpg_quality'])) {
                $newval['jpg_quality'] = absint($newval['jpg_quality']);
                $newval['jpg_quality'] = min(100, max(0, $newval['jpg_quality']));
            }

            if (isset($newval['trim_feather'])) {
                $newval['trim_feather'] = absint($newval['trim_feather']);
                $newval['trim_feather'] = min(100, max(0, $newval['trim_feather']));
            }

            if (isset($newval['trim_tolerance'])) {
                $newval['trim_tolerance'] = absint($newval['trim_tolerance']);
                $newval['trim_tolerance'] = min(100, max(0, $newval['trim_tolerance']));
            }

            if (isset($newval['watermark_size'])) {
                $newval['watermark_size'] = absint($newval['watermark_size']);
                $newval['watermark_size'] = min(100, max(1, $newval['watermark_size']));
            }

            if (isset($newval['watermark_opacity'])) {
                $newval['watermark_opacity'] = absint($newval['watermark_opacity']);
                $newval['watermark_opacity'] = min(100, max(0, $newval['watermark_opacity']));
            }

            if (isset($newval['watermark_image'])) {
                $newval['watermark_image'] = absint($newval['watermark_image']);
            }

            // Sanitize watermark position
            if (isset($newval['watermark_position'])) {
                $allowed_positions = ['top-left', 'top', 'top-right', 'left', 'center', 'right', 'bottom-left', 'bottom', 'bottom-right'];
                $newval['watermark_position'] = sanitize_text_field($newval['watermark_position']);
                if (!in_array($newval['watermark_position'], $allowed_positions, true)) {
                    $newval['watermark_position'] = 'center';
                }
            }

            // Sanitize crop mode
            if (isset($newval['crop_mode'])) {
                $newval['crop_mode'] = sanitize_text_field($newval['crop_mode']);
                if (!in_array($newval['crop_mode'], ['pad', 'fill'], true)) {
                    $newval['crop_mode'] = 'pad';
                }
            }

            // Sanitize watermark offset
            if (!isset($newval['watermark_offset']) || !is_array($newval['watermark_offset'])) {
                $newval['watermark_offset'] = [];
            }
            $newval['watermark_offset']['x'] = isset($newval['watermark_offset']['x']) ? absint($newval['watermark_offset']['x']) : 0;
            $newval['watermark_offset']['y'] = isset($newval['watermark_offset']['y']) ? absint($newval['watermark_offset']['y']) : 0;

            // Sanitize sizes array
            if (isset($newval['sizes']) && is_array($newval['sizes'])) {
                $newval['sizes'] = array_map('sanitize_text_field', $newval['sizes']);
            } else {
                $newval['sizes'] = [];
            }

            // Sanitize processable images
            if (isset($newval['processable_images']['taxonomies']) && is_array($newval['processable_images']['taxonomies'])) {
                $newval['processable_images']['taxonomies'] = array_map('sanitize_text_field', $newval['processable_images']['taxonomies']);
            } else {
                $newval['processable_images']['taxonomies'] = [];
            }
            
            if (isset($newval['processable_images']['post_types']) && is_array($newval['processable_images']['post_types'])) {
                $newval['processable_images']['post_types'] = array_map('sanitize_text_field', $newval['processable_images']['post_types']);
            } else {
                $newval['processable_images']['post_types'] = [];
            }

            // Sanitize size_options
            if (isset($newval['size_options']) && is_array($newval['size_options'])) {
                $sanitized_size_options = [];
                foreach ($newval['size_options'] as $size_name => $options) {
                    $size_name = sanitize_text_field($size_name);
                    if (is_array($options)) {
                        $sanitized_size_options[$size_name] = [];
                        if (isset($options['width'])) {
                            $sanitized_size_options[$size_name]['width'] = absint($options['width']);
                        }
                        if (isset($options['height'])) {
                            $sanitized_size_options[$size_name]['height'] = absint($options['height']);
                        }
                        if (isset($options['fit_mode'])) {
                            $fit_mode = sanitize_text_field($options['fit_mode']);
                            $sanitized_size_options[$size_name]['fit_mode'] = in_array($fit_mode, ['contain', 'none'], true) ? $fit_mode : 'contain';
                        }
                    }
                }
                $newval['size_options'] = $sanitized_size_options;
            } else {
                $newval['size_options'] = [];
            }

            $settings = wp_parse_args($newval, $defaults);
            
            
            $settings['enable_watermark'] = 0;
            $settings['jpg_convert'] = 0;
            $settings['enable_webp'] = 0;
            //   $settings['enable_avif'] = 0;
            
            
            return $settings;
        }


        public function fileinfo_not_enabled() {
            if (!extension_loaded('fileinfo')) : ?>
                <div class="notice notice-error  is-dismissible">
                    <p><?php esc_html_e(
                            'Smart Image Resize: PHP Fileinfo extension is not enabled, contact your hosting provider to enable it.',
                            'wp-smart-image-resize'
                        ); ?></p>
                </div>
            <?php endif;
        }

        public function phpversion_not_supported() {
            if (!version_compare(PHP_VERSION, '5.6.0', '>=')) : ?>
                <div class="notice notice-error  is-dismissible">
                    <p><?php esc_html_e(
                            'Smart Image Resize requires PHP 5.6.0 or greater to work correctly.',
                            'wp-smart-image-resize'
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
         * AJAX: return IDs of all images that have been processed by the plugin.
         * Used to populate the bulk restore UI on page load.
         */
        public function ajax_bulk_restore_status() {
            check_ajax_referer( 'wp_sir_bulk_restore', 'nonce' );

            if ( ! current_user_can( 'upload_files' ) ) {
                wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-smart-image-resize' ) ], 403 );
            }

            global $wpdb;

            $ids = $wpdb->get_col(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_processed_at' AND meta_value != ''
                 ORDER BY post_id ASC"
            );

            $ids = array_map( 'intval', array_filter( $ids ) );

            wp_send_json_success( [ 'total' => count( $ids ), 'ids' => array_values( $ids ) ] );
        }

        /**
         * AJAX: restore a batch of images to their pre-plugin state.
         *
         * For each image:
         *   1. If a file backup exists → restore the original file from backup.
         *   2. Regenerate thumbnails with the plugin bypassed (runtime filter).
         *   3. Delete plugin meta so the image is treated as unprocessed.
         *
         * Expects `ids` (JSON array of attachment IDs) in POST.
         */
        public function ajax_bulk_restore_batch() {
            check_ajax_referer( 'wp_sir_bulk_restore', 'nonce' );

            if ( ! current_user_can( 'upload_files' ) ) {
                wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-smart-image-resize' ) ], 403 );
            }

            $ids = json_decode( stripslashes( $_POST['ids'] ?? '[]' ), true );
            if ( ! is_array( $ids ) || empty( $ids ) ) {
                wp_send_json_error( [ 'message' => __( 'No IDs provided.', 'wp-smart-image-resize' ) ] );
            }

            require_once ABSPATH . 'wp-admin/includes/image.php';

            $backup  = new \WP_Smart_Image_Resize\Utilities\Backup();
            $results = [ 'restored' => [], 'errors' => [] ];

            // Runtime filter: makes the plugin a no-op for the duration of
            // wp_generate_attachment_metadata() without touching the DB option.
            $bypass = function ( $settings ) {
                $settings['enable'] = 0;
                return $settings;
            };

            foreach ( $ids as $attachment_id ) {
                $attachment_id = absint( $attachment_id );
                $file          = get_attached_file( $attachment_id );

                // For PNG→JPG converted images, the backup is stored under the
                // original .png path. Check for the pre-conversion meta.
                $pre_conversion_relative = get_post_meta( $attachment_id, '_sir_pre_conversion_file', true );
                if ( $pre_conversion_relative ) {
                    $uploads_dir         = trailingslashit( wp_get_upload_dir()['basedir'] );
                    $pre_conversion_file = $uploads_dir . $pre_conversion_relative;
                    if ( $backup->exists( $pre_conversion_file ) ) {
                        $file = $pre_conversion_file;
                    }
                }

                if ( ! $file || ! is_readable( $file ) ) {
                    // If the file isn't readable yet, the backup might restore it.
                    if ( ! $backup->exists( $file ) ) {
                        $results['errors'][] = [
                            'id'     => $attachment_id,
                            'reason' => __( 'File not found or not readable.', 'wp-smart-image-resize' ),
                        ];
                        continue;
                    }
                }

                try {
                    @set_time_limit( 60 );

                    // 0. Delete old thumbnails (including WebP and converted variants).
                    $this->cleanup_thumbnails( $attachment_id );

                    // 1. Restore original file from backup if one exists.
                    if ( $backup->exists( $file ) ) {
                        $backup->restore( $file );
                        // If this was a converted file, reset the attached file path.
                        if ( $pre_conversion_relative ) {
                            update_attached_file( $attachment_id, $pre_conversion_relative );
                            // Remove the .jpg if it's different from the restored .png.
                            $old_file = get_attached_file( $attachment_id );
                            if ( $old_file !== $file && file_exists( $old_file ) ) {
                                @unlink( $old_file );
                            }
                        }
                    }

                    // 2. Regenerate thumbnails with the plugin disabled.
                    $regenerate_file = get_attached_file( $attachment_id );
                    add_filter( 'wp_sir_settings', $bypass, PHP_INT_MAX );
                    $meta = wp_generate_attachment_metadata( $attachment_id, $regenerate_file );
                    remove_filter( 'wp_sir_settings', $bypass, PHP_INT_MAX );

                    if ( ! empty( $meta ) && is_array( $meta ) ) {
                        wp_update_attachment_metadata( $attachment_id, $meta );
                    }

                    // 3. Clear plugin meta so the image is treated as unprocessed.
                    delete_post_meta( $attachment_id, '_processed_at' );
                    delete_post_meta( $attachment_id, '_processed_by' );
                    delete_post_meta( $attachment_id, '_old_image_meta' );
                    delete_post_meta( $attachment_id, '_sir_pre_conversion_file' );

                    
                    \WP_Smart_Image_Resize\Process_Tracker::unrecord( $attachment_id );
                    

                    $results['restored'][] = $attachment_id;

                } catch ( \Exception $e ) {
                    // Make sure the bypass filter is removed even on failure.
                    remove_filter( 'wp_sir_settings', $bypass, PHP_INT_MAX );
                    $results['errors'][] = [ 'id' => $attachment_id, 'reason' => $e->getMessage() ];
                }
            }

            wp_send_json_success( $results );
        }

        /**
         * AJAX: restore the original image from its backup.
         */
        public function ajax_restore_original() {
            // Support both POST (grid-view button sends 'nonce') and GET
            // (list-view row action sends '_wpnonce' via wp_nonce_url).
            if ( ! empty( $_REQUEST['nonce'] ) ) {
                check_ajax_referer( 'wp_sir_restore_original', 'nonce' );
            } else {
                check_ajax_referer( 'wp_sir_restore_original' );
            }

            if ( ! current_user_can( 'upload_files' ) ) {
                wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-smart-image-resize' ) ], 403 );
            }

            $attachment_id = absint( $_REQUEST['attachment_id'] ?? 0 );
            if ( ! $attachment_id ) {
                wp_send_json_error( [ 'message' => __( 'Invalid attachment.', 'wp-smart-image-resize' ) ] );
            }

            $file   = get_attached_file( $attachment_id );
            $backup = new \WP_Smart_Image_Resize\Utilities\Backup();

            // For PNG→JPG converted images, the backup is under the original .png path.
            $pre_conversion_relative = get_post_meta( $attachment_id, '_sir_pre_conversion_file', true );
            if ( $pre_conversion_relative && ! $backup->exists( $file ) ) {
                $uploads_dir = trailingslashit( wp_get_upload_dir()['basedir'] );
                $pre_conversion_file = $uploads_dir . $pre_conversion_relative;
                if ( $backup->exists( $pre_conversion_file ) ) {
                    $file = $pre_conversion_file;
                }
            }

            if ( ! $backup->exists( $file ) ) {
                wp_send_json_error( [ 'message' => __( 'No backup found for this image.', 'wp-smart-image-resize' ) ] );
            }

            try {
                // Delete old thumbnails before restoring.
                $this->cleanup_thumbnails( $attachment_id );

                $backup->restore( $file );

                // If this was a PNG→JPG conversion, reset the attached file path.
                if ( $pre_conversion_relative ) {
                    $current_file = get_attached_file( $attachment_id );
                    if ( $current_file !== $file && file_exists( $current_file ) ) {
                        @unlink( $current_file );
                    }
                    update_attached_file( $attachment_id, $pre_conversion_relative );
                    $file = get_attached_file( $attachment_id );
                }

                // Regenerate metadata with the plugin disabled so it doesn't re-process.
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $bypass = function ( $settings ) {
                    $settings['enable'] = 0;
                    return $settings;
                };
                add_filter( 'wp_sir_settings', $bypass, PHP_INT_MAX );
                $meta = wp_generate_attachment_metadata( $attachment_id, $file );
                remove_filter( 'wp_sir_settings', $bypass, PHP_INT_MAX );

                if ( ! empty( $meta ) ) {
                    wp_update_attachment_metadata( $attachment_id, $meta );
                }

                // Clear plugin meta.
                delete_post_meta( $attachment_id, '_processed_at' );
                delete_post_meta( $attachment_id, '_processed_by' );
                delete_post_meta( $attachment_id, '_old_image_meta' );
                delete_post_meta( $attachment_id, '_sir_pre_conversion_file' );

                
                \WP_Smart_Image_Resize\Process_Tracker::unrecord( $attachment_id );
                

                wp_send_json_success( [ 'message' => __( 'Original image restored successfully.', 'wp-smart-image-resize' ) ] );
            } catch ( \Exception $e ) {
                wp_send_json_error( [ 'message' => $e->getMessage() ] );
            }
        }

        /**
         * Delete all thumbnail files for an attachment, including WebP and
         * PNG→JPG converted variants. Does NOT delete the original/full file.
         *
         * @param int $attachment_id
         */
        private function cleanup_thumbnails( $attachment_id ) {
            $meta = wp_get_attachment_metadata( $attachment_id );
            if ( empty( $meta ) || empty( $meta['file'] ) ) {
                return;
            }

            $uploads_dir = trailingslashit( wp_get_upload_dir()['basedir'] );
            $image_dir   = $uploads_dir . trailingslashit( dirname( $meta['file'] ) );
            $original    = wp_basename( $meta['file'] );

            // Collect all thumbnail filenames from metadata.
            $files_to_delete = [];
            if ( ! empty( $meta['sizes'] ) ) {
                foreach ( $meta['sizes'] as $size_data ) {
                    if ( ! empty( $size_data['file'] ) && $size_data['file'] !== $original ) {
                        $files_to_delete[] = $size_data['file'];
                    }
                }
            }

            // Also check _old_image_meta for previously generated thumbnails.
            $old_meta = get_post_meta( $attachment_id, '_old_image_meta', true );
            if ( is_array( $old_meta ) && ! empty( $old_meta['sizes'] ) ) {
                foreach ( $old_meta['sizes'] as $size_data ) {
                    if ( ! empty( $size_data['file'] ) && $size_data['file'] !== $original ) {
                        $files_to_delete[] = $size_data['file'];
                    }
                }
            }

            $files_to_delete = array_unique( $files_to_delete );

            foreach ( $files_to_delete as $file ) {
                $full_path = $image_dir . $file;

                // Delete the thumbnail.
                if ( file_exists( $full_path ) ) {
                    @unlink( $full_path );
                }

                // Delete WebP variant.
                $webp_path = $image_dir . pathinfo( $file, PATHINFO_FILENAME ) . '.webp';
                if ( file_exists( $webp_path ) && wp_basename( $webp_path ) !== $original ) {
                    @unlink( $webp_path );
                }
            }

            // Delete full-size WebP copy.
            $original_webp = $image_dir . pathinfo( $original, PATHINFO_FILENAME ) . '.webp';
            if ( file_exists( $original_webp ) && wp_basename( $original_webp ) !== $original ) {
                @unlink( $original_webp );
            }
        }

        /**
         * Add a "Restore Original" row action in the media library list view.
         * Only shown when a backup exists for the attachment.
         */
        public function media_row_restore_action( $actions, $post ) {
            if ( ! current_user_can( 'upload_files' ) ) {
                return $actions;
            }

            $file   = get_attached_file( $post->ID );
            $backup = new \WP_Smart_Image_Resize\Utilities\Backup();

            // Also check the pre-conversion path for PNG→JPG converted images.
            $has_backup = $backup->exists( $file );
            if ( ! $has_backup ) {
                $pre_conversion = get_post_meta( $post->ID, '_sir_pre_conversion_file', true );
                if ( $pre_conversion ) {
                    $uploads_dir = trailingslashit( wp_get_upload_dir()['basedir'] );
                    $has_backup  = $backup->exists( $uploads_dir . $pre_conversion );
                }
            }

            if ( ! $has_backup ) {
                return $actions;
            }

            $url = wp_nonce_url(
                add_query_arg( [
                    'action'        => 'wp_sir_restore_original',
                    'attachment_id' => $post->ID,
                ], admin_url( 'admin-ajax.php' ) ),
                'wp_sir_restore_original'
            );

            $actions['wp_sir_restore'] = sprintf(
                '<a href="%s" class="wp-sir-restore-original" data-id="%d" aria-label="%s">%s</a>',
                esc_url( $url ),
                esc_attr( $post->ID ),
                esc_attr__( 'Restore original image', 'wp-smart-image-resize' ),
                esc_html__( 'Restore Original', 'wp-smart-image-resize' )
            );

            return $actions;
        }

        /**
         * Add a "Restore Original" button in the media library grid/attachment modal.
         * Only shown when a backup exists for the attachment.
         */
        public function attachment_field_restore_action( $form_fields, $post ) {
            if ( ! current_user_can( 'upload_files' ) ) {
                return $form_fields;
            }

            $file   = get_attached_file( $post->ID );
            $backup = new \WP_Smart_Image_Resize\Utilities\Backup();

            // Also check the pre-conversion path for PNG→JPG converted images.
            $has_backup = $backup->exists( $file );
            if ( ! $has_backup ) {
                $pre_conversion = get_post_meta( $post->ID, '_sir_pre_conversion_file', true );
                if ( $pre_conversion ) {
                    $uploads_dir = trailingslashit( wp_get_upload_dir()['basedir'] );
                    $has_backup  = $backup->exists( $uploads_dir . $pre_conversion );
                }
            }

            if ( ! $has_backup ) {
                return $form_fields;
            }

            $nonce = wp_create_nonce( 'wp_sir_restore_original' );

            $form_fields['wp_sir_restore'] = [
                'label' => __( 'Smart Image Resize', 'wp-smart-image-resize' ),
                'input' => 'html',
                'html'  => sprintf(
                    '<button type="button" class="button wp-sir-restore-original-btn" data-id="%d" data-nonce="%s">%s</button>
                     <span class="wp-sir-restore-msg" style="margin-left:8px;display:none"></span>',
                    esc_attr( $post->ID ),
                    esc_attr( $nonce ),
                    esc_html__( 'Restore Original Image', 'wp-smart-image-resize' )
                ),
            ];

            return $form_fields;
        }

        /**
         * Print inline JS for the grid-view "Restore Original" button.
         * Only outputs on media library screens.
         */
        public function print_restore_original_js() {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ( ! $screen || ! in_array( $screen->id, [ 'upload', 'media' ], true ) ) {
                return;
            }
            ?>
            <script>
            (function($){
                // Grid view / attachment modal button.
                $(document).on('click', '.wp-sir-restore-original-btn', function(){
                    var $btn  = $(this);
                    var $msg  = $btn.siblings('.wp-sir-restore-msg');
                    var id    = $btn.data('id');
                    var nonce = $btn.data('nonce');

                    $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Restoring…', 'wp-smart-image-resize' ) ); ?>');
                    $msg.hide();

                    $.post(ajaxurl, {
                        action: 'wp_sir_restore_original',
                        attachment_id: id,
                        nonce: nonce
                    }, function(res){
                        if (res.success) {
                            $msg.css('color','green').text(res.data.message).show();
                            $btn.text('<?php echo esc_js( __( 'Restored', 'wp-smart-image-resize' ) ); ?>');
                        } else {
                            $msg.css('color','red').text(res.data.message).show();
                            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Restore Original Image', 'wp-smart-image-resize' ) ); ?>');
                        }
                    });
                });

                // List view row action link — use direct URL but intercept for feedback.
                $(document).on('click', 'a.wp-sir-restore-original', function(e){
                    e.preventDefault();
                    var $link = $(this);
                    var href  = $link.attr('href');

                    $link.text('<?php echo esc_js( __( 'Restoring…', 'wp-smart-image-resize' ) ); ?>');

                    $.get(href, function(res){
                        if (res.success) {
                            $link.text('<?php echo esc_js( __( 'Restored', 'wp-smart-image-resize' ) ); ?>');
                        } else {
                            $link.text('<?php echo esc_js( __( 'Error — try again', 'wp-smart-image-resize' ) ); ?>');
                        }
                    });
                });
            }(jQuery));
            </script>
            <?php
        }

        /**
         * Initialize settings form.
         *
         * We keep register_setting() so WordPress handles sanitization and saving
         * via options.php. The sections and fields are rendered manually in the
         * template, so we no longer register them with the Settings API.
         *
         * @return void
         */
        public function init_settings() {
            register_setting(WP_SIR_NAME, 'wp_sir_settings', [
                'sanitize_callback' => [$this, 'pre_update_settings'],
                'default' => _wp_sir_get_default_settings(),
            ]);
        }

        function settings_field_enable_trim() {
            $settings = \wp_sir_get_settings(); ?>
            <div class="wp-sir-trim-settings">
                <label class="wp-sir-toggle-row" for="wp-sir-enable-trim">
                    <input type="checkbox"
                           name="wp_sir_settings[enable_trim]"
                           <?php checked( $settings['enable_trim'], 1 ); ?>
                           id="wp-sir-enable-trim"
                           class="wp-sir-as-toggle"
                           value="1" />
                    <span class="wp-sir-toggle-row__desc">
                        <?php esc_html_e( 'Remove excess white space from around images for a clean, uniform appearance.', 'wp-smart-image-resize' ); ?>
                    </span>
                </label>

                <div class="wp-sir-trim-advanced-settings" style="display:<?php echo $settings['enable_trim'] ? 'block' : 'none'; ?>">

                    <div class="wp-sir-sub-field">
                        <label class="wp-sir-sub-field__label" for="wp-sir-trim-tolerance">
                            <?php esc_html_e( 'Color Tolerance', 'wp-smart-image-resize' ); ?>
                            <span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Higher values trim colors similar to white. Use with caution — it may trim parts of your image.', 'wp-smart-image-resize' ); ?>"></span>
                        </label>
                        <div class="wp-sir-range-wrapper">
                            <input type="range"
                                   min="0" max="100"
                                   name="wp_sir_settings[trim_tolerance]"
                                   value="<?php echo esc_attr( $settings['trim_tolerance'] ); ?>"
                                   class="wp-sir-range-input"
                                   id="wp-sir-trim-tolerance"
                                   data-value-display="wp-sir-tolerance-value" />
                            <span class="wp-sir-range-value" id="wp-sir-tolerance-value"><?php echo esc_html( $settings['trim_tolerance'] ); ?>%</span>
                        </div>
                        <p class="wp-sir-field-hint wp-sir-tolerance-feedback">
                            <?php esc_html_e( 'Default: 3%. Increase to trim more aggressively.', 'wp-smart-image-resize' ); ?>
                        </p>
                    </div>

                    <div class="wp-sir-sub-field">
                        <label class="wp-sir-sub-field__label" for="wp-sir-trim-feather">
                            <?php esc_html_e( 'Preserve Border', 'wp-smart-image-resize' ); ?>
                            <span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Add a small border around the trimmed image to prevent cutting too close to the edge.', 'wp-smart-image-resize' ); ?>"></span>
                        </label>
                        <div class="wp-sir-input-suffix">
                            <input type="number"
                                   min="0" max="100"
                                   name="wp_sir_settings[trim_feather]"
                                   id="wp-sir-trim-feather"
                                   value="<?php echo esc_attr( $settings['trim_feather'] ); ?>"
                                   class="small-text" />
                            <span class="wp-sir-input-suffix__unit">px</span>
                        </div>
                        <p class="wp-sir-field-hint">
                            <?php esc_html_e( 'Border width to keep around the trimmed image.', 'wp-smart-image-resize' ); ?>
                        </p>
                    </div>

                </div>
            </div>
        <?php
        }

        function settings_field_watermark() {
            $settings = \wp_sir_get_settings(); ?>
            <div class="wp-sir-watermark-layout">

                <!-- Controls column -->
                <div class="wp-sir-watermark-controls">

                    <div class="wp-sir-sub-field">
                        <label class="wp-sir-sub-field__label">
                            <?php esc_html_e( 'Watermark Image', 'wp-smart-image-resize' ); ?>
                        </label>
                        <button type="button" class="button button-secondary" id="wp-sir-open-media-uploader">
                            <span class="dashicons dashicons-upload" style="margin-top:3px"></span>
                            <?php esc_html_e( 'Select / Upload Image', 'wp-smart-image-resize' ); ?>
                        </button>
                        <input type="hidden"
                               name="wp_sir_settings[watermark_image]"
                               value="<?php echo esc_attr( $settings['watermark_image'] ); ?>"
                               <?php if ( ! empty( $settings['watermark_image'] ) ) :
                                   $wm   = wp_get_attachment_image_src( $settings['watermark_image'], 'full' );
                                   $size = is_array( $wm ) ? esc_attr( json_encode( [ 'w' => $wm[1], 'h' => $wm[2] ] ) ) : '';
                               ?>
                               data-size='<?php echo $size; ?>'
                               <?php endif; ?> />
                    </div>

                    <div class="wp-sir-sub-field">
                        <label class="wp-sir-sub-field__label" for="wp-sir-watermark-size-input">
                            <?php esc_html_e( 'Size', 'wp-smart-image-resize' ); ?>
                            <span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Watermark size as a percentage of the image dimensions (1–100%).', 'wp-smart-image-resize' ); ?>"></span>
                        </label>
                        <div class="wp-sir-range-wrapper">
                            <input name="wp_sir_settings[watermark_size]"
                                   class="wp-sir-watermark-size wp-sir-range-input"
                                   type="range" min="1" max="100"
                                   value="<?php echo esc_attr( $settings['watermark_size'] ); ?>"
                                   data-value-display="wp-sir-watermark-size-value" />
                            <span class="wp-sir-range-value" id="wp-sir-watermark-size-value"><?php echo esc_html( $settings['watermark_size'] ); ?>%</span>
                        </div>
                    </div>

                    <div class="wp-sir-sub-field">
                        <label class="wp-sir-sub-field__label" for="wp-sir-watermark-opacity-input">
                            <?php esc_html_e( 'Opacity', 'wp-smart-image-resize' ); ?>
                            <span class="wp-sir-help-tip" title="<?php esc_attr_e( '0% = fully transparent, 100% = fully visible.', 'wp-smart-image-resize' ); ?>"></span>
                        </label>
                        <div class="wp-sir-range-wrapper">
                            <input name="wp_sir_settings[watermark_opacity]"
                                   class="wp-sir-watermark-opacity wp-sir-range-input"
                                   type="range" min="0" max="100"
                                   value="<?php echo esc_attr( $settings['watermark_opacity'] ); ?>"
                                   data-value-display="wp-sir-watermark-opacity-value" />
                            <span class="wp-sir-range-value" id="wp-sir-watermark-opacity-value"><?php echo esc_html( $settings['watermark_opacity'] ); ?>%</span>
                        </div>
                    </div>

                    <div class="wp-sir-sub-field">
                        <label class="wp-sir-sub-field__label">
                            <?php esc_html_e( 'Position', 'wp-smart-image-resize' ); ?>
                        </label>
                        <!-- Hidden select — value driven by the 3×3 grid below -->
                        <select name="wp_sir_settings[watermark_position]" id="wp-sir-watermark-position" class="hidden">
                            <option value="top-left"     <?php selected( $settings['watermark_position'], 'top-left' ); ?>><?php esc_html_e( 'Top Left', 'wp-smart-image-resize' ); ?></option>
                            <option value="top"          <?php selected( $settings['watermark_position'], 'top' ); ?>><?php esc_html_e( 'Top Center', 'wp-smart-image-resize' ); ?></option>
                            <option value="top-right"    <?php selected( $settings['watermark_position'], 'top-right' ); ?>><?php esc_html_e( 'Top Right', 'wp-smart-image-resize' ); ?></option>
                            <option value="left"         <?php selected( $settings['watermark_position'], 'left' ); ?>><?php esc_html_e( 'Middle Left', 'wp-smart-image-resize' ); ?></option>
                            <option value="center"       <?php selected( $settings['watermark_position'], 'center' ); ?>><?php esc_html_e( 'Center', 'wp-smart-image-resize' ); ?></option>
                            <option value="right"        <?php selected( $settings['watermark_position'], 'right' ); ?>><?php esc_html_e( 'Middle Right', 'wp-smart-image-resize' ); ?></option>
                            <option value="bottom-left"  <?php selected( $settings['watermark_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom Left', 'wp-smart-image-resize' ); ?></option>
                            <option value="bottom"       <?php selected( $settings['watermark_position'], 'bottom' ); ?>><?php esc_html_e( 'Bottom Center', 'wp-smart-image-resize' ); ?></option>
                            <option value="bottom-right" <?php selected( $settings['watermark_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom Right', 'wp-smart-image-resize' ); ?></option>
                        </select>
                        <!-- 3×3 position picker rendered by JS (createCurveControl) -->
                    </div>

                    <div class="wp-sir-sub-field">
                        <label class="wp-sir-sub-field__label">
                            <?php esc_html_e( 'Offset', 'wp-smart-image-resize' ); ?>
                            <span class="wp-sir-help-tip" title="<?php esc_attr_e( 'Fine-tune the watermark position in pixels from the chosen anchor point.', 'wp-smart-image-resize' ); ?>"></span>
                        </label>
                        <div class="wp-sir-offset-row">
                            <label class="wp-sir-offset-field" for="wp-sir-watermark-offset-x">
                                <span class="wp-sir-offset-field__axis">X</span>
                                <input type="number" min="0"
                                       id="wp-sir-watermark-offset-x"
                                       class="wp-sir-offset-input small-text"
                                       name="wp_sir_settings[watermark_offset][x]"
                                       value="<?php echo esc_attr( $settings['watermark_offset']['x'] ); ?>" />
                                <span class="wp-sir-input-suffix__unit">px</span>
                            </label>
                            <label class="wp-sir-offset-field" for="wp-sir-watermark-offset-y">
                                <span class="wp-sir-offset-field__axis">Y</span>
                                <input type="number" min="0"
                                       id="wp-sir-watermark-offset-y"
                                       class="wp-sir-offset-input small-text"
                                       name="wp_sir_settings[watermark_offset][y]"
                                       value="<?php echo esc_attr( $settings['watermark_offset']['y'] ); ?>" />
                                <span class="wp-sir-input-suffix__unit">px</span>
                            </label>
                        </div>
                    </div>

                </div><!-- /.wp-sir-watermark-controls -->

                <!-- Preview column -->
                <div class="wp-sir-watermark-preview-wrap">
                    <span class="wp-sir-sub-field__label"><?php esc_html_e( 'Preview', 'wp-smart-image-resize' ); ?></span>
                    <div class="wp-sir-watermark-preview-container"
                         style="background-image:url(<?php echo esc_url( WP_SIR_URL . '/images/watermark-preview.jpg' ); ?>)">
                        <?php if ( ! empty( $settings['watermark_image'] ) ) :
                            $watermark_fullpath = get_attached_file( $settings['watermark_image'] );
                            if ( is_readable( $watermark_fullpath ) ) :
                                $wm_url = wp_get_attachment_image_url( $settings['watermark_image'], 'full' );
                                if ( ! empty( $wm_url ) ) : ?>
                                    <img src="<?php echo esc_url( $wm_url ); ?>"
                                         class="wp-sir-watermark-preview"
                                         alt="" />
                                <?php endif;
                            endif;
                        endif; ?>
                    </div>
                </div><!-- /.wp-sir-watermark-preview-wrap -->

            </div><!-- /.wp-sir-watermark-layout -->
        <?php
        }

        function settings_field_processable_images() {
            $settings = \wp_sir_get_settings(); ?>
            <div class="wp-sir-checkbox-group">
                <label class="wp-sir-checkbox-row" for="wp-sir-processable-images-product">
                    <input type="checkbox"
                           name="wp_sir_settings[processable_images][post_types][]"
                           <?php echo in_array( 'product', $settings['processable_images']['post_types'], true ) ? 'checked' : ''; ?>
                           id="wp-sir-processable-images-product"
                           value="product" />
                    <?php esc_html_e( 'Product images', 'wp-smart-image-resize' ); ?>
                </label>
                <label class="wp-sir-checkbox-row" for="wp-sir-processable-images-product-cat">
                    <input type="checkbox"
                           name="wp_sir_settings[processable_images][taxonomies][]"
                           <?php echo in_array( 'product_cat', $settings['processable_images']['taxonomies'], true ) ? 'checked' : ''; ?>
                           id="wp-sir-processable-images-product-cat"
                           value="product_cat" />
                    <?php esc_html_e( 'Product category images', 'wp-smart-image-resize' ); ?>
                </label>
                <label class="wp-sir-checkbox-row" for="wp-sir-processable-images-product-brand">
                    <input type="checkbox"
                           name="wp_sir_settings[processable_images][taxonomies][]"
                           <?php echo in_array( 'product_brand', $settings['processable_images']['taxonomies'], true ) ? 'checked' : ''; ?>
                           id="wp-sir-processable-images-product-brand"
                           value="product_brand" />
                    <?php esc_html_e( 'Product brand images', 'wp-smart-image-resize' ); ?>
                </label>
            </div>
        <?php
        }

        function settings_field_process_original() {
            $settings = \wp_sir_get_settings(); ?>
            <label class="wp-sir-toggle-row" for="wp-sir-pad-original">
                <input type="checkbox"
                       name="wp_sir_settings[process_original]"
                       <?php checked( $settings['process_original'], 1 ); ?>
                       id="wp-sir-pad-original"
                       class="wp-sir-as-toggle"
                       value="1" />
                <span class="wp-sir-toggle-row__desc">
                    <?php esc_html_e( 'Resize the original image so it matches the same aspect ratio as your thumbnails. The original is backed up and can be restored at any time.', 'wp-smart-image-resize' ); ?>
                </span>
            </label>
        <?php
        }

        function settings_field_jpg_convert() {            $settings = \wp_sir_get_settings(); ?>
            
            
            <div class="wp-sir-pro-feature-row">
                <label class="wp-sir-toggle-row wp-sir-toggle-row--disabled" for="wp-sir-jpg-convert">
                    <input type="checkbox"
                           id="wp-sir-jpg-convert"
                           class="wp-sir-as-toggle"
                           disabled
                           value="1" />
                    <span class="wp-sir-toggle-row__desc">
                        <?php esc_html_e( 'Convert PNG images to JPG for smaller file sizes. Reduces file size by up to 70%.', 'wp-smart-image-resize' ); ?>
                    </span>
                </label>
                <a href="https://sirplugin.com/#pricing?utm_source=wp&amp;utm_medium=plugin&amp;utm_campaign=png2jpg" target="_blank" class="wp-sir-pro-feature-link">
                    <span class="wp-sir-pro-pill"><?php esc_html_e( 'PRO', 'wp-smart-image-resize' ); ?></span>
                    <?php esc_html_e( 'Upgrade to unlock', 'wp-smart-image-resize' ); ?> →
                </a>
            </div>
            
        <?php
        }

        function settings_field_enable_nextgen_format() {
            $settings = \wp_sir_get_settings(); ?>
            
            
            <div class="wp-sir-pro-feature-row">
                <label class="wp-sir-toggle-row wp-sir-toggle-row--disabled" for="wp-sir-enable-webp">
                    <input type="checkbox"
                           id="wp-sir-enable-webp"
                           class="wp-sir-as-toggle"
                           disabled
                           value="1" />
                    <span class="wp-sir-toggle-row__desc">
                        <?php esc_html_e( 'Serve images in WebP format — up to 90% smaller than PNG. Faster page loads, better Core Web Vitals.', 'wp-smart-image-resize' ); ?>
                    </span>
                </label>
                <a href="https://sirplugin.com/?utm_source=wordpress&amp;utm_medium=plugin&amp;utm_campaign=webp" target="_blank" class="wp-sir-pro-feature-link">
                    <span class="wp-sir-pro-pill"><?php esc_html_e( 'PRO', 'wp-smart-image-resize' ); ?></span>
                    <?php esc_html_e( 'Upgrade to unlock', 'wp-smart-image-resize' ); ?> →
                </a>
            </div>
            
        <?php
        }

        function settings_field_enable_nextgen_format_avif() {
            $settings = \wp_sir_get_settings(); ?>
            <label>
                WebP <input type="checkbox" name="wp_sir_settings[enable_webp]" <?php checked($settings['enable_webp'], 1); ?> id="wp-sir-enable-webp" class="wp-sir-as-toggle"    value="1" />
                                                                                                                                                                                        </label>
&nbsp;                                                                                                                                                                                        AVIF  <input type="checkbox" name="wp_sir_settings[enable_avif]" <?php checked($settings['enable_avif'], 1); ?> id="wp-sir-enable-avif" class="wp-sir-as-toggle"    value="1" />
                                                                                                                                                                                        </plabel>
                
               

            </label>
            <p class="description">
            AVIF: Maximum Optimization – Up to 50% Smaller than WebP.
<br>
WebP: Up to 90% Smaller than PNG.
<br>
We automatically serve the best format to ensure optimal performance.

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
                       <?php echo $settings['crop_mode'] === 'pad' ? 'checked' : ''; ?> 
                       name="wp_sir_settings[crop_mode]">
                Scale and Add whitespace
                <span class="wp-sir-help-tip" title="Scales the image to fit the dimensions while preserving all content. If needed, adds white space to fill the remaining area. Best for when you want to keep the entire image visible."></span>
            </label>
            &nbsp;&nbsp;
            <label for="wp-sir-crop-mode-crop">
                <input type="radio"
                       id="wp-sir-crop-mode-crop" 
                       value="fill"
                       <?php echo $settings['crop_mode'] === 'fill' ? 'checked' : ''; ?> 
                       name="wp_sir_settings[crop_mode]">
                Fill and Crop
                <span class="wp-sir-help-tip" title="Fills the entire dimensions by trimming edges of the image as needed. Some parts of the image will be cut off to ensure the image fits perfectly without any whitespace."></span>
            </label>
        </div>
        </div>
        <?php
        }
        function settings_field_sizes() {
            $settings               = \wp_sir_get_settings( 'view' );
            $additional_sizes       = wp_sir_get_additional_sizes( 'view' );
            $enable_fit_mode_option = ! empty( _wp_sir_get_excluded_sizes( false ) );
            $default_sizes          = _wp_sir_get_default_sizes();
            $selected_count         = count( $settings['sizes'] );
            $total_count            = count( $additional_sizes );
        ?>
            <p class="wp-sir-field-hint">
                <?php esc_html_e( 'Choose which image sizes WordPress generates when uploading product images. The defaults cover most stores.', 'wp-smart-image-resize' ); ?>
            </p>
            <div class="wp-sir-sizes-wrapper">
                <div class="wp-sir-sizes-header">
                    <div class="wp-sir-sizes-header-left">
                        <button type="button" class="button wp-sir-toggle-sizes" aria-expanded="false">
                            <span class="wp-sir-toggle-text"><?php esc_html_e( 'Customize image sizes', 'wp-smart-image-resize' ); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                        </button>
                    </div>
                    <div class="wp-sir-sizes-info">
                        <div class="wp-sir-sizes-summary">
                            <?php echo esc_html( sprintf(
                                _n( '%d of %d size selected', '%d of %d sizes selected', $total_count, 'wp-smart-image-resize' ),
                                $selected_count,
                                $total_count
                            ) ); ?>
                        </div>
                        <button id="wpsirResetDefaultSizes" type="button"
                                class="wp-sir-reset-link <?php echo $selected_count === count( $default_sizes ) ? 'hidden' : ''; ?>">
                            <span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
                            <?php esc_html_e( 'Reset to defaults', 'wp-smart-image-resize' ); ?>
                        </button>
                    </div>
                </div>

                <div id="wp-sir-sizes-options" class="wp-sir-sizes-table-wrapper" style="display:none">
                    <table id="wp-sir-sizes-selector" data-defaults="<?php echo esc_attr( implode( ',', $default_sizes ) ); ?>">
                        <thead>
                            <tr>
                                <th class="wp-sir-sizes-th wp-sir-sizes-th--name">
                                    <input type="checkbox" id="wp-sir-toggle-all-sizes"
                                           <?php echo count( $additional_sizes ) === $selected_count ? 'checked' : ''; ?> />
                                    <?php esc_html_e( 'Select all', 'wp-smart-image-resize' ); ?>
                                </th>
                                <?php if ( $enable_fit_mode_option ) : ?>
                                <th class="wp-sir-sizes-th wp-sir-sizes-th--fit">
                                    <?php esc_html_e( 'Use WordPress Cropping', 'wp-smart-image-resize' ); ?>
                                    <?php
                                    $tooltip = esc_attr__( 'Check this to apply the thumbnail cropping setting under Settings → Media', 'wp-smart-image-resize' );
                                    if ( wp_sir_is_woocommerce_activated() ) {
                                        $tooltip .= esc_attr__( ' and Appearance → Customize → WooCommerce → Product Images.', 'wp-smart-image-resize' );
                                    } else {
                                        $tooltip .= '.';
                                    }
                                    ?>
                                    <span class="wp-sir-help-tip" title="<?php echo $tooltip; ?>"></span>
                                </th>
                                <?php endif; ?>
                                <?php if ( wp_sir_is_woocommerce_activated() ) : ?>
                                <th class="wp-sir-sizes-th wp-sir-sizes-th--dim"><?php esc_html_e( 'Width (px)', 'wp-smart-image-resize' ); ?></th>
                                <th class="wp-sir-sizes-th wp-sir-sizes-th--dim"><?php esc_html_e( 'Height (px)', 'wp-smart-image-resize' ); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $additional_sizes as $size_name => $size_data ) :
                            if ( ! empty( $settings['size_options'][ $size_name ]['width'] ) ) {
                                $size_data['width'] = $settings['size_options'][ $size_name ]['width'];
                            }
                            if ( ! empty( $settings['size_options'][ $size_name ]['height'] ) ) {
                                $size_data['height'] = $settings['size_options'][ $size_name ]['height'];
                            }
                            $size_tips = [
                                'woocommerce_thumbnail'         => esc_attr__( 'Used in product grids such as the shop page.', 'wp-smart-image-resize' ),
                                'woocommerce_single'            => esc_attr__( 'Used on single product pages.', 'wp-smart-image-resize' ),
                                'woocommerce_gallery_thumbnail' => esc_attr__( 'Used below the main image on the single product page to switch the gallery.', 'wp-smart-image-resize' ),
                            ];
                            if ( wp_sir_is_woocommerce_activated() ) {
                                $size_tips['thumbnail'] = esc_attr__( 'Used to preview images in the WordPress media library.', 'wp-smart-image-resize' );
                            }
                        ?>
                            <tr>
                                <td class="wp-sir-sizes-td wp-sir-sizes-td--name <?php echo wp_sir_is_woocommerce_activated() ? 'wp-sir-sizes-td--wide' : ''; ?>">
                                    <label class="wp-sir-sizes-label">
                                        <input type="checkbox"
                                               class="wpSirSelectSize"
                                               value="<?php echo esc_attr( $size_name ); ?>"
                                               <?php echo in_array( $size_name, $settings['sizes'] ) ? 'checked' : ''; ?>
                                               name="wp_sir_settings[sizes][]" />
                                        <span>
                                            <?php echo esc_html( str_replace( '_', ' ', ucfirst( $size_name ) ) ); ?>
                                            <span class="wp-sir-sizes-dims">(<?php echo esc_html( $size_data['width'] . '×' . $size_data['height'] ); ?>)</span>
                                        </span>
                                        <?php if ( isset( $size_tips[ $size_name ] ) ) : ?>
                                            <span class="wp-sir-help-tip" title="<?php echo $size_tips[ $size_name ]; ?>"></span>
                                        <?php endif; ?>
                                    </label>
                                </td>
                                <?php if ( $enable_fit_mode_option ) : ?>
                                <td class="wp-sir-sizes-td wp-sir-sizes-td--fit">
                                    <input type="hidden"
                                           name="wp_sir_settings[size_options][<?php echo esc_attr( $size_name ); ?>][fit_mode]"
                                           value="contain" />
                                    <input type="checkbox"
                                           class="wp-sir-fit-mode"
                                           name="wp_sir_settings[size_options][<?php echo esc_attr( $size_name ); ?>][fit_mode]"
                                           value="none"
                                           <?php echo _wp_sir_exclude_size( $size_name, $settings['size_options'] ) ? 'checked' : ''; ?> />
                                </td>
                                <?php endif; ?>
                                <?php if ( is_woocommerce_size( $size_name ) ) : ?>
                                <td class="wp-sir-sizes-td wp-sir-custom-dimensions wp-sir-sizes-td--dim">
                                    <input type="number"
                                           class="small-text"
                                           value="<?php echo esc_attr( $size_data['width'] ); ?>"
                                           name="wp_sir_settings[size_options][<?php echo esc_attr( $size_name ); ?>][width]" />
                                </td>
                                <td class="wp-sir-sizes-td wp-sir-custom-dimensions wp-sir-sizes-td--dim">
                                    <input type="number"
                                           class="small-text"
                                           value="<?php echo esc_attr( $size_data['height'] ); ?>"
                                           name="wp_sir_settings[size_options][<?php echo esc_attr( $size_name ); ?>][height]" />
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php
        }



        public function settings_field_bg_color( $args ) {
            $settings = \wp_sir_get_settings(); ?>
            <div class="wp-sir-color-field">
                <input name="wp_sir_settings[bg_color]"
                       value="<?php echo esc_attr( $settings['bg_color'] ); ?>"
                       type="text"
                       id="wpSirColorPicker" />
                <button type="button" class="button button-secondary button-small wp-sir-clear-color" id="wp-sir-clear-bg-color">
                    <?php esc_html_e( 'Clear', 'wp-smart-image-resize' ); ?>
                </button>
            </div>
            <p class="wp-sir-field-hint">
                <?php esc_html_e( 'Fills empty space when padding an image. Leave empty to preserve transparency.', 'wp-smart-image-resize' ); ?>
            </p>
        <?php
        }

        public function settings_field_enable($args) {
            $settings = \wp_sir_get_settings(); ?>
            <label for="wp-sir-enable">
                <input type="checkbox" class="wp-sir-as-toggle wp-sir-as-toggle--large" name="wp_sir_settings[enable]" id="wp-sir-enable" value="1" <?php checked($settings['enable'], 1); ?> />
            </label>
            <?php
            
            echo Process_Tracker::render_status();
            
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
                'title'   => esc_html__('Overview', 'wp-smart-image-resize'),
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
                    esc_html__('For more information:', 'wp-smart-image-resize') .
                    '</strong></p>' . $help_sidebar
            );
        }

        function settings_field_disable_upscale() {
            $settings = wp_sir_get_settings(); ?>
            <label class="wp-sir-toggle-row" for="wp-sir-disable-upscale">
                <input type="checkbox"
                       name="wp_sir_settings[disable_upscale]"
                       <?php checked( $settings['disable_upscale'], 1 ); ?>
                       id="wp-sir-disable-upscale"
                       class="wp-sir-as-toggle"
                       value="1" />
                <span class="wp-sir-toggle-row__desc">
                    <?php esc_html_e( 'Keep small images at their original size instead of stretching them. Empty space is added around the image to fill the target dimensions.', 'wp-smart-image-resize' ); ?>
                </span>
            </label>
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
            // Only allow administrators to generate system reports
            if (!current_user_can('manage_options')) {
                return [];
            }

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
            
            // Sanitize sensitive settings before including
            $settings = get_option('wp_sir_settings');
            if (is_array($settings)) {
                // Remove potentially sensitive data
                unset($settings['watermark_image']);
                $report['Plugin Settings'] = $settings;
            }
            
            return $report;
        }

        public function render_help_tab() {
            $image_processors = $this->get_image_processor_info();
            $current_processor = get_option('wp_sir_image_processor', '');
            ?>
            <div class="wp-sir-help-page">
                <div class="wp-sir-help-section">
                    <h2><?php esc_html_e('Quick Links', 'wp-smart-image-resize'); ?></h2>
                    <ul class="wp-sir-help-links">
                        <li>
                            <span class="dashicons dashicons-book"></span>
                            <a href="https://sirplugin.com/docs" target="_blank">
                                <?php esc_html_e('Documentation', 'wp-smart-image-resize'); ?>
                            </a>
                        </li>
                        <li>
                            <span class="dashicons dashicons-sos"></span>
                            <a href="https://sirplugin.com/support" target="_blank">
                                <?php esc_html_e('Contact Support', 'wp-smart-image-resize'); ?>
                            </a>
                        </li>
                        <li>
                            <span class="dashicons dashicons-warning"></span>
                            <a href="https://sirplugin.com/troubleshooting" target="_blank">
                                <?php esc_html_e('Troubleshooting Guide', 'wp-smart-image-resize'); ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="wp-sir-help-section">
                    <h2><?php esc_html_e('System Information', 'wp-smart-image-resize'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('Download this information when contacting support to help us assist you better.', 'wp-smart-image-resize'); ?>
                    </p>
                    <p>
                        <button type="button" id="wp-sir-download-report" class="button button-secondary">
                            <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                            <?php esc_html_e('Download System Report', 'wp-smart-image-resize'); ?>
                        </button>
                    </p>
                </div>

                <div class="wp-sir-help-section" id="wp-sir-reset-section">
                    <h2><?php esc_html_e( 'Restore Original Images', 'wp-smart-image-resize' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Restore all images to their original state before the plugin processed them. Use this if you plan to stop using the plugin or want to start fresh. Original files will be recovered from backup (if available) and thumbnails regenerated using WordPress defaults.', 'wp-smart-image-resize' ); ?>
                    </p>

                    <div id="wp-sir-reset-state-idle">
                        <button type="button" class="button button-secondary" id="wp-sir-reset-start" disabled>
                            <?php esc_html_e( 'Restore All Images', 'wp-smart-image-resize' ); ?>
                        </button>
                        <span id="wp-sir-reset-count" style="margin-left:8px;color:#8c8f94;font-size:12px">
                            <?php esc_html_e( 'Checking…', 'wp-smart-image-resize' ); ?>
                        </span>
                    </div>

                    <div id="wp-sir-reset-state-running" style="display:none;margin-top:14px;max-width:480px">
                        <div class="wp-sir-bulk-progress-header">
                            <span style="font-size:13px"><?php esc_html_e( 'Restoring…', 'wp-smart-image-resize' ); ?></span>
                            <span class="wp-sir-bulk-counts">
                                <span id="wp-sir-reset-done">0</span>
                                <span class="wp-sir-bulk-counts__sep">/</span>
                                <span id="wp-sir-reset-total">0</span>
                                <?php esc_html_e( 'images', 'wp-smart-image-resize' ); ?>
                            </span>
                        </div>
                        <div class="wp-sir-bulk-progress-bar-wrap" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="wp-sir-reset-progress-wrap">
                            <div class="wp-sir-bulk-progress-bar" id="wp-sir-reset-progress-bar"></div>
                        </div>
                        <p class="wp-sir-bulk-percent" id="wp-sir-reset-percent">0%</p>
                        <button type="button" class="button button-link-delete" id="wp-sir-reset-stop" style="margin-top:8px">
                            <?php esc_html_e( 'Stop', 'wp-smart-image-resize' ); ?>
                        </button>
                    </div>

                    <div id="wp-sir-reset-state-done" style="display:none;margin-top:10px">
                        <span style="color:green">
                            <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;font-size:16px"></span>
                            <span id="wp-sir-reset-summary"></span>
                        </span>
                    </div>

                    <div id="wp-sir-reset-errors" style="display:none;margin-top:10px">
                        <strong style="color:#b32d2e;font-size:13px"><?php esc_html_e( 'Some images could not be restored:', 'wp-smart-image-resize' ); ?></strong>
                        <ul id="wp-sir-reset-error-list" style="margin-top:4px;color:#b32d2e;font-size:12px"></ul>
                    </div>
                </div>

                <div class="wp-sir-help-section">
                    <h2><?php esc_html_e('Image Processing', 'wp-smart-image-resize'); ?></h2>                    <?php if ($image_processors['gd']['available'] || $image_processors['imagick']['available']) : ?>
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
                                <?php esc_html_e('Select which image processing library to use. Change this only if you experience issues with image processing.', 'wp-smart-image-resize'); ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-error">
                            <p><?php esc_html_e('No image processing library available. Please contact your hosting provider.', 'wp-smart-image-resize'); ?></p>
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

        /**
         * Handle settings form submission and redirect if needed
         */
        public function handle_settings_form_submission() {
            // Check if we're saving our plugin's settings
            $option_page = isset($_POST['option_page']) ? sanitize_text_field($_POST['option_page']) : '';
            if ($option_page !== WP_SIR_NAME) {
                return;
            }

            // WordPress settings API handles nonce verification via check_admin_referer()
            // which is called before this hook in options.php

            // Check if bulk resize button was clicked
            if (!isset($_POST['submit_and_bulk_resize'])) {
                return;
            }

            // Add a flag to redirect after settings are saved
            add_filter('wp_redirect', function($location) {
                return admin_url('admin.php?page=wp-smart-image-resize&tab=bulk-regenerate');
            });
        }

    }
endif;

