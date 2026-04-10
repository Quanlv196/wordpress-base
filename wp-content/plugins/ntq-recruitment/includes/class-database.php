<?php
/**
 * Database management: table creation and application CRUD.
 */

defined( 'ABSPATH' ) || exit;

class NTQ_Database {

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ntq_applications';
	}

	// ─── Schema ───────────────────────────────────────────────────────────────

	public static function create_tables() {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id            BIGINT(20)   NOT NULL AUTO_INCREMENT,
			job_id        BIGINT(20)   NOT NULL,
			applicant_name VARCHAR(255) NOT NULL,
			phone         VARCHAR(50)  NOT NULL,
			email         VARCHAR(255) NOT NULL,
			cv_file_id    BIGINT(20)   NOT NULL DEFAULT 0,
			cv_file_url   VARCHAR(500) NOT NULL DEFAULT '',
			status        VARCHAR(50)  NOT NULL DEFAULT 'new',
			created_at    DATETIME     NOT NULL,
			updated_at    DATETIME     NOT NULL,
			PRIMARY KEY  (id),
			KEY job_id   (job_id),
			KEY email    (email),
			KEY status   (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'ntq_rec_db_version', '1.0.0' );
	}

	// ─── Write ────────────────────────────────────────────────────────────────

	/**
	 * Insert a new application row.
	 *
	 * @param array $data Associative array with keys: job_id, applicant_name, phone, email,
	 *                    cv_file_id, cv_file_url, status (optional).
	 * @return int|false  New row ID or false on failure.
	 */
	public static function insert_application( array $data ) {
		global $wpdb;

		$now    = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::table_name(),
			array(
				'job_id'         => absint( $data['job_id'] ),
				'applicant_name' => sanitize_text_field( $data['applicant_name'] ),
				'phone'          => sanitize_text_field( $data['phone'] ),
				'email'          => sanitize_email( $data['email'] ),
				'cv_file_id'     => absint( $data['cv_file_id'] ?? 0 ),
				'cv_file_url'    => esc_url_raw( $data['cv_file_url'] ?? '' ),
				'status'         => sanitize_text_field( $data['status'] ?? 'new' ),
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update application status.
	 *
	 * @param int    $id     Application ID.
	 * @param string $status One of: new, reviewing, accepted, rejected.
	 * @return int|false
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;

		$allowed = array( 'new', 'reviewing', 'accepted', 'rejected' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		return $wpdb->update(
			self::table_name(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete an application.
	 *
	 * @param int $id Application ID.
	 * @return int|false
	 */
	public static function delete_application( $id ) {
		global $wpdb;
		return $wpdb->delete(
			self::table_name(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	// ─── Read ─────────────────────────────────────────────────────────────────

	/**
	 * Get a single application by ID (with job title from WP_Posts).
	 *
	 * @param int $id Application ID.
	 * @return object|null
	 */
	public static function get_application( $id ) {
		global $wpdb;

		$table = self::table_name();
		$posts = $wpdb->posts;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, p.post_title AS job_title
				 FROM {$table} a
				 LEFT JOIN {$posts} p ON p.ID = a.job_id
				 WHERE a.id = %d",
				absint( $id )
			)
		);
	}

	/**
	 * Query applications with optional filters and pagination.
	 *
	 * @param array $args {
	 *   Optional.
	 *   @type int    $job_id     Filter by job ID.
	 *   @type string $department Filter by department taxonomy slug.
	 *   @type string $location   Filter by location taxonomy slug.
	 *   @type string $search     Search applicant name / email.
	 *   @type string $status     Filter by status.
	 *   @type int    $per_page   Results per page (default 20).
	 *   @type int    $page       Current page (default 1).
	 *   @type string $orderby    Column to order by (default created_at).
	 *   @type string $order      ASC or DESC (default DESC).
	 * }
	 * @return array
	 */
	public static function get_applications( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'job_id'     => 0,
			'department' => '',
			'location'   => '',
			'search'     => '',
			'status'     => '',
			'per_page'   => 20,
			'page'       => 1,
			'orderby'    => 'created_at',
			'order'      => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		$table  = self::table_name();
		$posts  = $wpdb->posts;
		$where  = array( 'a.id > 0' );
		$params = array();

		// Filter by job
		if ( ! empty( $args['job_id'] ) ) {
			$where[]  = 'a.job_id = %d';
			$params[] = absint( $args['job_id'] );
		}

		// Filter by status
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'a.status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		// Search by name or email
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '( a.applicant_name LIKE %s OR a.email LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		// Filter by department taxonomy slug (subquery)
		if ( ! empty( $args['department'] ) ) {
			$where[] = self::taxonomy_subquery( 'job_department', sanitize_text_field( $args['department'] ) );
		}

		// Filter by location taxonomy slug (subquery)
		if ( ! empty( $args['location'] ) ) {
			$where[] = self::taxonomy_subquery( 'job_location', sanitize_text_field( $args['location'] ) );
		}

		$allowed_orderby = array( 'id', 'created_at', 'applicant_name', 'email', 'job_id', 'status' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$per_page        = max( 1, absint( $args['per_page'] ) );
		$offset          = ( max( 1, absint( $args['page'] ) ) - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		$base_sql = "SELECT a.*, p.post_title AS job_title
					 FROM {$table} a
					 LEFT JOIN {$posts} p ON p.ID = a.job_id
					 WHERE {$where_sql}
					 ORDER BY a.{$orderby} {$order}
					 LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $base_sql, $params ) );
	}

	/**
	 * Count applications matching the same filters (without pagination).
	 *
	 * @param array $args Same keys as get_applications() (page/per_page ignored).
	 * @return int
	 */
	public static function count_applications( array $args = array() ) {
		global $wpdb;

		$table  = self::table_name();
		$where  = array( 'a.id > 0' );
		$params = array();

		if ( ! empty( $args['job_id'] ) ) {
			$where[]  = 'a.job_id = %d';
			$params[] = absint( $args['job_id'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'a.status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '( a.applicant_name LIKE %s OR a.email LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['department'] ) ) {
			$where[] = self::taxonomy_subquery( 'job_department', sanitize_text_field( $args['department'] ) );
		}
		if ( ! empty( $args['location'] ) ) {
			$where[] = self::taxonomy_subquery( 'job_location', sanitize_text_field( $args['location'] ) );
		}

		$where_sql = implode( ' AND ', $where );
		$base_sql  = "SELECT COUNT(*) FROM {$table} a WHERE {$where_sql}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $base_sql, $params ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $base_sql );
	}

	// ─── Private helpers ──────────────────────────────────────────────────────

	/**
	 * Build a subquery string that limits applications to jobs with a given taxonomy term.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $slug     Term slug.
	 * @return string SQL fragment suitable for use inside a WHERE clause.
	 */
	private static function taxonomy_subquery( $taxonomy, $slug ) {
		global $wpdb;

		$tr  = $wpdb->term_relationships;
		$tt  = $wpdb->term_taxonomy;
		$t   = $wpdb->terms;

		$subquery = $wpdb->prepare(
			"SELECT DISTINCT object_id
			 FROM {$tr}
			 INNER JOIN {$tt} ON {$tt}.term_taxonomy_id = {$tr}.term_taxonomy_id
			 INNER JOIN {$t}  ON {$t}.term_id = {$tt}.term_id
			 WHERE {$tt}.taxonomy = %s
			   AND {$t}.slug      = %s",
			$taxonomy,
			$slug
		);

		return "a.job_id IN ( {$subquery} )";
	}
}
