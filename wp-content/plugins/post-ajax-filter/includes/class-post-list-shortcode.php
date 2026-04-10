<?php
/**
 * Đăng ký và render shortcode [post_list] và cung cấp các helper tĩnh
 * được dùng bởi AJAX handler để rebuild HTML danh sách bài viết và phân trang.
 *
 * Ví dụ sử dụng:
 *   [post_list id="list1" filter_ids="f1,f2" per_page="9" columns="3" tablet="2" mobile="1"]
 *
 * @package Post_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PAF_Post_List_Shortcode
 */
class PAF_Post_List_Shortcode {

	/**
	 * Instance singleton.
	 *
	 * @var PAF_Post_List_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Lấy hoặc tạo instance singleton.
	 *
	 * @return PAF_Post_List_Shortcode
	 */
	public static function get_instance(): PAF_Post_List_Shortcode {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Constructor riêng tư — dùng get_instance(). */
	private function __construct() {
		add_shortcode( 'post_list', array( $this, 'render' ) );
	}

	// -------------------------------------------------------------------------
	// Điểm vào shortcode
	// -------------------------------------------------------------------------

	/**
	 * Render widget danh sách bài viết (toolbar + lưới + phân trang).
	 *
	 * @param array|string $atts Thuộc tính shortcode.
	 * @return string            HTML đầu ra.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'             => 'paf-list-' . wp_unique_id(),
				'filter_ids'     => '',       // ID các [post_filter] liên kết, phân cách bởi dấu phẩy.
				'cat_ids'        => '',       // Giới hạn danh mục (phân cách bởi dấu phẩy).
				'tag_ids'        => '',       // Giới hạn thẻ (phân cách bởi dấu phẩy).
				'per_page'       => 9,
				'columns'        => 3,
				'tablet'         => 2,
				'mobile'         => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'class'          => '',
				// --- Flatsome blog_posts style options ---
				'style'          => '',       // '' | overlay | shade | badge
				'show_date'      => 'badge',  // badge | text | false
				'show_category'  => 'false',  // false | true | label
				'excerpt'        => 'visible',// visible | false
				'excerpt_length' => 15,
				'image_height'   => '56%',    // padding-top dùng cho tỉ lệ ảnh
				'image_size'     => 'medium', // thumbnail | medium | large | full
				'text_align'     => 'center', // left | center | right
				'readmore'       => '',       // Văn bản nút readmore, rỗng = ẩn nút
				'readmore_style' => 'outline',
				'readmore_size'  => 'small',
			),
			$atts,
			'post_list'
		);

		// Làm sạch tất cả giá trị đầu vào.
		$list_id    = sanitize_html_class( $atts['id'] );
		$filter_ids = array_values(
			array_filter(
				array_map( 'sanitize_html_class', array_map( 'trim', explode( ',', $atts['filter_ids'] ) ) )
			)
		);
		$cat_ids    = array_values(
			array_filter( array_map( 'absint', array_map( 'trim', explode( ',', $atts['cat_ids'] ) ) ) )
		);
		$tag_ids    = array_values(
			array_filter( array_map( 'absint', array_map( 'trim', explode( ',', $atts['tag_ids'] ) ) ) )
		);
		$per_page   = max( 1, min( 100, absint( $atts['per_page'] ) ) );
		$columns    = max( 1, min( 6, absint( $atts['columns'] ) ) );
		$tablet     = max( 1, min( 4, absint( $atts['tablet'] ) ) );
		$mobile     = max( 1, min( 2, absint( $atts['mobile'] ) ) );
		$orderby    = sanitize_key( $atts['orderby'] );
		$order      = 'ASC' === strtoupper( sanitize_key( $atts['order'] ) ) ? 'ASC' : 'DESC';
		$extra_cls  = implode( ' ', array_map( 'sanitize_html_class', explode( ' ', trim( $atts['class'] ) ) ) );

		// Cấu hình Flatsome card.
		$allowed_styles = array( '', 'overlay', 'shade', 'badge' );
		$style          = in_array( $atts['style'], $allowed_styles, true ) ? $atts['style'] : '';
		$show_date      = sanitize_key( $atts['show_date'] );
		$show_category  = sanitize_key( $atts['show_category'] );
		$excerpt        = sanitize_key( $atts['excerpt'] );
		$excerpt_length = max( 1, absint( $atts['excerpt_length'] ) );
		$image_height   = preg_replace( '/[^0-9.%]/', '', $atts['image_height'] );
		$image_size     = sanitize_key( $atts['image_size'] );
		$text_align     = in_array( $atts['text_align'], array( 'left', 'center', 'right' ), true ) ? $atts['text_align'] : 'center';
		$readmore       = sanitize_text_field( $atts['readmore'] );
		$readmore_style = sanitize_key( $atts['readmore_style'] );
		$readmore_size  = sanitize_key( $atts['readmore_size'] );

		$config = compact(
			'columns', 'tablet', 'mobile',
			'style', 'show_date', 'show_category',
			'excerpt', 'excerpt_length',
			'image_height', 'image_size',
			'text_align', 'readmore', 'readmore_style', 'readmore_size'
		);

		// Chạy query ban đầu — truyền cat_ids/tag_ids để lần load đầu đã giới hạn đúng.
		$list_args         = compact( 'per_page', 'orderby', 'order' );
		$list_args['page'] = 1;
		$initial_filters   = array();
		if ( ! empty( $cat_ids ) ) {
			$initial_filters['cat_ids'] = $cat_ids;
		}
		if ( ! empty( $tag_ids ) ) {
			$initial_filters['tag_ids'] = $tag_ids;
		}

		$query_args = PAF_Query_Builder::build_args( $initial_filters, $list_args );
		$posts      = new WP_Query( $query_args );

		ob_start();
		?>
		<div
			id="<?php echo esc_attr( $list_id ); ?>"
			class="paf-post-list<?php echo $extra_cls ? ' ' . esc_attr( $extra_cls ) : ''; ?>"
			data-list-id="<?php echo esc_attr( $list_id ); ?>"
			data-filter-ids="<?php echo esc_attr( implode( ',', $filter_ids ) ); ?>"
			data-cat-ids="<?php echo esc_attr( implode( ',', $cat_ids ) ); ?>"
			data-tag-ids="<?php echo esc_attr( implode( ',', $tag_ids ) ); ?>"
			data-per-page="<?php echo esc_attr( $per_page ); ?>"
			data-columns="<?php echo esc_attr( $columns ); ?>"
			data-tablet="<?php echo esc_attr( $tablet ); ?>"
			data-mobile="<?php echo esc_attr( $mobile ); ?>"
			data-orderby="<?php echo esc_attr( $orderby ); ?>"
			data-order="<?php echo esc_attr( $order ); ?>"
			data-page="1"
			data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
		>
			<!-- Toolbar: số kết quả + sắp xếp -->
			<div class="paf-post-list__toolbar">
				<p class="paf-post-list__results-count">
					<?php
					printf(
						/* translators: %d: số bài viết tìm thấy */
						esc_html( _n( 'Tìm thấy %d bài viết', 'Tìm thấy %d bài viết', $posts->found_posts, 'post-ajax-filter' ) ),
						absint( $posts->found_posts )
					);
					?>
				</p>

