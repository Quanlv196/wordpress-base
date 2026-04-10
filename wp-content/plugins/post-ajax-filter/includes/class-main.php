<?php
/**
 * Điều phối chính của plugin.
 *
 * Tải tất cả các thành phần theo đúng thứ tự và cung cấp
 * một điểm truy cập duy nhất vào instance plugin.
 *
 * @package Post_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PAF_Main
 */
class PAF_Main {

	/**
	 * Instance singleton.
	 *
	 * @var PAF_Main|null
	 */
	private static $instance = null;

	/**
	 * Lấy hoặc tạo instance singleton.
	 *
	 * @return PAF_Main
	 */
	public static function get_instance(): PAF_Main {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor riêng tư — dùng get_instance().
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_components();
	}

	/**
	 * Nạp tất cả file class.
	 */
	private function load_dependencies(): void {
		require_once PAF_PLUGIN_DIR . 'includes/class-assets.php';
		require_once PAF_PLUGIN_DIR . 'includes/class-query-builder.php';
		require_once PAF_PLUGIN_DIR . 'includes/class-filter-shortcode.php';
		require_once PAF_PLUGIN_DIR . 'includes/class-post-list-shortcode.php';
		require_once PAF_PLUGIN_DIR . 'includes/class-ajax.php';
	}

	/**
	 * Khởi tạo từng thành phần.
	 */
	private function init_components(): void {
		PAF_Assets::get_instance();
		PAF_Filter_Shortcode::get_instance();
		PAF_Post_List_Shortcode::get_instance();
		PAF_Ajax::get_instance();
	}
}
