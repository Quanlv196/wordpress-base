<?php
/**
 * Plugin Name:       EVF API Connector
 * Description:       Kết nối vào quy trình gửi biểu mẫu Everest Forms và gửi dữ liệu lên API bên thứ ba có thể cấu hình, hỗ trợ ánh xạ trường động riêng cho từng biểu mẫu.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Quanlv
 * License:           GPL v2 or later
 * Text Domain:       evf-api-connector
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'EVF_API_CONNECTOR_VERSION',    '1.0.0' );
define( 'EVF_API_CONNECTOR_FILE',       __FILE__ );
define( 'EVF_API_CONNECTOR_DIR',        plugin_dir_path( __FILE__ ) );
define( 'EVF_API_CONNECTOR_URL',        plugin_dir_url( __FILE__ ) );
define( 'EVF_API_CONNECTOR_OPTION_KEY', 'evf_api_connector_settings' );

/**
 * Main plugin class – singleton.
 *
 * Responsibilities:
 *  - Load required class files.
 *  - Verify that Everest Forms is active before booting.
 *  - Lazily instantiate Admin Settings, Hook Handler, and API Service objects.
 */
final class EVF_API_Connector {

	/** @var self|null */
	private static $instance = null;

	/** @var EVF_API_Admin_Settings */
	public $admin_settings;

	/** @var EVF_API_Hook_Handler */
	public $hook_handler;

	// ── Singleton ─────────────────────────────────────────────────────────────

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_files();
		add_action( 'plugins_loaded', array( $this, 'boot' ), 20 );
	}

	// ── Boot ──────────────────────────────────────────────────────────────────

	/**
	 * Boot plugin components after all plugins are loaded so we can safely
	 * check for the Everest Forms main class.
	 */
	public function boot(): void {
		if ( ! class_exists( 'EverestForms' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_missing_evf' ) );
			return;
		}

		$this->admin_settings = new EVF_API_Admin_Settings();
		$this->hook_handler   = new EVF_API_Hook_Handler( new EVF_API_Service() );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
	}

	/**
	 * Enqueue a small frontend script that reliably resets the EVF form after
	 * a successful AJAX submission by listening to the custom DOM event that
	 * Everest Forms dispatches and calling the native form.reset() method.
	 */
	public function enqueue_frontend_scripts(): void {
		wp_enqueue_script(
			'evf-api-connector-frontend',
			EVF_API_CONNECTOR_URL . 'assets/js/evf-api-frontend.js',
			array(),
			EVF_API_CONNECTOR_VERSION,
			true
		);
	}

	// ── File Loading ──────────────────────────────────────────────────────────

	private function load_files(): void {
		$includes = EVF_API_CONNECTOR_DIR . 'includes/';
		require_once $includes . 'class-evf-api-logger.php';
		require_once $includes . 'class-evf-api-service.php';
		require_once $includes . 'class-evf-api-hook-handler.php';
		require_once $includes . 'class-evf-api-admin-settings.php';
	}

	// ── Admin notice ──────────────────────────────────────────────────────────

	public function notice_missing_evf(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			wp_kses_post(
				__( '<strong>EVF API Connector</strong> yêu cầu <strong>Everest Forms</strong> phải được cài đặt và kích hoạt.', 'evf-api-connector' )
			)
		);
	}

	// ── Settings Helpers ──────────────────────────────────────────────────────

	/**
	 * Return the full plugin settings array.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		return (array) get_option( EVF_API_CONNECTOR_OPTION_KEY, array() );
	}

	/**
	 * Return settings for a specific Everest Forms form ID.
	 *
	 * @param  int $form_id
	 * @return array
	 */
	public static function get_form_settings( int $form_id ): array {
		$settings = self::get_settings();
		return isset( $settings['forms'][ $form_id ] ) ? (array) $settings['forms'][ $form_id ] : array();
	}
}

/**
 * Global function accessor – same pattern as WooCommerce/EVF.
 */
function evf_api_connector(): EVF_API_Connector {
	return EVF_API_Connector::instance();
}

// Kick off.
evf_api_connector();
