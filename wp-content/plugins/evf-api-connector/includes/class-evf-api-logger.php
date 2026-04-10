<?php
/**
 * Logger – thin wrapper around WP debug log and an optional custom log file.
 *
 * @package EVF_API_Connector
 */

defined( 'ABSPATH' ) || exit;

/**
 * EVF_API_Logger
 *
 * Usage:
 *   EVF_API_Logger::info( 'Something happened' );
 *   EVF_API_Logger::error( 'Something failed', [ 'reason' => '...' ] );
 */
class EVF_API_Logger {

	// ── Public API ────────────────────────────────────────────────────────────

	public static function info( string $message, array $context = array() ): void {
		self::log( 'INFO', $message, $context );
	}

	public static function error( string $message, array $context = array() ): void {
		self::log( 'ERROR', $message, $context );
	}

	public static function debug( string $message, array $context = array() ): void {
		self::log( 'DEBUG', $message, $context );
	}

	// ── Internal ──────────────────────────────────────────────────────────────

	private static function log( string $level, string $message, array $context ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$settings = EVF_API_Connector::get_settings();
		$custom   = ! empty( $settings['global']['use_custom_log'] );

		if ( $custom ) {
			self::write_custom( $level, $message, $context );
		} else {
			self::write_wp_debug( $level, $message, $context );
		}
	}

	/**
	 * Is logging turned on in global settings?
	 */
	private static function is_enabled(): bool {
		$settings = EVF_API_Connector::get_settings();
		return ! empty( $settings['global']['enable_logging'] );
	}

	/**
	 * Write to WordPress native debug log (requires WP_DEBUG_LOG to be true).
	 */
	private static function write_wp_debug( string $level, string $message, array $context ): void {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$line = sprintf(
			'[EVF API Connector][%s] %s%s',
			$level,
			$message,
			empty( $context ) ? '' : ' ' . wp_json_encode( $context )
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $line );
	}

	/**
	 * Write to a custom log file inside wp-content, protected from web access.
	 */
	private static function write_custom( string $level, string $message, array $context ): void {
		$dir  = WP_CONTENT_DIR . '/evf-api-connector-logs';
		$file = $dir . '/debug.log';

		// Create directory with protection files on first use.
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			// Deny direct HTTP access.
			// phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $dir . '/.htaccess', 'Deny from all' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $dir . '/index.php', '<?php // Silence.' );
		}

		$entry = sprintf(
			"[%s][%s] %s%s\n",
			current_time( 'mysql' ),
			$level,
			$message,
			empty( $context ) ? '' : ' ' . wp_json_encode( $context )
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $file, $entry, FILE_APPEND | LOCK_EX );
	}
}
