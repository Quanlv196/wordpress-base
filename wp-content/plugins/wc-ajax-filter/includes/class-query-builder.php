<?php
/**
 * Builds WP_Query arguments from sanitised filter data.
 *
 * All public methods are static so the class can be used without instantiation
 * from both the shortcode renderer and the AJAX handler.
 *
 * @package WC_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WCAF_Query_Builder
 */
class WCAF_Query_Builder {

	/**
	 * Build WP_Query arguments from sanitised filter and list-config arrays.
	 *
	 * Developers can modify the final args via the `wcaf_query_args` filter:
	 *
	 *   add_filter( 'wcaf_query_args', function( $args, $filters, $list_args ) {
	 *       // modify $args...
	 *       return $args;
	 *   }, 10, 3 );
	 *
	 * @param array $filters   Sanitised filter values (from ::sanitize_filters()).
	 * @param array $list_args Config from the product-list shortcode (per_page, page, orderby, order).
	 * @return array           Arguments ready to pass to WP_Query.
	 */
	public static function build_args( array $filters, array $list_args = array() ): array {
		$per_page = isset( $list_args['per_page'] ) ? absint( $list_args['per_page'] ) : 12;
		$page     = isset( $list_args['page'] )     ? absint( $list_args['page'] )     : 1;

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'tax_query'      => array( 'relation' => 'AND' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'meta_query'     => array( 'relation' => 'AND' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		// ---- WooCommerce catalog visibility -------------------------------- //
		// Mirrors what WooCommerce does on its archive pages: exclude products
		// that are explicitly hidden from the catalog (the "Hidden" visibility
		// option), and optionally exclude products that are out of stock when
		// the corresponding WooCommerce setting is enabled.

		$visibility_term_ids = wc_get_product_visibility_term_ids();

		if ( ! empty( $visibility_term_ids['exclude-from-catalog'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array( $visibility_term_ids['exclude-from-catalog'] ),
				'operator' => 'NOT IN',
			);
		}

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' )
			&& ! empty( $visibility_term_ids['outofstock'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array( $visibility_term_ids['outofstock'] ),
				'operator' => 'NOT IN',
			);
		}

		// ---- Taxonomy filters ------------------------------------------- //

		if ( ! empty( $filters['category'] ) ) {
			$term_ids = array_values( array_filter( array_map( 'absint', (array) $filters['category'] ) ) );
			if ( $term_ids ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $term_ids,
					'operator' => 'IN',
				);
			}
		}

		if ( ! empty( $filters['brand'] ) ) {
			$term_ids = array_values( array_filter( array_map( 'absint', (array) $filters['brand'] ) ) );
			if ( $term_ids ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'product_brand',
					'field'    => 'term_id',
					'terms'    => $term_ids,
					'operator' => 'IN',
				);
			}
		}

		// ---- Cat IDs base constraint (restricts query to a category subtree) -- //

		if ( ! empty( $filters['cat_ids'] ) && empty( $filters['category'] ) ) {
			// No specific category selected by the user — restrict to the entire subtree.
			$base_ids = self::expand_term_ids(
				array_values( array_filter( array_map( 'absint', (array) $filters['cat_ids'] ) ) ),
				'product_cat'
			);
			if ( ! empty( $base_ids ) ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $base_ids,
					'operator' => 'IN',
				);
			}
		}

		// ---- Price filter ------------------------------------------------ //

		$min_price = isset( $filters['min_price'] ) && '' !== $filters['min_price']
			? floatval( $filters['min_price'] )
			: null;

		$max_price = isset( $filters['max_price'] ) && '' !== $filters['max_price']
			? floatval( $filters['max_price'] )
			: null;

		if ( null !== $min_price || null !== $max_price ) {
			$price_clause = array(
				'key'  => '_price',
				'type' => 'NUMERIC',
			);

			if ( null !== $min_price && null !== $max_price ) {
				$price_clause['value']   = array( $min_price, $max_price );
				$price_clause['compare'] = 'BETWEEN';
			} elseif ( null !== $min_price ) {
				$price_clause['value']   = $min_price;
				$price_clause['compare'] = '>=';
			} else {
				$price_clause['value']   = $max_price;
				$price_clause['compare'] = '<=';
			}

			$args['meta_query'][] = $price_clause;
		}

		// ---- Ordering ---------------------------------------------------- //

		$allowed_orderby = array( 'date', 'title', 'price', 'popularity', 'rating', 'rand' );
		$orderby         = isset( $list_args['orderby'] ) ? sanitize_key( $list_args['orderby'] ) : 'date';

		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'date';
		}

		$order = isset( $list_args['order'] ) && 'ASC' === strtoupper( $list_args['order'] ) ? 'ASC' : 'DESC';

		switch ( $orderby ) {
			case 'price':
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['orderby']  = 'meta_value_num';
				break;
			case 'popularity':
				$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['orderby']  = 'meta_value_num';
				break;
			case 'rating':
				$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['orderby']  = 'meta_value_num';
				break;
			default:
				$args['orderby'] = $orderby;
		}

		$args['order'] = $order;

		/**
		 * Filters the final WP_Query arguments.
		 *
		 * @param array $args      WP_Query args.
		 * @param array $filters   Sanitised filter data.
		 * @param array $list_args Product-list shortcode config.
		 */
		return apply_filters( 'wcaf_query_args', $args, $filters, $list_args );
	}

	/**
	 * Sanitise raw filter data received from AJAX POST.
	 *
	 * @param array $raw Unsanitised array (already wp_unslash'd).
	 * @return array     Sanitised data safe to use in build_args().
	 */
	public static function sanitize_filters( array $raw ): array {
		$filters = array();

		if ( ! empty( $raw['category'] ) ) {
			$filters['category'] = array_values(
				array_filter( array_map( 'absint', (array) $raw['category'] ) )
			);
		}

		if ( ! empty( $raw['brand'] ) ) {
			$filters['brand'] = array_values(
				array_filter( array_map( 'absint', (array) $raw['brand'] ) )
			);
		}

		if ( isset( $raw['min_price'] ) && '' !== $raw['min_price'] ) {
			$filters['min_price'] = max( 0.0, (float) $raw['min_price'] );
		}

		if ( isset( $raw['max_price'] ) && '' !== $raw['max_price'] ) {
			$filters['max_price'] = max( 0.0, (float) $raw['max_price'] );
		}

		if ( ! empty( $raw['cat_ids'] ) ) {
			$filters['cat_ids'] = array_values(
				array_filter( array_map( 'absint', (array) $raw['cat_ids'] ) )
			);
		}

		return $filters;
	}

	/**
	 * Build a flat array of term IDs that includes the given IDs and all their descendants.
	 *
	 * @param int[]  $term_ids Root term IDs.
	 * @param string $taxonomy Taxonomy slug.
	 * @return int[]           Input IDs plus every descendant, deduplicated.
	 */
	private static function expand_term_ids( array $term_ids, string $taxonomy ): array {
		$all_ids = $term_ids;
		foreach ( $term_ids as $term_id ) {
			$children = get_term_children( $term_id, $taxonomy );
			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				$all_ids = array_merge( $all_ids, $children );
			}
		}
		return array_unique( array_values( array_filter( $all_ids ) ) );
	}
}
