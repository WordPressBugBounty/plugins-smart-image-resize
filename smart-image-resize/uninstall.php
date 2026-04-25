<?php
/**
 * Fired when the plugin is uninstalled (deleted via WP admin).
 *
 * @package WP_Smart_Image_Resize
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'src/Singleton_Trait.php';
require_once plugin_dir_path( __FILE__ ) . 'src/Bulk_Processor.php';

WP_Smart_Image_Resize\Bulk_Processor::uninstall();
