<?php
/**
 * Admin Class.
 *
 * Handles all WordPress Admin UI responsibilities:
 *  • Registering menu pages (Settings + Logs).
 *  • Registering and sanitising plugin settings via the Settings API.
 *  • Enqueueing admin assets.
 *  • AJAX handlers for: Test API, Generate Now, Clear Logs.
 *  • Static helpers `get_settings()` and `get_default_settings()`.
 *
 * @package GeminiAutoBlogger
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GAB_Admin – admin interface controller.
 */
class GAB_Admin {

	/** WordPress option key that stores all plugin settings. */
	const OPTION_KEY = 'gab_settings';

	/** Settings group name used with register_setting() / settings_fields(). */
	const SETTINGS_GROUP = 'gab_settings_group';

	// ── Constructor ────────────────────────────────────────────────────────

	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'add_menu_pages' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX handlers (wp_ajax_{action} = admin only, already logged-in).
		add_action( 'wp_ajax_gab_test_api',           array( $this, 'ajax_test_api' ) );
		add_action( 'wp_ajax_gab_generate_now',       array( $this, 'ajax_generate_now' ) );
		add_action( 'wp_ajax_gab_check_generation',   array( $this, 'ajax_check_generation' ) );
		add_action( 'wp_ajax_gab_clear_logs',         array( $this, 'ajax_clear_logs' ) );

