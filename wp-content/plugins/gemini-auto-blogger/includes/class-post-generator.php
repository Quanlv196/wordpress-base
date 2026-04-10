<?php
/**
 * Post Generator Class.
 *
 * Orchestrates the full post-generation pipeline:
 *  1. Pick a topic from the configured list.
 *  2. Build a structured JSON prompt and send it to the AI.
 *  3. Parse the response (title, excerpt, content, image prompts, tags).
 *  4. Generate each requested image via Imagen and upload it to the Media Library.
 *  5. Replace [IMAGE_N] placeholders in the content with real <figure> blocks.
 *  6. Create the WordPress post with the correct author, categories, and status.
 *  7. Set the featured image and record metadata for duplicate-topic detection.
 *
 * @package GeminiAutoBlogger
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GAB_Post_Generator – generates and publishes AI-authored blog posts.
 */
class GAB_Post_Generator {

	/**
	 * AI API wrapper instance.
	 *
	 * @var GAB_Gemini_Api
	 */
	private $api;

	/**
	 * Plugin settings array (from GAB_Admin::get_settings()).
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Category ID resolved during topic selection (per-category mode).
	 *
	 * @var int|null
	 */
	private $current_category_id = null;

	// ── Constructor ────────────────────────────────────────────────────────

	/**
	 * @param GAB_Gemini_Api $api      Instantiated API wrapper.
	 * @param array          $settings Plugin settings.
	 */
	public function __construct( GAB_Gemini_Api $api, array $settings ) {
		$this->api      = $api;
		$this->settings = $settings;
	}

	// ── Public entry point ─────────────────────────────────────────────────

	/**
	 * Run the full generation pipeline and create a WordPress post.
	 *
	 * @return int|WP_Error New post ID on success, WP_Error on failure.
	 */
	public function generate_post() {

		// 1. Pick a topic.
		$topic = $this->pick_topic();
		if ( empty( $topic ) ) {
			GAB_Logger::error( 'Post generation aborted: no topics configured.' );
			return new WP_Error( 'no_topic', __( 'No topics configured. Add topics in the plugin settings.', 'gemini-auto-blogger' ) );
		}

		GAB_Logger::info( 'Starting post generation.', array( 'topic' => $topic ) );

		// 2. Generate structured content via Groq AI.
		$has_custom_template = '' !== trim( $this->settings['content_prompt_template'] ?? '' );
		if ( $has_custom_template ) {
			// Custom template: single-call JSON approach (backward compat).
			$raw = $this->api->generate_text( $this->build_prompt( $topic ) );
			if ( is_wp_error( $raw ) ) {
				GAB_Logger::error(
					'Text generation failed: [' . $raw->get_error_code() . '] ' . $raw->get_error_message(),
					array(
						'topic'      => $topic,
						'error_code' => $raw->get_error_code(),
						'error_data' => $raw->get_error_data(),
					)
				);
				return $raw;
			}
			$data = $this->parse_json_response( $raw );
		} else {
			// Default: two-step approach — content first, metadata second.
			// Step 1 gets all tokens for article HTML → longer, richer content.
			// Step 2 generates image prompts FROM the actual written content → better accuracy.
			$data = $this->generate_content_two_step( $topic );
		}

		// 3. Validate parsed data.
		if ( is_wp_error( $data ) ) {
			GAB_Logger::error( 'Content generation failed: ' . $data->get_error_message() );
			return $data;
		}

		// 4. Generate and upload images (if enabled).
		$featured_id = null;
		$inline_ids  = array();

		if ( ! empty( $this->settings['generate_images'] ) && ! empty( $this->settings['gemini_api_key'] ) ) {
			$images      = $this->generate_images( $data, $topic );
			$featured_id = $images['featured_id'];
			$inline_ids  = $images['inline_ids'];
		} elseif ( ! empty( $this->settings['generate_images'] ) ) {
			GAB_Logger::warning( 'Image generation skipped: Stable Diffusion API key is empty.' );
		}

		// 5. Replace image placeholders in content.
		$content = $this->inject_images( $data['content'], $inline_ids );

		// 6. Resolve author and categories.
		$author_id    = $this->resolve_author();
		$category_ids = $this->resolve_categories();

		// 7. Build wp_insert_post args.
		$post_args = array(
			'post_title'    => wp_strip_all_tags( $data['title'] ),
			'post_excerpt'  => wp_strip_all_tags( $data['excerpt'] ?? '' ),
			'post_content'  => wp_kses_post( $content ),
			'post_status'   => $this->settings['publish_status'] ?? 'publish',
			'post_author'   => $author_id,
			'post_category' => $category_ids,
			'tags_input'    => array_map( 'sanitize_text_field', (array) ( $data['tags'] ?? array() ) ),
		);

		// Schedule for a future date when publish_status === 'future'.
		if ( 'future' === $post_args['post_status'] ) {
			$delay_hours = max( 0, (int) ( $this->settings['publish_delay'] ?? 0 ) );
			if ( $delay_hours > 0 ) {
				$future_date = gmdate( 'Y-m-d H:i:s', time() + $delay_hours * HOUR_IN_SECONDS );
				$post_args['post_date']     = $future_date;
				$post_args['post_date_gmt'] = get_gmt_from_date( $future_date );
			} else {
				// No delay configured – fall back to immediate publish.
				$post_args['post_status'] = 'publish';
			}
		}

		// 8. Insert the post.
		$post_id = wp_insert_post( $post_args, true );
		if ( is_wp_error( $post_id ) ) {
			GAB_Logger::error( 'wp_insert_post failed: ' . $post_id->get_error_message(), array( 'topic' => $topic ) );
			return $post_id;
		}

		// 9. Attach featured image.
		if ( $featured_id ) {
			set_post_thumbnail( $post_id, $featured_id );
		}

		// 10. Store generation metadata.
		update_post_meta( $post_id, '_gab_topic',     sanitize_text_field( $topic ) );
		update_post_meta( $post_id, '_gab_generated', 1 );
		update_post_meta( $post_id, '_gab_version',   GAB_VERSION );

		// 11. Mark topic as used (for duplicate avoidance).
		$this->mark_topic_used( $topic );

		GAB_Logger::success(
			sprintf( 'Post #%d "%s" created.', $post_id, get_the_title( $post_id ) ),
			array(
				'post_id'    => $post_id,
				'topic'      => $topic,
				'author'     => $author_id,
				'status'     => $post_args['post_status'],
				'images'     => count( $inline_ids ),
				'featured'   => (bool) $featured_id,
			)
		);

		return $post_id;
	}

