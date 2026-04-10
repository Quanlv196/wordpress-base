<?php
/**
 * Template: job application form ([job_apply] shortcode / appended to single job).
 * Variables available: $job_id (int).
 */

defined( 'ABSPATH' ) || exit;

$job       = $job_id ? get_post( $job_id ) : null;
$is_general = ! $job_id; // true when used as a general application form

// If a specific job_id was given but the job is invalid/unpublished, bail.
if ( $job_id && ( ! $job || 'job' !== $job->post_type || 'publish' !== $job->post_status ) ) {
	return;
}

$form_title = $is_general
	? __( 'Nộp Hồ Sơ Tổng Hợp', 'ntq-recruitment' )
	: __( 'Ứng Tuyển Vị Trí Này', 'ntq-recruitment' );
?>
<div class="ntq-apply-section ntq-rec">
	<!-- <h2><?php echo esc_html( $form_title ); ?></h2> -->

	<div class="ntq-form-message" role="alert" aria-live="polite"></div>

	<form
		id="ntq-rec-apply-form"
		class="ntq-apply-form"
		method="post"
		enctype="multipart/form-data"
		novalidate
	>
		<?php wp_nonce_field( 'ntq_rec_nonce', '_wpnonce' ); ?>
		<input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>">

		<!-- Name -->
		<div class="ntq-form-group">
			<label for="ntq-name">
				<?php esc_html_e( 'Họ Và Tên', 'ntq-recruitment' ); ?>
				<span class="ntq-required" aria-hidden="true">*</span>
			</label>
			<input
				type="text"
				id="ntq-name"
				name="applicant_name"
				maxlength="255"
				autocomplete="name"
				required
				placeholder="<?php esc_attr_e( 'Nguyễn Văn A', 'ntq-recruitment' ); ?>"
			>
			<span class="ntq-field-error error-name" role="alert"></span>
		</div>

		<!-- Phone -->
		<div class="ntq-form-group">
			<label for="ntq-phone">
				<?php esc_html_e( 'Số Điện Thoại', 'ntq-recruitment' ); ?>
				<span class="ntq-required" aria-hidden="true">*</span>
			</label>
			<input
				type="tel"
				id="ntq-phone"
				name="phone"
				maxlength="50"
				autocomplete="tel"
				required
				placeholder="<?php esc_attr_e( '0901 234 567', 'ntq-recruitment' ); ?>"
			>
			<span class="ntq-field-error error-phone" role="alert"></span>
		</div>

		<!-- Email -->
		<div class="ntq-form-group">
			<label for="ntq-email">
				<?php esc_html_e( 'Địa Chỉ Email', 'ntq-recruitment' ); ?>
				<span class="ntq-required" aria-hidden="true">*</span>
			</label>
			<input
				type="email"
				id="ntq-email"
				name="email"
				maxlength="255"
				autocomplete="email"
				required
				placeholder="<?php esc_attr_e( 'example@email.com', 'ntq-recruitment' ); ?>"
			>
			<span class="ntq-field-error error-email" role="alert"></span>
		</div>

		<!-- CV Upload -->
		<div class="ntq-form-group">
			<label for="ntq-cv">
				<?php esc_html_e( 'Tải Lên CV', 'ntq-recruitment' ); ?>
				<span class="ntq-required" aria-hidden="true">*</span>
			</label>
			<input
				type="file"
				id="ntq-cv"
				name="cv_file"
				accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
				required
			>
			<span class="ntq-field-error error-cv" role="alert"></span>
			<small style="color:#6b7280;font-size:12px;display:block;margin-top:4px;">
				<?php esc_html_e( 'Định dạng chấp nhận: PDF, DOC, DOCX.', 'ntq-recruitment' ); ?>
			</small>
		</div>

		<button type="submit" class="ntq-submit-btn">
			<?php esc_html_e( 'Nộp Hồ Sơ', 'ntq-recruitment' ); ?>
		</button>
	</form>
</div>