				<div class="paf-post-list__toolbar-controls">
					<label for="<?php echo esc_attr( $list_id ); ?>-orderby" class="screen-reader-text">
						<?php esc_html_e( 'Sắp xếp theo', 'post-ajax-filter' ); ?>
					</label>
					<select
						id="<?php echo esc_attr( $list_id ); ?>-orderby"
						class="paf-post-list__orderby"
					>
						<?php
						$current_ob = "{$orderby}:{$order}";
						$sort_opts  = array(
							'date:DESC'          => __( 'Mới nhất', 'post-ajax-filter' ),
							'date:ASC'           => __( 'Cũ nhất', 'post-ajax-filter' ),
							'title:ASC'          => __( 'Tên: A → Z', 'post-ajax-filter' ),
							'title:DESC'         => __( 'Tên: Z → A', 'post-ajax-filter' ),
							'comment_count:DESC' => __( 'Bình luận nhiều nhất', 'post-ajax-filter' ),
							'modified:DESC'      => __( 'Cập nhật gần nhất', 'post-ajax-filter' ),
						);
						foreach ( $sort_opts as $val => $sort_label ) :
						?>
							<option value="<?php echo esc_attr( $val ); ?>"<?php selected( $current_ob, $val ); ?>>
								<?php echo esc_html( $sort_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div><!-- /.toolbar -->

			<!-- Loading overlay -->
			<div class="paf-post-list__loading" aria-hidden="true" aria-live="polite">
				<div class="paf-spinner"></div>
				<span class="screen-reader-text"><?php esc_html_e( 'Đang tải...', 'post-ajax-filter' ); ?></span>
			</div>

			<!-- Lưới bài viết (được thay thế mỗi lần AJAX phản hồi) -->
			<div class="paf-post-list__grid-wrap">
				<?php self::render_posts_grid( $posts, $config ); ?>
			</div>

			<!-- Phân trang (được thay thế mỗi lần AJAX phản hồi) -->
			<div class="paf-post-list__pagination">
				<?php self::render_pagination( 1, $posts->max_num_pages ); ?>
			</div>

		</div><!-- /.paf-post-list -->
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Static renderers — dùng bởi cả shortcode và AJAX handler
	// -------------------------------------------------------------------------

	/**
	 * Render lưới bài viết dùng cấu trúc Flatsome row/col + box-blog-post.
	 *
	 * Phương thức này phục vụ cả lần load đầu và mỗi lần refresh AJAX,
	 * do đó được khai báo là static.
	 *
	 * @param WP_Query $posts  Query có vòng lặp sẽ được render.
	 * @param array    $config Cấu hình hiển thị (columns, style, show_date, v.v.).
	 */
	public static function render_posts_grid( WP_Query $posts, array $config = array() ): void {
		$columns = isset( $config['columns'] ) ? absint( $config['columns'] ) : 3;
		$tablet  = isset( $config['tablet'] )  ? absint( $config['tablet'] )  : 2;
		$mobile  = isset( $config['mobile'] )  ? absint( $config['mobile'] )  : 1;

		if ( ! $posts->have_posts() ) {
			echo '<div class="paf-no-posts"><p>' . esc_html__( 'Không tìm thấy bài viết phù hợp.', 'post-ajax-filter' ) . '</p></div>';
			return;
		}

		printf(
			'<div class="row large-columns-%d medium-columns-%d small-columns-%d">',
			$columns,
			$tablet,
			$mobile
		);

		while ( $posts->have_posts() ) {
			$posts->the_post();
			self::render_post_card( get_post(), $config );
		}

		echo '</div>';
		wp_reset_postdata();
	}

	/**
	 * Render card bài viết theo cấu trúc HTML của Flatsome blog_posts shortcode.
	 *
	 * @param WP_Post $post   Đối tượng bài viết hiện tại.
	 * @param array   $config Cấu hình hiển thị.
	 */
	private static function render_post_card( WP_Post $post, array $config = array() ): void {
		$style          = $config['style']          ?? '';
		$show_date      = $config['show_date']      ?? 'badge';
		$show_category  = $config['show_category']  ?? 'false';
		$excerpt_vis    = $config['excerpt']         ?? 'visible';
		$excerpt_length = isset( $config['excerpt_length'] ) ? absint( $config['excerpt_length'] ) : 15;
		$image_height   = $config['image_height']   ?? '56%';
		$image_size     = $config['image_size']     ?? 'medium';
		$text_align     = $config['text_align']     ?? 'center';
		$readmore       = $config['readmore']        ?? '';
		$readmore_style = $config['readmore_style']  ?? 'outline';
		$readmore_size  = $config['readmore_size']   ?? 'small';

		$permalink = get_permalink( $post );
		$title     = get_the_title( $post );

		// Xây dựng class cho box.
		$box_classes = array( 'box', 'box-blog-post', 'has-hover' );
		if ( $style ) {
			$box_classes[] = 'box-' . sanitize_html_class( $style );
		}
		if ( in_array( $style, array( 'overlay', 'shade' ), true ) ) {
			$box_classes[] = 'dark';
		}
		if ( 'badge' === $style ) {
			$box_classes[] = 'hover-dark';
		}

		$image_overlay = ( 'overlay' === $style ) ? 'rgba(0,0,0,.25)' : '';
		?>
		<div class="col post-service-item post-item">
			<div class="col-inner">
				<a href="<?php echo esc_url( $permalink ); ?>" class="plain">
					<div class="<?php echo esc_attr( implode( ' ', $box_classes ) ); ?>">

						<?php if ( has_post_thumbnail( $post ) ) : ?>
							<div class="box-image image-zoom">
								<div class="image-cover" style="padding-top:<?php echo esc_attr( $image_height ); ?>">
									<?php echo get_the_post_thumbnail( $post, $image_size ); ?>
									<?php if ( $image_overlay ) : ?>
										<div class="overlay" style="background-color:<?php echo esc_attr( $image_overlay ); ?>;"></div>
									<?php endif; ?>
									<?php if ( 'shade' === $style ) : ?>
										<div class="shade"></div>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>

						<div class="box-text text-<?php echo esc_attr( $text_align ); ?>">
							<div class="box-text-inner blog-post-inner">

								<?php
								do_action( 'flatsome_blog_post_before' );
								if ( 'false' !== $show_category ) :
									$cats = get_the_category( $post->ID );
									if ( ! empty( $cats ) ) :
									?>
										<p class="cat-label <?php echo 'label' === $show_category ? 'tag-label' : ''; ?> is-xxsmall op-7 uppercase">
											<?php foreach ( $cats as $cat ) : ?>
												<?php echo esc_html( $cat->name ) . ' '; ?>
											<?php endforeach; ?>
										</p>
									<?php
									endif;
								endif;
								?>

								<h5 class="post-title is-large"><?php echo esc_html( $title ); ?></h5>

								<?php if ( ( ! has_post_thumbnail( $post ) && 'false' !== $show_date ) || 'text' === $show_date ) : ?>
									<div class="post-meta is-small op-8"><?php echo esc_html( get_the_date( '', $post ) ); ?></div>
								<?php endif; ?>

								<div class="is-divider"></div>

								<?php if ( 'false' !== $excerpt_vis ) :
									$the_excerpt = get_the_excerpt( $post );
									if ( $the_excerpt ) :
										$words = explode( ' ', $the_excerpt );
										if ( count( $words ) > $excerpt_length ) {
											$the_excerpt = implode( ' ', array_slice( $words, 0, $excerpt_length ) ) . '…';
										}
										?>
										<p class="from_the_blog_excerpt"><?php echo esc_html( $the_excerpt ); ?></p>
									<?php
									endif;
								endif; ?>

								<?php if ( $readmore ) : ?>
									<span class="button is-<?php echo esc_attr( $readmore_style ); ?> is-<?php echo esc_attr( $readmore_size ); ?> mb-0">
										<?php echo esc_html( $readmore ); ?>
									</span>
								<?php endif; ?>

								<?php do_action( 'flatsome_blog_post_after' ); ?>

							</div>
						</div>

						<?php if ( has_post_thumbnail( $post ) && in_array( $show_date, array( 'badge', 'true' ), true ) ) : ?>
							<div class="badge absolute top post-date badge-outline">
								<div class="badge-inner">
									<span class="post-date-day"><?php echo esc_html( get_the_time( 'd', $post ) ); ?></span><br>
									<span class="post-date-month is-xsmall"><?php echo esc_html( get_the_time( 'M', $post ) ); ?></span>
								</div>
							</div>
						<?php endif; ?>

					</div>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render các nút phân trang nhận biết AJAX.
	 *
	 * @param int $current_page Trang hiện tại (bắt đầu từ 1).
	 * @param int $total_pages  Tổng số trang.
	 */
	public static function render_pagination( int $current_page, int $total_pages ): void {
		if ( $total_pages <= 1 ) {
			return;
		}
		?>
		<nav class="paf-pagination" aria-label="<?php esc_attr_e( 'Điều hướng bài viết', 'post-ajax-filter' ); ?>">

			<?php if ( $current_page > 1 ) : ?>
				<button
					type="button"
					class="paf-pagination__btn paf-pagination__btn--prev"
					data-page="<?php echo absint( $current_page - 1 ); ?>"
					aria-label="<?php esc_attr_e( 'Trang trước', 'post-ajax-filter' ); ?>"
				>&larr; <?php esc_html_e( 'Trước', 'post-ajax-filter' ); ?></button>
			<?php endif; ?>

			<?php
			// Hiển thị tối đa 5 nút trang xung quanh trang hiện tại.
			$start = max( 1, $current_page - 2 );
			$end   = min( $total_pages, $current_page + 2 );

			if ( $start > 1 ) {
				echo '<button type="button" class="paf-pagination__btn" data-page="1">1</button>';
				if ( $start > 2 ) {
					echo '<span class="paf-pagination__ellipsis">…</span>';
				}
			}

			for ( $i = $start; $i <= $end; $i++ ) {
				$current_cls = ( $i === $current_page ) ? ' paf-pagination__btn--current' : '';
				printf(
					'<button type="button" class="paf-pagination__btn%s" data-page="%d" %s>%d</button>',
					esc_attr( $current_cls ),
					absint( $i ),
					( $i === $current_page ) ? 'aria-current="page"' : '',
					absint( $i )
				);
			}

			if ( $end < $total_pages ) {
				if ( $end < $total_pages - 1 ) {
					echo '<span class="paf-pagination__ellipsis">…</span>';
				}
				printf(
					'<button type="button" class="paf-pagination__btn" data-page="%d">%d</button>',
					absint( $total_pages ),
					absint( $total_pages )
				);
			}
			?>

			<?php if ( $current_page < $total_pages ) : ?>
				<button
					type="button"
					class="paf-pagination__btn paf-pagination__btn--next"
					data-page="<?php echo absint( $current_page + 1 ); ?>"
					aria-label="<?php esc_attr_e( 'Trang sau', 'post-ajax-filter' ); ?>"
				><?php esc_html_e( 'Sau', 'post-ajax-filter' ); ?> &rarr;</button>
			<?php endif; ?>

		</nav>
		<?php
	}
}
