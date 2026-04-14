<?php
/**
 * Template: [job_filter] shortcode.
 *
 * Variables available:
 *   $filter_type (string) – passed from the shortcode handler.
 *                           '' = classic dropdown form (default).
 *                           'department' | 'location' = tab-style filter.
 */

defined( 'ABSPATH' ) || exit;

// ── Tab mode ──────────────────────────────────────────────────────────────────
if ( ! empty( $filter_type ) ) :

	// Map shortcode type → taxonomy + AJAX key
	$tab_map = array(
		'department' => array( 'taxonomy' => 'job_department', 'key' => 'department' ),
		'location'   => array( 'taxonomy' => 'job_location',   'key' => 'location' ),
	);

	if ( ! isset( $tab_map[ $filter_type ] ) ) {
		return; // Unknown type – render nothing
	}

	$taxonomy   = $tab_map[ $filter_type ]['taxonomy'];
	$filter_key = $tab_map[ $filter_type ]['key'];

	// Total published jobs
	$total_jobs = (int) wp_count_posts( 'job' )->publish;

	// Terms with at least one published job (WP counts only published by default)
	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => true,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );

	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}

	// Currently selected value (for non-JS page load fallback)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$selected_val = sanitize_text_field( wp_unslash( $_GET[ $filter_key ] ?? '' ) );
	?>
<div
	class="ntq-tab-filter ntq-rec"
	data-filter-type="<?php echo esc_attr( $filter_key ); ?>"
	role="group"
	aria-label="<?php esc_attr_e( 'Lọc công việc', 'ntq-recruitment' ); ?>"
>
	<button
		type="button"
		class="ntq-tab-filter__item<?php echo '' === $selected_val ? ' ntq-tab-filter__item--active' : ''; ?>"
		data-value=""
	>
		<?php esc_html_e( 'Tất cả', 'ntq-recruitment' ); ?>
		<span class="ntq-tab-filter__count">(<?php echo esc_html( $total_jobs ); ?>)</span>
	</button>

	<?php foreach ( $terms as $term ) : ?>
		<button
			type="button"
			class="ntq-tab-filter__item<?php echo $selected_val === $term->slug ? ' ntq-tab-filter__item--active' : ''; ?>"
			data-value="<?php echo esc_attr( $term->slug ); ?>"
		>
			<?php echo esc_html( $term->name ); ?>
			<span class="ntq-tab-filter__count">(<?php echo esc_html( $term->count ); ?>)</span>
		</button>
	<?php endforeach; ?>
</div>

<?php
// ── Classic dropdown form (default) ──────────────────────────────────────────
else :

	$departments = NTQ_Helpers::get_term_options( 'job_department' );
	$locations   = NTQ_Helpers::get_term_options( 'job_location' );

	$all_jobs = get_posts( array(
		'post_type'      => 'job',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$selected_dept = sanitize_text_field( wp_unslash( $_GET['department'] ?? '' ) );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$selected_loc  = sanitize_text_field( wp_unslash( $_GET['location'] ?? '' ) );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
<?php endif; ?>
