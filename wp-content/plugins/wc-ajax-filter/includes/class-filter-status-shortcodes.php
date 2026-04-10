<?php
/**
 * Registers the [wc_filter_count], [wc_clear_filter], and [wc_active_filters] shortcodes.
 *
 * [wc_filter_count] — renders a live badge showing how many filter values
 *   are currently active. JavaScript updates the number whenever filters change.
 *   Usage: [wc_filter_count filter_ids="f1,f2"]
 *
 * [wc_clear_filter] — renders a "clear all" button that resets every connected
 *   filter widget and triggers a product-list refresh.
 *   Usage: [wc_clear_filter filter_ids="f1,f2" label="Xoá bộ lọc"]
 *
 * [wc_active_filters] — renders a live list of active filter chips (each showing
 *   the selected value label with an "×" to remove it individually) plus a
 *   "clear all" button. JavaScript populates and updates the chips automatically.
 *   Usage: [wc_active_filters filter_ids="f1,f2" clear_label="Xoá tất cả" empty_text=""]
 *
 * @package WC_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WCAF_Filter_Status_Shortcodes
 */
class WCAF_Filter_Status_Shortcodes {

	/**
	 * Singleton instance.
	 *
	 * @var WCAF_Filter_Status_Shortcodes|null
	 */
	private static $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return WCAF_Filter_Status_Shortcodes
	 */
	public static function get_instance(): WCAF_Filter_Status_Shortcodes {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Private constructor — use get_instance(). */
	private function __construct() {
		add_shortcode( 'wc_filter_count',    array( $this, 'render_count' ) );
		add_shortcode( 'wc_clear_filter',    array( $this, 'render_clear' ) );
		add_shortcode( 'wc_active_filters',  array( $this, 'render_active_filters' ) );
	}

	// -------------------------------------------------------------------------
	// [wc_filter_count]
	// -------------------------------------------------------------------------

	/**
	 * Render the active-filter count badge.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string            HTML output.
	 */
	public function render_count( $atts ): string {
		$atts = shortcode_atts(
			array(
				'filter_ids' => '', // Comma-separated IDs of [wc_filter] widgets to watch.
				'class'      => '',
				'zero_text'  => '',  // Optional text to display when count is 0.
			),
			$atts,
			'wc_filter_count'
		);

		$filter_ids = $this->parse_ids( $atts['filter_ids'] );
		$extra_cls  = $this->sanitize_classes( $atts['class'] );
		$zero_text  = sanitize_text_field( $atts['zero_text'] );

		$classes = 'wcaf-filter-count';
		if ( $extra_cls ) {
			$classes .= ' ' . $extra_cls;
		}

		ob_start();
		?>
		<span
			class="<?php echo esc_attr( $classes ); ?>"
			data-filter-ids="<?php echo esc_attr( implode( ',', $filter_ids ) ); ?>"
			data-zero-text="<?php echo esc_attr( $zero_text ); ?>"
			aria-live="polite"
			aria-atomic="true"
		>0</span>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [wc_active_filters]
	// -------------------------------------------------------------------------

	/**
	 * Render the active-filter chips container.
	 *
	 * JavaScript fills in the chip tags on every filter change.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string            HTML output.
	 */
	public function render_active_filters( $atts ): string {
		$atts = shortcode_atts(
			array(
				'filter_ids'  => '',
				'clear_label' => __( 'Xoá tất cả', 'wc-ajax-filter' ),
				'empty_text'  => '',
				'class'       => '',
			),
			$atts,
			'wc_active_filters'
		);

		$filter_ids  = $this->parse_ids( $atts['filter_ids'] );
		$clear_label = sanitize_text_field( $atts['clear_label'] );
		$empty_text  = sanitize_text_field( $atts['empty_text'] );
		$extra_cls   = $this->sanitize_classes( $atts['class'] );

		$classes = 'wcaf-active-filters is-empty';
		if ( $extra_cls ) {
			$classes .= ' ' . $extra_cls;
		}

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( $classes ); ?>"
			data-filter-ids="<?php echo esc_attr( implode( ',', $filter_ids ) ); ?>"
			aria-live="polite"
		>
			<?php if ( $empty_text ) : ?>
			<span class="wcaf-active-filters__empty"><?php echo esc_html( $empty_text ); ?></span>
			<?php endif; ?>
			<div class="wcaf-active-filters__tags"></div>
			<button
				type="button"
				class="wcaf-active-filters__clear-all"
				style="display:none"
				aria-label="<?php echo esc_attr( $clear_label ); ?>"
			><?php echo esc_html( $clear_label ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [wc_clear_filter]
	// -------------------------------------------------------------------------

	/**
	 * Render the clear-all-filters button.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string            HTML output.
	 */
	public function render_clear( $atts ): string {
		$atts = shortcode_atts(
			array(
				'filter_ids' => '', // Comma-separated IDs of [wc_filter] widgets to clear.
				'label'      => __( 'Xoá bộ lọc', 'wc-ajax-filter' ),
				'class'      => '',
			),
			$atts,
			'wc_clear_filter'
		);

		$filter_ids = $this->parse_ids( $atts['filter_ids'] );
		$label      = sanitize_text_field( $atts['label'] );
		$extra_cls  = $this->sanitize_classes( $atts['class'] );

		$classes = 'wcaf-clear-filter';
		if ( $extra_cls ) {
			$classes .= ' ' . $extra_cls;
		}

		ob_start();
		?>
		<button
			type="button"
			class="<?php echo esc_attr( $classes ); ?>"
			data-filter-ids="<?php echo esc_attr( implode( ',', $filter_ids ) ); ?>"
			aria-label="<?php echo esc_attr( $label ); ?>"
		><?php echo esc_html( $label ); ?></button>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Parse a comma-separated list of element IDs into a sanitised array.
	 *
	 * @param  string $raw Raw attribute value.
	 * @return string[]    Array of sanitised HTML-class-safe IDs.
	 */
	private function parse_ids( string $raw ): array {
		return array_values(
			array_filter(
				array_map( 'sanitize_html_class', array_map( 'trim', explode( ',', $raw ) ) )
			)
		);
	}

	/**
	 * Convert a space-separated class string into a sanitised string.
	 *
	 * @param  string $raw Raw class attribute value.
	 * @return string      Sanitised class string.
	 */
	private function sanitize_classes( string $raw ): string {
		return implode(
			' ',
			array_filter(
				array_map( 'sanitize_html_class', explode( ' ', trim( $raw ) ) )
			)
		);
	}
}
