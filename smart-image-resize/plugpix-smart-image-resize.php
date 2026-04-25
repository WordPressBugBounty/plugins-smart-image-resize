<?php


/**
 *
 * @link              https://sirplugin.com
 * @since             1.0.0
 * @package           WP_Smart_Image_Resize
 *
 * @wordpress-plugin
 * Plugin Name: Smart Image Resize for WooCommerce
 * Plugin URI: http://wordpress.org/plugins/smart-image-resize
 * Description: Make WooCommerce products images the same size and uniform without cropping.
 * Version: 1.16.0
 * Author: Nabil Lemsieh
 * Author URI: https://sirplugin.com
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl.html
 * Text Domain: wp-smart-image-resize
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 10.7
 */



// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Require PHP 7.0+. Bail early to avoid fatal errors on older versions.
if ( version_compare( PHP_VERSION, '7.0.0', '<' ) ) {
    add_action( 'admin_notices', function () {
        $message = sprintf(
            /* translators: 1: plugin name, 2: required PHP version, 3: current PHP version */
            __( '<strong>%1$s</strong> requires PHP %2$s or higher. You are running PHP %3$s. Please upgrade PHP to use this plugin.', 'wp-smart-image-resize' ),
            'Smart Image Resize',
            '7.0',
            PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>' . wp_kses( $message, [ 'strong' => [] ] ) . '</p></div>';
    } );
    return;
}

if (!(defined('WP_CLI') && WP_CLI) && function_exists('\is_plugin_active') && function_exists('\deactivate_plugins')):

    
    if ( defined( 'WP_SIR_IS_PRO' ) ) {
        if (is_plugin_active('wp-smart-image-resize-pro/wp-smart-image-resize-pro.php')) {
            deactivate_plugins('wp-smart-image-resize-pro/wp-smart-image-resize-pro.php');
        }

        wp_redirect($_SERVER['REQUEST_URI']);
        exit;
    }
    

    
endif;


define( 'WP_SIR_VERSION', '1.16.0' );
define( 'WP_SIR_NAME', 'wp-smart-image-resize' );
define( 'WP_SIR_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_SIR_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_SIR_BASENAME', plugin_basename( __FILE__ ) );

// Declare compatibility with custom order tables for WooCommerce.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);



// Activate
if( ! function_exists('\wp_sir_activate') ){

    function wp_sir_activate()
    {
        add_option( 'wp_sir_plugin_version', WP_SIR_VERSION );

        // Create bulk processor DB tables.
        require_once plugin_dir_path( __FILE__ ) . 'src/Singleton_Trait.php';
        require_once plugin_dir_path( __FILE__ ) . 'src/Bulk_Processor.php';
        WP_Smart_Image_Resize\Bulk_Processor::install();
    }
    
}

// Load
register_activation_hook( __FILE__, 'wp_sir_activate' );

include_once WP_SIR_DIR . 'src/Plugin.php';

// Run the plugin.
add_action( 'plugins_loaded', [\WP_Smart_Image_Resize\Plugin::get_instance(), 'run']);

if(apply_filters('wp_sir_allow_background_processing', true)){
    include_once(WP_SIR_DIR . '/libraries/action-scheduler/action-scheduler.php');
}
