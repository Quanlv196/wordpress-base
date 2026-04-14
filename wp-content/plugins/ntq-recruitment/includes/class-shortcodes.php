<?php
/**
 * Shortcodes: [job_filter], [job_list], [job_apply].
 */

defined( 'ABSPATH' ) || exit;

class NTQ_Shortcodes {

	public static function init() {
		add_shortcode( 'job_filter', array( __CLASS__, 'shortcode_filter' ) );
		add_shortcode( 'job_list',   array( __CLASS__, 'shortcode_list' ) );
		add_shortcode( 'job_apply',  array( __CLASS__, 'shortcode_apply' ) );
	}

	// ─── [job_filter] ─────────────────────────────────────────────────────────

	/**
	 * Renders a filter form with Department and Location dropdowns,
	 * or a tabs-style filter when `type` is specified.
	 *
	 * Usage: [job_filter]
	 *        [job_filter type="department"]
	 *        [job_filter type="location"]
	 */
	public static function shortcode_filter( $atts ) {
		$atts = shortcode_atts(
			array( 'type' => '' ),
			$atts,
			'job_filter'
		);

		$filter_type = sanitize_key( $atts['type'] );

		ob_start();
		include NTQ_REC_PLUGIN_DIR . 'templates/job-filter.php';
		return ob_get_clean();
	}

	// ─── [job_list] ───────────────────────────────────────────────────────────

	/**
	 * Renders the job listing with pagination and AJAX filter support.
	 *
	 * Usage: [job_list limit="10" offset="0"]
	 */
	public static function shortcode_list( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'  => 10,
				'offset' => 0,
			),
			$atts,
			'job_list'
		);

		$limit  = max( 1, min( 50, absint( $atts['limit'] ) ) );
		$offset = max( 0, absint( $atts['offset'] ) );
		$page   = max( 1, absint( NTQ_Helpers::request( 'job_page' ) ?: 1 ) );

		ob_start();
		include NTQ_REC_PLUGIN_DIR . 'templates/job-list.php';
		return ob_get_clean();
	}

	// ─── [job_apply] ──────────────────────────────────────────────────────────

	/**
	 * Renders the job application form.
	 *
	 * Usage: [job_apply job_id="123"]
	 */
	public static function shortcode_apply( $atts ) {
		$atts   = shortcode_atts( array( 'job_id' => 0 ), $atts, 'job_apply' );
		$job_id = absint( $atts['job_id'] );

		ob_start();
		self::render_apply_form( array( 'job_id' => $job_id ) );
		return ob_get_clean();
	}

	// ─── Shared form renderer ─────────────────────────────────────────────────

	/**
	 * Called by the shortcode and also by the_content filter on single job pages.
	 *
	 * @param array $args Accepts 'job_id'.
	 */
	public static function render_apply_form( array $args ) {
		$job_id = absint( $args['job_id'] ?? 0 );
		include NTQ_REC_PLUGIN_DIR . 'templates/job-apply.php';
	}

	/**
	 * Returns the apply form as a string (used by the_content filter).
	 *
	 * @param int $job_id Post ID.
	 * @return string
	 */
	public static function get_apply_form( $job_id ) {
		ob_start();
		self::render_apply_form( array( 'job_id' => absint( $job_id ) ) );
		return ob_get_clean();
	}
}
