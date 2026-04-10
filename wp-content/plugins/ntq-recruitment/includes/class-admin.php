<?php
/**
 * Admin menu, applications list, and application detail pages.
 */

defined( 'ABSPATH' ) || exit;

class NTQ_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	}

	// ─── Menu ─────────────────────────────────────────────────────────────────

	public static function register_menu() {
		// Thêm trang Hồ Sơ Ứng Tuyển vào bên trong menu Việc Làm (CPT job)
		add_submenu_page(
			'edit.php?post_type=job',
			__( 'Hồ Sơ Ứng Tuyển', 'ntq-recruitment' ),
			__( 'Hồ Sơ Ứng Tuyển', 'ntq-recruitment' ),
			'manage_options',
			'ntq-rec-applications',
			array( __CLASS__, 'page_applications' )
		);
	}

	// ─── Page routing ─────────────────────────────────────────────────────────

	public static function page_applications() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền xem trang này.', 'ntq-recruitment' ) );
		}

		$action = NTQ_Helpers::request( 'action' );
		$id     = absint( NTQ_Helpers::request( 'id' ) );

		if ( 'view' === $action && $id > 0 ) {
			self::render_detail( $id );
		} else {
			self::render_list();
		}
	}

	// ─── Applications list ────────────────────────────────────────────────────

	private static function render_list() {
		$per_page   = 20;
		$page       = max( 1, absint( NTQ_Helpers::request( 'paged' ) ?: 1 ) );
		$job_id     = absint( NTQ_Helpers::request( 'job_id' ) );
		$department = NTQ_Helpers::request( 'department' );
		$location   = NTQ_Helpers::request( 'location' );
		$search     = NTQ_Helpers::request( 's' );
		$status_filter = NTQ_Helpers::request( 'status_filter' );

		$query_args = array(
			'per_page'   => $per_page,
			'page'       => $page,
			'job_id'     => $job_id,
			'department' => $department,
			'location'   => $location,
			'search'     => $search,
			'status'     => $status_filter,
		);

		$applications = NTQ_Database::get_applications( $query_args );
		$total        = NTQ_Database::count_applications( $query_args );
		$total_pages  = ceil( $total / $per_page );

		// All jobs for filter dropdown
		$jobs = get_posts( array(
			'post_type'      => 'job',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$departments = NTQ_Helpers::get_term_options( 'job_department' );
		$locations   = NTQ_Helpers::get_term_options( 'job_location' );
		$statuses    = NTQ_Helpers::application_statuses();

		include NTQ_REC_PLUGIN_DIR . 'templates/admin/applications-list.php';
	}

	// ─── Application detail ───────────────────────────────────────────────────

	private static function render_detail( $id ) {
		$application = NTQ_Database::get_application( $id );

		if ( ! $application ) {
			wp_die( esc_html__( 'Không tìm thấy hồ sơ ứng tuyển.', 'ntq-recruitment' ) );
		}

		$statuses = NTQ_Helpers::application_statuses();
		$job      = get_post( $application->job_id );

		include NTQ_REC_PLUGIN_DIR . 'templates/admin/application-detail.php';
	}

	// ─── Handle admin POST actions ────────────────────────────────────────────

	public static function handle_actions() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_GET['page'] ) || 'ntq-rec-applications' !== $_GET['page'] ) {
			return;
		}
		// phpcs:enable

		$action = NTQ_Helpers::request( 'action' );

		// Delete application
		if ( 'delete' === $action ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'ntq_rec_delete_application' ) ) {
				wp_die( esc_html__( 'Kiểm tra bảo mật thất bại.', 'ntq-recruitment' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Bạn không có đủ quyền truy cập.', 'ntq-recruitment' ) );
			}

			$id = absint( NTQ_Helpers::request( 'id' ) );

			// Delete the CV file from disk (files are stored directly, not as WP attachments).
			$app = NTQ_Database::get_application( $id );
			if ( $app && ! empty( $app->cv_file_url ) ) {
				$upload_dir = wp_upload_dir();
				$file_path  = str_replace(
					trailingslashit( $upload_dir['baseurl'] ),
					trailingslashit( $upload_dir['basedir'] ),
					$app->cv_file_url
				);
				if ( $file_path && file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
				}
			}

			NTQ_Database::delete_application( $id );
			wp_redirect( add_query_arg( array( 'page' => 'ntq-rec-applications', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Update status
		if ( 'update_status' === $action ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'ntq_rec_update_status' ) ) {
				wp_die( esc_html__( 'Kiểm tra bảo mật thất bại.', 'ntq-recruitment' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Bạn không có đủ quyền truy cập.', 'ntq-recruitment' ) );
			}

			$id     = absint( $_POST['id'] ?? 0 );
			$status = sanitize_text_field( $_POST['status'] ?? '' );

			NTQ_Database::update_status( $id, $status );

			wp_redirect( add_query_arg(
				array( 'page' => 'ntq-rec-applications', 'action' => 'view', 'id' => $id, 'updated' => '1' ),
				admin_url( 'admin.php' )
			) );
			exit;
		}
	}

	// ─── Admin notices ────────────────────────────────────────────────────────

	public static function admin_notices() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Đã xóa hồ sơ ứng tuyển.', 'ntq-recruitment' ) . '</p></div>';
		}
		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Đã cập nhật trạng thái hồ sơ.', 'ntq-recruitment' ) . '</p></div>';
		}
		// phpcs:enable
	}
}
