<?php
/**
 * Main Plugin Class.
 *
 * Singleton orchestrator that boots all plugin components and owns the
 * static activation / deactivation callbacks registered in the main file.
 *
 * @package GeminiAutoBlogger
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GAB_Plugin – core bootstrap singleton.
 */
class GAB_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var GAB_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin component. Public so other classes can reach $plugin->admin->…
	 *
	 * @var GAB_Admin|null
	 */
	public $admin = null;

	/**
	 * Scheduler component. Public so AJAX handlers can call trigger_now().
	 *
	 * @var GAB_Scheduler|null
	 */
	public $scheduler = null;

	/** WP-Cron hook name for the daily log-purge event. */
	const LOG_PURGE_HOOK     = 'gab_purge_logs_daily';

	/** Number of days to retain log entries. */
	const LOG_RETENTION_DAYS = 7;

	// ── Singleton ──────────────────────────────────────────────────────────

	/**
	 * Get or create the singleton instance.
	 *
	 * @return GAB_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/** Prevent external instantiation. */
	private function __construct() {
		$this->init();
	}

	/** Prevent cloning. */
	private function __clone() {}

	// ── Initialisation ─────────────────────────────────────────────────────

	/**
	 * Wire up all plugin components.
	 */
	private function init() {
		// Load translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Migrate stale settings from old model names to current ones.
		$this->maybe_migrate_settings();

		// Register our custom cron schedule interval before anything
		// schedules events (runs on every page load).
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );

		// Wire the daily log-purge callback and ensure it is scheduled.
		add_action( self::LOG_PURGE_HOOK, array( $this, 'cleanup_old_logs' ) );
		$this->maybe_schedule_log_cleanup();

		// Always instantiate the Scheduler so the cron hook is registered
		// even on non-admin page loads (WP-Cron fires like a normal request).
		$this->scheduler = new GAB_Scheduler();

		// Admin UI is only needed inside the dashboard.
		if ( is_admin() ) {
			$this->admin = new GAB_Admin();
		}
	}

	/**
	 * One-time migration: replace deprecated / renamed model names that may
	 * be stored in the database with their current equivalents.
	 *
	 * Runs on every plugins_loaded but only writes to DB when a change is
	 * actually needed, so the overhead is negligible.
	 */
	private function maybe_migrate_settings() {
		$raw     = (array) get_option( GAB_Admin::OPTION_KEY, array() );
		$changed = false;

		if ( ! isset( $raw['groq_api_key'] ) ) {
			$raw['groq_api_key'] = '';
			$changed = true;
		}

		if ( ! isset( $raw['gemini_api_key'] ) ) {
			$raw['gemini_api_key'] = '';
			$changed = true;
		}

		if ( ! empty( $raw['api_key'] ) ) {
			$legacy_provider = (string) ( $raw['ai_provider'] ?? 'groq' );

			if ( 'gemini' === $legacy_provider && empty( $raw['gemini_api_key'] ) ) {
				$raw['gemini_api_key'] = $raw['api_key'];
				$changed = true;
			}

			if ( empty( $raw['groq_api_key'] ) ) {
				$raw['groq_api_key'] = $raw['api_key'];
				$changed = true;
			}

			unset( $raw['api_key'] );
			$changed = true;
		}

		if ( isset( $raw['ai_provider'] ) ) {
			unset( $raw['ai_provider'] );
			$changed = true;
		}

		if ( ! empty( $raw['text_model'] ) && 0 === strpos( (string) $raw['text_model'], 'gemini-' ) ) {
			$raw['text_model'] = '';
			$changed = true;
		}

		if ( $changed ) {
			update_option( GAB_Admin::OPTION_KEY, $raw );
			GAB_Logger::info( 'Settings migrated: Groq/Cloudflare API keys updated.', $raw );
		}
	}

	// ── i18n ───────────────────────────────────────────────────────────────

	public function load_textdomain() {
		load_plugin_textdomain(
			'gemini-auto-blogger',
			false,
			dirname( GAB_PLUGIN_BASENAME ) . '/languages'
		);
	}

	// ── WP-Cron schedule ───────────────────────────────────────────────────

	/**
	 * Register the `gab_custom_interval` named schedule so WP-Cron knows
	 * how many seconds to wait between runs.
	 *
	 * Because this filter runs on every request, changing the settings
	 * values will immediately affect the active interval (the schedule
	 * name is fixed; only its interval in seconds changes).
	 *
	 * @param  array $schedules Existing WP-Cron schedules.
	 * @return array
	 */
	public function add_cron_interval( $schedules ) {
		$settings = GAB_Admin::get_settings();

		$value = max( 1, (int) ( $settings['interval_value'] ?? 1 ) );
		$unit  = $settings['interval_unit'] ?? 'days';

		$multipliers = array(
			'minutes' => MINUTE_IN_SECONDS,
			'hours'   => HOUR_IN_SECONDS,
			'days'    => DAY_IN_SECONDS,
		);

		$seconds = $value * ( $multipliers[ $unit ] ?? DAY_IN_SECONDS );

		$schedules['gab_custom_interval'] = array(
			'interval' => $seconds,
			'display'  => sprintf(
				/* translators: %1$d: number, %2$s: unit (minutes/hours/days) */
				__( 'Every %1$d %2$s (AI Auto Blogger)', 'gemini-auto-blogger' ),
				$value,
				$unit
			),
		);

		return $schedules;
	}

	// ── Activation / Deactivation ──────────────────────────────────────────

	/**
	 * Static activation callback (called from the main plugin file).
	 *
	 * Creates the DB log table, stores default settings, and schedules
	 * the cron event.
	 */
	public static function activate() {
		// Create log table.
		GAB_Logger::create_table();

		// Persist default settings if the option does not exist yet.
		if ( false === get_option( GAB_Admin::OPTION_KEY ) ) {
			update_option( GAB_Admin::OPTION_KEY, GAB_Admin::get_default_settings() );
		}

		// Schedule the cron event (respects 'cron_enabled' setting).
		// We need the custom interval registered before scheduling,
		// so temporarily register it inline.
		$settings    = GAB_Admin::get_settings();
		$value       = max( 1, (int) ( $settings['interval_value'] ?? 1 ) );
		$unit        = $settings['interval_unit'] ?? 'days';
		$multipliers = array(
			'minutes' => MINUTE_IN_SECONDS,
			'hours'   => HOUR_IN_SECONDS,
			'days'    => DAY_IN_SECONDS,
		);
		$seconds = $value * ( $multipliers[ $unit ] ?? DAY_IN_SECONDS );

		/* Register inline so wp_schedule_event accepts the schedule name. */
		add_filter( 'cron_schedules', function ( $s ) use ( $seconds ) {
			$s['gab_custom_interval'] = array(
				'interval' => $seconds,
				'display'  => 'AI Auto Blogger custom interval',
			);
			return $s;
		} );

		if ( ! empty( $settings['cron_enabled'] ) && ! wp_next_scheduled( GAB_Scheduler::CRON_HOOK ) ) {
			wp_schedule_event( time() + $seconds, 'gab_custom_interval', GAB_Scheduler::CRON_HOOK );
		}

		// Schedule the daily log-purge event.
		if ( ! wp_next_scheduled( self::LOG_PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::LOG_PURGE_HOOK );
		}

		flush_rewrite_rules();
	}

	/**
	 * Static deactivation callback (called from the main plugin file).
	 *
	 * Removes all scheduled cron events for this plugin.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( GAB_Scheduler::CRON_HOOK );
		wp_clear_scheduled_hook( self::LOG_PURGE_HOOK );
		flush_rewrite_rules();
	}

	// ── Log maintenance ────────────────────────────────────────────────────

	/**
	 * Schedule the daily log-purge event if not already scheduled.
	 * Called on every page load from init().
	 */
	private function maybe_schedule_log_cleanup() {
		if ( ! wp_next_scheduled( self::LOG_PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::LOG_PURGE_HOOK );
		}
	}

	/**
	 * WP-Cron callback: delete log entries older than LOG_RETENTION_DAYS.
	 */
	public function cleanup_old_logs() {
		GAB_Logger::purge_old_logs( self::LOG_RETENTION_DAYS );
	}
}
