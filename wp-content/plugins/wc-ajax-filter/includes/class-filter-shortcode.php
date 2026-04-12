<?php
/**
 * Registers and renders the [wc_filter] shortcode.
 *
 * Usage examples:
 *   [wc_filter id="f1" type="category" ui="checkbox"]
 *   [wc_filter id="f2" type="brand"    ui="tabs"]
 *   [wc_filter id="f3" type="price"    ui="range"]
 *   [wc_filter id="f4" type="price"    ui="dropdown"]
 *
 * @package WC_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WCAF_Filter_Shortcode
 */
class WCAF_Filter_Shortcode {

	/**
	 * Singleton instance.
	 *
	 * @var WCAF_Filter_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return WCAF_Filter_Shortcode
	 */
	public static function get_instance(): WCAF_Filter_Shortcode {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Private constructor — use get_instance(). */
	private function __construct() {
		add_shortcode( 'wc_filter', array( $this, 'render' ) );
	}

	// -------------------------------------------------------------------------
	// Shortcode entry point
	// -------------------------------------------------------------------------

	/**
	 * Render the filter widget.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string            HTML output.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'         => 'wcaf-filter-' . wp_unique_id(),
				'type'       => 'category',  // category | brand | price
				'ui'         => 'checkbox',  // checkbox | radio | dropdown | tabs | range
				'label'      => '',
				'show_label' => 'true',      // false = ẩn tiêu đề
				'class'      => '',
				'expanded'   => 'true',
				'cat_ids'    => '',
			),
			$atts,
			'wc_filter'
		);

		$filter_id  = sanitize_html_class( $atts['id'] );
		$type       = sanitize_key( $atts['type'] );
		$ui         = sanitize_key( $atts['ui'] );
		$label      = sanitize_text_field( $atts['label'] );
		$extra_cls  = implode( ' ', array_map( 'sanitize_html_class', explode( ' ', trim( $atts['class'] ) ) ) );
		$expanded   = filter_var( $atts['expanded'], FILTER_VALIDATE_BOOLEAN );
		$show_label = filter_var( $atts['show_label'], FILTER_VALIDATE_BOOLEAN );

		// Parse comma-separated category/term IDs to restrict the filter scope.
		$cat_ids = array_values(
			array_filter(
				array_map( 'absint', explode( ',', $atts['cat_ids'] ) )
			)
		);

		// Validate type against allow-list (extensible via hook).
		$allowed_types = apply_filters( 'wcaf_allowed_filter_types', array( 'category', 'brand', 'price' ) );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return '';
		}

		// Fallback label.
		if ( '' === $label ) {
			$defaults = array(
				'category' => __( 'Danh mục', 'wc-ajax-filter' ),
				'brand'    => __( 'Thương hiệu', 'wc-ajax-filter' ),
				'price'    => __( 'Khoảng giá', 'wc-ajax-filter' ),
			);
			$label = isset( $defaults[ $type ] ) ? $defaults[ $type ] : ucfirst( $type );
		}

		ob_start();
		?>
		<div
			id="<?php echo esc_attr( $filter_id ); ?>"
			class="wcaf-filter wcaf-filter--<?php echo esc_attr( $type ); ?> wcaf-filter--ui-<?php echo esc_attr( $ui ); ?><?php echo $extra_cls ? ' ' . esc_attr( $extra_cls ) : ''; ?>"
			data-filter-id="<?php echo esc_attr( $filter_id ); ?>"
			data-filter-type="<?php echo esc_attr( $type ); ?>"
			data-filter-ui="<?php echo esc_attr( $ui ); ?>"
			data-cat-ids="<?php echo esc_attr( implode( ',', $cat_ids ) ); ?>"
			role="group"
			aria-label="<?php echo esc_attr( $label ); ?>"
		>
			<?php if ( $show_label ) : ?>
			<div class="wcaf-filter__header">
				<h4 class="wcaf-filter__title"><?php echo esc_html( $label ); ?></h4>
			</div>
			<?php endif; ?>

			<div class="wcaf-filter__body">
				<?php $this->render_body( $type, $ui, $filter_id, $cat_ids ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Body dispatch
	// -------------------------------------------------------------------------

	/**
	 * Route rendering to the correct method based on filter type.
	 *
	 * @param string $type      Filter type (category|brand|price).
	 * @param string $ui        UI style.
	 * @param string $filter_id Unique filter element ID.
	 */
	private function render_body( string $type, string $ui, string $filter_id, array $cat_ids = array() ): void {
		switch ( $type ) {
			case 'category':
				$this->render_taxonomy_filter( 'product_cat', $ui, $filter_id, 'category', $cat_ids );
				break;

			case 'brand':
				$this->render_taxonomy_filter( 'product_brand', $ui, $filter_id, 'brand' );
				break;

			case 'price':
				$this->render_price_filter( $ui, $filter_id );
				break;

			default:
				/**
				 * Fires for custom filter types added by third-party code.
				 *
				 * @param string $type      Filter type slug.
				 * @param string $ui        UI style.
				 * @param string $filter_id Unique element ID.
				 */
				do_action( 'wcaf_render_custom_filter', $type, $ui, $filter_id );
		}
	}

	// -------------------------------------------------------------------------
	// Taxonomy filters (category / brand)
	// -------------------------------------------------------------------------

	/**
	 * Fetch terms and delegate to the correct UI renderer.
	 *
	 * @param string $taxonomy  WP taxonomy slug.
	 * @param string $ui        UI style.
	 * @param string $filter_id Unique element ID.
	 * @param string $key       Filter data key (category|brand).
	 */
	private function render_taxonomy_filter( string $taxonomy, string $ui, string $filter_id, string $key, array $cat_ids = array() ): void {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			/* translators: %s: taxonomy slug */
			echo '<p class="wcaf-notice">' . sprintf( esc_html__( 'Taxonomy "%s" chưa được đăng ký.', 'wc-ajax-filter' ), esc_html( $taxonomy ) ) . '</p>';
			return;
		}

		$get_terms_args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		// Restrict to specified IDs and all their descendants.
		if ( ! empty( $cat_ids ) ) {
			$expanded_ids = $this->get_expanded_term_ids( $cat_ids, $taxonomy );
			if ( ! empty( $expanded_ids ) ) {
				$get_terms_args['include'] = $expanded_ids;
			}
		}

		$terms = get_terms( $get_terms_args );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<p class="wcaf-notice">' . esc_html__( 'Không có mục nào.', 'wc-ajax-filter' ) . '</p>';
			return;
		}

