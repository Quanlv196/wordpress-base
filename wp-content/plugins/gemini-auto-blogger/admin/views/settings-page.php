<?php
/**
 * Admin View – Settings Page.
 *
 * Rendered by GAB_Admin::page_settings().
 * Available variables: $settings (array), $scheduler (GAB_Scheduler).
 *
 * @package GeminiAutoBlogger
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Gather data for the status panel.
$next_run  = $scheduler->get_next_run();
$last_run  = $scheduler->get_last_run();
$generated = new WP_Query( array(
	'post_type'      => 'post',
	'post_status'    => 'any',
	'meta_key'       => '_gab_generated',
	'meta_value'     => '1',
	'fields'         => 'ids',
	'no_found_rows'  => false,
	'posts_per_page' => 1,
) );
$total_generated = $generated->found_posts;

// All registered categories.
$all_categories = get_categories( array( 'hide_empty' => false ) );

// All users who can publish posts.
$all_authors = get_users( array( 'capability' => 'publish_posts', 'fields' => array( 'ID', 'display_name' ) ) );
?>

<div class="wrap gab-wrap">

	<h1 class="gab-page-title">
		<span class="dashicons dashicons-robot"></span>
		<?php esc_html_e( 'AI Auto Blogger', 'gemini-auto-blogger' ); ?>
		<span class="gab-version">v<?php echo esc_html( GAB_VERSION ); ?></span>
	</h1>
	<!-- Tiêu đề trang cài đặt -->

	<?php settings_errors( 'gab_settings_group' ); ?>

	<!-- ── Status panel ──────────────────────────────────────────────── -->
	<div class="gab-status-bar">
		<div class="gab-stat">
			<span class="gab-stat-label"><?php esc_html_e( 'Trạng thái', 'gemini-auto-blogger' ); ?></span>
			<?php if ( ! empty( $settings['cron_enabled'] ) ) : ?>
				<span class="gab-badge gab-badge--green"><?php esc_html_e( 'Đang hoạt động', 'gemini-auto-blogger' ); ?></span>
			<?php else : ?>
				<span class="gab-badge gab-badge--grey"><?php esc_html_e( 'Tạm dừng', 'gemini-auto-blogger' ); ?></span>
			<?php endif; ?>
		</div>
		<div class="gab-stat">
			<span class="gab-stat-label"><?php esc_html_e( 'Bài đã tạo', 'gemini-auto-blogger' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $total_generated ) ); ?></strong>
		</div>
		<div class="gab-stat">
			<span class="gab-stat-label"><?php esc_html_e( 'Lần chạy cuối', 'gemini-auto-blogger' ); ?></span>
			<strong><?php echo $last_run ? esc_html( $last_run ) : esc_html__( 'Chưa có', 'gemini-auto-blogger' ); ?></strong>
		</div>
		<div class="gab-stat">
			<span class="gab-stat-label"><?php esc_html_e( 'Lần chạy tiếp theo', 'gemini-auto-blogger' ); ?></span>
			<strong>
				<?php
				if ( $next_run ) {
					echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_run ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
				} else {
					esc_html_e( 'Chưa lên lịch', 'gemini-auto-blogger' );
				}
				?>
			</strong>
		</div>
		<div class="gab-stat gab-stat--actions">
			<button id="gab-generate-now" type="button" class="button button-primary">
				<?php esc_html_e( 'Tạo bài ngay', 'gemini-auto-blogger' ); ?>
			</button>
			<span id="gab-generate-result" class="gab-inline-result"></span>
		</div>
	</div>

	<!-- ── Settings form ─────────────────────────────────────────────── -->
	<form method="post" action="options.php" id="gab-settings-form">
		<?php settings_fields( GAB_Admin::SETTINGS_GROUP ); ?>

		<div class="gab-grid">

			<!-- ── LEFT COLUMN ─────────────────────────────────────────── -->
			<div class="gab-col">

				<!-- Section: API Configuration -->
				<div class="gab-card">
					<h2><?php esc_html_e( '🔑 Cấu hình API', 'gemini-auto-blogger' ); ?></h2>

					<table class="form-table gab-table" role="presentation">
						<tr>
						<th><label for="gab_groq_api_key"><?php esc_html_e( 'Groq API Key (gen text)', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<input type="password"
								       id="gab_groq_api_key"
								       name="gab_settings[groq_api_key]"
								       value="<?php echo esc_attr( $settings['groq_api_key'] ?? '' ); ?>"
								       class="regular-text"
								       autocomplete="new-password"
								       placeholder="gsk_...">
								<p class="description"><?php esc_html_e( 'Bắt buộc để tạo nội dung bài viết.', 'gemini-auto-blogger' ); ?></p>
							</td>
						</tr>
						<tr>
						<th><label for="gab_cf_account_id"><?php esc_html_e( 'Cloudflare Account ID', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<input type="text"
								       id="gab_cf_account_id"
								       name="gab_settings[cf_account_id]"
								       value="<?php echo esc_attr( $settings['cf_account_id'] ?? '' ); ?>"
								       class="regular-text"
								       placeholder="a1b2c3d4e5f6...">
								<p class="description"><?php esc_html_e( 'Cloudflare Account ID dùng để tạo ảnh AI.', 'gemini-auto-blogger' ); ?> <a href="https://dash.cloudflare.com/" target="_blank" rel="noopener noreferrer">Cloudflare Dashboard</a></p>
							</td>
						</tr>
						<tr>
						<th><label for="gab_gemini_api_key"><?php esc_html_e( 'Cloudflare API Token (gen ảnh)', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<input type="password"
								       id="gab_gemini_api_key"
								       name="gab_settings[gemini_api_key]"
								       value="<?php echo esc_attr( $settings['gemini_api_key'] ?? '' ); ?>"
								       class="regular-text"
								       autocomplete="new-password"
								       placeholder="your-cf-api-token...">
								<br>
								<button type="button" id="gab-test-api" class="button button-secondary" style="margin-top:6px">
									<?php esc_html_e( 'Kiểm tra API', 'gemini-auto-blogger' ); ?>
								</button>
								<span id="gab-api-test-result" class="gab-inline-result"></span>
								<p class="description"><?php esc_html_e( 'Cloudflare Workers AI API Token để tạo ảnh bằng Stable Diffusion XL. Nếu bỏ trống, plugin vẫn tạo bài bằng text Groq nhưng không có ảnh AI.', 'gemini-auto-blogger' ); ?></p>
								<p class="description"><?php esc_html_e( 'Lấy API Token tại:', 'gemini-auto-blogger' ); ?> <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noopener noreferrer">Cloudflare API Tokens</a></p>
							</td>
						</tr>
						<tr>
							<th><label for="gab_text_model"><?php esc_html_e( 'Model tạo văn bản', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<select id="gab_text_model" name="gab_settings[text_model]">
									<option value="" <?php selected( $settings['text_model'], '' ); ?>><?php esc_html_e( '— Mặc định Groq —', 'gemini-auto-blogger' ); ?></option>
									<?php
									$groq_text_models = array(
										'llama-3.3-70b-versatile' => 'LLaMA 3.3 70B Versatile (khuyến nghị)',
										'llama-3.1-8b-instant'    => 'LLaMA 3.1 8B Instant (nhanh)',
										'mixtral-8x7b-32768'      => 'Mixtral 8x7B (context dài)',
										'gemma2-9b-it'            => 'Gemma 2 9B',
									);
									foreach ( $groq_text_models as $val => $label ) {
										printf(
											'<option value="%s"%s>%s</option>',
											esc_attr( $val ),
											selected( $settings['text_model'], $val, false ),
											esc_html( $label )
										);
									}
									?>
								</select>
								<p class="description"><?php esc_html_e( 'Có thể để trống để dùng model Groq mặc định.', 'gemini-auto-blogger' ); ?></p>
							</td>
						</tr>
						<tr>
						<th><?php esc_html_e( 'Model tạo hình ảnh', 'gemini-auto-blogger' ); ?></th>
							<td>
								<p class="description"><?php esc_html_e( 'Stable Diffusion XL (Cloudflare Workers AI) — model cố định, không cần chọn.', 'gemini-auto-blogger' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Section: Topic Configuration (per-category) -->
				<div class="gab-card">
					<h2><?php esc_html_e( '📋 Chủ đề theo danh mục', 'gemini-auto-blogger' ); ?></h2>
					<p class="description" style="margin:0 0 12px">
						<?php esc_html_e( 'Nhập từ khóa/chủ đề cho từng danh mục (mỗi dòng một chủ đề). Plugin sẽ tự động chọn chủ đề và gán bài vào đúng danh mục. Nếu để trống tất cả, plugin dùng danh sách chủ đề chung bên dưới.', 'gemini-auto-blogger' ); ?>
					</p>

					<table class="form-table gab-table" role="presentation">
						<?php
						$category_topics = (array) ( $settings['category_topics'] ?? array() );
						foreach ( $all_categories as $cat ) :
							$cat_val = $category_topics[ (int) $cat->term_id ] ?? '';
						?>
						<tr>
							<th>
								<label for="gab_cat_topics_<?php echo esc_attr( $cat->term_id ); ?>">
									<?php echo esc_html( $cat->name ); ?>
									<span style="font-weight:normal;color:#888">(<?php echo esc_html( $cat->count ); ?>)</span>
								</label>
							</th>
							<td>
								<textarea id="gab_cat_topics_<?php echo esc_attr( $cat->term_id ); ?>"
								          name="gab_settings[category_topics][<?php echo esc_attr( $cat->term_id ); ?>]"
								          rows="3"
								          class="large-text"
								          placeholder="<?php esc_attr_e( 'Từ khóa 1&#10;Từ khóa 2&#10;Từ khóa 3…', 'gemini-auto-blogger' ); ?>"><?php echo esc_textarea( $cat_val ); ?></textarea>
							</td>
						</tr>
						<?php endforeach; ?>

						<tr>
							<th><label for="gab_topics"><?php esc_html_e( 'Chủ đề chung (fallback)', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<textarea id="gab_topics"
								          name="gab_settings[topics]"
								          rows="4"
								          class="large-text"
								          placeholder="<?php esc_attr_e( 'Dùng khi không có chủ đề theo danh mục nào được cấu hình…', 'gemini-auto-blogger' ); ?>"><?php echo esc_textarea( $settings['topics'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Bài từ danh sách này sẽ được gán vào danh mục mặc định bên dưới.', 'gemini-auto-blogger' ); ?></p>
							</td>
						</tr>

						<tr>
							<th><?php esc_html_e( 'Danh mục mặc định (cho chủ đề chung)', 'gemini-auto-blogger' ); ?></th>
							<td>
								<select name="gab_settings[categories][]"
								        multiple
								        size="5"
								        style="min-width:220px">
									<?php foreach ( $all_categories as $cat ) : ?>
										<option value="<?php echo esc_attr( $cat->term_id ); ?>"
											<?php echo in_array( (int) $cat->term_id, array_map( 'intval', (array) $settings['categories'] ), true ) ? 'selected' : ''; ?>>
											<?php echo esc_html( $cat->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Giữ Ctrl (Windows) hoặc ⌘ (Mac) để chọn nhiều danh mục.', 'gemini-auto-blogger' ); ?></p>
							</td>
						</tr>

						<tr>
							<th><?php esc_html_e( 'Thứ tự chủ đề', 'gemini-auto-blogger' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="radio" name="gab_settings[topic_order]" value="random" <?php checked( $settings['topic_order'], 'random' ); ?>>
										<?php esc_html_e( 'Ngẫu nhiên – chọn một chủ đề bất kỳ mỗi lần', 'gemini-auto-blogger' ); ?>
									</label><br>
									<label>
										<input type="radio" name="gab_settings[topic_order]" value="sequential" <?php checked( $settings['topic_order'], 'sequential' ); ?>>
										<?php esc_html_e( 'Tuần tự – lần lượt qua từng chủ đề theo thứ tự', 'gemini-auto-blogger' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Tránh trùng lặp', 'gemini-auto-blogger' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="gab_settings[avoid_duplicates]" value="1" <?php checked( $settings['avoid_duplicates'], 1 ); ?>>
									<?php esc_html_e( 'Bỏ qua các chủ đề đã đăng gần đây (20 bài gần nhất)', 'gemini-auto-blogger' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

			</div><!-- /.gab-col -->

			<!-- ── RIGHT COLUMN ────────────────────────────────────────── -->
			<div class="gab-col">

				<!-- Section: Scheduling -->
				<div class="gab-card">
					<h2><?php esc_html_e( '⏰ Lịch đăng bài tự động', 'gemini-auto-blogger' ); ?></h2>

					<table class="form-table gab-table" role="presentation">
						<tr>
							<th><?php esc_html_e( 'Bật tự động hóa', 'gemini-auto-blogger' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="gab_settings[cron_enabled]" value="1" <?php checked( $settings['cron_enabled'], 1 ); ?>>
									<?php esc_html_e( 'Tự động tạo bài viết theo lịch đã cài đặt bên dưới', 'gemini-auto-blogger' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Chu kỳ tạo bài', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<div style="display:flex;gap:8px;align-items:center">
									<?php esc_html_e( 'Mỗi', 'gemini-auto-blogger' ); ?>
									<input type="number"
									       name="gab_settings[interval_value]"
									       value="<?php echo esc_attr( $settings['interval_value'] ); ?>"
									       min="1"
									       max="365"
									       style="width:70px"
									       class="small-text">
									<select name="gab_settings[interval_unit]">
									<option value="minutes" <?php selected( $settings['interval_unit'], 'minutes' ); ?>><?php esc_html_e( 'Phút', 'gemini-auto-blogger' ); ?></option>
									<option value="hours"   <?php selected( $settings['interval_unit'], 'hours' ); ?>><?php esc_html_e( 'Giờ', 'gemini-auto-blogger' ); ?></option>
									<option value="days"    <?php selected( $settings['interval_unit'], 'days' ); ?>><?php esc_html_e( 'Ngày', 'gemini-auto-blogger' ); ?></option>
									</select>
								</div>
								<p class="description"><?php esc_html_e( 'Để cron hoạt động ổn định trên site ít lượt truy cập, hãy cấu hình system cron thật để gọi wp-cron.php.', 'gemini-auto-blogger' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="gab_posts_per_run"><?php esc_html_e( 'Số bài mỗi lần chạy', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<input type="number"
								       id="gab_posts_per_run"
								       name="gab_settings[posts_per_run]"
								       value="<?php echo esc_attr( $settings['posts_per_run'] ); ?>"
								       min="1" max="5"
								       class="small-text">
								<p class="description"><?php esc_html_e( 'Số bài viết được tạo mỗi lần lịch kích hoạt (tối đa 5 bài).', 'gemini-auto-blogger' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Section: Publish Settings -->
				<div class="gab-card">
					<h2><?php esc_html_e( '📢 Cài đặt đăng bài', 'gemini-auto-blogger' ); ?></h2>

					<table class="form-table gab-table" role="presentation">
						<tr>
							<th><label for="gab_publish_status"><?php esc_html_e( 'Trạng thái bài viết', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<select id="gab_publish_status" name="gab_settings[publish_status]">
								<option value="publish" <?php selected( $settings['publish_status'], 'publish' ); ?>><?php esc_html_e( 'Đăng ngay lập tức', 'gemini-auto-blogger' ); ?></option>
								<option value="draft"   <?php selected( $settings['publish_status'], 'draft' ); ?>><?php esc_html_e( 'Bản nháp (duyệt trước khi đăng)', 'gemini-auto-blogger' ); ?></option>
								<option value="future"  <?php selected( $settings['publish_status'], 'future' ); ?>><?php esc_html_e( 'Hẹn giờ (đăng vào ngày tương lai)', 'gemini-auto-blogger' ); ?></option>
								</select>
							</td>
						</tr>
						<tr id="gab-publish-delay-row">
							<th><label for="gab_publish_delay"><?php esc_html_e( 'Thời gian trì hoãn', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<input type="number"
								       id="gab_publish_delay"
								       name="gab_settings[publish_delay]"
								       value="<?php echo esc_attr( $settings['publish_delay'] ); ?>"
								       min="0"
								       class="small-text">
							<?php esc_html_e( 'giờ kể từ bây giờ', 'gemini-auto-blogger' ); ?>
							<p class="description"><?php esc_html_e( 'Chỉ áp dụng khi Trạng thái bài viết là "Hẹn giờ". 0 = đăng ngay.', 'gemini-auto-blogger' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="gab_author_id"><?php esc_html_e( 'Tác giả bài viết', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<select id="gab_author_id" name="gab_settings[author_id]">
									<option value="0" <?php selected( $settings['author_id'], 0 ); ?>><?php esc_html_e( '— Tác giả ngẫu nhiên —', 'gemini-auto-blogger' ); ?></option>
									<?php foreach ( $all_authors as $author ) : ?>
										<option value="<?php echo esc_attr( $author->ID ); ?>" <?php selected( $settings['author_id'], $author->ID ); ?>>
											<?php echo esc_html( $author->display_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Chọn "Ngẫu nhiên" để luân phiên giữa các tác giả có quyền đăng bài.', 'gemini-auto-blogger' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Section: Image Settings -->
				<div class="gab-card">
					<h2><?php esc_html_e( '🖼 Cài đặt hình ảnh', 'gemini-auto-blogger' ); ?></h2>

					<table class="form-table gab-table" role="presentation">
						<tr>
							<th><?php esc_html_e( 'Tạo hình ảnh tự động', 'gemini-auto-blogger' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="gab_settings[generate_images]" value="1" <?php checked( $settings['generate_images'], 1 ); ?>>
									<?php esc_html_e( 'Dùng AI để tạo ảnh đại diện và ảnh minh họa trong bài', 'gemini-auto-blogger' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="gab_images_per_post"><?php esc_html_e( 'Số ảnh minh họa mỗi bài', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<input type="number"
								       id="gab_images_per_post"
								       name="gab_settings[images_per_post]"
								       value="<?php echo esc_attr( $settings['images_per_post'] ); ?>"
								       min="1" max="5"
								       class="small-text">
								<p class="description"><?php esc_html_e( 'Ảnh minh họa được chèn vào nội dung bài. Prompt đầu tiên luôn được dùng cho ảnh đại diện (không tính ở đây).', 'gemini-auto-blogger' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Section: Advanced -->
				<div class="gab-card">
					<h2><?php esc_html_e( '⚙ Nâng cao', 'gemini-auto-blogger' ); ?></h2>

					<table class="form-table gab-table" role="presentation">
						<tr>
							<th><label for="gab_max_retries"><?php esc_html_e( 'Số lần thử lại API tối đa', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<input type="number"
								       id="gab_max_retries"
								       name="gab_settings[max_retries]"
								       value="<?php echo esc_attr( $settings['max_retries'] ); ?>"
								       min="1" max="5"
								       class="small-text">
								<p class="description"><?php esc_html_e( 'Số lần thử lại khi gặp lỗi 429 / 5xx (tăng dần thời gian chờ).', 'gemini-auto-blogger' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="gab_prompt_template"><?php esc_html_e( 'Mẫu prompt tùy chỉnh', 'gemini-auto-blogger' ); ?></label></th>
							<td>
								<textarea id="gab_prompt_template"
								          name="gab_settings[content_prompt_template]"
								          rows="6"
								          class="large-text"
						      placeholder="<?php esc_attr_e( 'Để trống để dùng mẫu prompt có sẵn. Dùng {topic} làm vị trí chủ đề.', 'gemini-auto-blogger' ); ?>"><?php echo esc_textarea( $settings['content_prompt_template'] ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Ghi đè prompt mặc định. Phải yêu cầu Gemini trả về JSON hợp lệ với các key: title, excerpt, content, image_prompts, tags. Dùng {topic} tại vị trí cần chèn chủ đề.', 'gemini-auto-blogger' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

			</div><!-- /.gab-col -->

		</div><!-- /.gab-grid -->

		<?php submit_button( __( 'Lưu cài đặt', 'gemini-auto-blogger' ) ); ?>

	</form>

</div><!-- /.gab-wrap -->
