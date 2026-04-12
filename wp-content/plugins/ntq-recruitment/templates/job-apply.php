<?php
/**
 * Template: job application form ([job_apply] shortcode / appended to single job).
 * Variables available: $job_id (int).
 */

defined( 'ABSPATH' ) || exit;

$job        = $job_id ? get_post( $job_id ) : null;
$is_general = ! $job_id; // true when used as a general application form

// If a specific job_id was given but the job is invalid/unpublished, bail.
if ( $job_id && ( ! $job || 'job' !== $job->post_type || 'publish' !== $job->post_status ) ) {
	return;
}

if ( $is_general ) {
	$all_jobs    = get_posts( array(
		'post_type'      => 'job',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	$departments = NTQ_Helpers::get_term_options( 'job_department' );
}
?>
<div class="ntq-apply-section ntq-rec">

	<div class="ntq-form-message" role="alert" aria-live="polite"></div>

	<form
		id="ntq-rec-apply-form"
		class="ntq-apply-form"
		method="post"
		enctype="multipart/form-data"
		novalidate
	>
		<?php wp_nonce_field( 'ntq_rec_nonce', '_wpnonce' ); ?>
		<?php if ( ! $is_general ) : ?>
			<input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>">
		<?php endif; ?>

		<!-- Row 1: Họ và tên (full width) -->
		<div class="ntq-form-group">
			<label for="ntq-name">
				<?php esc_html_e( 'Họ và tên', 'ntq-recruitment' ); ?>
				<span class="ntq-required" aria-hidden="true">*</span>
			</label>
			<input
				type="text"
				id="ntq-name"
				name="applicant_name"
				maxlength="255"
				autocomplete="name"
				required
				placeholder="<?php esc_attr_e( 'Nhập tên đầy đủ', 'ntq-recruitment' ); ?>"
			>
			<span class="ntq-field-error error-name" role="alert"></span>
		</div>

		<!-- Row 2: Email | Số điện thoại -->
		<div class="ntq-form-row">
			<div class="ntq-form-group">
				<label for="ntq-email">
					<?php esc_html_e( 'Email', 'ntq-recruitment' ); ?>
					<span class="ntq-required" aria-hidden="true">*</span>
				</label>
				<input
					type="email"
					id="ntq-email"
					name="email"
					maxlength="255"
					autocomplete="email"
					required
					placeholder="<?php esc_attr_e( 'Nhập email', 'ntq-recruitment' ); ?>"
				>
				<span class="ntq-field-error error-email" role="alert"></span>
			</div>

			<div class="ntq-form-group">
				<label for="ntq-phone">
					<?php esc_html_e( 'Số điện thoại', 'ntq-recruitment' ); ?>
					<span class="ntq-required" aria-hidden="true">*</span>
				</label>
				<input
					type="tel"
					id="ntq-phone"
					name="phone"
					maxlength="50"
					autocomplete="tel"
					required
					placeholder="<?php esc_attr_e( 'Nhập số điện thoại', 'ntq-recruitment' ); ?>"
				>
				<span class="ntq-field-error error-phone" role="alert"></span>
			</div>
		</div>

		<?php if ( $is_general ) : ?>
		<!-- Row 3: Vị trí ứng tuyển | Phòng ban (general form only) -->
		<div class="ntq-form-row">
			<div class="ntq-form-group">
				<label for="ntq-job-position">
					<?php esc_html_e( 'Vị trí ứng tuyển', 'ntq-recruitment' ); ?>
					<span class="ntq-required" aria-hidden="true">*</span>
				</label>
				<div class="ntq-select-wrap">
					<select id="ntq-job-position" name="job_id" required>
						<option value=""><?php esc_html_e( 'Chọn vị trí ứng tuyển', 'ntq-recruitment' ); ?></option>
						<?php foreach ( $all_jobs as $job_post ) : ?>
							<option value="<?php echo esc_attr( $job_post->ID ); ?>">
								<?php echo esc_html( $job_post->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<span class="ntq-field-error error-job" role="alert"></span>
			</div>

			<div class="ntq-form-group">
				<label for="ntq-department">
					<?php esc_html_e( 'Phòng ban', 'ntq-recruitment' ); ?>
				</label>
				<div class="ntq-select-wrap">
					<select id="ntq-department" name="department">
						<option value=""><?php esc_html_e( 'Chọn phòng ban', 'ntq-recruitment' ); ?></option>
						<?php foreach ( $departments as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>">
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- CV Upload -->
		<div class="ntq-form-group">
			<label>
				<?php esc_html_e( 'Tải lên CV / Hồ sơ cá nhân', 'ntq-recruitment' ); ?>
				<span class="ntq-required" aria-hidden="true">*</span>
				<span class="ntq-label-hint"><?php esc_html_e( '(tối đa 2MB)', 'ntq-recruitment' ); ?></span>
			</label>
			<label class="ntq-file-label" for="ntq-cv">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
				<span class="ntq-file-label__text"><?php esc_html_e( 'Chọn tệp tin', 'ntq-recruitment' ); ?></span>
				<input
					type="file"
					id="ntq-cv"
					name="cv_file"
					accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
					required
				>
			</label>
			<span class="ntq-field-error error-cv" role="alert"></span>
			<small class="ntq-file-hint"><?php esc_html_e( 'Định dạng chấp nhận: PDF, DOC, DOCX.', 'ntq-recruitment' ); ?></small>
		</div>

		<!-- Submit (right-aligned) -->
		<div class="ntq-form-actions">
			<button type="submit" class="ntq-submit-btn">
				<?php esc_html_e( 'Gửi ứng tuyển', 'ntq-recruitment' ); ?>
			</button>
		</div>
	</form>
</div>
