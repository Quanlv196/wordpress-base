<?php
/**
 * Template: [job_filter] shortcode.
 * Variables available: none (fetches own data).
 */

defined( 'ABSPATH' ) || exit;

$departments = NTQ_Helpers::get_term_options( 'job_department' );
$locations   = NTQ_Helpers::get_term_options( 'job_location' );

// Preserve currently selected values (for non-JS fallback or initial page load)
$selected_dept = sanitize_text_field( wp_unslash( $_GET['department'] ?? '' ) );
$selected_loc  = sanitize_text_field( wp_unslash( $_GET['location'] ?? '' ) );
?>
<form
	id="ntq-rec-filter-form"
	class="ntq-filter-form ntq-rec"
	method="GET"
	action="<?php echo esc_url( get_permalink() ?: home_url() ); ?>"
>
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

	<button type="submit">
		<?php esc_html_e( 'Tìm Kiếm Việc Làm', 'ntq-recruitment' ); ?>
	</button>
</form>