		// Add "Settings" link on the Plugins list page.
		add_filter( 'plugin_action_links_' . GAB_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	// ── Menu pages ─────────────────────────────────────────────────────────

	public function add_menu_pages() {
		add_menu_page(
			__( 'AI Auto Blogger', 'gemini-auto-blogger' ),
			__( 'Auto Blogger', 'gemini-auto-blogger' ),
			'manage_options',
			'gemini-auto-blogger',
			array( $this, 'page_settings' ),
			'dashicons-robot',
			30
		);

		add_submenu_page(
			'gemini-auto-blogger',
			__( 'Cài đặt – AI Auto Blogger', 'gemini-auto-blogger' ),
			__( 'Cài đặt', 'gemini-auto-blogger' ),
			'manage_options',
			'gemini-auto-blogger',
			array( $this, 'page_settings' )
		);

		add_submenu_page(
			'gemini-auto-blogger',
			__( 'Nhật ký – AI Auto Blogger', 'gemini-auto-blogger' ),
			__( 'Nhật ký', 'gemini-auto-blogger' ),
			'manage_options',
			'gemini-auto-blogger-logs',
			array( $this, 'page_logs' )
		);
	}

	// ── Settings API ───────────────────────────────────────────────────────

	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_default_settings(),
			)
		);
	}

	/**
	 * Full sanitisation pass on submitted settings form data.
	 *
	 * @param  array $input Raw $_POST values from the settings form.
	 * @return array        Clean, type-safe settings array.
	 */
	public function sanitize_settings( $input ) {
		$defaults  = self::get_default_settings();
		$sanitized = array();

		// ── API ──────────────────────────────────────────────────────────────
		$sanitized['groq_api_key']   = sanitize_text_field( $input['groq_api_key'] ?? '' );
		$sanitized['gemini_api_key'] = sanitize_text_field( $input['gemini_api_key'] ?? '' );
		$sanitized['cf_account_id']  = sanitize_text_field( $input['cf_account_id'] ?? '' );

		$allowed_text_models  = array(
			'',
			// Groq.
			'llama-3.3-70b-versatile',
			'llama-3.1-8b-instant',
			'mixtral-8x7b-32768',
			'gemma2-9b-it',
		);
		$allowed_image_models = array(
			'', // SDXL is the only model; field kept for future use.
		);

		$text_input  = sanitize_text_field( $input['text_model']  ?? $defaults['text_model'] );
		$image_input = sanitize_text_field( $input['image_model'] ?? $defaults['image_model'] );

		$sanitized['text_model']  = in_array( $text_input, $allowed_text_models, true ) ? $text_input : $defaults['text_model'];
		$sanitized['image_model'] = in_array( $image_input, $allowed_image_models, true ) ? $image_input : $defaults['image_model'];

		// Legacy compatibility for very old installs that still post api_key.
		if ( empty( $sanitized['groq_api_key'] ) && ! empty( $input['api_key'] ) ) {
			$sanitized['groq_api_key'] = sanitize_text_field( $input['api_key'] );
		}

		// ── Topics ───────────────────────────────────────────────────────────
		$sanitized['topics']           = sanitize_textarea_field( $input['topics'] ?? '' );
		$sanitized['topic_order']      = in_array( $input['topic_order'] ?? '', array( 'random', 'sequential' ), true )
		                                  ? $input['topic_order'] : 'random';
		$sanitized['avoid_duplicates'] = ! empty( $input['avoid_duplicates'] ) ? 1 : 0;

		// ── Per-category topics ──────────────────────────────────────────────
		$raw_cat_topics = isset( $input['category_topics'] ) && is_array( $input['category_topics'] )
		                  ? $input['category_topics'] : array();
		$sanitized['category_topics'] = array();
		foreach ( $raw_cat_topics as $cat_id => $text ) {
			$cat_id = (int) $cat_id;
			if ( $cat_id > 0 ) {
				$sanitized['category_topics'][ $cat_id ] = sanitize_textarea_field( (string) $text );
			}
		}

		// ── Categories (multi-select – each value is a category ID integer) ──
		$raw_cats = isset( $input['categories'] ) && is_array( $input['categories'] )
		            ? $input['categories'] : array();
		$sanitized['categories'] = array_values( array_filter( array_map( 'intval', $raw_cats ) ) );

		// ── Author ───────────────────────────────────────────────────────────
		$sanitized['author_id'] = (int) ( $input['author_id'] ?? 0 );

		// ── Scheduling ───────────────────────────────────────────────────────
		$sanitized['cron_enabled']   = ! empty( $input['cron_enabled'] ) ? 1 : 0;
		$sanitized['interval_value'] = max( 1, (int) ( $input['interval_value'] ?? 1 ) );
		$sanitized['interval_unit']  = in_array( $input['interval_unit'] ?? '', array( 'minutes', 'hours', 'days' ), true )
		                                ? $input['interval_unit'] : 'days';

		// ── Publish ──────────────────────────────────────────────────────────
		$sanitized['publish_status'] = in_array( $input['publish_status'] ?? '', array( 'publish', 'draft', 'future' ), true )
		                                ? $input['publish_status'] : 'publish';
		$sanitized['publish_delay']  = max( 0, (int) ( $input['publish_delay'] ?? 0 ) );

		// ── Images ───────────────────────────────────────────────────────────
		$sanitized['generate_images'] = ! empty( $input['generate_images'] ) ? 1 : 0;
		$sanitized['images_per_post'] = min( 5, max( 1, (int) ( $input['images_per_post'] ?? 2 ) ) );

		// ── Prompt template ──────────────────────────────────────────────────
		// wp_kses_post allows basic HTML but strips scripts / iframes, etc.
		$sanitized['content_prompt_template'] = wp_kses_post( $input['content_prompt_template'] ?? '' );

		// ── Advanced ─────────────────────────────────────────────────────────
		$sanitized['max_retries']   = min( 5, max( 1, (int) ( $input['max_retries']   ?? 3 ) ) );
		$sanitized['posts_per_run'] = min( 5, max( 1, (int) ( $input['posts_per_run'] ?? 1 ) ) );

		return $sanitized;
	}

	// ── Assets ─────────────────────────────────────────────────────────────

	/**
	 * Enqueue CSS and JS only on plugin admin pages.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		$plugin_pages = array(
			'toplevel_page_gemini-auto-blogger',
			'auto-blogger_page_gemini-auto-blogger-logs',
		);

		if ( ! in_array( $hook, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'gab-admin',
			GAB_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			GAB_VERSION
		);

		wp_enqueue_script(
			'gab-admin',
			GAB_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			GAB_VERSION,
			true
		);

		// Pass data to JS safely via wp_localize_script.
		wp_localize_script(
			'gab-admin',
			'gabAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gab_admin_nonce' ),
				'i18n'    => array(
				'testing'         => __( 'Đang kiểm tra…', 'gemini-auto-blogger' ),
				'generating'      => __( 'Đang tạo bài viết… (có thể mất 3-5 phút)', 'gemini-auto-blogger' ),
				'clearing'        => __( 'Đang xóa…', 'gemini-auto-blogger' ),
				'confirmGenerate' => __( 'Tạo bài viết mới ngay bây giờ? Quá trình có thể mất 3-5 phút do tạo ảnh AI.', 'gemini-auto-blogger' ),
				'confirmClear'    => __( 'Xóa toàn bộ nhật ký? Hành động này không thể hoàn tác.', 'gemini-auto-blogger' ),
				'requestFailed'   => __( 'Yêu cầu thất bại hoặc hết thời gian chờ. Vui lòng kiểm tra Nhật ký để biết chi tiết.', 'gemini-auto-blogger' ),
				),
			)
		);
	}

	// ── Page renderers ─────────────────────────────────────────────────────

	public function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'gemini-auto-blogger' ) );
		}

		$settings  = self::get_settings();
		$scheduler = GAB_Plugin::get_instance()->scheduler;

		require GAB_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	public function page_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'gemini-auto-blogger' ) );
		}

		$current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$per_page     = 50;
		$offset       = ( $current_page - 1 ) * $per_page;
		$level_filter = sanitize_text_field( $_GET['level'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification

		$logs        = GAB_Logger::get_logs( $per_page, $offset, $level_filter );
		$total_logs  = GAB_Logger::count_logs( $level_filter );
		$total_pages = (int) ceil( $total_logs / $per_page );

		require GAB_PLUGIN_DIR . 'admin/views/logs-page.php';
	}

	// ── AJAX handlers ──────────────────────────────────────────────────────

	/**
	 * Test the saved (or provided) API keys.
	 */
	public function ajax_test_api() {
		check_ajax_referer( 'gab_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Không có quyền thực hiện thao tác này.', 'gemini-auto-blogger' ) ), 403 );
		}

		$groq_api_key   = sanitize_text_field( wp_unslash( $_POST['groq_api_key'] ?? '' ) );
		$gemini_api_key = sanitize_text_field( wp_unslash( $_POST['gemini_api_key'] ?? '' ) );
		$cf_account_id  = sanitize_text_field( wp_unslash( $_POST['cf_account_id'] ?? '' ) );

		if ( '' === $groq_api_key && '' === $gemini_api_key ) {
			wp_send_json_error( array( 'message' => __( 'Vui lòng nhập ít nhất một API key (Groq hoặc Cloudflare).', 'gemini-auto-blogger' ) ) );
		}

		$settings = self::get_settings();
		$test_text_model = sanitize_text_field( wp_unslash( $_POST['text_model'] ?? $settings['text_model'] ?? '' ) );
		$test_image_model = sanitize_text_field( wp_unslash( $_POST['image_model'] ?? $settings['image_model'] ?? '' ) );

		$api = new GAB_Gemini_Api(
			$groq_api_key,
			$gemini_api_key,
			$cf_account_id,
			$test_text_model,
			$test_image_model,
			1
		);

		$messages = array();

		if ( '' !== $groq_api_key ) {
			$groq_result = $api->test_groq_connection();
			if ( is_wp_error( $groq_result ) ) {
				wp_send_json_error( array( 'message' => sprintf( __( 'Groq lỗi: %s', 'gemini-auto-blogger' ), $groq_result->get_error_message() ) ) );
			}
			$messages[] = __( 'Groq OK', 'gemini-auto-blogger' );
		}

		if ( '' !== $gemini_api_key ) {
			$gemini_result = $api->test_gemini_connection();
			if ( is_wp_error( $gemini_result ) ) {
				wp_send_json_error( array( 'message' => sprintf( __( 'Cloudflare Workers AI lỗi: %s', 'gemini-auto-blogger' ), $gemini_result->get_error_message() ) ) );
			}
			$messages[] = __( 'Cloudflare Workers AI OK', 'gemini-auto-blogger' );
		}

		wp_send_json_success( array( 'message' => implode( ' | ', $messages ) ) );
	}

	/**
	 * Immediately generate one blog post and return its details.
	 *
	 * Runs synchronously: generation happens inside this AJAX handler and the
	 * result (success or error) is returned directly to the browser.
	 * No background jobs, no transients, no polling needed.
	 */
	public function ajax_generate_now() {
		check_ajax_referer( 'gab_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Không có quyền thực hiện thao tác này.', 'gemini-auto-blogger' ) ), 403 );
		}

		$settings = self::get_settings();
		if ( empty( $settings['groq_api_key'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Groq API key chưa được cấu hình.', 'gemini-auto-blogger' ) ) );
		}

		// Allow up to 10 minutes; generation + two image sleeps can take 5–8 min.
		@set_time_limit( 600 );
		ignore_user_abort( true );

		$result = GAB_Plugin::get_instance()->scheduler->trigger_now();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => sprintf(
				/* translators: %s: post title */
				__( 'Bài viết "%s" đã được tạo thành công!', 'gemini-auto-blogger' ),
				get_the_title( $result )
			),
			'post_id'  => $result,
			'view_url' => get_permalink( $result ),
			'edit_url' => get_edit_post_link( $result, 'raw' ),
		) );
	}

	/**
	 * Poll endpoint: check whether the background generation has finished.
	 */
	public function ajax_check_generation() {
		check_ajax_referer( 'gab_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(), 403 );
		}

		$result = get_transient( GAB_Scheduler::MANUAL_RESULT_KEY );

		if ( false === $result ) {
			// Cron hasn't finished yet.
			wp_send_json_success( array( 'status' => 'pending' ) );
		}

		// Consume the transient so it doesn't reappear on the next poll.
		delete_transient( GAB_Scheduler::MANUAL_RESULT_KEY );

		wp_send_json_success( $result ); // contains 'status', 'message', optionally post_id/urls
	}

	/**
	 * Delete all entries from the log table.
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'gab_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Không có quyền thực hiện thao tác này.', 'gemini-auto-blogger' ) ), 403 );
		}

		GAB_Logger::clear_logs();
		wp_send_json_success( array( 'message' => __( 'Đã xóa toàn bộ nhật ký.', 'gemini-auto-blogger' ) ) );
	}

	// ── Plugin action links ────────────────────────────────────────────────

	/**
	 * Add a "Settings" link on the Plugins list page.
	 *
	 * @param  array $links Existing action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=gemini-auto-blogger' ) ),
			esc_html__( 'Settings', 'gemini-auto-blogger' )
		);
		array_unshift( $links, $link );
		return $links;
	}

	// ── Static helpers ─────────────────────────────────────────────────────

	/**
	 * Return the saved settings merged with defaults.
	 * Safe to call before the plugin is fully initialised.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return wp_parse_args(
			(array) get_option( self::OPTION_KEY, array() ),
			self::get_default_settings()
		);
	}

	/**
	 * Return the plugin's default settings.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			// API
			'groq_api_key'             => '',
			'gemini_api_key'           => '',
			'cf_account_id'            => '',
			'text_model'               => '',
			'image_model'              => '',
			// Topics
			'topics'                   => '',
			'topic_order'              => 'random',
			'avoid_duplicates'         => 1,
			'category_topics'          => array(),
			// Categories (fallback when category_topics not set)
			'categories'               => array( 1 ),
			// Author
			'author_id'                => 0,
			// Scheduling
			'cron_enabled'             => 0,
			'interval_value'           => 1,
			'interval_unit'            => 'days',
			// Publish
			'publish_status'           => 'publish',
			'publish_delay'            => 0,
			// Images
			'generate_images'          => 1,
			'images_per_post'          => 2,
			// Prompt
			'content_prompt_template'  => '',
			// Advanced
			'max_retries'              => 3,
			'posts_per_run'            => 1,
		);
	}
}
