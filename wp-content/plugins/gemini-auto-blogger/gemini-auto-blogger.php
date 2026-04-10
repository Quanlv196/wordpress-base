<?php
/**
 * Plugin Name:       AI Auto Blogger
 * Description:       Automatically generate and publish SEO-friendly blog posts (text + images) using Google Gemini AI on a configurable schedule.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Quanlv
 * License:           GPL v2 or later
 * Text Domain:       ai-auto-blogger
 * Domain Path:       /languages
 *
 * @package AIAutoBlogger
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Plugin constants ────────────────────────────────────────────────────────
define( 'GAB_VERSION',         '1.0.0' );
define( 'GAB_PLUGIN_FILE',     __FILE__ );
define( 'GAB_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'GAB_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'GAB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ─── Load required class files ───────────────────────────────────────────────
/**
 * Require all plugin class files in dependency order.
 */
function gab_load_files() {
	require_once GAB_PLUGIN_DIR . 'includes/class-logger.php';
	require_once GAB_PLUGIN_DIR . 'includes/class-gemini-api.php';
	require_once GAB_PLUGIN_DIR . 'includes/class-post-generator.php';
	require_once GAB_PLUGIN_DIR . 'includes/class-scheduler.php';
	require_once GAB_PLUGIN_DIR . 'includes/class-admin.php';
	require_once GAB_PLUGIN_DIR . 'includes/class-plugin.php';
}

// ─── Activation / Deactivation hooks ────────────────────────────────────────
register_activation_hook( __FILE__, 'gab_activate' );
register_deactivation_hook( __FILE__, 'gab_deactivate' );

/**
 * Runs when the plugin is activated.
 * Creates the DB log table, sets default options, and schedules the cron job.
 */
function gab_activate() {
	gab_load_files();
	GAB_Plugin::activate();
}

/**
 * Runs when the plugin is deactivated.
 * Clears the scheduled cron event.
 */
function gab_deactivate() {
	gab_load_files();
	GAB_Plugin::deactivate();
}

// ─── Boot on plugins_loaded ──────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
	gab_load_files();
	GAB_Plugin::get_instance();
} );
