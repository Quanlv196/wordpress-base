<?php
/**
 * Registers and renders the [wc_product_list] shortcode and exposes static
 * helpers used by the AJAX handler to rebuild the grid and pagination HTML.
 *
 * Usage example:
 *   [wc_product_list id="list1" filter_ids="f1,f2" per_page="12" columns="4" tablet="2" mobile="1"]
 *
 * @package WC_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WCAF_Product_List_Shortcode
 */
class WCAF_Product_List_Shortcode {

	/**
	 * Singleton instance.
	 *
	 * @var WCAF_Product_List_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return WCAF_Product_List_Shortcode
	 */
	public static function get_instance(): WCAF_Product_List_Shortcode {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Private constructor — use get_instance(). */
	private function __construct() {
		add_shortcode( 'wc_product_list', array( $this, 'render' ) );
	}

	// -------------------------------------------------------------------------
	// Shortcode entry point
	// -------------------------------------------------------------------------

	/**
	 * Render the product list widget (toolbar + grid + pagination).
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string            HTML output.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'         => 'wcaf-list-' . wp_unique_id(),
				'filter_ids' => '',    // Comma-separated IDs of connected [wc_filter] shortcodes.
				'cat_ids'    => '',    // Comma-separated category IDs to restrict this list (and all AJAX refreshes) to.
				'per_page'   => 12,
				'columns'    => 4,
				'tablet'     => 2,
				'mobile'     => 1,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'class'      => '',
			),
			$atts,
			'wc_product_list'
		);

		// Sanitise all incoming values.
		$list_id    = sanitize_html_class( $atts['id'] );
		$filter_ids = array_values(
			array_filter(
				array_map( 'sanitize_html_class', array_map( 'trim', explode( ',', $atts['filter_ids'] ) ) )
			)
		);
		$cat_ids    = array_values(
			array_filter( array_map( 'absint', array_map( 'trim', explode( ',', $atts['cat_ids'] ) ) ) )
		);
		$per_page   = max( 1, min( 100, absint( $atts['per_page'] ) ) );
		$columns    = max( 1, min( 6,   absint( $atts['columns'] ) ) );
		$tablet     = max( 1, min( 4,   absint( $atts['tablet'] ) ) );
		$mobile     = max( 1, min( 2,   absint( $atts['mobile'] ) ) );
		$orderby    = sanitize_key( $atts['orderby'] );
		$order      = 'ASC' === strtoupper( sanitize_key( $atts['order'] ) ) ? 'ASC' : 'DESC';
		$extra_cls  = implode( ' ', array_map( 'sanitize_html_class', explode( ' ', trim( $atts['class'] ) ) ) );

		// Run the initial query — pass cat_ids so the initial page load is
		// already restricted to the same category subtree as the AJAX results.
		$list_args         = compact( 'per_page', 'orderby', 'order' );
		$list_args['page'] = 1;
		$initial_filters   = ! empty( $cat_ids ) ? array( 'cat_ids' => $cat_ids ) : array();
		$query_args        = WCAF_Query_Builder::build_args( $initial_filters, $list_args );
		$products          = new WP_Query( $query_args );

		$per_page_options = apply_filters( 'wcaf_per_page_options', array( 6, 12, 24, 48 ) );

		ob_start();
		?>
		<div
			id="<?php echo esc_attr( $list_id ); ?>"
			class="wcaf-product-list<?php echo $extra_cls ? ' ' . esc_attr( $extra_cls ) : ''; ?>"
			data-list-id="<?php echo esc_attr( $list_id ); ?>"
			data-filter-ids="<?php echo esc_attr( implode( ',', $filter_ids ) ); ?>"
			data-cat-ids="<?php echo esc_attr( implode( ',', $cat_ids ) ); ?>"
			data-per-page="<?php echo esc_attr( $per_page ); ?>"
			data-columns="<?php echo esc_attr( $columns ); ?>"
			data-tablet="<?php echo esc_attr( $tablet ); ?>"
			data-mobile="<?php echo esc_attr( $mobile ); ?>"
			data-orderby="<?php echo esc_attr( $orderby ); ?>"
			data-order="<?php echo esc_attr( $order ); ?>"
			data-page="1"
		>

			<!-- Toolbar: result count + per-page + sort -->
			<div class="wcaf-product-list__toolbar">
				<p class="wcaf-product-list__results-count">
					<?php
					printf(
						/* translators: %d: number of products found */
							esc_html( _n( 'Tìm thấy %d sản phẩm', 'Tìm thấy %d sản phẩm', $products->found_posts, 'wc-ajax-filter' ) ),
						absint( $products->found_posts )
					);
					?>
				</p>