	// ── Topic selection ────────────────────────────────────────────────────

	/**
	 * Pick a topic from the configured list, respecting order and
	 * duplicate-avoidance settings.
	 *
	 * @return string|null
	 */
	private function pick_topic() {
		$this->current_category_id = null;

		// Per-category topics take priority when any are configured.
		$cat_topics = array_filter( (array) ( $this->settings['category_topics'] ?? array() ) );
		if ( ! empty( $cat_topics ) ) {
			return $this->pick_topic_by_category( $cat_topics );
		}

		// Fallback: global topics list.
		return $this->pick_from_global_topics();
	}

	/**
	 * Pick a topic from the per-category pool and set $current_category_id.
	 *
	 * @param array $cat_topics [ cat_id => "topic\ntopic" ]
	 * @return string|null
	 */
	private function pick_topic_by_category( array $cat_topics ) {
		// Build pool: [ cat_id => [topic, topic, …] ]
		$pool = array();
		foreach ( $cat_topics as $cat_id => $raw ) {
			$cat_id = (int) $cat_id;
			$topics = array_values( array_filter( array_map( 'trim', explode( "\n", (string) $raw ) ) ) );
			if ( $cat_id > 0 && ! empty( $topics ) ) {
				$pool[ $cat_id ] = $topics;
			}
		}

		if ( empty( $pool ) ) {
			return $this->pick_from_global_topics();
		}

		// Apply duplicate avoidance.
		if ( ! empty( $this->settings['avoid_duplicates'] ) ) {
			$used     = $this->get_recently_used_topics();
			$filtered = array();
			foreach ( $pool as $cat_id => $topics ) {
				$avail = array_values( array_diff( $topics, $used ) );
				if ( ! empty( $avail ) ) {
					$filtered[ $cat_id ] = $avail;
				}
			}
			if ( ! empty( $filtered ) ) {
				$pool = $filtered;
			} else {
				GAB_Logger::info( 'All per-category topics used. Resetting topic pool.' );
			}
		}

		$cat_ids = array_keys( $pool );

		if ( 'sequential' === ( $this->settings['topic_order'] ?? 'random' ) ) {
			// Flatten all (cat_id, topic) pairs and cycle through them.
			$pairs = array();
			foreach ( $pool as $cat_id => $topics ) {
				foreach ( $topics as $topic ) {
					$pairs[] = array( 'cat_id' => $cat_id, 'topic' => $topic );
				}
			}
			$index  = (int) get_option( 'gab_topic_index', 0 );
			$index  = $index % count( $pairs );
			update_option( 'gab_topic_index', $index + 1 );
			$chosen = $pairs[ $index ];
		} else {
			// Random: pick a random category then a random topic within it.
			$chosen_cat = $cat_ids[ array_rand( $cat_ids ) ];
			$chosen     = array(
				'cat_id' => $chosen_cat,
				'topic'  => $pool[ $chosen_cat ][ array_rand( $pool[ $chosen_cat ] ) ],
			);
		}

		$this->current_category_id = $chosen['cat_id'];
		return $chosen['topic'];
	}

