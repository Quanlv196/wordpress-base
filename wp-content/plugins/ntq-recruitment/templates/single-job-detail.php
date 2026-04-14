<?php
/**
 * Template: single job detail layout.
 * Wraps $content (the job post content) and the apply form side-by-side.
 * Variables: $content (string), $job_id (int).
 */

defined( 'ABSPATH' ) || exit;

$salary      = get_post_meta( $job_id, '_job_salary', true );
$deadline    = get_post_meta( $job_id, '_job_deadline', true );
$dept_text   = NTQ_Helpers::get_terms_string( $job_id, 'job_department' );
$loc_text    = NTQ_Helpers::get_terms_string( $job_id, 'job_location' );
?>
<div class="ntq-job-detail ntq-rec">

	<!-- ── Two-column layout ──────────────────────────────────────────────── -->
	<div class="ntq-job-detail__layout">

		<!-- Left: job content -->
		<div class="ntq-job-detail__content">
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped – $content already processed by WP ?>
		</div>

		<!-- Right: sticky apply form sidebar -->
		<aside class="ntq-job-detail__sidebar">
			<div class="ntq-apply-card">

				<!-- Meta badges -->
				<div class="ntq-job-detail__badges">
					<?php if ( $deadline ) : ?>
						<span class="ntq-badge ntq-badge--deadline">
							<?php esc_html_e( 'Hạn nộp:', 'ntq-recruitment' ); ?>
							<strong><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $deadline ) ) ); ?></strong>
						</span>
					<?php endif; ?>

					<?php if ( '—' !== $dept_text ) : ?>
						<span class="ntq-badge ntq-badge--recruitment">
							<?php esc_html_e( 'Phòng ban:', 'ntq-recruitment' ); ?>
							<strong><?php echo esc_html( $dept_text ); ?></strong>
						</span>
					<?php endif; ?>

					<?php if ( $salary ) : ?>
						<span class="ntq-badge ntq-badge--salary">
							<?php esc_html_e( 'Mức lương:', 'ntq-recruitment' ); ?>
							<strong><?php echo esc_html( $salary ); ?></strong>
						</span>
					<?php endif; ?>

					<?php if ( '—' !== $loc_text ) : ?>
						<span class="ntq-badge ntq-badge--location">
							<?php esc_html_e( 'Địa điểm:', 'ntq-recruitment' ); ?>
							<strong><?php echo esc_html( $loc_text ); ?></strong>
						</span>
					<?php endif; ?>
				</div>

				<h3 class="ntq-apply-card__title">
					<?php esc_html_e( 'Ứng tuyển tại đây', 'ntq-recruitment' ); ?>
				</h3>
				<?php NTQ_Shortcodes::render_apply_form( array( 'job_id' => $job_id ) ); ?>
			</div>
		</aside>

	</div><!-- .ntq-job-detail__layout -->

</div><!-- .ntq-job-detail -->
