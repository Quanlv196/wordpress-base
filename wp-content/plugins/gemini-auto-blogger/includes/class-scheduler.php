<?php
/**
 * Scheduler Class.
 *
 * Manages the WP-Cron event that fires post generation on a configurable
 * interval. Also exposes a method to trigger generation immediately
 * from the admin UI.
 *
 * When the admin saves new interval settings the cron event is automatically
 * rescheduled so the change takes effect on the next WordPress page load.
 *
 * @package GeminiAutoBlogger
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GAB_Scheduler – owns the WP-Cron lifecycle.
 */
class GAB_Scheduler {

	/** Name of the WP-Cron action this class hooks into. */
	const CRON_HOOK = 'gab_generate_post_cron';

	/** One-time cron hook for manual "Generate Now" triggered from admin. */
	const MANUAL_CRON_HOOK = 'gab_manual_generate';

	/** Transient key that carries the manual generation result. */
	const MANUAL_RESULT_KEY = 'gab_manual_gen_result';

	// ── Constructor ────────────────────────────────────────────────────────

	/**
	 * Register the cron action handler and the settings-change listener.
	 */
	public function __construct() {
		// This fires every time WordPress calls our cron hook.
		add_action( self::CRON_HOOK,        array( $this, 'run' ) );
		add_action( self::MANUAL_CRON_HOOK, array( $this, 'run_manual' ) );

		// Self-heal: ensure cron remains scheduled when automation is enabled.
		add_action( 'init', array( $this, 'ensure_scheduled' ) );

		// Fallback runner for environments where WP-Cron spawn is blocked.
		add_action( 'init', array( $this, 'maybe_run_overdue_event' ) );

		// When the admin saves settings, reschedule if the interval changed.
		add_action( 'update_option_gab_settings', array( $this, 'maybe_reschedule' ), 10, 2 );
	}

	// ── Cron callback ──────────────────────────────────────────────────────

	/**
	 * Main cron callback – instantiates the generator and creates posts.
	 *
	 * Guards:
	 *  • Cron must be enabled in settings.
	 *  • API key must be present.
	 *  • Posts-per-run is capped between 1 and 5.
	 */
	public function run() {
		$settings = GAB_Admin::get_settings();

		if ( empty( $settings['cron_enabled'] ) ) {
			GAB_Logger::info( 'Scheduled run skipped: automation is disabled in settings.' );
			return;
		}

		if ( empty( $settings['groq_api_key'] ) ) {
			GAB_Logger::error( 'Scheduled run aborted: Groq API key is not configured (required for text generation).' );
			return;
		}

		GAB_Logger::info( 'Scheduled post generation started.' );
		$this->mark_last_run();

		$api       = $this->make_api( $settings );
		$generator = new GAB_Post_Generator( $api, $settings );

		$posts_per_run = min( 5, max( 1, (int) ( $settings['posts_per_run'] ?? 1 ) ) );

		for ( $i = 0; $i < $posts_per_run; $i++ ) {
			$result = $generator->generate_post();

			if ( is_wp_error( $result ) ) {
				GAB_Logger::error(
					sprintf( 'Run #%d failed: %s', $i + 1, $result->get_error_message() )
				);
			} else {
				GAB_Logger::success( sprintf( 'Run #%d produced post ID %d.', $i + 1, $result ) );
			}

			// Brief pause between consecutive posts to avoid rate-limiting.
			if ( $i < $posts_per_run - 1 ) {
				sleep( 3 );
			}
		}
	}

	// ── Manual trigger ─────────────────────────────────────────────────────

	/**
	 * Generate exactly one post right now (called from the admin AJAX handler).
	 *
	 * @return int|WP_Error New post ID or WP_Error.
	 */
	public function trigger_now() {
		$settings = GAB_Admin::get_settings();

		if ( empty( $settings['groq_api_key'] ) ) {
			return new WP_Error( 'no_groq_api_key', __( 'Groq API key is not configured.', 'gemini-auto-blogger' ) );
		}

		$api       = $this->make_api( $settings );
		$generator = new GAB_Post_Generator( $api, $settings );

		$this->mark_last_run();

		$result = $generator->generate_post();

		// Keep "next run" in sync with current interval after manual execution.
		if ( ! empty( $settings['cron_enabled'] ) ) {
			$this->schedule( time() + $this->get_interval_seconds( $settings ) );
		}

		return $result;
	}