	/**
	 * Pick a topic from the global (non-category-specific) topics list.
	 *
	 * @return string|null
	 */
	private function pick_from_global_topics() {
		$raw    = $this->settings['topics'] ?? '';
		$topics = array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );

		if ( empty( $topics ) ) {
			return null;
		}

		// Filter out recently-used topics when duplicate avoidance is on.
		if ( ! empty( $this->settings['avoid_duplicates'] ) ) {
			$used      = $this->get_recently_used_topics();
			$available = array_values( array_diff( $topics, $used ) );

			if ( empty( $available ) ) {
				GAB_Logger::info( 'All topics have been used. Resetting topic pool.' );
				$available = $topics;
			}
		} else {
			$available = $topics;
		}

		if ( 'sequential' === ( $this->settings['topic_order'] ?? 'random' ) ) {
			$index = (int) get_option( 'gab_topic_index', 0 );
			$index = $index % count( $available );
			update_option( 'gab_topic_index', $index + 1 );
			return $available[ $index ];
		}

		return $available[ array_rand( $available ) ];
	}

	/**
	 * Return the last 20 topics that were used (stored as post meta).
	 *
	 * @return array
	 */
	private function get_recently_used_topics() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			"SELECT DISTINCT meta_value
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_gab_topic'
			 ORDER BY meta_id DESC
			 LIMIT 20"
		);

		return $rows ?: array();
	}

	/**
	 * Record that a topic was just used (post meta is written by generate_post,
	 * so this is a hook point for any additional tracking).
	 *
	 * @param string $topic The topic string.
	 */
	private function mark_topic_used( $topic ) {
		// Topic is already persisted as _gab_topic post meta. This method
		// exists as an extension point for future tracking logic.
	}

	// ── Two-step content generation ────────────────────────────────────────

	/**
	 * Generate article content in two Groq API calls.
	 *
	 * Step 1: Write the full HTML article body with maximum token budget.
	 * Step 2: Generate compact metadata JSON (title, excerpt, image_prompts, tags)
	 *         from the ACTUAL written content so image prompts are content-accurate.
	 *
	 * @param string $topic Post topic.
	 * @return array|WP_Error Parsed data array or WP_Error.
	 */
	private function generate_content_two_step( $topic ) {
		$images_count = max( 1, (int) ( $this->settings['images_per_post'] ?? 2 ) );
		$placeholders = implode( ', ', array_map( fn( $n ) => "[IMAGE_{$n}]", range( 1, $images_count ) ) );

		// ── Step 1: Generate the HTML article body ────────────────────────
		GAB_Logger::info( 'Two-step generation: step 1 (content).', array( 'topic' => $topic ) );
		// Step 1 gets max tokens for rich HTML content (prompt ~300 tok + 7500 out = ~7800, under 12k TPM).
		$raw_content = $this->api->generate_text( $this->build_content_prompt( $topic, $placeholders ), '', 7500 );
		if ( is_wp_error( $raw_content ) ) {
			GAB_Logger::error(
				'Content step 1 failed: [' . $raw_content->get_error_code() . '] ' . $raw_content->get_error_message(),
				array( 'topic' => $topic )
			);
			return $raw_content;
		}

		// Strip accidental markdown fences the model may have wrapped around HTML.
		$content_html = preg_replace( '/^```(?:html)?\s*/im', '', trim( $raw_content ) );
		$content_html = trim( preg_replace( '/```\s*$/im', '', $content_html ) );

		if ( empty( $content_html ) ) {
			return new WP_Error( 'empty_content', 'AI returned empty content in step 1.' );
		}

		GAB_Logger::info( 'Step 1 complete.', array( 'content_bytes' => strlen( $content_html ) ) );

		// ── Step 2: Generate metadata JSON from the written content ───────
		GAB_Logger::info( 'Two-step generation: step 2 (metadata).', array( 'topic' => $topic ) );
		$meta_system_prompt = 'You are a JSON metadata and image-prompt generator for blog posts. '
			. 'You MUST respond ONLY with a single valid JSON object. '
			. 'ALL values in the "image_prompts" array MUST be written in English — no Vietnamese, no other language. '
			. 'Image prompts must follow Stable Diffusion XL format: specific subject + setting + lighting + style. '
			. 'Never use vague phrases like "an image of" or "people doing things".';
		// Step 2 only needs a small JSON (title + excerpt + image_prompts + tags).
		// Prompt includes article preview (~500 tok) + 1500 output = ~2000, well under 12k TPM.
		$raw_meta = $this->api->generate_text( $this->build_metadata_prompt( $topic, $content_html ), $meta_system_prompt, 1500 );
		if ( is_wp_error( $raw_meta ) ) {
			GAB_Logger::error(
				'Metadata step 2 failed: [' . $raw_meta->get_error_code() . '] ' . $raw_meta->get_error_message(),
				array( 'topic' => $topic )
			);
			return $raw_meta;
		}

		$data = $this->parse_json_response( $raw_meta, array( 'title' ) );
		if ( is_wp_error( $data ) ) {
			GAB_Logger::error( 'Metadata parse step 2 failed: ' . $data->get_error_message() );
			return $data;
		}

		// Attach the full HTML body as the content field.
		$data['content'] = $content_html;
		GAB_Logger::info( 'Two-step generation complete.', array( 'title' => $data['title'] ?? '' ) );

		return $data;
	}

	/**
	 * Build step-1 prompt: article HTML body only (no JSON overhead).
	 *
	 * @param string $topic        Post topic.
	 * @param string $placeholders Comma-separated image placeholders, e.g. "[IMAGE_1], [IMAGE_2]".
	 * @return string
	 */
	private function build_content_prompt( $topic, $placeholders ) {
		return <<<PROMPT
Viết một bài blog SEO chuyên sâu bằng TIẾNG VIỆT về chủ đề: "{$topic}"

YÊU CẦU BẮT BUỘC:
1. Chỉ trả về nội dung HTML thuần. KHÔNG có JSON. KHÔNG có markdown. KHÔNG có giải thích.
2. Sử dụng các thẻ HTML: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote>
3. Độ dài BẮT BUỘC: Tối thiểu 1000 từ tiếng Việt (không tính thẻ HTML). Đếm từ trước khi kết thúc.
4. Cấu trúc bài viết:
   - Đoạn mở đầu hấp dẫn: 2-3 đoạn văn liên tiếp (≥200 từ) — không có <h2> trước đoạn mở đầu
   - Đặt [IMAGE_1] ngay sau đoạn mở đầu
   - Tối thiểu 5 mục <h2> chính, mỗi mục CÓ ÍT NHẤT 300 từ (viết đầy đủ, không tóm tắt)
   - Ít nhất 3 mục <h2> phải có <h3> con với nội dung chi tiết cụ thể
   - Các placeholder ảnh còn lại ({$placeholders}) đặt ngay sau tiêu đề <h2> phù hợp
   - Kết luận + lời kêu gọi hành động (≥100 từ)
5. Nội dung chuyên sâu: số liệu thực tế, mẹo cụ thể, ví dụ, giải thích rõ ràng.
6. Bắt đầu ngay bằng thẻ HTML đầu tiên (<p> hoặc <h2>). KHÔNG có <html>, <body>, <head>.

CHỈ TRẢ VỀ HTML THUẦN. BẮT ĐẦU NGAY BẰNG THẺ HTML. KHÔNG CÓ GÌ KHÁC.
PROMPT;
	}

	/**
	 * Build step-2 prompt: metadata JSON based on the actual written content.
	 *
	 * Image prompts are tied to content sections for accuracy.
	 *
	 * @param string $topic   Post topic.
	 * @param string $content Full HTML article body from step 1.
	 * @return string
	 */
	private function build_metadata_prompt( $topic, $content ) {
		$images_count = max( 1, (int) ( $this->settings['images_per_post'] ?? 2 ) );

		// Extract H2 headings to map each image placeholder to a content section.
		preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/si', $content, $hm );
		$headings = array_map( 'strip_tags', $hm[1] ?? array() );

		// Build a placement map: IMAGE_1 = intro (hero), IMAGE_N = near H2 section N-1.
		$placement_lines = "[IMAGE_1] → đoạn mở đầu bài (ảnh hero về chủ đề tổng quát)\n";
		for ( $i = 2; $i <= $images_count; $i++ ) {
			$h2_idx   = $i - 2; // IMAGE_2 maps to H2[0], IMAGE_3 to H2[1], etc.
			$h2_title = isset( $headings[ $h2_idx ] ) ? trim( $headings[ $h2_idx ] ) : "phần {$h2_idx} của bài";
			$placement_lines .= "[IMAGE_{$i}] → gần mục H2: \"{$h2_title}\"\n";
		}

		// Build image_prompts JSON template lines.
		$img_examples = '';
		for ( $i = 1; $i <= $images_count; $i++ ) {
			$img_examples .= "    \"<English SDXL prompt for [IMAGE_{$i}]>\"";
			if ( $i < $images_count ) {
				$img_examples .= ',';
			}
			$img_examples .= "\n";
		}

		// Provide a brief content preview for title/excerpt context (strip tags, cap length).
		$preview = mb_substr( wp_strip_all_tags( $content ), 0, 600 );

		// Build a concrete example tied to the actual topic for image_prompts guidance.
		$topic_ascii = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $topic );
		$topic_ascii = trim( preg_replace( '/\s+/', ' ', preg_replace( '/[^a-zA-Z0-9 ]/', ' ', (string) $topic_ascii ) ) );
		if ( strlen( $topic_ascii ) < 3 ) {
			$topic_ascii = 'the article topic';
		}

		return <<<PROMPT
