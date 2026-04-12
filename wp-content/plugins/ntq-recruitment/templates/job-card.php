<?php
/**
 * Template: individual job card (used inside job-list.php and AJAX response).
 * Assumes being called inside a WP_Query loop (have_posts/the_post already called).
 */

defined( 'ABSPATH' ) || exit;

$departments_text = NTQ_Helpers::get_terms_string( get_the_ID(), 'job_department' );
$locations_text   = NTQ_Helpers::get_terms_string( get_the_ID(), 'job_location' );
$salary           = get_post_meta( get_the_ID(), '_job_salary', true );
$deadline         = get_post_meta( get_the_ID(), '_job_deadline', true );
?>
<div class="ntq-job-card">
	<div class="ntq-job-card__body">
		<h3 class="ntq-job-card__title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h3>

		<div class="ntq-job-card__meta">
			<?php if ( '—' !== $departments_text ) : ?>
				<span class="ntq-job-card__tag">
					&#127970; <?php echo esc_html( $departments_text ); ?>
				</span>
			<?php endif; ?>

			<?php if ( '—' !== $locations_text ) : ?>
				<span class="ntq-job-card__tag">
					&#128205; <?php echo esc_html( $locations_text ); ?>
				</span>
			<?php endif; ?>

			<?php if ( $salary ) : ?>
				<span class="ntq-job-card__tag ntq-job-card__tag--salary">
					&#128176; <?php echo esc_html( $salary ); ?>
				</span>
			<?php endif; ?>

			<?php if ( $deadline ) : ?>
				<span class="ntq-job-card__tag">
					&#128197; <?php esc_html_e( 'Hạn nộp:', 'ntq-recruitment' ); ?> <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $deadline ) ) ); ?>
				</span>
			<?php else : ?>
				<span class="ntq-job-card__tag">
					&#128197; <?php echo esc_html( get_the_date() ); ?>
				</span>
			<?php endif; ?>
		</div>

		<p class="ntq-job-card__excerpt">
			<?php echo esc_html( wp_trim_words( get_the_excerpt(), 20, '…' ) ); ?>
		</p>
	</div>

	<div class="ntq-job-card__action">
		<a href="<?php the_permalink(); ?>" class="ntq-btn">
			<?php esc_html_e( 'Xem Chi Tiết', 'ntq-recruitment' ); ?>
		</a>
	</div>
</div>
