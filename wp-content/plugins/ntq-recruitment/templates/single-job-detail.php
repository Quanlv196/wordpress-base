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
						<span class="ntq-badge ntq-badge--green">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
							<?php esc_html_e( 'Hạn nộp:', 'ntq-recruitment' ); ?>
							<strong><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $deadline ) ) ); ?></strong>
						</span>
					<?php endif; ?>

					<?php if ( '—' !== $dept_text ) : ?>
						<span class="ntq-badge ntq-badge--green">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 21h18M3 7v14M21 7v14M6 21V11M10 21V11M14 21V11M18 21V11M1 7l11-4 11 4"/></svg>
							<?php esc_html_e( 'Phòng ban:', 'ntq-recruitment' ); ?>
							<strong><?php echo esc_html( $dept_text ); ?></strong>
						</span>
					<?php endif; ?>

					<?php if ( $salary ) : ?>
						<span class="ntq-badge ntq-badge--green">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
							<?php esc_html_e( 'Mức lương:', 'ntq-recruitment' ); ?>
							<strong><?php echo esc_html( $salary ); ?></strong>
						</span>
					<?php endif; ?>

					<?php if ( '—' !== $loc_text ) : ?>
						<span class="ntq-badge ntq-badge--green">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
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
