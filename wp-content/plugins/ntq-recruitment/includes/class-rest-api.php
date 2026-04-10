<?php
/**
 * REST API endpoints for jobs and applications.
 *
 * Base URL: /wp-json/ntq-rec/v1/
 */

defined( 'ABSPATH' ) || exit;

class NTQ_Rest_API {

	const NAMESPACE = 'ntq-rec/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		// ── Jobs ──────────────────────────────────────────────────────────────
		register_rest_route( self::NAMESPACE, '/jobs', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_jobs' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'department' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'location' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 10,
						'minimum'           => 1,
						'maximum'           => 50,
						'sanitize_callback' => 'absint',
					),
					'page' => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			),
		) );

		register_rest_route( self::NAMESPACE, '/jobs/(?P<id>[\\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_job' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
						'sanitize_callback' => 'absint',
					),
				),
			),
		) );

		// ── Applications ──────────────────────────────────────────────────────
		register_rest_route( self::NAMESPACE, '/applications', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_applications' ),
				'permission_callback' => array( __CLASS__, 'admin_only' ),
				'args'                => array(
					'job_id'     => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'department' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'location'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'status'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'per_page'   => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100, 'sanitize_callback' => 'absint' ),
					'page'       => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'sanitize_callback' => 'absint' ),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_application' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( self::NAMESPACE, '/applications/(?P<id>[\\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_application' ),
				'permission_callback' => array( __CLASS__, 'admin_only' ),
				'args'                => array(
					'id' => array( 'sanitize_callback' => 'absint' ),
				),
			),
		) );
	}

	// ─── Permission callbacks ─────────────────────────────────────────────────

	public static function admin_only( $request ) {
		return current_user_can( 'manage_options' );
	}

	// ─── Job endpoints ────────────────────────────────────────────────────────

	public static function get_jobs( WP_REST_Request $request ) {
		$tax_query = array();

		$department = $request->get_param( 'department' );
		if ( ! empty( $department ) ) {
			$tax_query[] = array(
				'taxonomy' => 'job_department',
				'field'    => 'slug',
				'terms'    => $department,
			);
		}

		$location = $request->get_param( 'location' );
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

		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );

		$query_args = array(
			'post_type'      => 'job',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query;
		}

		$query = new WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = self::format_job( $post );
		}

		wp_reset_postdata();

		$response = new WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', $query->found_posts );
		$response->header( 'X-WP-TotalPages', $query->max_num_pages );

		return $response;
	}

	public static function get_job( WP_REST_Request $request ) {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'job' !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'not_found', __( 'Job not found.', 'ntq-recruitment' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( self::format_job( $post ), 200 );
	}

	// ─── Application endpoints ────────────────────────────────────────────────

	public static function get_applications( WP_REST_Request $request ) {
		$args = array(
			'job_id'     => $request->get_param( 'job_id' ),
			'department' => $request->get_param( 'department' ),
			'location'   => $request->get_param( 'location' ),
			'status'     => $request->get_param( 'status' ),
			'per_page'   => $request->get_param( 'per_page' ),
			'page'       => $request->get_param( 'page' ),
		);

		$items = NTQ_Database::get_applications( $args );
		$total = NTQ_Database::count_applications( $args );

		$formatted = array_map( array( __CLASS__, 'format_application' ), $items );

		$response = new WP_REST_Response( $formatted, 200 );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $args['per_page'] ) );

		return $response;
	}

	public static function get_application( WP_REST_Request $request ) {
		$app = NTQ_Database::get_application( $request->get_param( 'id' ) );

		if ( ! $app ) {
			return new WP_Error( 'not_found', __( 'Application not found.', 'ntq-recruitment' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( self::format_application( $app ), 200 );
	}

	/**
	 * Create an application via REST API (no file upload — multipart not handled here).
	 * For file uploads use the AJAX endpoint.
	 */
	public static function create_application( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		$name   = sanitize_text_field( $params['applicant_name'] ?? '' );
		$phone  = sanitize_text_field( $params['phone'] ?? '' );
		$email  = sanitize_email( $params['email'] ?? '' );
		$job_id = absint( $params['job_id'] ?? 0 );

		if ( empty( $name ) || empty( $phone ) || empty( $email ) || ! $job_id ) {
			return new WP_Error(
				'missing_fields',
				__( 'applicant_name, phone, email, and job_id are required.', 'ntq-recruitment' ),
				array( 'status' => 422 )
			);
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'ntq-recruitment' ), array( 'status' => 422 ) );
		}

		$job = get_post( $job_id );
		if ( ! $job || 'job' !== $job->post_type || 'publish' !== $job->post_status ) {
			return new WP_Error( 'invalid_job', __( 'Job not found or not published.', 'ntq-recruitment' ), array( 'status' => 404 ) );
		}

		$id = NTQ_Database::insert_application( array(
			'job_id'         => $job_id,
			'applicant_name' => $name,
			'phone'          => $phone,
			'email'          => $email,
		) );

		if ( ! $id ) {
			return new WP_Error( 'db_error', __( 'Failed to save application.', 'ntq-recruitment' ), array( 'status' => 500 ) );
		}

		NTQ_Mailer::send_new_application_notification( $id );

		$app = NTQ_Database::get_application( $id );

		return new WP_REST_Response( self::format_application( $app ), 201 );
	}

	// ─── Formatters ───────────────────────────────────────────────────────────

	private static function format_job( WP_Post $post ) {
		$departments = get_the_terms( $post->ID, 'job_department' );
		$locations   = get_the_terms( $post->ID, 'job_location' );

		return array(
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'excerpt'     => get_the_excerpt( $post ),
			'content'     => apply_filters( 'the_content', $post->post_content ),
			'link'        => get_permalink( $post ),
			'departments' => ( ! is_wp_error( $departments ) && $departments ) ? wp_list_pluck( $departments, 'name' ) : array(),
			'locations'   => ( ! is_wp_error( $locations ) && $locations ) ? wp_list_pluck( $locations, 'name' ) : array(),
			'date'        => $post->post_date,
		);
	}

	private static function format_application( $app ) {
		return array(
			'id'             => (int) $app->id,
			'job_id'         => (int) $app->job_id,
			'job_title'      => $app->job_title,
			'applicant_name' => $app->applicant_name,
			'phone'          => $app->phone,
			'email'          => $app->email,
			'cv_file_url'    => $app->cv_file_url,
			'status'         => $app->status,
			'created_at'     => $app->created_at,
		);
	}
}
