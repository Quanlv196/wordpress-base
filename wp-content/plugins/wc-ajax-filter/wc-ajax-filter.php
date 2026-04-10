<?php
/**
 * Plugin Name:       WC AJAX Filter
 * Description:       Filter WooCommerce products using AJAX, shortcodes, and a flexible filter UI. Supports categories, brands, price range, multiple UI types, pagination, and shareable URL state.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Quanlv
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-ajax-filter
 * Domain Path:       /languages
 * WC requires at least: 5.0
 * WC tested up to:   9.0
 *
 * @package WC_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declare compatibility with WooCommerce features to suppress admin warnings.
 * Must be called on the `before_woocommerce_init` hook.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			// High-Performance Order Storage (HPOS).
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
			// React-based Cart & Checkout blocks.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);
		}
	}
);

// Plugin constants.
define( 'WCAF_VERSION',    '1.0.0' );
define( 'WCAF_PLUGIN_FILE', __FILE__ );
define( 'WCAF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCAF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Bootstrap the plugin after all plugins are loaded.
 * Checks for WooCommerce before initialising.
 */
function wcaf_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function () {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html__( 'WC AJAX Filter requires WooCommerce to be installed and active.', 'wc-ajax-filter' )
				);
			}
		);
		return;
	}

	require_once WCAF_PLUGIN_DIR . 'includes/class-main.php';
	WCAF_Main::get_instance();
}
add_action( 'plugins_loaded', 'wcaf_init' );
