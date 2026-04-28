<?php
/**
 * Plugin Name: VN Map Partner
 * Description: Hiển thị bản đồ đối tác Việt Nam theo tỉnh thành
 * Version:     1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Quanlv
 * Text Domain: vn-map-partner
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// Hằng số plugin
define( 'VNM_VERSION',   '1.0.0' );
define( 'VNM_PATH',      plugin_dir_path( __FILE__ ) );
define( 'VNM_URL',       plugin_dir_url( __FILE__ ) );
define( 'VNM_CACHE_KEY', 'vn_map_partners_data' );
define( 'VNM_CACHE_TTL', 3600 ); // 1 giờ

// Nạp các class
foreach ( [ 'class-cpt', 'class-rest-api', 'class-shortcode', 'class-admin', 'class-settings' ] as $file ) {
    require_once VNM_PATH . "includes/{$file}.php";
}

// Đăng ký hooks
add_action( 'init',            [ 'VNM_CPT',       'register'         ] );
add_action( 'rest_api_init',   [ 'VNM_REST_API',  'register_routes'  ] );
add_action( 'init',            [ 'VNM_Shortcode', 'register'         ] );
add_action( 'add_meta_boxes',  [ 'VNM_Admin',     'add_meta_boxes'   ] );
add_action( 'save_post_province_partner', [ 'VNM_Admin', 'save_meta' ] );
add_action( 'wp_enqueue_scripts',    [ 'VNM_Shortcode', 'enqueue_scripts' ] );
add_action( 'admin_enqueue_scripts', [ 'VNM_Admin',     'enqueue_scripts' ] );
add_action( 'admin_notices',         [ 'VNM_Admin',     'outdated_code_notice' ] );
add_action( 'admin_menu',            [ 'VNM_Settings',  'register'             ] );
add_action( 'admin_init',            [ 'VNM_Settings',  'register_settings'    ] );
add_filter( 'vnm_register_url',      [ 'VNM_Settings',  'get_register_url'     ] );

// Xóa cache khi có thay đổi đối tác
add_action( 'save_post_province_partner', 'vnm_clear_cache' );
add_action( 'before_delete_post',         'vnm_clear_cache' );
add_action( 'trashed_post',               'vnm_clear_cache' );
add_action( 'untrashed_post',             'vnm_clear_cache' );

/**
 * Xóa transient cache dữ liệu đối tác.
 */
function vnm_clear_cache(): void {
    delete_transient( VNM_CACHE_KEY );
}

// Kích hoạt / Hủy kích hoạt
register_activation_hook( __FILE__, 'vnm_activate' );
function vnm_activate(): void {
    VNM_CPT::register();
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'vnm_deactivate' );
function vnm_deactivate(): void {
    flush_rewrite_rules();
}