		switch ( $ui ) {
			case 'dropdown':
				$this->render_terms_dropdown( $terms, $key );
				break;
			case 'tabs':
				$this->render_terms_tabs( $terms, $key );
				break;
			case 'radio':
				$this->render_terms_inputs( $terms, $key, $filter_id, 'radio' );
				break;
			case 'checkbox':
			default:
				$this->render_terms_inputs( $terms, $key, $filter_id, 'checkbox' );
		}
	}

	/**
	 * Build a flat array of term IDs that includes the given IDs and all their descendants.
	 *
	 * @param int[]  $term_ids Root term IDs.
	 * @param string $taxonomy Taxonomy slug.
	 * @return int[]           Input IDs plus every descendant, deduplicated.
	 */
	private function get_expanded_term_ids( array $term_ids, string $taxonomy ): array {
		$all_ids = $term_ids;
		foreach ( $term_ids as $term_id ) {
			$children = get_term_children( $term_id, $taxonomy );
			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				$all_ids = array_merge( $all_ids, $children );
			}
		}
		return array_unique( array_values( array_filter( $all_ids ) ) );
	}

	/**
	 * Render a <select> dropdown for a taxonomy.
	 *
	 * @param WP_Term[] $terms     Array of term objects.
	 * @param string    $key       Filter data key.
	 */
	private function render_terms_dropdown( array $terms, string $key ): void {
		?>
		<div class="wcaf-filter__dropdown-wrap">
			<select
				class="wcaf-filter__select wcaf-filter__input"
				name="wcaf_filter[<?php echo esc_attr( $key ); ?>]"
				data-filter-key="<?php echo esc_attr( $key ); ?>"
				aria-label="<?php echo esc_attr( ucfirst( $key ) ); ?>"
			>
				<option value=""><?php esc_html_e( 'Tất cả', 'wc-ajax-filter' ); ?></option>
				<?php foreach ( $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->term_id ); ?>">
						<?php echo esc_html( $term->name ); ?> (<?php echo absint( $term->count ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Render pill tabs for a taxonomy (single-select).
	 *
	 * @param WP_Term[] $terms Array of term objects.
	 * @param string    $key   Filter data key.
	 */
	private function render_terms_tabs( array $terms, string $key ): void {
		?>
		<div class="wcaf-filter__tabs" role="tablist" aria-label="<?php echo esc_attr( ucfirst( $key ) ); ?>">
			<button
				type="button"
				class="wcaf-filter__tab is-active"
				data-filter-key="<?php echo esc_attr( $key ); ?>"
				data-value=""
				role="tab"
				aria-selected="true"
			><?php esc_html_e( 'Tất cả', 'wc-ajax-filter' ); ?></button>

			<?php foreach ( $terms as $term ) : ?>
				<button
					type="button"
					class="wcaf-filter__tab"
					data-filter-key="<?php echo esc_attr( $key ); ?>"
					data-value="<?php echo esc_attr( $term->term_id ); ?>"
					role="tab"
					aria-selected="false"
				><?php echo esc_html( $term->name ); ?></button>
			<?php endforeach; ?>

			<!-- Hidden input carries the selected value to the JS collector -->
			<input
				type="hidden"
				class="wcaf-filter__input wcaf-filter__tab-value"
				data-filter-key="<?php echo esc_attr( $key ); ?>"
				value=""
			>
		</div>
		<?php
	}

	/**
	 * Render checkbox or radio inputs for a taxonomy.
	 *
	 * @param WP_Term[] $terms      Array of term objects.
	 * @param string    $key        Filter data key.
	 * @param string    $filter_id  Parent filter element ID (used for HTML id attr).
	 * @param string    $input_type 'checkbox' or 'radio'.
	 */
	private function render_terms_inputs( array $terms, string $key, string $filter_id, string $input_type ): void {
		// Checkboxes use array notation; radios use scalar.
		$name = 'checkbox' === $input_type
			? "wcaf_filter[{$key}][]"
			: "wcaf_filter[{$key}]";
		?>
		<ul class="wcaf-filter__list wcaf-filter__list--<?php echo esc_attr( $input_type ); ?>">
			<?php foreach ( $terms as $term ) :
				$input_id = $filter_id . '-' . $input_type . '-' . absint( $term->term_id );
			?>
				<li class="wcaf-filter__item">
					<label class="wcaf-filter__label" for="<?php echo esc_attr( $input_id ); ?>">
						<input
							type="<?php echo esc_attr( $input_type ); ?>"
							id="<?php echo esc_attr( $input_id ); ?>"
							class="wcaf-filter__input"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $term->term_id ); ?>"
							data-filter-key="<?php echo esc_attr( $key ); ?>"
						>
						<span class="wcaf-filter__label-text"><?php echo esc_html( $term->name ); ?></span>
						<span class="wcaf-filter__count">(<?php echo absint( $term->count ); ?>)</span>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	// -------------------------------------------------------------------------
	// Price filter
	// -------------------------------------------------------------------------

	/**
	 * Render a price range filter.
	 *
	 * @param string $ui        UI style ('dropdown' or range slider).
	 * @param string $filter_id Unique element ID.
	 */
	private function render_price_filter( string $ui, string $filter_id ): void {
		if ( 'dropdown' === $ui ) {
			$this->render_price_dropdown( $filter_id );
		} else {
			$this->render_price_slider( $filter_id );
		}
	}

	/**
	 * Render a predefined price-range dropdown.
	 *
	 * @param string $filter_id Unique element ID.
	 */
	private function render_price_dropdown( string $filter_id ): void {
		/**
		 * Filters the predefined price range options shown in the dropdown UI.
		 *
		 * Each item: array( 'min' => float|'', 'max' => float|'', 'label' => string )
		 *
		 * @param array $ranges Price range options.
		 */
		$ranges = apply_filters(
			'wcaf_price_dropdown_ranges',
			array(
				array( 'min' => '',  'max' => '',   'label' => __( 'Tất cả mức giá', 'wc-ajax-filter' ) ),
				array( 'min' => 0,   'max' => 25,   'label' => __( 'Dưới $25', 'wc-ajax-filter' ) ),
				array( 'min' => 25,  'max' => 50,   'label' => '$25 – $50' ),
				array( 'min' => 50,  'max' => 100,  'label' => '$50 – $100' ),
				array( 'min' => 100, 'max' => 250,  'label' => '$100 – $250' ),
				array( 'min' => 250, 'max' => '',   'label' => __( 'Trên $250', 'wc-ajax-filter' ) ),
			)
		);
		?>
		<div class="wcaf-filter__price-dropdown">
			<select
				id="<?php echo esc_attr( $filter_id ); ?>-price-range"
				class="wcaf-filter__select wcaf-filter__price-range-select"
				data-filter-key="price_range"
				aria-label="<?php esc_attr_e( 'Price range', 'wc-ajax-filter' ); ?>"
			>
				<?php foreach ( $ranges as $range ) : ?>
					<option value="<?php echo esc_attr( $range['min'] . ':' . $range['max'] ); ?>">
						<?php echo esc_html( $range['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<!-- These hidden inputs carry the parsed values to the JS collector. -->
			<input type="hidden" class="wcaf-filter__input" data-filter-key="min_price" value="">
			<input type="hidden" class="wcaf-filter__input" data-filter-key="max_price" value="">
		</div>
		<?php
	}

	/**
	 * Render a dual-handle range slider for the price filter.
	 *
	 * The slider boundaries are read from the live product catalogue and cached
	 * in a transient to avoid a DB hit on every page load.
	 *
	 * @param string $filter_id Unique element ID.
	 */
	private function render_price_slider( string $filter_id ): void {
		$prices = $this->get_price_boundaries();

		$min_price = $prices['min'];
		$max_price = $prices['max'];

		$currency_symbol = get_woocommerce_currency_symbol();
		?>
		<div
			class="wcaf-filter__price-range"
			data-min="<?php echo esc_attr( $min_price ); ?>"
			data-max="<?php echo esc_attr( $max_price ); ?>"
		>
			<!-- Dual-handle slider track -->
			<div class="wcaf-filter__price-slider" aria-hidden="true">
				<div class="wcaf-filter__price-track">
					<div class="wcaf-filter__price-fill"></div>
				</div>
				<input
					type="range"
					class="wcaf-filter__range wcaf-filter__range--min"
					min="<?php echo esc_attr( $min_price ); ?>"
					max="<?php echo esc_attr( $max_price ); ?>"
					value="<?php echo esc_attr( $min_price ); ?>"
					step="1"
					tabindex="-1"
				>
				<input
					type="range"
					class="wcaf-filter__range wcaf-filter__range--max"
					min="<?php echo esc_attr( $min_price ); ?>"
					max="<?php echo esc_attr( $max_price ); ?>"
					value="<?php echo esc_attr( $max_price ); ?>"
					step="1"
					tabindex="-1"
				>
			</div>

			<!-- Accessible numeric inputs -->
			<div class="wcaf-filter__price-inputs">
				<div class="wcaf-filter__price-input-group">
					<label for="<?php echo esc_attr( $filter_id ); ?>-min-price" class="wcaf-filter__price-label">
						<?php esc_html_e( 'Tối thiểu', 'wc-ajax-filter' ); ?>
					</label>
					<span class="wcaf-filter__currency" aria-hidden="true"><?php echo esc_html( $currency_symbol ); ?></span>
					<input
						type="number"
						id="<?php echo esc_attr( $filter_id ); ?>-min-price"
						class="wcaf-filter__input wcaf-filter__price-min"
						data-filter-key="min_price"
						value="<?php echo esc_attr( $min_price ); ?>"
						min="<?php echo esc_attr( $min_price ); ?>"
						max="<?php echo esc_attr( $max_price ); ?>"
						aria-label="<?php esc_attr_e( 'Minimum price', 'wc-ajax-filter' ); ?>"
					>
				</div>

				<span class="wcaf-filter__price-separator" aria-hidden="true">&mdash;</span>

				<div class="wcaf-filter__price-input-group">
					<label for="<?php echo esc_attr( $filter_id ); ?>-max-price" class="wcaf-filter__price-label">
						<?php esc_html_e( 'Tối đa', 'wc-ajax-filter' ); ?>
					</label>
					<span class="wcaf-filter__currency" aria-hidden="true"><?php echo esc_html( $currency_symbol ); ?></span>
					<input
						type="number"
						id="<?php echo esc_attr( $filter_id ); ?>-max-price"
						class="wcaf-filter__input wcaf-filter__price-max"
						data-filter-key="max_price"
						value="<?php echo esc_attr( $max_price ); ?>"
						min="<?php echo esc_attr( $min_price ); ?>"
						max="<?php echo esc_attr( $max_price ); ?>"
						aria-label="<?php esc_attr_e( 'Maximum price', 'wc-ajax-filter' ); ?>"
					>
				</div>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Fetch min/max product prices from the DB, cached in a 30-minute transient.
	 *
	 * @return array { min: float, max: float }
	 */
	private function get_price_boundaries(): array {
		$cached = get_transient( 'wcaf_price_boundaries' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// Using a JOIN is faster than a subquery on large catalogues.
		// No user input — query is safe without prepare().
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT
				MIN( CAST( pm.meta_value AS DECIMAL(10,2) ) ) AS min_price,
				MAX( CAST( pm.meta_value AS DECIMAL(10,2) ) ) AS max_price
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = '_price'
			  AND p.post_type  = 'product'
			  AND p.post_status = 'publish'"
		);

		$result = array(
			'min' => $row ? (int) floor( (float) $row->min_price ) : 0,
			'max' => $row ? (int) ceil( (float) $row->max_price )  : 1000,
		);

		set_transient( 'wcaf_price_boundaries', $result, 30 * MINUTE_IN_SECONDS );

		return $result;
	}
}
