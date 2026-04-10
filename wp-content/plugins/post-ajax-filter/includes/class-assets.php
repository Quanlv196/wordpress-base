<?php
/**
 * Xử lý đăng ký script và style của plugin.
 *
 * @package Post_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PAF_Assets
 */
class PAF_Assets {

	/**
	 * Instance singleton.
	 *
	 * @var PAF_Assets|null
	 */
	private static $instance = null;

	/**
	 * Lấy hoặc tạo instance singleton.
	 *
	 * @return PAF_Assets
	 */
	public static function get_instance(): PAF_Assets {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor riêng tư — dùng get_instance().
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Đăng ký CSS và JS frontend.
	 */
	public function enqueue(): void {
		wp_enqueue_style(
			'post-ajax-filter',
			PAF_PLUGIN_URL . 'assets/css/post-ajax-filter.css',
			array(),
			PAF_VERSION
		);

		wp_enqueue_script(
			'post-ajax-filter',
			PAF_PLUGIN_URL . 'assets/js/post-ajax-filter.js',
			array( 'jquery' ),
			PAF_VERSION,
			true // Tải ở footer.
		);

		wp_localize_script(
			'post-ajax-filter',
			'paf_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'paf_filter_nonce' ),
				'i18n'     => array(
					'loading'   => esc_html__( 'Đang tải…', 'post-ajax-filter' ),
					'no_posts'  => esc_html__( 'Không tìm thấy bài viết phù hợp.', 'post-ajax-filter' ),
					'error'     => esc_html__( 'Đã xảy ra lỗi. Vui lòng thử lại.', 'post-ajax-filter' ),
				),
			)
		);
	}
}
