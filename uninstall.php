<?php
/**
 * Uninstall Thanks Mail for Stripe
 *
 * Removes all plugin data when uninstalled via WordPress admin.
 *
 * @package Thanks_Mail_For_Stripe
 * @since   1.0.0
 */

// Exit if accessed directly or not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean up plugin data on uninstall.
 *
 * Removes:
 * - Plugin options (settings and schema version) from wp_options table
 * - Custom database table for sent emails log
 *
 * @since 1.0.0
 */
function tmfs_uninstall_cleanup() {
    global $wpdb;

    // Delete plugin options.
    delete_option( 'tmfs_settings' );
    delete_option( 'tmfs_db_version' );

    // Drop custom table.
    $table_name = esc_sql( $wpdb->prefix . 'tmfs_sent_emails' );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );

    // The tmfs_rate_* transients expire on their own within a minute.
}

tmfs_uninstall_cleanup();
