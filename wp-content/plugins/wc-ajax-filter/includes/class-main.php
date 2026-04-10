<?php
/**
 * Main plugin orchestrator.
 *
 * Loads all sub-components in the correct order and provides a single
 * access point to the plugin instance.
 *
 * @package WC_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WCAF_Main
 */
class WCAF_Main {

	/**
	 * Singleton instance.
	 *
	 * @var WCAF_Main|null
	 */
	private static $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return WCAF_Main
	 */
	public static function get_instance(): WCAF_Main {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_components();
	}

	/**
	 * Require all class files.
	 */
	private function load_dependencies(): void {
		require_once WCAF_PLUGIN_DIR . 'includes/class-assets.php';
		require_once WCAF_PLUGIN_DIR . 'includes/class-query-builder.php';
		require_once WCAF_PLUGIN_DIR . 'includes/class-filter-shortcode.php';
		require_once WCAF_PLUGIN_DIR . 'includes/class-product-list-shortcode.php';
		require_once WCAF_PLUGIN_DIR . 'includes/class-filter-status-shortcodes.php';
		require_once WCAF_PLUGIN_DIR . 'includes/class-ajax.php';
	}

	/**
	 * Instantiate and initialise each component.
	 */
	private function init_components(): void {
		WCAF_Assets::get_instance();
		WCAF_Filter_Shortcode::get_instance();
		WCAF_Product_List_Shortcode::get_instance();
		WCAF_Filter_Status_Shortcodes::get_instance();
		WCAF_Ajax::get_instance();
	}
}
