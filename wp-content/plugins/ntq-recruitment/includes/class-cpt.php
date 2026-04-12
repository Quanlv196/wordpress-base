<?php
/**
 * Custom post type and taxonomies registration, plus admin columns / filters.
 */

defined( 'ABSPATH' ) || exit;

class NTQ_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );

		// Admin columns
		add_filter( 'manage_job_posts_columns',       array( __CLASS__, 'job_columns' ) );
		add_action( 'manage_job_posts_custom_column', array( __CLASS__, 'job_column_content' ), 10, 2 );
		add_filter( 'manage_edit-job_sortable_columns', array( __CLASS__, 'sortable_columns' ) );

		// Admin filter dropdowns above job list table
		add_action( 'restrict_manage_posts', array( __CLASS__, 'add_filter_dropdowns' ) );
		add_filter( 'parse_query',           array( __CLASS__, 'apply_admin_filters' ) );

		// Meta boxes for salary & deadline
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_job',  array( __CLASS__, 'save_meta_boxes' ), 10, 2 );
	}

	// ─── Registration ─────────────────────────────────────────────────────────

	public static function register_post_type() {
		$labels = array(
			'name'               => _x( 'Việc Làm', 'post type general name', 'ntq-recruitment' ),
			'singular_name'      => _x( 'Việc Làm', 'post type singular name', 'ntq-recruitment' ),
			'menu_name'          => _x( 'Việc Làm', 'admin menu', 'ntq-recruitment' ),
			'name_admin_bar'     => _x( 'Việc Làm', 'add new on admin bar', 'ntq-recruitment' ),
			'add_new'            => _x( 'Thêm Mới', 'job', 'ntq-recruitment' ),
			'add_new_item'       => __( 'Thêm Việc Làm Mới', 'ntq-recruitment' ),
			'new_item'           => __( 'Việc Làm Mới', 'ntq-recruitment' ),
			'edit_item'          => __( 'Chỉnh Sửa Việc Làm', 'ntq-recruitment' ),
			'view_item'          => __( 'Xem Việc Làm', 'ntq-recruitment' ),
			'all_items'          => __( 'Tất Cả Việc Làm', 'ntq-recruitment' ),
			'search_items'       => __( 'Tìm Kiếm Việc Làm', 'ntq-recruitment' ),
			'not_found'          => __( 'Không tìm thấy việc làm nào.', 'ntq-recruitment' ),
			'not_found_in_trash' => __( 'Không tìm thấy việc làm trong thùng rác.', 'ntq-recruitment' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'jobs' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-businessman',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'job', $args );
	}

	public static function register_taxonomies() {
		// Department
		register_taxonomy(
			'job_department',
			'job',
			array(
				'labels'            => array(
					'name'              => _x( 'Phòng Ban', 'taxonomy general name', 'ntq-recruitment' ),
					'singular_name'     => _x( 'Phòng Ban', 'taxonomy singular name', 'ntq-recruitment' ),
					'search_items'      => __( 'Tìm Phòng Ban', 'ntq-recruitment' ),
					'all_items'         => __( 'Tất Cả Phòng Ban', 'ntq-recruitment' ),
					'edit_item'         => __( 'Chỉnh Sửa Phòng Ban', 'ntq-recruitment' ),
					'update_item'       => __( 'Cập Nhật Phòng Ban', 'ntq-recruitment' ),
					'add_new_item'      => __( 'Thêm Phòng Ban Mới', 'ntq-recruitment' ),
					'new_item_name'     => __( 'Tên Phòng Ban Mới', 'ntq-recruitment' ),
					'menu_name'         => __( 'Phòng Ban', 'ntq-recruitment' ),
				),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'job-department' ),
				'show_in_rest'      => true,
			)
		);

		// Location
		register_taxonomy(
			'job_location',
			'job',
			array(
				'labels'            => array(
					'name'              => _x( 'Địa Điểm', 'taxonomy general name', 'ntq-recruitment' ),
					'singular_name'     => _x( 'Địa Điểm', 'taxonomy singular name', 'ntq-recruitment' ),
					'search_items'      => __( 'Tìm Địa Điểm', 'ntq-recruitment' ),
					'all_items'         => __( 'Tất Cả Địa Điểm', 'ntq-recruitment' ),
					'edit_item'         => __( 'Chỉnh Sửa Địa Điểm', 'ntq-recruitment' ),
					'update_item'       => __( 'Cập Nhật Địa Điểm', 'ntq-recruitment' ),
					'add_new_item'      => __( 'Thêm Địa Điểm Mới', 'ntq-recruitment' ),
					'new_item_name'     => __( 'Tên Địa Điểm Mới', 'ntq-recruitment' ),
					'menu_name'         => __( 'Địa Điểm', 'ntq-recruitment' ),
				),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'job-location' ),
				'show_in_rest'      => true,
			)
		);
	}

	// ─── Meta boxes ───────────────────────────────────────────────────────────

	public static function add_meta_boxes() {
		add_meta_box(
			'ntq_job_details',
			__( 'Thông Tin Vị Trí', 'ntq-recruitment' ),
			array( __CLASS__, 'render_meta_box' ),
			'job',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'ntq_job_meta_nonce', '_ntq_job_meta_nonce' );
		$salary   = get_post_meta( $post->ID, '_job_salary', true );
		$deadline = get_post_meta( $post->ID, '_job_deadline', true );
		?>
		<table class="form-table" style="margin-top:0">
			<tr>
				<th><label for="job_salary"><?php esc_html_e( 'Mức Lương', 'ntq-recruitment' ); ?></label></th>
				<td>
					<input type="text" id="job_salary" name="job_salary"
						value="<?php echo esc_attr( $salary ); ?>"
						placeholder="<?php esc_attr_e( 'Ví dụ: 10-15 triệu hoặc Thỏa thuận', 'ntq-recruitment' ); ?>"
						class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="job_deadline"><?php esc_html_e( 'Hạn Nộp Hồ Sơ', 'ntq-recruitment' ); ?></label></th>
				<td>
					<input type="date" id="job_deadline" name="job_deadline"
						value="<?php echo esc_attr( $deadline ); ?>"
						class="regular-text">
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_meta_boxes( $post_id, $post ) {
		if ( ! isset( $_POST['_ntq_job_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['_ntq_job_meta_nonce'] ), 'ntq_job_meta_nonce' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Salary
		if ( isset( $_POST['job_salary'] ) ) {
			update_post_meta( $post_id, '_job_salary', sanitize_text_field( wp_unslash( $_POST['job_salary'] ) ) );
		}

		// Deadline
		if ( isset( $_POST['job_deadline'] ) ) {
			$deadline = sanitize_text_field( wp_unslash( $_POST['job_deadline'] ) );
			// Validate YYYY-MM-DD format
			if ( '' === $deadline || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) ) {
				update_post_meta( $post_id, '_job_deadline', $deadline );
			}
		}
	}

	// ─── Admin columns ────────────────────────────────────────────────────────

	public static function job_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
					$new['job_department'] = __( 'Phòng Ban', 'ntq-recruitment' );
					$new['job_location']   = __( 'Địa Điểm', 'ntq-recruitment' );
					$new['job_salary']     = __( 'Mức Lương', 'ntq-recruitment' );
					$new['job_deadline']   = __( 'Hạn Nộp', 'ntq-recruitment' );
					$new['applications']   = __( 'Hồ Sơ', 'ntq-recruitment' );
			}
		}
		return $new;
	}

	public static function job_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'job_department':
				echo esc_html( NTQ_Helpers::get_terms_string( $post_id, 'job_department' ) );
				break;
			case 'job_location':
				echo esc_html( NTQ_Helpers::get_terms_string( $post_id, 'job_location' ) );
				break;
			case 'job_salary':
				$salary = get_post_meta( $post_id, '_job_salary', true );
				echo esc_html( $salary ?: '—' );
				break;
			case 'job_deadline':
				$deadline = get_post_meta( $post_id, '_job_deadline', true );
				echo $deadline ? esc_html( date_i18n( 'd/m/Y', strtotime( $deadline ) ) ) : '—';
				break;
			case 'applications':
				$count = NTQ_Database::count_applications( array( 'job_id' => $post_id ) );
				$url   = add_query_arg(
					array(
						'page'   => 'ntq-rec-applications',
						'job_id' => $post_id,
					),
					admin_url( 'admin.php' )
				);
				printf(
					'<a href="%s">%d</a>',
					esc_url( $url ),
					(int) $count
				);
				break;
		}
	}

	public static function sortable_columns( $columns ) {
		$columns['job_department'] = 'job_department';
		$columns['job_location']   = 'job_location';
		return $columns;
	}

	// ─── Admin filters ────────────────────────────────────────────────────────

	public static function add_filter_dropdowns( $post_type ) {
		if ( 'job' !== $post_type ) {
			return;
		}

		// Department dropdown
		$selected_dept = NTQ_Helpers::request( 'job_department_filter' );
		$departments   = NTQ_Helpers::get_term_options( 'job_department' );
		echo '<select name="job_department_filter">';
		echo '<option value="">' . esc_html__( 'Tất Cả Phòng Ban', 'ntq-recruitment' ) . '</option>';
		foreach ( $departments as $term ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $term->slug ),
				selected( $selected_dept, $term->slug, false ),
				esc_html( $term->name )
			);
		}
		echo '</select>';

		// Location dropdown
		$selected_loc = NTQ_Helpers::request( 'job_location_filter' );
		$locations    = NTQ_Helpers::get_term_options( 'job_location' );
		echo '<select name="job_location_filter">';
		echo '<option value="">' . esc_html__( 'Tất Cả Địa Điểm', 'ntq-recruitment' ) . '</option>';
		foreach ( $locations as $term ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $term->slug ),
				selected( $selected_loc, $term->slug, false ),
				esc_html( $term->name )
			);
		}
		echo '</select>';
	}

	/**
	 * Modify the admin query to filter by selected department/location.
	 *
	 * @param WP_Query $query Admin query object.
	 */
	public static function apply_admin_filters( $query ) {
		global $pagenow;

		if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
			return;
		}

		if ( 'job' !== $query->get( 'post_type' ) ) {
			return;
		}

		$tax_query = $query->get( 'tax_query' ) ?: array();

		$dept = NTQ_Helpers::request( 'job_department_filter' );
		if ( ! empty( $dept ) ) {
			$tax_query[] = array(
				'taxonomy' => 'job_department',
				'field'    => 'slug',
				'terms'    => $dept,
			);
		}

		$loc = NTQ_Helpers::request( 'job_location_filter' );
		if ( ! empty( $loc ) ) {
			$tax_query[] = array(
				'taxonomy' => 'job_location',
				'field'    => 'slug',
				'terms'    => $loc,
			);
		}

		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$query->set( 'tax_query', $tax_query );
		}
	}
}
