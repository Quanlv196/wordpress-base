<?php
/**
 * AJAX handler cho các yêu cầu lọc bài viết.
 *
 * Xử lý action `paf_filter_posts` cho cả người dùng đã đăng nhập và khách.
 * Kết quả được cache bằng transient ngắn hạn và bị xóa khi có bài viết được lưu hoặc xóa.
 *
 * @package Post_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PAF_Ajax
 */
class PAF_Ajax {

	/**
	 * Thời gian sống của transient cache tính bằng giây (5 phút).
	 *
	 * @var int
	 */
	const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Instance singleton.
	 *
	 * @var PAF_Ajax|null
	 */
	private static $instance = null;

	/**
	 * Lấy hoặc tạo instance singleton.
	 *
	 * @return PAF_Ajax
	 */
	public static function get_instance(): PAF_Ajax {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Constructor riêng tư — dùng get_instance(). */
	private function __construct() {
		// Đăng ký AJAX action cho cả người dùng đã đăng nhập và khách.
		add_action( 'wp_ajax_paf_filter_posts',        array( $this, 'handle_filter' ) );
		add_action( 'wp_ajax_nopriv_paf_filter_posts', array( $this, 'handle_filter' ) );

		// Xóa transient cache khi có bài viết thay đổi.
		add_action( 'save_post',      array( $this, 'bust_cache' ) );
		add_action( 'trashed_post',   array( $this, 'bust_cache' ) );
		add_action( 'untrashed_post', array( $this, 'bust_cache' ) );
	}

	// -------------------------------------------------------------------------
	// Request handler
	// -------------------------------------------------------------------------

	/**
	 * Xử lý yêu cầu AJAX lọc bài viết và trả về JSON.
	 *
	 * Các trường POST cần thiết:
	 *   nonce    string
	 *   filters  array  { category:[], tag:[], search: '' }
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
		 * Buffer toàn bộ output từ điểm này để các notice debug hay echo lỗi
		 * từ plugin/theme bên thứ ba không làm hỏng JSON response.
		 */
		ob_start();

		// Bảo mật: xác thực nonce.
		if ( ! check_ajax_referer( 'paf_filter_nonce', 'nonce', false ) ) {
			ob_end_clean();
			wp_send_json_error(
				array( 'message' => __( 'Token bảo mật không hợp lệ. Vui lòng tải lại trang và thử lại.', 'post-ajax-filter' ) ),
				403
			);
		}

		// --- Làm sạch cấu hình danh sách ---------------------------------- //

		$per_page = isset( $_POST['per_page'] ) ? max( 1, min( 100, absint( $_POST['per_page'] ) ) ) : 9;
		$page     = isset( $_POST['page'] )     ? max( 1, absint( $_POST['page'] ) )                 : 1;

		$raw_orderby = isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : 'date';
		$raw_order   = isset( $_POST['order'] )   ? sanitize_key( wp_unslash( $_POST['order'] ) )   : 'DESC';
		$order       = 'ASC' === strtoupper( $raw_order ) ? 'ASC' : 'DESC';

		$columns = isset( $_POST['columns'] ) ? max( 1, min( 6, absint( $_POST['columns'] ) ) ) : 3;
		$tablet  = isset( $_POST['tablet'] )  ? max( 1, min( 4, absint( $_POST['tablet'] ) ) )  : 2;
		$mobile  = isset( $_POST['mobile'] )  ? max( 1, min( 2, absint( $_POST['mobile'] ) ) )  : 1;

		// --- Đọc cấu hình Flatsome card ---------------------------------- //

		$allowed_styles = array( '', 'overlay', 'shade', 'badge' );
		$style_raw      = isset( $_POST['style'] ) ? sanitize_key( wp_unslash( $_POST['style'] ) ) : '';
		$style          = in_array( $style_raw, $allowed_styles, true ) ? $style_raw : '';

		$show_date      = isset( $_POST['show_date'] )      ? sanitize_key( wp_unslash( $_POST['show_date'] ) )      : 'badge';
		$show_category  = isset( $_POST['show_category'] )  ? sanitize_key( wp_unslash( $_POST['show_category'] ) )  : 'false';
		$excerpt        = isset( $_POST['excerpt'] )        ? sanitize_key( wp_unslash( $_POST['excerpt'] ) )        : 'visible';
		$excerpt_length = isset( $_POST['excerpt_length'] ) ? max( 1, absint( $_POST['excerpt_length'] ) )           : 15;
		$image_height   = isset( $_POST['image_height'] )   ? preg_replace( '/[^0-9.%]/', '', sanitize_text_field( wp_unslash( $_POST['image_height'] ) ) ) : '56%';
		$image_size     = isset( $_POST['image_size'] )     ? sanitize_key( wp_unslash( $_POST['image_size'] ) )     : 'medium';
		$text_align_raw = isset( $_POST['text_align'] )     ? sanitize_key( wp_unslash( $_POST['text_align'] ) )     : 'center';
		$text_align     = in_array( $text_align_raw, array( 'left', 'center', 'right' ), true ) ? $text_align_raw : 'center';
		$readmore       = isset( $_POST['readmore'] )       ? sanitize_text_field( wp_unslash( $_POST['readmore'] ) )       : '';
		$readmore_style = isset( $_POST['readmore_style'] ) ? sanitize_key( wp_unslash( $_POST['readmore_style'] ) ) : 'outline';
		$readmore_size  = isset( $_POST['readmore_size'] )  ? sanitize_key( wp_unslash( $_POST['readmore_size'] ) )  : 'small';

		$config = compact(
			'columns', 'tablet', 'mobile',
			'style', 'show_date', 'show_category',
			'excerpt', 'excerpt_length',
			'image_height', 'image_size',
			'text_align', 'readmore', 'readmore_style', 'readmore_size'
		);

		// --- Làm sạch giá trị bộ lọc --------------------------------------- //

		$raw_filters = array();
		if ( isset( $_POST['filters'] ) && is_array( $_POST['filters'] ) ) {
			$raw_filters = $this->recursive_sanitize( wp_unslash( $_POST['filters'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$filters = PAF_Query_Builder::sanitize_filters( $raw_filters );

		// --- Tra cache ----------------------------------------------------- //

		$cache_key = 'paf_result_' . md5(
			wp_json_encode(
				compact( 'filters', 'per_page', 'page', 'raw_orderby', 'order', 'config' )
			)
		);

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			ob_end_clean();
			wp_send_json_success( $cached );
		}

		// --- Chạy query ---------------------------------------------------- //

		$list_args = array(
			'per_page' => $per_page,
			'page'     => $page,
			'orderby'  => $raw_orderby,
			'order'    => $order,
		);

		$query_args = PAF_Query_Builder::build_args( $filters, $list_args );
		$posts      = new WP_Query( $query_args );

		// --- Xây dựng HTML fragments --------------------------------------- //

		ob_start();
		PAF_Post_List_Shortcode::render_posts_grid( $posts, $config );
		$grid_html = ob_get_clean();

		ob_start();
		PAF_Post_List_Shortcode::render_pagination( $page, $posts->max_num_pages );
		$pagination_html = ob_get_clean();

		$found_posts = absint( $posts->found_posts );

		wp_reset_postdata();

		// --- Tạo response -------------------------------------------------- //

		$response = array(
			'html'         => $grid_html,
			'pagination'   => $pagination_html,
			'found_posts'  => $found_posts,
			'total_pages'  => absint( $posts->max_num_pages ),
			'current_page' => $page,
			'count_text'   => sprintf(
				/* translators: %d: số bài viết tìm thấy */
				_n( 'Tìm thấy %d bài viết', 'Tìm thấy %d bài viết', $found_posts, 'post-ajax-filter' ),
				$found_posts
			),
		);

		// Lưu cache.
		set_transient( $cache_key, $response, self::CACHE_TTL );

		ob_end_clean();
		wp_send_json_success( $response );
	}

	// -------------------------------------------------------------------------
	// Quản lý cache
	// -------------------------------------------------------------------------

	/**
	 * Xóa tất cả transient của plugin khi bài viết được lưu hoặc xóa.
	 *
	 * @param int $post_id ID bài viết thay đổi.
	 */
	public function bust_cache( int $post_id ): void {
		// Chỉ phản ứng với post type là 'post'.
		if ( 'post' !== get_post_type( $post_id ) ) {
			return;
		}

		// Xóa transient kết quả qua truy vấn DB trực tiếp với LIKE an toàn.
		global $wpdb;

		$prefix  = $wpdb->esc_like( '_transient_paf_result_' );
		$timeout = $wpdb->esc_like( '_transient_timeout_paf_result_' );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$prefix . '%',
				$timeout . '%'
			)
		);
	}

	// -------------------------------------------------------------------------
	// Tiện ích
	// -------------------------------------------------------------------------

	/**
	 * Làm sạch đệ quy mảng đầu vào: loại bỏ tag và cắt chuỗi.
	 *
	 * Không áp dụng absint ở đây — query builder sẽ xử lý kiểu dữ liệu tùy field.
	 *
	 * @param mixed $data Dữ liệu đầu vào.
	 * @return mixed      Dữ liệu đã làm sạch.
	 */
	private function recursive_sanitize( $data ) {
		if ( is_array( $data ) ) {
			return array_map( array( $this, 'recursive_sanitize' ), $data );
		}
		return sanitize_text_field( (string) $data );
	}
}