The following Vietnamese blog article has been written. Generate its metadata JSON.

ARTICLE TOPIC: {$topic}
ARTICLE OPENING (context):
{$preview}

IMAGE PLACEMENT MAP — each image_prompt must visually represent its assigned section:
{$placement_lines}
Return ONLY a single valid JSON object. No markdown fences. No text before or after the JSON.

{
  "title": "Tiêu đề SEO hấp dẫn (50-60 ký tự, tiếng Việt, chứa từ khóa chính)",
  "excerpt": "Mô tả meta 150-160 ký tự, tiếng Việt, kích thích click, có từ khóa",
  "image_prompts": [
{$img_examples}  ],
  "tags": ["tu-khoa-1", "tu-khoa-2", "tu-khoa-3", "tu-khoa-4", "tu-khoa-5"]
}

STRICT RULES for image_prompts:
1. ENGLISH ONLY — every single image_prompt value must be written entirely in English. No Vietnamese words whatsoever.
2. Be SPECIFIC to the content section shown in the placement map above.
3. Format: [specific subject] [specific setting/context], [lighting], photorealistic, high detail
4. Each prompt must be 15-40 words long.
5. Good example for a gold price article: "Stack of gleaming gold bars on a financial analyst desk, market charts in background, soft office lighting, photorealistic, high detail"
6. Good example for a real estate article: "Modern Vietnamese apartment building exterior at sunset, lush landscaping, golden hour light, photorealistic, high detail"
7. Topic for this article is about: {$topic_ascii} — make sure EVERY image clearly relates to this subject.
8. NEVER use: "an image of", "image showing", "a photo of", or any vague subjects like "person", "people doing things".
9. MUST strictly avoid illustration styles — no cartoon, anime, painting, sketch, 3D render, CGI, or any stylized or artistic visuals.
10. Prompts must imply real photography — include natural lighting, camera/lens details, or physical environment cues to reinforce realism.

