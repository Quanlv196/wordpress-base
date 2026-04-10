<?php
/**
 * Đăng ký và render shortcode [post_filter].
 *
 * Ví dụ sử dụng:
 *   [post_filter id="f1" type="category" ui="checkbox"]
 *   [post_filter id="f2" type="tag"      ui="tabs"]
 *   [post_filter id="f3" type="search"   ui="input"]
 *
 * @package Post_Ajax_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PAF_Filter_Shortcode
 */
class PAF_Filter_Shortcode {

	/**
	 * Instance singleton.
	 *
	 * @var PAF_Filter_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Lấy hoặc tạo instance singleton.
	 *
	 * @return PAF_Filter_Shortcode
	 */
	public static function get_instance(): PAF_Filter_Shortcode {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Constructor riêng tư — dùng get_instance(). */
	private function __construct() {
		add_shortcode( 'post_filter', array( $this, 'render' ) );
	}

	// -------------------------------------------------------------------------
	// Điểm vào shortcode
	// -------------------------------------------------------------------------

	/**
	 * Render widget bộ lọc.
	 *
	 * @param array|string $atts Thuộc tính shortcode.
	 * @return string            HTML đầu ra.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'         => 'paf-filter-' . wp_unique_id(),
				'type'       => 'category', // category | tag | search
				'ui'         => 'checkbox', // checkbox | radio | dropdown | tabs | input
				'label'      => '',
				'show_label' => 'true',     // false = ẩn tiêu đề
				'class'      => '',
				'cat_ids'    => '',         // Giới hạn danh mục (chỉ dùng type=category)
				'tag_ids'    => '',         // Giới hạn thẻ (chỉ dùng type=tag)
				'placeholder' => '',        // Dùng cho type=search
			),
			$atts,
			'post_filter'
		);

		$filter_id   = sanitize_html_class( $atts['id'] );
		$type        = sanitize_key( $atts['type'] );
		$ui          = sanitize_key( $atts['ui'] );
		$label       = sanitize_text_field( $atts['label'] );
		$extra_cls   = implode( ' ', array_map( 'sanitize_html_class', explode( ' ', trim( $atts['class'] ) ) ) );
		$show_label  = filter_var( $atts['show_label'], FILTER_VALIDATE_BOOLEAN );
		$placeholder = sanitize_text_field( $atts['placeholder'] );

		$cat_ids = array_values(
			array_filter( array_map( 'absint', explode( ',', $atts['cat_ids'] ) ) )
		);
		$tag_ids = array_values(
			array_filter( array_map( 'absint', explode( ',', $atts['tag_ids'] ) ) )
		);

		// Kiểm tra type trong danh sách cho phép.
		$allowed_types = apply_filters( 'paf_allowed_filter_types', array( 'category', 'tag', 'search' ) );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return '';
		}

		// Nhãn mặc định.
		if ( '' === $label ) {
			$defaults = array(
				'category' => __( 'Danh mục', 'post-ajax-filter' ),
				'tag'      => __( 'Thẻ', 'post-ajax-filter' ),
				'search'   => __( 'Tìm kiếm', 'post-ajax-filter' ),
			);
			$label = $defaults[ $type ] ?? ucfirst( $type );
		}

		ob_start();
		?>
		<div
			id="<?php echo esc_attr( $filter_id ); ?>"
			class="paf-filter paf-filter--<?php echo esc_attr( $type ); ?> paf-filter--ui-<?php echo esc_attr( $ui ); ?><?php echo $extra_cls ? ' ' . esc_attr( $extra_cls ) : ''; ?>"
			data-filter-id="<?php echo esc_attr( $filter_id ); ?>"
			data-filter-type="<?php echo esc_attr( $type ); ?>"
			data-filter-ui="<?php echo esc_attr( $ui ); ?>"
			data-cat-ids="<?php echo esc_attr( implode( ',', $cat_ids ) ); ?>"
			data-tag-ids="<?php echo esc_attr( implode( ',', $tag_ids ) ); ?>"
			role="group"
			aria-label="<?php echo esc_attr( $label ); ?>"
		>
			<?php if ( $show_label ) : ?>
			<div class="paf-filter__header">
				<h4 class="paf-filter__title"><?php echo esc_html( $label ); ?></h4>
			</div>
			<?php endif; ?>

			<div class="paf-filter__body">
				<?php $this->render_body( $type, $ui, $filter_id, $cat_ids, $tag_ids, $placeholder ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Điều phối render body
	// -------------------------------------------------------------------------

	/**
	 * Định tuyến render đến phương thức đúng dựa theo loại bộ lọc.
	 *
	 * @param string $type        Loại bộ lọc (category|tag|search).
	 * @param string $ui          Kiểu giao diện.
	 * @param string $filter_id   ID element bộ lọc duy nhất.
	 * @param int[]  $cat_ids     Giới hạn danh mục.
	 * @param int[]  $tag_ids     Giới hạn thẻ.
	 * @param string $placeholder Placeholder cho ô tìm kiếm.
	 */
	private function render_body(
		string $type,
		string $ui,
		string $filter_id,
		array $cat_ids = array(),
		array $tag_ids = array(),
		string $placeholder = ''
	): void {
		switch ( $type ) {
			case 'category':
				$this->render_taxonomy_filter( 'category', $ui, $filter_id, 'category', $cat_ids );
				break;

			case 'tag':
				$this->render_taxonomy_filter( 'post_tag', $ui, $filter_id, 'tag', $tag_ids );
				break;

			case 'search':
				$this->render_search_filter( $filter_id, $placeholder );
				break;

			default:
				/**
				 * Fires cho các loại bộ lọc tùy chỉnh được thêm bởi code bên ngoài.
				 *
				 * @param string $type      Slug loại bộ lọc.
				 * @param string $ui        Kiểu giao diện.
				 * @param string $filter_id ID element duy nhất.
				 */
				do_action( 'paf_render_custom_filter', $type, $ui, $filter_id );
		}
	}

	// -------------------------------------------------------------------------
	// Bộ lọc taxonomy (category / tag)
	// -------------------------------------------------------------------------

	/**
	 * Lấy terms và chuyển đến renderer giao diện phù hợp.
	 *
	 * @param string $taxonomy  Slug taxonomy WP.
	 * @param string $ui        Kiểu giao diện.
	 * @param string $filter_id ID element duy nhất.
	 * @param string $key       Khóa bộ lọc (category|tag).
	 * @param int[]  $scope_ids Giới hạn theo term ID.
	 */
	private function render_taxonomy_filter(
		string $taxonomy,
		string $ui,
		string $filter_id,
		string $key,
		array $scope_ids = array()
	): void {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			/* translators: %s: tên taxonomy */
			echo '<p class="paf-notice">' . sprintf( esc_html__( 'Taxonomy "%s" chưa được đăng ký.', 'post-ajax-filter' ), esc_html( $taxonomy ) ) . '</p>';
			return;
		}

		$get_terms_args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( ! empty( $scope_ids ) ) {
			$expanded_ids = $this->expand_term_ids( $scope_ids, $taxonomy );
			if ( ! empty( $expanded_ids ) ) {
				$get_terms_args['include'] = $expanded_ids;
			}
		}

		$terms = get_terms( $get_terms_args );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<p class="paf-notice">' . esc_html__( 'Không có mục nào.', 'post-ajax-filter' ) . '</p>';
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
	 * Mở rộng danh sách term ID gồm ID gốc và tất cả con cháu.
	 *
	 * @param int[]  $term_ids ID term gốc.
	 * @param string $taxonomy Taxonomy slug.
	 * @return int[]           Tất cả IDs đã loại trùng.
	 */
	private function expand_term_ids( array $term_ids, string $taxonomy ): array {
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
	 * Render dropdown <select> cho taxonomy.
	 *
	 * @param WP_Term[] $terms Mảng term objects.
	 * @param string    $key   Khóa bộ lọc.
	 */
	private function render_terms_dropdown( array $terms, string $key ): void {
		?>
		<div class="paf-filter__dropdown-wrap">
			<select
				class="paf-filter__select paf-filter__input"
				name="paf_filter[<?php echo esc_attr( $key ); ?>]"
				data-filter-key="<?php echo esc_attr( $key ); ?>"
			>
				<option value=""><?php esc_html_e( 'Tất cả', 'post-ajax-filter' ); ?></option>
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
	 * Render tab pills cho taxonomy (chọn một).
	 *
	 * @param WP_Term[] $terms Mảng term objects.
	 * @param string    $key   Khóa bộ lọc.
	 */
	private function render_terms_tabs( array $terms, string $key ): void {
		?>
		<div class="paf-filter__tabs" role="tablist">
			<button
				type="button"
				class="paf-filter__tab is-active"
				data-filter-key="<?php echo esc_attr( $key ); ?>"
				data-value=""
				role="tab"
				aria-selected="true"
			><?php esc_html_e( 'Tất cả', 'post-ajax-filter' ); ?></button>

			<?php foreach ( $terms as $term ) : ?>
				<button
					type="button"
					class="paf-filter__tab"
					data-filter-key="<?php echo esc_attr( $key ); ?>"
					data-value="<?php echo esc_attr( $term->term_id ); ?>"
					role="tab"
					aria-selected="false"
				><?php echo esc_html( $term->name ); ?></button>
			<?php endforeach; ?>

			<!-- Input ẩn mang giá trị được chọn lên JS -->
			<input
				type="hidden"
				class="paf-filter__input paf-filter__tab-value"
				data-filter-key="<?php echo esc_attr( $key ); ?>"
				value=""
			/>
		</div>
		<?php
	}

	/**
	 * Render danh sách checkbox hoặc radio cho taxonomy.
	 *
	 * @param WP_Term[] $terms     Mảng term objects.
	 * @param string    $key       Khóa bộ lọc.
	 * @param string    $filter_id ID element bộ lọc.
	 * @param string    $input_type 'checkbox' hoặc 'radio'.
	 */
	private function render_terms_inputs( array $terms, string $key, string $filter_id, string $input_type ): void {
		?>
		<ul class="paf-filter__list" role="group">
			<?php foreach ( $terms as $term ) :
				$input_id = $filter_id . '-' . $key . '-' . absint( $term->term_id );
			?>
				<li class="paf-filter__item">
					<label class="paf-filter__label" for="<?php echo esc_attr( $input_id ); ?>">
						<input
							type="<?php echo esc_attr( $input_type ); ?>"
							id="<?php echo esc_attr( $input_id ); ?>"
							class="paf-filter__input"
							name="paf_filter[<?php echo esc_attr( $key ); ?>]<?php echo 'checkbox' === $input_type ? '[]' : ''; ?>"
							value="<?php echo esc_attr( $term->term_id ); ?>"
							data-filter-key="<?php echo esc_attr( $key ); ?>"
						/>
						<span class="paf-filter__label-text"><?php echo esc_html( $term->name ); ?></span>
						<span class="paf-filter__count">(<?php echo absint( $term->count ); ?>)</span>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	// -------------------------------------------------------------------------
	// Bộ lọc tìm kiếm
	// -------------------------------------------------------------------------

	/**
	 * Render ô nhập từ khóa tìm kiếm.
	 *
	 * @param string $filter_id   ID element bộ lọc.
	 * @param string $placeholder Placeholder text.
	 */
	private function render_search_filter( string $filter_id, string $placeholder ): void {
		if ( '' === $placeholder ) {
			$placeholder = __( 'Nhập từ khóa…', 'post-ajax-filter' );
		}
		$input_id = $filter_id . '-search';
		?>
		<div class="paf-filter__search-wrap">
			<label for="<?php echo esc_attr( $input_id ); ?>" class="screen-reader-text">
				<?php esc_html_e( 'Tìm kiếm bài viết', 'post-ajax-filter' ); ?>
			</label>
			<input
				type="search"
				id="<?php echo esc_attr( $input_id ); ?>"
				class="paf-filter__search paf-filter__input"
				data-filter-key="search"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				autocomplete="off"
			/>
		</div>
		<?php
	}
}
