<?php
/**
 * Template: [job_filter] shortcode.
 * Variables available: none (fetches own data).
 */

defined( 'ABSPATH' ) || exit;

$departments = NTQ_Helpers::get_term_options( 'job_department' );
$locations   = NTQ_Helpers::get_term_options( 'job_location' );

// All published jobs for the position dropdown
$all_jobs = get_posts( array(
	'post_type'      => 'job',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
) );

// Preserve currently selected values (for non-JS fallback or initial page load)
$selected_dept = sanitize_text_field( wp_unslash( $_GET['department'] ?? '' ) );
$selected_loc  = sanitize_text_field( wp_unslash( $_GET['location'] ?? '' ) );
$selected_job  = absint( $_GET['job_id'] ?? 0 );
?>
<form
	id="ntq-rec-filter-form"
	class="ntq-filter-form ntq-rec"
	method="GET"
	action="<?php echo esc_url( get_permalink() ?: home_url() ); ?>"
>
	<div class="ntq-select-wrap">
		<select name="job_id" aria-label="<?php esc_attr_e( 'Lọc Theo Vị Trí', 'ntq-recruitment' ); ?>">
			<option value="0"><?php esc_html_e( 'Tất Cả Vị Trí', 'ntq-recruitment' ); ?></option>
			<?php foreach ( $all_jobs as $job_post ) : ?>
				<option
					value="<?php echo esc_attr( $job_post->ID ); ?>"
					<?php selected( $selected_job, $job_post->ID ); ?>
				>
					<?php echo esc_html( $job_post->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="ntq-select-wrap">
		<select name="department" aria-label="<?php esc_attr_e( 'Lọc Theo Phòng Ban', 'ntq-recruitment' ); ?>">
			<option value=""><?php esc_html_e( 'Tất Cả Phòng Ban', 'ntq-recruitment' ); ?></option>
			<?php foreach ( $departments as $term ) : ?>
				<option
					value="<?php echo esc_attr( $term->slug ); ?>"
					<?php selected( $selected_dept, $term->slug ); ?>
				>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="ntq-select-wrap">
		<select name="location" aria-label="<?php esc_attr_e( 'Lọc Theo Địa Điểm', 'ntq-recruitment' ); ?>">
			<option value=""><?php esc_html_e( 'Tất Cả Địa Điểm', 'ntq-recruitment' ); ?></option>
			<?php foreach ( $locations as $term ) : ?>
				<option
					value="<?php echo esc_attr( $term->slug ); ?>"
					<?php selected( $selected_loc, $term->slug ); ?>
				>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<button type="submit">
		<?php esc_html_e( 'Tìm Kiếm Việc Làm', 'ntq-recruitment' ); ?>
	</button>
</form>
