<?php
/**
 * Uninstall hook – runs when the plugin is deleted from the WordPress admin.
 *
 * Removes:
 *  - The plugin option from wp_options.
 *  - The custom log directory (if it was created).
 *
 * Does NOT remove Everest Forms data or any other plugin data.
 *
 * @package EVF_API_Connector
 */

// Only execute when WordPress calls this file during uninstall.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove plugin settings.
delete_option( 'evf_api_connector_settings' );

// Remove custom log directory.
$log_dir = WP_CONTENT_DIR . '/evf-api-connector-logs';
if ( is_dir( $log_dir ) ) {
	evf_api_connector_rrmdir( $log_dir );
}

/**
 * Recursively delete a directory and its contents.
 *
 * @param string $dir Absolute path to the directory.
 */
function evf_api_connector_rrmdir( string $dir ): void {
	$entries = glob( $dir . '/*' );
	if ( is_array( $entries ) ) {
		foreach ( $entries as $entry ) {
			is_dir( $entry ) ? evf_api_connector_rrmdir( $entry ) : wp_delete_file( $entry );
		}
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}
