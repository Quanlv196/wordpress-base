<?php
/**
 * Plugin Name:       Custom Order API
 * Description:       Replaces WooCommerce default order flow with a custom order system powered by a configurable JSON Mock API. Provides shortcodes for product listing, product detail, and checkout.
 * Version:           1.0.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Quanlv
 * Author URI:        https://ntqsolution.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ntq-custom-order
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
define( 'NCO_VERSION',     '1.0.3' );
define( 'NCO_PLUGIN_FILE', __FILE__ );
define( 'NCO_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'NCO_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------------
foreach ( [ 'class-api', 'class-admin', 'class-shortcode', 'class-ajax' ] as $file ) {
    require_once NCO_PLUGIN_DIR . 'includes/' . $file . '.php';
}

// ---------------------------------------------------------------------------
// Main plugin class (singleton)
// ---------------------------------------------------------------------------
final class NTQ_Custom_Order {

    /** @var NTQ_Custom_Order|null */
    private static $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_filter( 'query_vars',         [ $this, 'register_query_vars' ] );
        add_action( 'init',               [ $this, 'register_rewrite_rules' ] );
        $this->bypass_woocommerce();
        new NCO_Admin();
        new NCO_Shortcode();
        new NCO_Ajax();
    }

    // -----------------------------------------------------------------------
    // Clean URL support: /products/15
    // -----------------------------------------------------------------------

    public function register_query_vars( array $vars ): array {
        $vars[] = 'nco_product_id';
        return $vars;
    }

    public function register_rewrite_rules(): void {
        $detail_url = (string) get_option( 'nco_detail_page_url', '' );
        if ( empty( $detail_url ) ) {
            return;
        }
        $slug = trim( (string) wp_parse_url( $detail_url, PHP_URL_PATH ), '/' );
        if ( empty( $slug ) ) {
            return;
        }
        add_rewrite_rule(
            '^' . preg_quote( $slug, '#' ) . '/([0-9]+)/?$',
            'index.php?pagename=' . $slug . '&nco_product_id=$matches[1]',
            'top'
        );
    }

    // -----------------------------------------------------------------------
    // Front-end assets
    // -----------------------------------------------------------------------
    public function enqueue_assets(): void {
        wp_enqueue_style(
            'ntq-custom-order',
            NCO_PLUGIN_URL . 'assets/css/style.css',
            [],
            NCO_VERSION
        );

        wp_enqueue_script(
            'ntq-custom-order',
            NCO_PLUGIN_URL . 'assets/js/main.js',
            [ 'jquery' ],
            NCO_VERSION,
            true
        );

        $currency = function_exists( 'get_woocommerce_currency_symbol' )
            ? get_woocommerce_currency_symbol()
            : '$';

        wp_localize_script( 'ntq-custom-order', 'nco_params', [
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'nco_ajax_nonce' ),
            'currency'     => $currency,
            'checkout_url' => (string) get_option( 'nco_checkout_url', '' ),
            'detail_url'   => (string) get_option( 'nco_detail_page_url', '' ),
            'i18n'         => [
                'loading'       => __( 'Loading\u2026', 'ntq-custom-order' ),
                'add_cart_ok'   => __( 'Product added to cart!', 'ntq-custom-order' ),
                'order_success' => __( 'Order placed successfully!', 'ntq-custom-order' ),
                'empty_cart'    => __( 'Your cart is empty.', 'ntq-custom-order' ),
                'api_error'     => __( 'API error. Please try again.', 'ntq-custom-order' ),
                'fill_form'     => __( 'Please fill in all required fields.', 'ntq-custom-order' ),
                'view_detail'   => __( 'View Detail', 'ntq-custom-order' ),
                'add_to_cart'   => __( 'Add to Cart', 'ntq-custom-order' ),
                'go_checkout'   => __( 'Go to Checkout', 'ntq-custom-order' ),
                'quantity'      => __( 'Quantity', 'ntq-custom-order' ),
                'no_products'   => __( 'No products found.', 'ntq-custom-order' ),
                'no_product_id' => __( 'No product ID specified.', 'ntq-custom-order' ),
            ],
        ] );
    }

    // -----------------------------------------------------------------------
    // WooCommerce bypass
    // -----------------------------------------------------------------------
    private function bypass_woocommerce(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        // Prevent WooCommerce from redirecting away from a page that has
        // our custom checkout shortcode.
        add_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false' );
    }

    // -----------------------------------------------------------------------
    // Activation / Deactivation
    // -----------------------------------------------------------------------
    public static function activate(): void {
        if ( ! get_option( 'nco_api_endpoint' ) ) {
            update_option( 'nco_api_endpoint', 'https://fakestoreapi.com' );
        }
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}

register_activation_hook(   NCO_PLUGIN_FILE, [ 'NTQ_Custom_Order', 'activate' ] );
register_deactivation_hook( NCO_PLUGIN_FILE, [ 'NTQ_Custom_Order', 'deactivate' ] );

add_action( 'plugins_loaded', static function () {
    NTQ_Custom_Order::get_instance();
} );
