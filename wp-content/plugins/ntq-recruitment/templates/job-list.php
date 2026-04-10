<?php
/**
 * Template: [job_list] shortcode – outer wrapper.
 * Variables available from NTQ_Shortcodes::shortcode_list():
 *   $limit, $offset (from shortcode_atts, page is always 1 on first load).
 */

defined( 'ABSPATH' ) || exit;
?>
<div
	id="ntq-rec-job-list"
	class="ntq-job-list ntq-rec"
	data-limit="<?php echo esc_attr( $limit ); ?>"
	data-offset="<?php echo esc_attr( $offset ); ?>"
>
	<?php
	// ── Initial WP_Query (server-side render for SEO & non-JS fallback) ──────
	$initial_args = array(
		'post_type'      => 'job',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'offset'         => $offset,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	$jobs_query = new WP_Query( $initial_args );

	if ( $jobs_query->have_posts() ) :
		while ( $jobs_query->have_posts() ) :
			$jobs_query->the_post();
			include __DIR__ . '/job-card.php';
		endwhile;
		wp_reset_postdata();

		// Pagination for initial render
		$total_pages = ceil( $jobs_query->found_posts / $limit );
		NTQ_Helpers::render_pagination( $total_pages, 1 );
	else :
		echo '<p class="ntq-no-jobs">' . esc_html__( 'Không có vị trí tuyển dụng nào.', 'ntq-recruitment' ) . '</p>';
	endif;
	?>
</div>