				<div class="wcaf-product-list__toolbar-controls">
					<!-- Per-page selector -->
					<!-- <label for="<?php echo esc_attr( $list_id ); ?>-per-page" class="screen-reader-text">
						<?php esc_html_e( 'Sản phẩm mỗi trang', 'wc-ajax-filter' ); ?>
					</label>
					<select
						id="<?php echo esc_attr( $list_id ); ?>-per-page"
						class="wcaf-product-list__per-page"
					>
						<?php foreach ( $per_page_options as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>"<?php selected( $per_page, $opt ); ?>>
								<?php
								printf(
									/* translators: %d: items per page */
									esc_html__( '%d / trang', 'wc-ajax-filter' ),
									absint( $opt )
								);
								?>
							</option>
						<?php endforeach; ?>
					</select> -->

					<!-- Sort selector -->
					<label for="<?php echo esc_attr( $list_id ); ?>-orderby" class="screen-reader-text">
						<?php esc_html_e( 'Sắp xếp theo', 'wc-ajax-filter' ); ?>
					</label>
					<select
						id="<?php echo esc_attr( $list_id ); ?>-orderby"
						class="wcaf-product-list__orderby"
					>
						<?php
						$current_ob = "{$orderby}:{$order}";
						$sort_opts  = array(
					'date:DESC'       => __( 'Mới nhất', 'wc-ajax-filter' ),
					'price:ASC'       => __( 'Giá: Thấp → Cao', 'wc-ajax-filter' ),
					'price:DESC'      => __( 'Giá: Cao → Thấp', 'wc-ajax-filter' ),
					'popularity:DESC' => __( 'Phổ biến nhất', 'wc-ajax-filter' ),
					'rating:DESC'     => __( 'Đánh giá cao nhất', 'wc-ajax-filter' ),
					'title:ASC'       => __( 'Tên: A → Z', 'wc-ajax-filter' ),
						);
						foreach ( $sort_opts as $val => $label ) :
						?>
							<option value="<?php echo esc_attr( $val ); ?>"<?php selected( $current_ob, $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div><!-- /.toolbar -->

			<!-- Loading overlay -->
			<div class="wcaf-product-list__loading" aria-hidden="true" aria-live="polite">
				<div class="wcaf-spinner"></div>
				<span class="screen-reader-text"><?php esc_html_e( 'Đang tải...', 'wc-ajax-filter' ); ?></span>
			</div>

			<!-- Products grid (replaced on each AJAX response) -->
			<div class="wcaf-product-list__grid-wrap">
				<?php self::render_products_grid( $products, $columns, $tablet, $mobile ); ?>
			</div>

			<!-- Pagination (replaced on each AJAX response) -->
			<div class="wcaf-product-list__pagination">
				<?php self::render_pagination( 1, $products->max_num_pages ); ?>
			</div>

		</div><!-- /.wcaf-product-list -->
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Static renderers — called by both shortcode and AJAX handler
	// -------------------------------------------------------------------------

	/**
	 * Render the product grid using WooCommerce's native loop templates.
	 *
	 * Uses wc_get_template_part('content', 'product') for each item, which
	 * respects the active theme and all standard WooCommerce action hooks
	 * (woocommerce_before/after_shop_loop_item, etc.).
	 *
	 * This method powers both the initial page load and every subsequent AJAX
	 * refresh, so it is intentionally static.
	 *
	 * @param WP_Query $products Query whose loop will be rendered.
	 * @param int      $columns  Desktop column count.
	 * @param int      $tablet   Tablet column count.
	 * @param int      $mobile   Mobile column count.
	 */
	public static function render_products_grid( WP_Query $products, int $columns = 4, int $tablet = 2, int $mobile = 1 ): void {
		if ( ! $products->have_posts() ) {
			?>
			<div class="wcaf-no-products">
				<p><?php esc_html_e( 'Không tìm thấy sản phẩm phù hợp.', 'wc-ajax-filter' ); ?></p>
			</div>
			<?php
			return;
		}

		// Pass context into the WooCommerce loop so themes and plugins can read
		// column count and pagination data via wc_get_loop_prop().
		wc_set_loop_prop( 'columns',      $columns );
		wc_set_loop_prop( 'is_filtered',  true );
		wc_set_loop_prop( 'total',        $products->found_posts );
		wc_set_loop_prop( 'total_pages',  $products->max_num_pages );
		wc_set_loop_prop( 'per_page',     $products->get( 'posts_per_page' ) );
		wc_set_loop_prop( 'current_page', max( 1, (int) $products->get( 'paged' ) ) );

		// Output the <ul> manually so we can attach the WooCommerce columns class
		// AND our CSS custom properties for responsive breakpoint control, while
		// still letting the active theme style .products.columns-N as usual.
		printf(
			'<ul class="products columns-%d wcaf-product-list__grid" style="--wcaf-cols:%d;--wcaf-cols-tablet:%d;--wcaf-cols-mobile:%d;">',
			absint( $columns ),
			absint( $columns ),
			absint( $tablet ),
			absint( $mobile )
		);

		while ( $products->have_posts() ) {
			$products->the_post();
			// WooCommerce's own content-product.php template — honours theme/child-theme
			// overrides and fires all standard WooCommerce action hooks.
			wc_get_template_part( 'content', 'product' );
		}

		echo '</ul>';
		wp_reset_postdata();

		// Reset WooCommerce loop props so any subsequent shop loop on the same
		// request is not affected by our column/pagination overrides.
		wc_reset_loop();
	}

	/**
	 * Render AJAX-aware pagination buttons.
	 *
	 * @param int $current_page Current page number (1-based).
	 * @param int $total_pages  Total number of pages.
	 */
	public static function render_pagination( int $current_page, int $total_pages ): void {
		if ( $total_pages <= 1 ) {
			return;
		}
		?>
		<nav class="wcaf-pagination" aria-label="<?php esc_attr_e( 'Products navigation', 'wc-ajax-filter' ); ?>">

			<?php if ( $current_page > 1 ) : ?>
				<button
					type="button"
					class="wcaf-pagination__btn wcaf-pagination__btn--prev"
					data-page="<?php echo absint( $current_page - 1 ); ?>"
					aria-label="<?php esc_attr_e( 'Previous page', 'wc-ajax-filter' ); ?>"
				>&larr; <?php esc_html_e( 'Trước', 'wc-ajax-filter' ); ?></button>
			<?php endif; ?>

			<span class="wcaf-pagination__info">
				<?php
				printf(
					/* translators: 1: current page, 2: total pages */
					esc_html__( 'Page %1$s of %2$s', 'wc-ajax-filter' ),
					'<strong>' . absint( $current_page ) . '</strong>',
					'<strong>' . absint( $total_pages ) . '</strong>'
				);
				?>
			</span>

			<?php if ( $current_page < $total_pages ) : ?>
				<button
					type="button"
					class="wcaf-pagination__btn wcaf-pagination__btn--next"
					data-page="<?php echo absint( $current_page + 1 ); ?>"
					aria-label="<?php esc_attr_e( 'Next page', 'wc-ajax-filter' ); ?>"
				><?php esc_html_e( 'Tiếp theo', 'wc-ajax-filter' ); ?> &rarr;</button>
			<?php endif; ?>

		</nav>
		<?php
	}
}
