<?php
/**
 * Admin template: Application detail page.
 * Variables: $application, $statuses, $job.
 */

defined( 'ABSPATH' ) || exit;

$back_url = admin_url( 'admin.php?page=ntq-rec-applications' );
?>
<div class="wrap">
	<div class="ntq-admin-header">
		<h1>
			<a href="<?php echo esc_url( $back_url ); ?>" style="text-decoration:none;font-size:14px;margin-right:10px;">&#8592;</a>
			<?php
			printf(
				/* translators: applicant name */
				esc_html__( 'Hồ Sơ: %s', 'ntq-recruitment' ),
				esc_html( $application->applicant_name )
			);
			?>
		</h1>
	</div>

	<!-- ── Status update ──────────────────────────────────────────────── -->
	<form method="POST" action="<?php echo esc_url( admin_url( 'admin.php?page=ntq-rec-applications&action=update_status' ) ); ?>" class="ntq-status-form">
		<?php wp_nonce_field( 'ntq_rec_update_status' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $application->id ); ?>">

		<label for="ntq-status-select"><?php esc_html_e( 'Trạng Thái Hồ Sơ:', 'ntq-recruitment' ); ?></label>
		<select id="ntq-status-select" name="status">
			<?php foreach ( $statuses as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $application->status, $slug ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<button type="submit" class="button button-primary">
			<?php esc_html_e( 'Cập Nhật Trạng Thái', 'ntq-recruitment' ); ?>
		</button>
	</form>

	<!-- ── Detail grid ────────────────────────────────────────────────── -->
	<div class="ntq-detail-grid">

		<!-- Applicant info -->
		<div class="ntq-detail-card">
			<h3><?php esc_html_e( 'Thông Tin Ứng Viên', 'ntq-recruitment' ); ?></h3>

			<div class="ntq-detail-row">
				<label><?php esc_html_e( 'Họ Tên:', 'ntq-recruitment' ); ?></label>
				<span><?php echo esc_html( $application->applicant_name ); ?></span>
			</div>

			<div class="ntq-detail-row">
				<label><?php esc_html_e( 'Email:', 'ntq-recruitment' ); ?></label>
				<a href="mailto:<?php echo esc_attr( $application->email ); ?>">
					<?php echo esc_html( $application->email ); ?>
				</a>
			</div>

			<div class="ntq-detail-row">
				<label><?php esc_html_e( 'Điện Thoại:', 'ntq-recruitment' ); ?></label>
				<a href="tel:<?php echo esc_attr( $application->phone ); ?>">
					<?php echo esc_html( $application->phone ); ?>
				</a>
			</div>

			<div class="ntq-detail-row">
				<label><?php esc_html_e( 'CV:', 'ntq-recruitment' ); ?></label>
				<?php echo wp_kses_post( NTQ_Helpers::cv_download_link( $application->cv_file_id, $application->cv_file_url ) ); ?>
			</div>
		</div>

		<!-- Job info -->
		<div class="ntq-detail-card">
			<h3><?php esc_html_e( 'Thông Tin Việc Làm', 'ntq-recruitment' ); ?></h3>

			<div class="ntq-detail-row">
				<label><?php esc_html_e( 'Vị Trí:', 'ntq-recruitment' ); ?></label>
				<?php if ( $job ) : ?>
					<a href="<?php echo esc_url( get_permalink( $job ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $job->post_title ); ?>
					</a>
				<?php else : ?>
					<em><?php esc_html_e( 'Ứng tuyển chung', 'ntq-recruitment' ); ?></em>
				<?php endif; ?>
			</div>

			<?php if ( $job ) : ?>
				<div class="ntq-detail-row">
					<label><?php esc_html_e( 'Phòng Ban:', 'ntq-recruitment' ); ?></label>
					<span><?php echo esc_html( NTQ_Helpers::get_terms_string( $job->ID, 'job_department' ) ); ?></span>
				</div>

				<div class="ntq-detail-row">
					<label><?php esc_html_e( 'Địa Điểm:', 'ntq-recruitment' ); ?></label>
					<span><?php echo esc_html( NTQ_Helpers::get_terms_string( $job->ID, 'job_location' ) ); ?></span>
				</div>

				<div class="ntq-detail-row">
					<label><?php esc_html_e( 'Chỉnh Sửa:', 'ntq-recruitment' ); ?></label>
					<a href="<?php echo esc_url( get_edit_post_link( $job->ID ) ); ?>">
						<?php esc_html_e( 'Chỉnh Sửa Trong Admin', 'ntq-recruitment' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="ntq-detail-row">
				<label><?php esc_html_e( 'Trạng Thái:', 'ntq-recruitment' ); ?></label>
				<span class="ntq-badge ntq-badge--<?php echo esc_attr( NTQ_Helpers::status_class( $application->status ) ); ?>">
					<?php echo esc_html( NTQ_Helpers::status_label( $application->status ) ); ?>
				</span>
			</div>

			<div class="ntq-detail-row">
				<label><?php esc_html_e( 'Ngày Ứng Tuyển:', 'ntq-recruitment' ); ?></label>
				<span>
					<?php echo esc_html( date_i18n(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
						strtotime( $application->created_at )
					) ); ?>
				</span>
			</div>
		</div>

	</div><!-- /.ntq-detail-grid -->

	<!-- ── Quick actions ──────────────────────────────────────────────── -->
	<p>
		<a
			href="<?php echo esc_url( wp_nonce_url( add_query_arg( array(
				'page'   => 'ntq-rec-applications',
				'action' => 'delete',
				'id'     => $application->id,
			), admin_url( 'admin.php' ) ), 'ntq_rec_delete_application' ) ); ?>"
			class="button button-secondary delete-btn"
			style="color:#dc2626;border-color:#fca5a5;"
		>
			<?php esc_html_e( 'Xóa Hồ Sơ Này', 'ntq-recruitment' ); ?>
		</a>

		&nbsp;

		<a href="<?php echo esc_url( $back_url ); ?>" class="button">
			<?php esc_html_e( '&larr; Quay Lại Danh Sách', 'ntq-recruitment' ); ?>
		</a>
	</p>

</div><!-- /.wrap -->
