<?php
/**
 * Logger Class.
 *
 * Handles all database logging for the Gemini Auto Blogger plugin.
 * Creates a custom table `{prefix}_gab_logs` and provides static helper
 * methods for writing and reading log entries.
 *
 * @package GeminiAutoBlogger
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GAB_Logger – persistent log storage backed by a custom DB table.
 */
class GAB_Logger {

	/** DB-version option key used to trigger table upgrades. */
	const TABLE_VERSION_OPTION = 'gab_db_version';

	/** Current table schema version. */
	const TABLE_VERSION = '1.0';

	// ── Log-level constants ────────────────────────────────────────────────
	const LEVEL_INFO    = 'info';
	const LEVEL_SUCCESS = 'success';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';

	// ── Schema ─────────────────────────────────────────────────────────────

	/**
	 * Create (or upgrade) the log table via dbDelta.
	 * Called on plugin activation.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'gab_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level      varchar(20)         NOT NULL DEFAULT 'info',
			message    text                NOT NULL,
			context    longtext                     DEFAULT NULL,
			created_at datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY level      (level),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::TABLE_VERSION_OPTION, self::TABLE_VERSION );
	}

	// ── Write helpers ──────────────────────────────────────────────────────

	/**
	 * Write a log entry.
	 *
	 * @param string $message Human-readable log message.
	 * @param string $level   One of the LEVEL_* constants.
	 * @param array  $context Optional key-value data stored as JSON.
	 */
	public static function log( $message, $level = self::LEVEL_INFO, array $context = array() ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'gab_logs',
			array(
				'level'      => sanitize_text_field( $level ),
				'message'    => sanitize_textarea_field( $message ),
				'context'    => ! empty( $context ) ? wp_json_encode( $context ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/** Shorthand – info level. */
	public static function info( $message, array $context = array() ) {
		self::log( $message, self::LEVEL_INFO, $context );
	}

	/** Shorthand – success level. */
	public static function success( $message, array $context = array() ) {
		self::log( $message, self::LEVEL_SUCCESS, $context );
	}

	/** Shorthand – warning level. */
	public static function warning( $message, array $context = array() ) {
		self::log( $message, self::LEVEL_WARNING, $context );
	}

	/** Shorthand – error level. */
	public static function error( $message, array $context = array() ) {
		self::log( $message, self::LEVEL_ERROR, $context );
	}

	// ── Read helpers ───────────────────────────────────────────────────────

	/**
	 * Retrieve log entries.
	 *
	 * @param int    $limit  Max rows to return.
	 * @param int    $offset Pagination offset.
	 * @param string $level  Filter by log level; empty = all levels.
	 * @return array Array of stdClass row objects.
	 */
	public static function get_logs( $limit = 50, $offset = 0, $level = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'gab_logs';

		if ( '' !== $level ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM `{$table}` WHERE level = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$level,
					$limit,
					$offset
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	/**
	 * Count total log entries (optionally filtered by level).
	 *
	 * @param string $level Filter by level; empty = all.
	 * @return int
	 */
	public static function count_logs( $level = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'gab_logs';

		if ( '' !== $level ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT COUNT(*) FROM `{$table}` WHERE level = %s",
					$level
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// ── Maintenance ────────────────────────────────────────────────────────

	/**
	 * Delete all log entries (TRUNCATE).
	 */
	public static function clear_logs() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->prefix}gab_logs`" );
	}

	/**
	 * Purge log entries older than N days.
	 *
	 * @param int $days Entries older than this many days are deleted.
	 */
	public static function purge_old_logs( $days = 30 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM `{$wpdb->prefix}gab_logs` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				(int) $days
			)
		);
	}
}