	/**
	 * Cron callback for a one-time manual generation triggered from the admin panel.
	 *
	 * Runs the generation and stores the result in a short-lived transient so the
	 * JS polling endpoint can retrieve it.
	 */
	public function run_manual() {
		@set_time_limit( 600 ); // Allow up to 10 minutes for this background task.

		$settings = GAB_Admin::get_settings();

		if ( empty( $settings['groq_api_key'] ) ) {
			set_transient(
				self::MANUAL_RESULT_KEY,
				array(
					'status'  => 'error',
					'message' => __( 'Groq API key chưa được cấu hình.', 'gemini-auto-blogger' ),
				),
				10 * MINUTE_IN_SECONDS
			);
			return;
		}

		$api       = $this->make_api( $settings );
		$generator = new GAB_Post_Generator( $api, $settings );

		$this->mark_last_run();
		$result = $generator->generate_post();

		if ( is_wp_error( $result ) ) {
			set_transient(
				self::MANUAL_RESULT_KEY,
				array(
					'status'  => 'error',
					'message' => $result->get_error_message(),
				),
				10 * MINUTE_IN_SECONDS
			);
			return;
		}

		// Keep next-run in sync when automation is enabled.
		if ( ! empty( $settings['cron_enabled'] ) ) {
			$this->schedule( time() + $this->get_interval_seconds( $settings ) );
		}

		set_transient(
			self::MANUAL_RESULT_KEY,
			array(
				'status'   => 'done',
				'message'  => sprintf(
					/* translators: %s: post title */
					__( 'Bài viết "%s" đã được tạo thành công!', 'gemini-auto-blogger' ),
					get_the_title( $result )
				),
				'post_id'  => $result,
				'view_url' => get_permalink( $result ),
				'edit_url' => get_edit_post_link( $result, 'raw' ),
			),
			10 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Ensure the cron event exists whenever automation is enabled.
	 *
	 * This protects against edge cases where events are lost (cache reset,
	 * DB restore, or manual cleanup), while keeping behaviour unchanged when
	 * automation is disabled.
	 */
	public function ensure_scheduled() {
		$settings = GAB_Admin::get_settings();

		if ( empty( $settings['cron_enabled'] ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + $this->get_interval_seconds( $settings ), 'gab_custom_interval', self::CRON_HOOK );
			GAB_Logger::info( 'Cron event was missing and has been re-scheduled automatically.' );
		}
	}

	/**
	 * Run overdue job inline when WP-Cron background spawning does not execute.
	 *
	 * This keeps automation working on local/dev setups while still relying on
	 * standard WP-Cron scheduling for normal environments.
	 */
	public function maybe_run_overdue_event() {
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return;
		}

		$settings = GAB_Admin::get_settings();

		if ( empty( $settings['cron_enabled'] ) ) {
			return;
		}

		$next_run = wp_next_scheduled( self::CRON_HOOK );
		if ( false === $next_run || $next_run > time() ) {
			return;
		}

		$lock_key = 'gab_overdue_runner_lock';
		if ( get_transient( $lock_key ) ) {
			return;
		}

		set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );

		try {
			GAB_Logger::warning( 'Detected overdue cron event. Running fallback execution on current request.' );
			$this->run();
			$this->schedule( time() + $this->get_interval_seconds( $settings ) );
		} finally {
			delete_transient( $lock_key );
		}
	}

	// ── Schedule management ────────────────────────────────────────────────

	/**
	 * Schedule or replace the cron event.
	 * Called on activation and whenever the scheduler should start fresh.
	 */
	public function schedule( $start_time = null ) {
		$settings = GAB_Admin::get_settings();

		// Clear any existing event first.
		wp_clear_scheduled_hook( self::CRON_HOOK );

		if ( ! empty( $settings['cron_enabled'] ) ) {
			if ( null === $start_time ) {
				$start_time = time() + $this->get_interval_seconds( $settings );
			}

			wp_schedule_event( (int) $start_time, 'gab_custom_interval', self::CRON_HOOK );
			GAB_Logger::info( 'Cron event scheduled.' );
		}
	}

	/**
	 * Unschedule the cron event (called on deactivation).
	 */
	public function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Listens to `update_option_gab_settings` and reschedules the cron only
	 * when the interval value or unit actually changed.
	 *
	 * @param mixed $old Old option value.
	 * @param mixed $new New option value.
	 */
	public function maybe_reschedule( $old, $new ) {
		$interval_changed =
			( $old['interval_value'] ?? null ) !== ( $new['interval_value'] ?? null ) ||
			( $old['interval_unit']  ?? null ) !== ( $new['interval_unit']  ?? null ) ||
			( $old['cron_enabled']   ?? null ) !== ( $new['cron_enabled']   ?? null );

		if ( $interval_changed ) {
			$this->schedule();
			GAB_Logger::info( 'Cron rescheduled after settings change.' );
		}
	}

	// ── Status helpers ─────────────────────────────────────────────────────

	/**
	 * Return the Unix timestamp of the next scheduled run, or false.
	 *
	 * @return int|false
	 */
	public function get_next_run() {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Return the MySQL datetime string of the last completed run, or false.
	 *
	 * @return string|false
	 */
	public function get_last_run() {
		return get_option( 'gab_last_run', false );
	}

	/**
	 * Persist the current site-local datetime as the latest run timestamp.
	 */
	private function mark_last_run() {
		update_option( 'gab_last_run', current_time( 'mysql' ) );
	}

	/**
	 * Convert scheduling settings into a seconds interval.
	 *
	 * @param array $settings Plugin settings.
	 * @return int
	 */
	private function get_interval_seconds( array $settings ) {
		$value = max( 1, (int) ( $settings['interval_value'] ?? 1 ) );
		$unit  = $settings['interval_unit'] ?? 'days';

		$multipliers = array(
			'minutes' => MINUTE_IN_SECONDS,
			'hours'   => HOUR_IN_SECONDS,
			'days'    => DAY_IN_SECONDS,
		);

		return $value * ( $multipliers[ $unit ] ?? DAY_IN_SECONDS );
	}

	// ── Private helpers ────────────────────────────────────────────────────

	/**
	 * Instantiate the AI API wrapper from the current settings.
	 *
	 * @param array $settings Plugin settings.
	 * @return GAB_Gemini_Api
	 */
	private function make_api( array $settings ) {
		return new GAB_Gemini_Api(
			$settings['groq_api_key']   ?? '',
			$settings['gemini_api_key'] ?? '',
			$settings['cf_account_id']  ?? '',
			$settings['text_model']  ?? '',
			$settings['image_model'] ?? '',
			(int) ( $settings['max_retries'] ?? 3 )
		);
	}
}
