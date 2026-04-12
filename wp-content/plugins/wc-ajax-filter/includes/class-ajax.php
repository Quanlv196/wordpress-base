<?php
/**
 * AJAX handler for product filtering requests.
 *
 * Handles the `wcaf_filter_products` action for both logged-in and
 * logged-out users.  Results are cached in short-lived transients and
 * invalidated whenever a product is saved or trashed.
 *
 * @package WC_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WCAF_Ajax
 */
class WCAF_Ajax {

	/**
	 * Transient cache lifetime in seconds (5 minutes).
	 *
	 * @var int
	 */
	const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Singleton instance.
	 *
	 * @var WCAF_Ajax|null
	 */
	private static $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return WCAF_Ajax
	 */
	public static function get_instance(): WCAF_Ajax {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Private constructor — use get_instance(). */
	private function __construct() {
		// Register the AJAX action for both authenticated and guest users.
		add_action( 'wp_ajax_wcaf_filter_products',        array( $this, 'handle_filter' ) );
		add_action( 'wp_ajax_nopriv_wcaf_filter_products', array( $this, 'handle_filter' ) );

		// Bust transient cache when any product changes.
		add_action( 'save_post_product', array( $this, 'bust_cache' ) );
		add_action( 'trashed_post',      array( $this, 'bust_cache' ) );
		add_action( 'untrashed_post',    array( $this, 'bust_cache' ) );
	}

	// -------------------------------------------------------------------------
	// Request handler
	// -------------------------------------------------------------------------

	/**
	 * Process the AJAX filter request and return JSON.
	 *
	 * Expected POST fields:
	 *   nonce    string
	 *   filters  array  { category:[], brand:[], min_price, max_price }
	 *   per_page int
	 *   page     int
	 *   orderby  string
	 *   order    string
	 *   columns  int
	 *   tablet   int
	 *   mobile   int
	 */
	public function handle_filter(): void {
		/*
		 * Buffer all output from this point so that any WP_DEBUG notices or stray
		 * echo statements from third-party plugins/themes cannot corrupt the JSON
		 * response. We ob_end_clean() before every wp_send_json_* call.
		 */
		ob_start();

		// Security: verify the nonce. False = do NOT die automatically.
		if ( ! check_ajax_referer( 'wcaf_filter_nonce', 'nonce', false ) ) {
			ob_end_clean();
			wp_send_json_error(
				array( 'message' => __( 'Token bảo mật không hợp lệ. Vui lòng tải lại trang và thử lại.', 'wc-ajax-filter' ) ),
				403
			);
		}

		// --- Sanitise list configuration ---------------------------------- //

		$per_page = isset( $_POST['per_page'] ) ? max( 1, min( 100, absint( $_POST['per_page'] ) ) ) : 12;
		$page     = isset( $_POST['page'] )     ? max( 1, absint( $_POST['page'] ) )                 : 1;

		$raw_orderby = isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : 'date';
		$raw_order   = isset( $_POST['order'] )   ? sanitize_key( wp_unslash( $_POST['order'] ) )   : 'DESC';
		$order       = 'ASC' === strtoupper( $raw_order ) ? 'ASC' : 'DESC';

		$columns = isset( $_POST['columns'] ) ? max( 1, min( 6, absint( $_POST['columns'] ) ) ) : 4;
		$tablet  = isset( $_POST['tablet'] )  ? max( 1, min( 4, absint( $_POST['tablet'] ) ) )  : 2;
		$mobile  = isset( $_POST['mobile'] )  ? max( 1, min( 2, absint( $_POST['mobile'] ) ) )  : 1;

		// --- Flatsome / display options ------------------------------------ //
		$show_title        = ! isset( $_POST['show_title'] )        || (bool) absint( $_POST['show_title'] );
		$show_price        = ! isset( $_POST['show_price'] )        || (bool) absint( $_POST['show_price'] );
		$show_rating       = ! isset( $_POST['show_rating'] )       || (bool) absint( $_POST['show_rating'] );
		$show_add_to_cart  = ! isset( $_POST['show_add_to_cart'] )  || (bool) absint( $_POST['show_add_to_cart'] );
		$show_second_image = ! isset( $_POST['show_second_image'] ) || (bool) absint( $_POST['show_second_image'] );
		$show_view_detail  = ! isset( $_POST['show_view_detail'] )  || (bool) absint( $_POST['show_view_detail'] );
		$view_detail_label = isset( $_POST['view_detail_label'] ) ? sanitize_text_field( wp_unslash( $_POST['view_detail_label'] ) ) : '';
		$image_size        = isset( $_POST['image_size'] ) ? sanitize_key( wp_unslash( $_POST['image_size'] ) ) : 'woocommerce_thumbnail';
		$image_size        = $image_size ?: 'woocommerce_thumbnail';
		$style             = isset( $_POST['style'] )      ? sanitize_key( wp_unslash( $_POST['style'] ) )      : '';
		$text_align_raw    = isset( $_POST['text_align'] ) ? sanitize_key( wp_unslash( $_POST['text_align'] ) ) : '';
		$text_align        = in_array( $text_align_raw, array( 'left', 'center', 'right' ), true ) ? $text_align_raw : '';

		// --- Sanitise filter values --------------------------------------- //

		$raw_filters = array();
		if ( isset( $_POST['filters'] ) && is_array( $_POST['filters'] ) ) {
			// wp_unslash handles magic-quote escaping; recursive_sanitize handles values.
			$raw_filters = $this->recursive_sanitize( wp_unslash( $_POST['filters'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$filters = WCAF_Query_Builder::sanitize_filters( $raw_filters );

		// --- Cache lookup ------------------------------------------------- //

		$cache_key = 'wcaf_result_' . md5(
			wp_json_encode(
				compact( 'filters', 'per_page', 'page', 'raw_orderby', 'order', 'columns', 'tablet', 'mobile', 'show_title', 'show_price', 'show_rating', 'show_add_to_cart', 'show_second_image', 'show_view_detail', 'view_detail_label', 'image_size', 'style', 'text_align' )
			)
		);

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			ob_end_clean();
			wp_send_json_success( $cached );
		}

		// --- Run query ---------------------------------------------------- //

		$list_args = array(
			'per_page' => $per_page,
			'page'     => $page,
			'orderby'  => $raw_orderby,
			'order'    => $order,
		);

		$query_args = WCAF_Query_Builder::build_args( $filters, $list_args );
		$products   = new WP_Query( $query_args );

		// --- Build HTML fragments ----------------------------------------- //

		ob_start();
		WCAF_Product_List_Shortcode::render_products_grid( $products, $columns, $tablet, $mobile, compact( 'show_title', 'show_price', 'show_rating', 'show_add_to_cart', 'show_second_image', 'show_view_detail', 'view_detail_label', 'image_size', 'style', 'text_align' ) );
		$grid_html = ob_get_clean();

		ob_start();
		WCAF_Product_List_Shortcode::render_pagination( $page, $products->max_num_pages );
		$pagination_html = ob_get_clean();

		$found_posts = absint( $products->found_posts );

		wp_reset_postdata();

		// --- Compose response --------------------------------------------- //

		$response = array(
			'html'         => $grid_html,
			'pagination'   => $pagination_html,
			'found_posts'  => $found_posts,
			'total_pages'  => absint( $products->max_num_pages ),
			'current_page' => $page,
			'count_text'   => sprintf(
				/* translators: %d: number of products found */
				_n( 'Tìm thấy %d sản phẩm', 'Tìm thấy %d sản phẩm', $found_posts, 'wc-ajax-filter' ),
				$found_posts
			),
		);

		// Cache response.
		set_transient( $cache_key, $response, self::CACHE_TTL );

		ob_end_clean();
		wp_send_json_success( $response );
	}

	// -------------------------------------------------------------------------
	// Cache management
	// -------------------------------------------------------------------------

	/**
	 * Delete all plugin transients when a product post is saved or trashed.
	 *
	 * WordPress does not provide a transient-group API, so we store cache keys
	 * in an option and delete each one individually.
	 *
	 * @param int $post_id Changed post ID.
	 */
	public function bust_cache( int $post_id ): void {
		// Only react to product post type.
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		// Bust the price-boundaries cache used by the filter slider.
		delete_transient( 'wcaf_price_boundaries' );

		// Delete result transients via a direct DB LIKE query — safe because
		// the key prefix is a hard-coded string, not user input.
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_wcaf_result_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_wcaf_result_' ) . '%'
			)
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Recursively sanitize an arbitrary value (scalar or nested array).
	 *
	 * @param mixed $value Input value.
	 * @return mixed       Sanitised value.
	 */
	private function recursive_sanitize( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'recursive_sanitize' ), $value );
		}
		return sanitize_text_field( (string) $value );
	}
}
