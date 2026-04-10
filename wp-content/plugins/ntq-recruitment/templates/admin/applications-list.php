<?php
/**
 * Admin template: Applications list page.
 * Variables from NTQ_Admin::render_list():
 *   $applications, $total, $total_pages, $page, $per_page,
 *   $jobs, $departments, $locations, $statuses,
 *   $job_id, $department, $location, $search, $status_filter.
 */

defined( 'ABSPATH' ) || exit;

$base_url = admin_url( 'admin.php?page=ntq-rec-applications' );
?>
<div class="wrap">

	<div class="ntq-admin-header">
		<h1><?php esc_html_e( 'Hồ Sơ Ứng Tuyển', 'ntq-recruitment' ); ?></h1>
	</div>

	<!-- ── Filter form ──────────────────────────────────────────────── -->
	<form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ntq-admin-filter">
		<input type="hidden" name="page" value="ntq-rec-applications">

		<!-- Job dropdown -->
		<select name="job_id">
			<option value=""><?php esc_html_e( 'Tất Cả Việc Làm', 'ntq-recruitment' ); ?></option>
			<?php foreach ( $jobs as $j ) : ?>
				<option value="<?php echo esc_attr( $j->ID ); ?>" <?php selected( $job_id, $j->ID ); ?>>
					<?php echo esc_html( $j->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<!-- Department dropdown -->
		<select name="department">
			<option value=""><?php esc_html_e( 'Tất Cả Phòng Ban', 'ntq-recruitment' ); ?></option>
			<?php foreach ( $departments as $term ) : ?>
				<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $department, $term->slug ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<!-- Location dropdown -->
		<select name="location">
			<option value=""><?php esc_html_e( 'Tất Cả Địa Điểm', 'ntq-recruitment' ); ?></option>
			<?php foreach ( $locations as $term ) : ?>
				<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $location, $term->slug ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<!-- Status dropdown -->
		<select name="status_filter">
			<option value=""><?php esc_html_e( 'Tất Cả Trạng Thái', 'ntq-recruitment' ); ?></option>
			<?php foreach ( $statuses as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $status_filter, $slug ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<!-- Search -->
		<input
			type="search"
			name="s"
			value="<?php echo esc_attr( $search ); ?>"
			placeholder="<?php esc_attr_e( 'Tìm tên hoặc email…', 'ntq-recruitment' ); ?>"
		>

		<button type="submit" class="button"><?php esc_html_e( 'Lọc', 'ntq-recruitment' ); ?></button>

		<?php if ( $job_id || $department || $location || $search || $status_filter ) : ?>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button">
				<?php esc_html_e( 'Xóa Bộ Lọc', 'ntq-recruitment' ); ?>
			</a>
		<?php endif; ?>
	</form>

	<!-- ── Summary ────────────────────────────────────────────────────── -->
	<p>
		<?php
		printf(
			/* translators: 1: number of results */
			esc_html( _n( 'Tìm thấy %s hồ sơ.', 'Tìm thấy %s hồ sơ.', $total, 'ntq-recruitment' ) ),
			'<strong>' . number_format_i18n( $total ) . '</strong>'
		);
		?>
	</p>

	<!-- ── Table ──────────────────────────────────────────────────────── -->
	<?php if ( $applications ) : ?>
		<div class="ntq-table-wrap">
			<table class="ntq-admin-table widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( '#', 'ntq-recruitment' ); ?></th>
						<th><?php esc_html_e( 'Họ Tên', 'ntq-recruitment' ); ?></th>
						<th><?php esc_html_e( 'Email', 'ntq-recruitment' ); ?></th>
						<th><?php esc_html_e( 'Điện Thoại', 'ntq-recruitment' ); ?></th>
						<th><?php esc_html_e( 'Vị Trí', 'ntq-recruitment' ); ?></th>
						<th><?php esc_html_e( 'Trạng Thái', 'ntq-recruitment' ); ?></th>
						<th><?php esc_html_e( 'Ngày', 'ntq-recruitment' ); ?></th>
						<th><?php esc_html_e( 'CV', 'ntq-recruitment' ); ?></th>
						<th><?php esc_html_e( 'Thảo Luận', 'ntq-recruitment' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $applications as $app ) : ?>
						<tr>
							<td><?php echo esc_html( $app->id ); ?></td>
							<td><?php echo esc_html( $app->applicant_name ); ?></td>
							<td>
								<a href="mailto:<?php echo esc_attr( $app->email ); ?>">
									<?php echo esc_html( $app->email ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $app->phone ); ?></td>
							<td>
								<?php if ( $app->job_id ) : ?>
									<a href="<?php echo esc_url( get_permalink( $app->job_id ) ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $app->job_title ?: __( '(Không rõ)', 'ntq-recruitment' ) ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Ứng tuyển chung', 'ntq-recruitment' ); ?></em>
								<?php endif; ?>
							</td>
							<td>
								<span class="ntq-badge ntq-badge--<?php echo esc_attr( NTQ_Helpers::status_class( $app->status ) ); ?>">
									<?php echo esc_html( NTQ_Helpers::status_label( $app->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $app->created_at ) ) ); ?></td>
							<td><?php echo wp_kses_post( NTQ_Helpers::cv_download_link( $app->cv_file_id, $app->cv_file_url ) ); ?></td>
							<td>
								<div class="ntq-row-actions">
									<a href="<?php echo esc_url( add_query_arg( array(
										'page'   => 'ntq-rec-applications',
										'action' => 'view',
										'id'     => $app->id,
									), admin_url( 'admin.php' ) ) ); ?>">
									<?php esc_html_e( 'Xem', 'ntq-recruitment' ); ?>
									</a>
									<a
										href="<?php echo esc_url( wp_nonce_url( add_query_arg( array(
											'page'   => 'ntq-rec-applications',
											'action' => 'delete',
											'id'     => $app->id,
										), admin_url( 'admin.php' ) ), 'ntq_rec_delete_application' ) ); ?>"
										class="delete-btn"
									>
										<?php esc_html_e( 'Xóa', 'ntq-recruitment' ); ?>
									</a>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- ── Admin pagination ───────────────────────────────────────────── -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="ntq-admin-pagination">
				<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
					<?php if ( $i === $page ) : ?>
						<span class="current"><?php echo esc_html( $i ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( array(
							'page'              => 'ntq-rec-applications',
							'paged'             => $i,
							'job_id'            => $job_id,
							'department'        => $department,
							'location'          => $location,
							's'                 => $search,
							'status_filter'     => $status_filter,
						), admin_url( 'admin.php' ) ) ); ?>">
							<?php echo esc_html( $i ); ?>
						</a>
					<?php endif; ?>
				<?php endfor; ?>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<div class="ntq-empty">
			<p><?php esc_html_e( 'No applications found.', 'ntq-recruitment' ); ?></p>
		</div>
	<?php endif; ?>

</div><!-- /.wrap -->
