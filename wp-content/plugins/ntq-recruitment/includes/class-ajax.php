<?php
/**
 * AJAX handlers for job filtering/pagination and application form submission.
 */

defined( 'ABSPATH' ) || exit;

class NTQ_Ajax {

	public static function init() {
		// Job filter (public)
		add_action( 'wp_ajax_ntq_rec_filter_jobs',        array( __CLASS__, 'filter_jobs' ) );
		add_action( 'wp_ajax_nopriv_ntq_rec_filter_jobs', array( __CLASS__, 'filter_jobs' ) );

		// Application submission (public)
		add_action( 'wp_ajax_ntq_rec_submit_application',        array( __CLASS__, 'submit_application' ) );
		add_action( 'wp_ajax_nopriv_ntq_rec_submit_application', array( __CLASS__, 'submit_application' ) );
	}

	// ─── Filter / paginate jobs ───────────────────────────────────────────────

	public static function filter_jobs() {
		check_ajax_referer( 'ntq_rec_nonce', 'nonce' );

		$department = sanitize_text_field( wp_unslash( $_POST['department'] ?? '' ) );
		$location   = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
		$job_id     = absint( $_POST['job_id'] ?? 0 );
		$page       = max( 1, absint( $_POST['page'] ?? 1 ) );
		$limit      = min( 50, max( 1, absint( $_POST['limit'] ?? 10 ) ) );

		$tax_query = array();

		if ( ! empty( $department ) ) {
			$tax_query[] = array(
				'taxonomy' => 'job_department',
				'field'    => 'slug',
				'terms'    => $department,
			);
		}

		if ( ! empty( $location ) ) {
			$tax_query[] = array(
				'taxonomy' => 'job_location',
				'field'    => 'slug',
				'terms'    => $location,
			);
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		$query_args = array(
			'post_type'      => 'job',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Filter by specific job post ID
		if ( $job_id > 0 ) {
			$query_args['post__in'] = array( $job_id );
		}

		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query;
		}

		$query = new WP_Query( $query_args );

		ob_start();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				include NTQ_REC_PLUGIN_DIR . 'templates/job-card.php';
			}
			wp_reset_postdata();
		} else {
			echo '<p class="ntq-no-jobs">' . esc_html__( 'Không có vị trí tuyển dụng nào.', 'ntq-recruitment' ) . '</p>';
		}

		$items_html = ob_get_clean();

		// Pagination HTML
		ob_start();
		NTQ_Helpers::render_pagination( $query->max_num_pages, $page );
		$pagination_html = ob_get_clean();

		wp_send_json_success( array(
			'html'       => $items_html . $pagination_html,
			'total'      => (int) $query->found_posts,
			'pages'      => (int) $query->max_num_pages,
			'page'       => $page,
		) );
	}

	// ─── Submit application ───────────────────────────────────────────────────

	public static function submit_application() {
		check_ajax_referer( 'ntq_rec_nonce', 'nonce' );

		// ── Collect & sanitize ────────────────────────────────────────────────
		$name   = sanitize_text_field( wp_unslash( $_POST['applicant_name'] ?? '' ) );
		$phone  = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$email  = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$job_id = absint( $_POST['job_id'] ?? 0 );

		// ── Validation ────────────────────────────────────────────────────────
		$errors = array();

		if ( empty( $name ) ) {
			$errors[] = __( 'Họ tên là bắt buộc.', 'ntq-recruitment' );
		}

		if ( empty( $phone ) ) {
			$errors[] = __( 'Số điện thoại là bắt buộc.', 'ntq-recruitment' );
		}

		if ( empty( $email ) ) {
			$errors[] = __( 'Email là bắt buộc.', 'ntq-recruitment' );
		} elseif ( ! is_email( $email ) ) {
			$errors[] = __( 'Vui lòng nhập địa chỉ email hợp lệ.', 'ntq-recruitment' );
		}

		if ( $job_id <= 0 ) {
			$errors[] = __( 'Vui lòng chọn vị trí ứng tuyển.', 'ntq-recruitment' );
		}

		// Verify the job exists and is published (only when a specific job is selected)
		if ( $job_id > 0 ) {
			$job = get_post( $job_id );
			if ( ! $job || 'job' !== $job->post_type || 'publish' !== $job->post_status ) {
				$errors[] = __( 'Vị trí tuyển dụng đã chọn không còn khả dụng.', 'ntq-recruitment' );
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
		}

		// ── File upload ───────────────────────────────────────────────────────
		if ( empty( $_FILES['cv_file'] ) || UPLOAD_ERR_OK !== (int) $_FILES['cv_file']['error'] ) {
			wp_send_json_error( array( 'message' => __( 'CV là bắt buộc.', 'ntq-recruitment' ) ) );
		}

		$upload_result = NTQ_Helpers::upload_cv( $_FILES['cv_file'] );

		if ( is_wp_error( $upload_result ) ) {
			wp_send_json_error( array( 'message' => $upload_result->get_error_message() ) );
		}

		// ── Persist ───────────────────────────────────────────────────────────
		$application_id = NTQ_Database::insert_application( array(
			'job_id'         => $job_id,
			'applicant_name' => $name,
			'phone'          => $phone,
			'email'          => $email,
			'cv_file_id'     => $upload_result['attachment_id'],
			'cv_file_url'    => $upload_result['url'],
		) );

		if ( ! $application_id ) {
			wp_send_json_error( array( 'message' => __( 'Không thể lưu hồ sơ. Vui lòng thử lại.', 'ntq-recruitment' ) ) );
		}

		// ── Notifications (deferred – runs after response is sent) ───────────
		// Registering as a shutdown function ensures wp_send_json_success()
		// flushes the HTTP response to the browser BEFORE wp_mail() is called.
		// This prevents slow/unavailable mail servers from blocking the AJAX
		// response and causing the loading button to appear stuck.
		$app_id = (int) $application_id;
		register_shutdown_function( function () use ( $app_id ) {
			// Flush response to the browser now (works with PHP-FPM).
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}
			NTQ_Mailer::send_new_application_notification( $app_id );
		} );

		wp_send_json_success( array(
			'message' => __( 'Cảm ơn! Hồ sơ của bạn đã được gửi thành công. Chúng tôi sẽ liên hệ với bạn sớm.', 'ntq-recruitment' ),
		) );
	}
}
