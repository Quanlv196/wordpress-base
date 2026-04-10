<?php
/**
 * Handles enqueuing of plugin scripts and styles.
 *
 * @package WC_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WCAF_Assets
 */
class WCAF_Assets {

	/**
	 * Singleton instance.
	 *
	 * @var WCAF_Assets|null
	 */
	private static $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return WCAF_Assets
	 */
	public static function get_instance(): WCAF_Assets {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue frontend CSS and JS.
	 */
	public function enqueue(): void {
		wp_enqueue_style(
			'wc-ajax-filter',
			WCAF_PLUGIN_URL . 'assets/css/wc-ajax-filter.css',
			array(),
			WCAF_VERSION
		);

		wp_enqueue_script(
			'wc-ajax-filter',
			WCAF_PLUGIN_URL . 'assets/js/wc-ajax-filter.js',
			array( 'jquery' ),
			WCAF_VERSION,
			true // Load in footer.
		);

		wp_localize_script(
			'wc-ajax-filter',
			'wcaf_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wcaf_filter_nonce' ),
				'i18n'     => array(
					'loading'     => esc_html__( 'Đang tải…', 'wc-ajax-filter' ),
					'no_products' => esc_html__( 'Không tìm thấy sản phẩm phù hợp.', 'wc-ajax-filter' ),
					'error'       => esc_html__( 'Đã xảy ra lỗi. Vui lòng thử lại.', 'wc-ajax-filter' ),
				),
			)
		);
	}
}
