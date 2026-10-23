<?php
/**
 * Fired when the plugin is uninstalled (deleted via WP admin).
 *
 * Self-contained on purpose: Free and Pro ship the same classes, so loading
 * them here would be a fatal redeclare when the other build is active.
 *
 * @package WP_Smart_Image_Resize
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// The other build (Free/Pro) is active and still uses the shared bulk
// tables and options — leave them alone.
if ( defined( 'WP_SIR_VERSION' ) ) {
    return;
}

global $wpdb;

// Bulk processor tables and state.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sir_bulk_queue" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sir_bulk_errors" );
// phpcs:enable
delete_option( 'wp_sir_bulk_state' );
delete_option( 'wp_sir_bulk_db_version' );
