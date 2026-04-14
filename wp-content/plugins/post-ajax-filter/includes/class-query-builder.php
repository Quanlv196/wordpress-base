<?php
/**
 * Xây dựng tham số WP_Query từ dữ liệu bộ lọc đã được làm sạch.
 *
 * Tất cả phương thức đều là static để có thể dùng không cần khởi tạo
 * từ cả shortcode và AJAX handler.
 *
 * @package Post_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PAF_Query_Builder
 */
class PAF_Query_Builder {

	/**
	 * Xây dựng tham số WP_Query từ mảng filter và list-config đã làm sạch.
	 *
	 * Developers có thể chỉnh sửa tham số cuối qua hook `paf_query_args`:
	 *
	 *   add_filter( 'paf_query_args', function( $args, $filters, $list_args ) {
	 *       return $args;
	 *   }, 10, 3 );
	 *
	 * @param array $filters   Giá trị bộ lọc đã làm sạch (từ ::sanitize_filters()).
	 * @param array $list_args Cấu hình từ shortcode post-list (per_page, page, orderby, order).
	 * @return array           Tham số sẵn sàng truyền vào WP_Query.
	 */
	public static function build_args( array $filters, array $list_args = array() ): array {
		$per_page = isset( $list_args['per_page'] ) ? absint( $list_args['per_page'] ) : 9;
		$page     = isset( $list_args['page'] )     ? absint( $list_args['page'] )     : 1;

		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'tax_query'           => array( 'relation' => 'AND' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);

		// ---- Bộ lọc theo danh mục ---------------------------------------- //

		if ( ! empty( $filters['category'] ) ) {
			$term_ids = array_values( array_filter( array_map( 'absint', (array) $filters['category'] ) ) );
			if ( $term_ids ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'category',
					'field'    => 'term_id',
					'terms'    => $term_ids,
					'operator' => 'IN',
				);
			}
		}

		// ---- Bộ lọc theo thẻ ---------------------------------------------- //

		if ( ! empty( $filters['tag'] ) ) {
			$term_ids = array_values( array_filter( array_map( 'absint', (array) $filters['tag'] ) ) );
			if ( $term_ids ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'post_tag',
					'field'    => 'term_id',
					'terms'    => $term_ids,
					'operator' => 'IN',
				);
			}
		}

		// ---- Giới hạn theo cat_ids (danh mục cố định từ shortcode) --------- //

		if ( ! empty( $filters['cat_ids'] ) && empty( $filters['category'] ) ) {
			$base_ids = self::expand_term_ids(
				array_values( array_filter( array_map( 'absint', (array) $filters['cat_ids'] ) ) ),
				'category'
			);
			if ( ! empty( $base_ids ) ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'category',
					'field'    => 'term_id',
					'terms'    => $base_ids,
					'operator' => 'IN',
				);
			}
		}

		// ---- Giới hạn theo tag_ids (thẻ cố định từ shortcode) -------------- //

		if ( ! empty( $filters['tag_ids'] ) && empty( $filters['tag'] ) ) {
			$base_ids = array_values( array_filter( array_map( 'absint', (array) $filters['tag_ids'] ) ) );
			if ( ! empty( $base_ids ) ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'post_tag',
					'field'    => 'term_id',
					'terms'    => $base_ids,
					'operator' => 'IN',
				);
			}
		}

		// ---- Bộ lọc tìm kiếm từ khóa --------------------------------------- //

		if ( ! empty( $filters['search'] ) ) {
			$args['s'] = $filters['search'];
		}

		// Dọn dẹp tax_query nếu không có điều kiện nào.
		if ( count( $args['tax_query'] ) <= 1 ) {
			unset( $args['tax_query'] );
		}

		// ---- Sắp xếp ------------------------------------------------------- //

		$allowed_orderby = array( 'date', 'title', 'comment_count', 'modified', 'rand' );
		$orderby         = isset( $list_args['orderby'] ) ? sanitize_key( $list_args['orderby'] ) : 'date';

		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'date';
		}

		$order           = isset( $list_args['order'] ) && 'ASC' === strtoupper( $list_args['order'] ) ? 'ASC' : 'DESC';
		$args['orderby'] = $orderby;
		$args['order']   = $order;

		/**
		 * Lọc tham số WP_Query cuối cùng.
		 *
		 * @param array $args      Tham số WP_Query.
		 * @param array $filters   Dữ liệu bộ lọc đã làm sạch.
		 * @param array $list_args Cấu hình shortcode post-list.
		 */
		return apply_filters( 'paf_query_args', $args, $filters, $list_args );
	}

	/**
	 * Làm sạch dữ liệu thô nhận từ AJAX POST.
	 *
	 * @param array $raw Mảng chưa làm sạch (đã wp_unslash).
	 * @return array     Dữ liệu an toàn để dùng trong build_args().
	 */
	public static function sanitize_filters( array $raw ): array {
		$filters = array();

		if ( ! empty( $raw['category'] ) ) {
			$filters['category'] = array_values(
				array_filter( array_map( 'absint', (array) $raw['category'] ) )
			);
		}

		if ( ! empty( $raw['tag'] ) ) {
			$filters['tag'] = array_values(
				array_filter( array_map( 'absint', (array) $raw['tag'] ) )
			);
		}

		if ( ! empty( $raw['search'] ) ) {
			$filters['search'] = sanitize_text_field( $raw['search'] );
		}

		if ( ! empty( $raw['cat_ids'] ) ) {
			$filters['cat_ids'] = array_values(
				array_filter( array_map( 'absint', (array) $raw['cat_ids'] ) )
			);
		}

		if ( ! empty( $raw['tag_ids'] ) ) {
			$filters['tag_ids'] = array_values(
				array_filter( array_map( 'absint', (array) $raw['tag_ids'] ) )
			);
		}

		return $filters;
	}

	/**
	 * Tạo mảng phẳng gồm term IDs bao gồm ID đã cho và tất cả con cháu.
	 *
	 * @param int[]  $term_ids ID term gốc.
	 * @param string $taxonomy Taxonomy slug.
	 * @return int[]           ID đầu vào cộng mọi hậu duệ, đã loại trùng.
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
