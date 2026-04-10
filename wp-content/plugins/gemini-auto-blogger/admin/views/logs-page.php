<?php
/**
 * Admin View – Logs Page.
 *
 * Rendered by GAB_Admin::page_logs().
 * Available variables:
 *   $logs         – array of stdClass log rows
 *   $total_logs   – int total matching rows
 *   $total_pages  – int total pages
 *   $current_page – int current page number
 *   $level_filter – string active level filter (may be empty)
 *
 * @package GeminiAutoBlogger
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Level badge config.
$level_classes = array(
	'info'    => 'gab-badge--blue',
	'success' => 'gab-badge--green',
	'warning' => 'gab-badge--yellow',
	'error'   => 'gab-badge--red',
);
?>

<div class="wrap gab-wrap">

	<h1 class="gab-page-title">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'Nhật ký hoạt động', 'gemini-auto-blogger' ); ?>
	</h1>

	<!-- ── Toolbar ─────────────────────────────────────────────────────── -->
	<div class="gab-logs-toolbar">

		<!-- Level filter links -->
		<div class="gab-filter-links">
			<?php
			$base_url = admin_url( 'admin.php?page=gemini-auto-blogger-logs' );
			$all_link = add_query_arg( array( 'level' => '', 'paged' => 1 ), $base_url );
			$levels   = array( '', 'info', 'success', 'warning', 'error' );
			$labels   = array(
			''        => __( 'Tất cả', 'gemini-auto-blogger' ),
			'info'    => __( 'Thông tin', 'gemini-auto-blogger' ),
			'success' => __( 'Thành công', 'gemini-auto-blogger' ),
			'warning' => __( 'Cảnh báo', 'gemini-auto-blogger' ),
			'error'   => __( 'Lỗi', 'gemini-auto-blogger' ),
			);
			foreach ( $levels as $level_key ) {
				$url     = add_query_arg( array( 'level' => $level_key, 'paged' => 1 ), $base_url );
				$active  = ( $level_filter === $level_key ) ? ' class="current"' : '';
				$count   = GAB_Logger::count_logs( $level_key );
				printf(
					'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
					esc_url( $url ),
					$active, // already harmless string
					esc_html( $labels[ $level_key ] ),
					esc_html( number_format_i18n( $count ) )
				);
				if ( 'error' !== $level_key ) {
					echo ' | ';
				}
			}
			?>
		</div>

		<!-- Clear button -->
		<button id="gab-clear-logs" class="button button-secondary">
			<?php esc_html_e( 'Xóa tất cả nhật ký', 'gemini-auto-blogger' ); ?>
		</button>
	</div>

	<?php if ( empty( $logs ) ) : ?>
		<div class="gab-card gab-card--notice">
			<p><?php esc_html_e( 'Không có bản ghi nhật ký nào.', 'gemini-auto-blogger' ); ?></p>
		</div>
	<?php else : ?>

		<!-- ── Top pagination ──────────────────────────────────────────── -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav top">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post( paginate_links( array(
						'base'      => add_query_arg( array( 'paged' => '%#%', 'level' => $level_filter ), $base_url ),
						'format'    => '',
						'current'   => $current_page,
						'total'     => $total_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					) ) );
					?>
				</div>
			</div>
		<?php endif; ?>

		<!-- ── Log table ───────────────────────────────────────────────── -->
		<table class="wp-list-table widefat fixed striped gab-log-table">
			<thead>
				<tr>
					<th style="width:50px"><?php esc_html_e( '#', 'gemini-auto-blogger' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Mức độ', 'gemini-auto-blogger' ); ?></th>
					<th><?php esc_html_e( 'Nội dung', 'gemini-auto-blogger' ); ?></th>
					<th style="width:200px"><?php esc_html_e( 'Chi tiết', 'gemini-auto-blogger' ); ?></th>
					<th style="width:160px"><?php esc_html_e( 'Ngày / Giờ', 'gemini-auto-blogger' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<?php
					$ctx_data    = json_decode( $log->context ?? '', true );
					$badge_class = $level_classes[ $log->level ] ?? 'gab-badge--blue';
					$level_vi    = array(
						'info'    => 'Thông tin',
						'success' => 'Thành công',
						'warning' => 'Cảnh báo',
						'error'   => 'Lỗi',
					);
					?>
					<tr>
						<td><?php echo esc_html( $log->id ); ?></td>
						<td>
							<span class="gab-badge <?php echo esc_attr( $badge_class ); ?>">
								<?php echo esc_html( $level_vi[ $log->level ] ?? ucfirst( $log->level ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $log->message ); ?></td>
						<td>
							<?php if ( ! empty( $ctx_data ) ) : ?>
								<details>
									<summary><?php esc_html_e( 'Xem chi tiết', 'gemini-auto-blogger' ); ?></summary>
									<pre class="gab-context-pre"><?php echo esc_html( wp_json_encode( $ctx_data, JSON_PRETTY_PRINT ) ); ?></pre>
								</details>
							<?php else : ?>
								<span class="gab-text-muted">—</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $log->created_at ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- ── Bottom pagination ───────────────────────────────────────── -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post( paginate_links( array(
						'base'      => add_query_arg( array( 'paged' => '%#%', 'level' => $level_filter ), $base_url ),
						'format'    => '',
						'current'   => $current_page,
						'total'     => $total_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					) ) );
					?>
				</div>
			</div>
		<?php endif; ?>

	<?php endif; ?>

</div><!-- /.gab-wrap -->
