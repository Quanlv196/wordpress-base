<?php
/**
 * Plugin Name:       Recruitment Manager
 * Description:       Hệ thống quản lý tuyển dụng và việc làm cho WordPress.
 * Author:            Quanlv
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'NTQ_REC_VERSION',            '1.0.0' );
define( 'NTQ_REC_PLUGIN_FILE',        __FILE__ );
define( 'NTQ_REC_PLUGIN_DIR',         plugin_dir_path( __FILE__ ) );
define( 'NTQ_REC_PLUGIN_URL',         plugin_dir_url( __FILE__ ) );
define( 'NTQ_REC_MAX_UPLOAD_SIZE',    5 * 1024 * 1024 ); // 5 MB
define( 'NTQ_REC_ALLOWED_FILE_EXTS',  array( 'pdf', 'doc', 'docx' ) );
define( 'NTQ_REC_ALLOWED_MIME_TYPES', array(
	'pdf'  => 'application/pdf',
	'doc'  => 'application/msword',
	'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
) );

// ─── Autoload includes ────────────────────────────────────────────────────────
foreach ( array(
	'class-database',
	'class-helpers',
	'class-cpt',
	'class-admin',
	'class-shortcodes',
	'class-ajax',
	'class-rest-api',
	'class-mailer',
) as $file ) {
	require_once NTQ_REC_PLUGIN_DIR . "includes/{$file}.php";
}

// ─── Main plugin class ────────────────────────────────────────────────────────
final class NTQ_Recruitment {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	public function boot() {
		NTQ_CPT::init();
		NTQ_Admin::init();
		NTQ_Shortcodes::init();
		NTQ_Ajax::init();
		NTQ_Rest_API::init();

		add_action( 'init',                  array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts',    array( $this, 'frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_filter( 'the_content',           array( $this, 'append_apply_form' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'ntq-recruitment',
			false,
			dirname( plugin_basename( NTQ_REC_PLUGIN_FILE ) ) . '/languages'
		);
	}

	public function frontend_assets() {
		wp_enqueue_style(
			'ntq-rec-frontend',
			NTQ_REC_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			NTQ_REC_VERSION
		);
		wp_enqueue_script(
			'ntq-rec-frontend',
			NTQ_REC_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			NTQ_REC_VERSION,
			true
		);
		wp_localize_script( 'ntq-rec-frontend', 'NTQRec', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ntq_rec_nonce' ),
			'i18n'    => array(
				'loading'      => esc_html__( 'Đang tải…', 'ntq-recruitment' ),
				'error'        => esc_html__( 'Có lỗi xảy ra. Vui lòng thử lại.', 'ntq-recruitment' ),
				'required'     => esc_html__( 'Trường này là bắt buộc.', 'ntq-recruitment' ),
				'invalidEmail' => esc_html__( 'Vui lòng nhập địa chỉ email hợp lệ.', 'ntq-recruitment' ),
				'invalidFile'  => esc_html__( 'Chỉ chấp nhận file PDF, DOC hoặc DOCX.', 'ntq-recruitment' ),
				'fileTooLarge' => esc_html__( 'Kích thước file không được vượt quá 5 MB.', 'ntq-recruitment' ),
				'noJobs'       => esc_html__( 'Không có vị trí tuyển dụng nào.', 'ntq-recruitment' ),
				'success'      => esc_html__( 'Hồ sơ của bạn đã được gửi thành công! Chúng tôi sẽ liên hệ với bạn sớm.', 'ntq-recruitment' ),
				'successTitle' => esc_html__( 'Đã Gửi Hồ Sơ!', 'ntq-recruitment' ),
			),
		) );
	}

	public function admin_assets() {
		wp_enqueue_style(
			'ntq-rec-admin',
			NTQ_REC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			NTQ_REC_VERSION
		);
		wp_enqueue_script(
			'ntq-rec-admin',
			NTQ_REC_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			NTQ_REC_VERSION,
			true
		);
		wp_localize_script( 'ntq-rec-admin', 'NTQRecAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ntq_rec_admin_nonce' ),
			'confirm' => esc_html__( 'Bạn có chắc không? Hành động này không thể hoàn tác.', 'ntq-recruitment' ),
		) );
	}

	/**
	 * Replaces single-job content with the two-column detail layout.
	 */
	public function append_apply_form( $content ) {
		if ( is_singular( 'job' ) && in_the_loop() && is_main_query() ) {
			$job_id = get_the_ID();
			ob_start();
			include NTQ_REC_PLUGIN_DIR . 'templates/single-job-detail.php';
			return ob_get_clean();
		}
		return $content;
	}
}

// ─── Activation / Deactivation ────────────────────────────────────────────────
register_activation_hook( NTQ_REC_PLUGIN_FILE, function () {
	NTQ_Database::create_tables();
	NTQ_CPT::register_post_type();
	NTQ_CPT::register_taxonomies();
	flush_rewrite_rules();
} );

register_deactivation_hook( NTQ_REC_PLUGIN_FILE, function () {
	flush_rewrite_rules();
} );

// ─── Boot ─────────────────────────────────────────────────────────────────────
NTQ_Recruitment::instance();
