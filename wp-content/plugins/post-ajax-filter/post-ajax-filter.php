<?php
/**
 * Plugin Name:       Post AJAX Filter
 * Description:       Lọc bài viết WordPress bằng AJAX, sử dụng shortcode với giao diện bộ lọc linh hoạt. Hỗ trợ danh mục, thẻ, tìm kiếm, nhiều kiểu giao diện, phân trang và chia sẻ URL trạng thái bộ lọc.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Quanlv
 * License:           GPL-2.0+
 * Text Domain:       post-ajax-filter
 * Domain Path:       /languages
 *
 * @package Post_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

// Hằng số plugin.
define( 'PAF_VERSION',     '1.0.0' );
define( 'PAF_PLUGIN_FILE', __FILE__ );
define( 'PAF_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'PAF_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

/**
 * Khởi tạo plugin sau khi tất cả plugin đã được tải.
 */
function paf_init(): void {
	require_once PAF_PLUGIN_DIR . 'includes/class-main.php';
	PAF_Main::get_instance();
}
add_action( 'plugins_loaded', 'paf_init' );