title and excerpt must be in Vietnamese. tags must be lowercase slugs with hyphens.
JSON string values must NOT contain real newlines — use \n if needed.
PROMPT;
	}

	// ── Prompt building ────────────────────────────────────────────────────

	/**
	 * Build the text-generation prompt for the given topic.
	 *
	 * If the admin has supplied a custom template (containing {topic}), that
	 * takes precedence. Otherwise the built-in template is used.
	 *
	 * @param string $topic Post topic.
	 * @return string
	 */
	private function build_prompt( $topic ) {
		$custom_template = trim( $this->settings['content_prompt_template'] ?? '' );
		if ( '' !== $custom_template ) {
			return str_replace( '{topic}', $topic, $custom_template );
		}

		$images_count = max( 1, (int) ( $this->settings['images_per_post'] ?? 2 ) );

		// Build image-prompt placeholders list.
		$img_prompt_examples = '';
		for ( $i = 1; $i <= $images_count; $i++ ) {
			$img_prompt_examples .= "    \"Stable Diffusion XL prompt: vivid, specific English scene description for image {$i} that matches the surrounding article content. Include subject, setting, mood, lighting. Example: 'A Vietnamese farmer harvesting rice in golden sunset light, lush green paddy fields, cinematic, photorealistic'\"";
			if ( $i < $images_count ) {
				$img_prompt_examples .= ',';
			}
			$img_prompt_examples .= "\n";
		}

		// Build placeholder list for content body.
		$placeholders = implode( ', ', array_map( fn( $n ) => "[IMAGE_{$n}]", range( 1, $images_count ) ) );

		return <<<PROMPT
Viết một bài blog SEO chuyên sâu bằng TIẾNG VIỆT về chủ đề: "{$topic}"

QUAN TRỌNG: Toàn bộ nội dung (tiêu đề, mô tả, nội dung, thẻ) PHẢI viết bằng tiếng Việt. Không dùng tiếng Anh.

Trả về DUY NHẤT một JSON object hợp lệ – không dùng markdown fence, không có text thừa, chỉ JSON thuần.

Cấu trúc JSON bắt buộc:
{
  "title": "Tiêu đề hấp dẫn, tối ưu SEO (50-60 ký tự, có từ khóa chính)",
  "excerpt": "Mô tả meta / trích đoạn (150-160 ký tự, có từ khóa chính, kích thích click)",
  "content": "Nội dung HTML đầy đủ dùng <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote>.Độ dài từ 1000 đến 1500 từ không bao gồm các thẻ. Đặt placeholder ảnh ({$placeholders}) tại các vị trí phù hợp trong bài.",
  "image_prompts": [
{$img_prompt_examples}  ],
  "tags": ["tu-khoa-1", "tu-khoa-2", "tu-khoa-3", "tu-khoa-4", "tu-khoa-5"]
}

Quy tắc nội dung:
- Tiêu đề phải chứa từ khóa chính một cách tự nhiên.
- Mô tả phải kích thích người đọc click và không vượt quá giới hạn ký tự.
- Nội dung phải có ít nhất hai thẻ <h2> và cảm giác chuyên nghiệp, hấp dẫn.
- Mỗi image_prompt PHẢI viết bằng tiếng Anh, mô tả cảnh cụ thể, sinh động theo định dạng Stable Diffusion XL: chủ thể + bối cảnh + ánh sáng + phong cách + chất lượng (ví dụ: 'A smiling Vietnamese chef cooking pho in a traditional kitchen, warm light, photorealistic, high detail'). Không dùng ngôn ngữ mơ hồ như 'an image of' hay 'image showing'.
- Tags: 3-5 thẻ viết thường, dùng dấu gạch ngang, liên quan đến chủ đề.

Trả về DUY NHẤT JSON object. Không có gì trước hoặc sau nó. Nội dung JSON value KHÔNG được chứa ký tự xuống dòng thật – dùng \n thay thế.
PROMPT;
	}

	// ── JSON response parsing ──────────────────────────────────────────────

	/**
	 * Strip Markdown fences and parse the AI JSON response.
	 *
	 * @param string   $raw             Raw API response text.
	 * @param string[] $required_fields  Fields that must be non-empty (default: title + content).
	 * @return array|WP_Error Parsed data array or WP_Error.
	 */
	private function parse_json_response( $raw, array $required_fields = array( 'title', 'content' ) ) {
		// Remove optional ```json … ``` fences.
		$clean = preg_replace( '/^```(?:json)?\s*/im', '', trim( $raw ) );
		$clean = preg_replace( '/```\s*$/im', '', $clean );
		$clean = trim( $clean );

		// If there is leading prose, extract the first JSON object.
		if ( '{' !== $clean[0] ) {
			preg_match( '/\{.*\}/s', $clean, $m );
			$clean = $m[0] ?? $clean;
		}

		// Groq (và một số LLM) đôi khi đưa ký tự điều khiển thật vào trong
		// chuỗi JSON (vd: newline thật thay vì \n) – đây là JSON không hợp lệ.
		// Bước 1: xóa ký tự điều khiển hoàn toàn không hợp lệ trong JSON.
		$clean = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean );
		// Bước 2: trong mỗi chuỗi JSON, chuyển newline/tab thật → escape sequence.
		$clean = preg_replace_callback(
			'/"(?:[^"\\\\]|\\\\.)*"/s',
			static function ( $m ) {
				return str_replace(
					array( "\r\n", "\r", "\n", "\t" ),
					array( '\n',   '\n', '\n', '\t' ),
					$m[0]
				);
			},
			$clean
		);

		$data = json_decode( $clean, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			GAB_Logger::error(
				'JSON decode error: ' . json_last_error_msg(),
				array( 'raw_excerpt' => substr( $clean, 0, 500 ) )
			);
			return new WP_Error(
				'json_parse_error',
				'Failed to parse AI response as JSON: ' . json_last_error_msg()
			);
		}

		// Validate required fields.
		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error( 'missing_field', "Required field '{$field}' missing in AI response." );
			}
		}

		// Apply defaults for optional fields.
		$data['excerpt']       = $data['excerpt']       ?? '';
		$data['image_prompts'] = $data['image_prompts'] ?? array();
		$data['tags']          = $data['tags']          ?? array();

		return $data;
	}

	// ── Image generation & upload ──────────────────────────────────────────

	/**
	 * Generate the featured + inline images and upload them to WordPress.
	 *
	 * @param array  $data  Parsed content data (needs 'image_prompts').
	 * @param string $topic Topic string (used for descriptive alt text).
	 * @return array{ featured_id: int|null, inline_ids: int[] }
	 */
	private function generate_images( array $data, $topic ) {
		$prompts     = (array) ( $data['image_prompts'] ?? array() );
		$featured_id = null;
		$inline_ids  = array();

		// ── Featured image ──────────────────────────────────────────────────
		$featured_prompt = $this->build_image_prompt_from_content( $data, $topic, 0, $prompts[0] ?? '' );
		$b64             = $this->api->generate_image( $featured_prompt );

		if ( is_wp_error( $b64 ) ) {
			GAB_Logger::warning( 'Featured image generation failed: [' . $b64->get_error_code() . '] ' . $b64->get_error_message() );
			// If we hit the rate limit on the first call, skip inline images to avoid cascading errors.
			if ( 'rate_limit' === $b64->get_error_code() ) {
				return compact( 'featured_id', 'inline_ids' );
			}
		} else {
			$att_id = $this->upload_image( $b64, 'featured-' . $this->slug( $topic ), "Featured image: {$topic}" );
			if ( is_wp_error( $att_id ) ) {
				GAB_Logger::warning( 'Featured image upload failed: ' . $att_id->get_error_message() );
			} else {
				$featured_id = $att_id;
			}
		}

		// ── Inline images (skipping index 0 already used for featured) ──────
		$inline_prompts = count( $prompts ) > 1 ? array_slice( $prompts, 1 ) : array();
		$inline_count   = max( 1, (int) ( $this->settings['images_per_post'] ?? 2 ) );

		if ( empty( $inline_prompts ) ) {
			$inline_prompts = array_fill( 0, $inline_count, '' );
		}

		foreach ( $inline_prompts as $idx => $prompt ) {
			// Wait between image API calls to stay within free-tier rate limits (10 RPM).
			sleep( 15 );

			$b64 = $this->api->generate_image(
				$this->build_image_prompt_from_content( $data, $topic, $idx + 1, $prompt )
			);
			if ( is_wp_error( $b64 ) ) {
				GAB_Logger::warning( "Inline image {$idx} generation failed: [" . $b64->get_error_code() . '] ' . $b64->get_error_message() );
				// Stop all further image generation if we're rate-limited.
				if ( 'rate_limit' === $b64->get_error_code() ) {
					break;
				}
				continue;
			}

			$att_id = $this->upload_image(
				$b64,
				'inline-' . ( $idx + 1 ) . '-' . $this->slug( $topic ),
				"Inline image " . ( $idx + 1 ) . ": {$topic}"
			);

			if ( is_wp_error( $att_id ) ) {
				GAB_Logger::warning( "Inline image {$idx} upload failed: " . $att_id->get_error_message() );
				continue;
			}

			$inline_ids[] = $att_id;
		}

		return compact( 'featured_id', 'inline_ids' );
	}

	/**
	 * Build an image prompt from the generated Groq article content.
	 *
	 * @param array  $data        Parsed generation data.
	 * @param string $topic       Topic string.
	 * @param int    $image_index 0 = featured, >0 inline index.
	 * @param string $hint        Optional model-supplied image hint.
	 * @return string
	 */
	private function build_image_prompt_from_content( array $data, $topic, $image_index, $hint = '' ) {
		// SDXL quality suffix applied to every image.
		$quality = 'photorealistic, high detail, sharp focus, natural lighting, clean composition, no text, no watermark, no logo';

		$hint = trim( sanitize_text_field( (string) $hint ) );

		if ( '' !== $hint ) {
			// Clean up any leading meta-phrase.
			$hint = preg_replace( '/^(stable diffusion xl prompt:\s*)/i', '', $hint );

			// Validate: SDXL is English-only. Reject hint if >15% non-ASCII chars
			// (indicates Vietnamese diacritics that SDXL cannot interpret).
			$ascii_chars = preg_replace( '/[^\x00-\x7F]/', '', $hint );
			$is_valid    = strlen( $hint ) > 0
				&& ( strlen( $ascii_chars ) / strlen( $hint ) ) >= 0.85
				&& strlen( $ascii_chars ) >= 10;

			if ( $is_valid ) {
				return $ascii_chars . ', ' . $quality;
			}

			GAB_Logger::warning( 'Image hint rejected (non-ASCII/Vietnamese detected), using fallback.', array( 'hint' => substr( $hint, 0, 100 ) ) );
		}

		// Fallback: transliterate Vietnamese title to ASCII so SDXL can parse it.
		$title_raw   = sanitize_text_field( $data['title'] ?? $topic );
		$title_ascii = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $title_raw );
		$title_ascii = preg_replace( '/[^a-zA-Z0-9 .\',\-]/', ' ', (string) $title_ascii );
		$title_ascii = trim( preg_replace( '/\s+/', ' ', $title_ascii ) );

		$role = ( 0 === (int) $image_index ) ? 'hero banner' : 'editorial illustration';

		if ( strlen( $title_ascii ) >= 5 ) {
			return "Professional {$role} photograph for an article about: {$title_ascii}. Clean studio lighting, {$quality}";
		}

		return "Professional {$role} photograph, magazine quality, {$quality}";
	}

	/**
	 * Decode a base64 image string and sideload it into the WordPress
	 * Media Library via `media_handle_sideload`.
	 *
	 * @param string $base64   Raw base64-encoded image data (PNG from Imagen).
	 * @param string $basename Filename base (without extension).
	 * @param string $alt_text Image alt / description text.
	 * @return int|WP_Error Attachment post ID or error.
	 */
	private function upload_image( $base64, $basename, $alt_text ) {
		// Ensure WP media/file helpers are loaded (cron/frontend may not load them).
		if ( ! function_exists( 'wp_tempnam' ) || ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$binary = base64_decode( $base64, true );
		if ( false === $binary ) {
			return new WP_Error( 'decode_error', 'base64_decode returned false for image data.' );
		}

		// Detect MIME type from binary.
		$finfo     = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : null;
		$mime_type = $finfo ? finfo_buffer( $finfo, $binary ) : 'image/png';
		if ( $finfo ) {
			finfo_close( $finfo );
		}
		$ext = $this->mime_to_ext( $mime_type );

		// Write to a temp file.
		$tmp = function_exists( 'wp_tempnam' ) ? wp_tempnam( $basename ) : tempnam( sys_get_temp_dir(), 'gab_' );
		if ( false === $tmp || '' === $tmp ) {
			return new WP_Error( 'temp_file_error', 'Could not create temp file for image upload.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $tmp, $binary );
		if ( false === $written ) {
			return new WP_Error( 'write_error', 'Could not write image to temp file.' );
		}

		$file_array = array(
			'name'     => sanitize_file_name( $basename . '.' . $ext ),
			'type'     => $mime_type,
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => $written,
		);

		$attachment_id = media_handle_sideload( $file_array, 0, $alt_text );

		// Clean up the temp file (sideload moves it, but guard just in case).
		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Write alt text as post meta.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );

		return $attachment_id;
	}

	// ── Content injection ──────────────────────────────────────────────────

	/**
	 * Replace [IMAGE_1], [IMAGE_2] … placeholders in HTML content with
	 * WordPress-generated <figure><img …></figure> markup.
	 *
	 * Any placeholder with no corresponding attachment ID is stripped.
	 *
	 * @param string $content    Raw HTML content.
	 * @param int[]  $inline_ids Ordered array of attachment IDs.
	 * @return string
	 */
	private function inject_images( $content, array $inline_ids ) {
		foreach ( $inline_ids as $i => $att_id ) {
			$tag  = '[IMAGE_' . ( $i + 1 ) . ']';
			$img  = wp_get_attachment_image(
				$att_id,
				'large',
				false,
				array(
					'class'   => 'gab-inline-image aligncenter',
					'loading' => 'lazy',
				)
			);

			if ( $img ) {
				$content = str_replace( $tag, '<figure class="gab-figure wp-caption aligncenter">' . $img . '</figure>', $content );
			}
		}

		// Remove any remaining unfilled placeholders.
		$content = preg_replace( '/\[IMAGE_\d+\]/', '', $content );

		return $content;
	}

	// ── Author & category resolution ───────────────────────────────────────

	/**
	 * Return the author ID to assign, falling back to a random author.
	 *
	 * @return int WordPress user ID.
	 */
	private function resolve_author() {
		$author_id = (int) ( $this->settings['author_id'] ?? 0 );

		if ( $author_id > 0 && get_user_by( 'id', $author_id ) ) {
			return $author_id;
		}

		// Random: any user who can publish posts.
		$users = get_users( array(
			'capability' => 'publish_posts',
			'fields'     => 'ID',
			'number'     => 100,
		) );

		if ( ! empty( $users ) ) {
			return (int) $users[ array_rand( $users ) ];
		}

		// Ultimate fallback: site admin (ID 1).
		return 1;
	}

	/**
	 * Return the array of category IDs to assign.
	 *
	 * @return int[]
	 */
	private function resolve_categories() {
		// If pick_topic() resolved a specific category, use it.
		if ( null !== $this->current_category_id ) {
			return array( (int) $this->current_category_id );
		}

		$cats = (array) ( $this->settings['categories'] ?? array() );
		$cats = array_filter( array_map( 'intval', $cats ) );

		return ! empty( $cats ) ? array_values( $cats ) : array( 1 ); // 1 = Uncategorized.
	}

	// ── Utility methods ────────────────────────────────────────────────────

	/**
	 * Generate a URL-safe, filesystem-safe slug from a string.
	 *
	 * @param string $str Input string.
	 * @return string Max 30-character slug.
	 */
	private function slug( $str ) {
		return substr( sanitize_title( $str ), 0, 30 );
	}

	/**
	 * Map a MIME type to a common file extension.
	 *
	 * @param string $mime MIME type string.
	 * @return string File extension without leading dot.
	 */
	private function mime_to_ext( $mime ) {
		$map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
		);

		return $map[ $mime ] ?? 'png';
	}
}
