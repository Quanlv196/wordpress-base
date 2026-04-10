<?php
/**
 * Uninstall Gemini Auto Blogger.
 *
 * This file runs automatically when the plugin is deleted from the WordPress
 * Plugins screen. It removes all plugin data: options and the log table.
 *
 * @package GeminiAutoBlogger
 */

// Exit if WordPress uninstall process did not call this file.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'gab_settings' );
delete_option( 'gab_topic_index' );
delete_option( 'gab_last_run' );
delete_option( 'gab_db_version' );

// Remove the log table.
global $wpdb;
$table_name = $wpdb->prefix . 'gab_logs';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
