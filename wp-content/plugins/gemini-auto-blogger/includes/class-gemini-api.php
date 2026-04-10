<?php
/**
 * Hybrid AI API Class.
 *
 * Uses:
 *   - Groq for text generation
 *   - Cloudflare Workers AI (Stable Diffusion XL) for image generation
 *
 * @package GeminiAutoBlogger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GAB_Gemini_Api {

	const GROQ_BASE_URL    = 'https://api.groq.com/openai/v1';
	const CF_AI_BASE_URL   = 'https://api.cloudflare.com/client/v4';
	const CF_IMAGE_MODEL   = '@cf/stabilityai/stable-diffusion-xl-base-1.0';

	/** @var string */
	private $groq_api_key;

	/** @var string Cloudflare API Token (stored in gemini_api_key DB field). */
	private $gemini_api_key;

	/** @var string Cloudflare Account ID. */
	private $cf_account_id;

	/** @var string */
	private $text_model;

	/** @var string */
	private $image_model;

	/** @var int */
	private $max_retries;

	/**
	 * @param string $groq_api_key   Groq API key (text).
	 * @param string $gemini_api_key Cloudflare API Token (image).
	 * @param string $cf_account_id  Cloudflare Account ID.
	 * @param string $text_model     Optional Groq text model.
	 * @param string $image_model    Unused (SDXL is fixed). Kept for compat.
	 * @param int    $max_retries    Retry ceiling (1-5).
	 */
	public function __construct(
		$groq_api_key,
		$gemini_api_key,
		$cf_account_id = '',
		$text_model = '',
		$image_model = '',
		$max_retries = 3
	) {
		$this->groq_api_key   = sanitize_text_field( $groq_api_key );
		$this->gemini_api_key = $this->normalize_image_api_key( $gemini_api_key );
		$this->cf_account_id  = sanitize_text_field( $cf_account_id );
		$this->text_model     = sanitize_text_field( $text_model );
		$this->image_model    = sanitize_text_field( $image_model );
		$this->max_retries    = max( 1, min( 5, (int) $max_retries ) );
	}

	/**
	 * Generate text via Groq.
	 *
	 * @param string $prompt        User prompt text.
	 * @param string $system_prompt Optional system message prepended to the conversation.
	 * @param int    $max_tokens    Maximum tokens to generate. Keep prompt+max_tokens < 12000
	 *                              for Groq free-tier TPM limit.
	 * @return string|WP_Error
	 */
	public function generate_text( $prompt, $system_prompt = '', $max_tokens = 7500 ) {
		if ( '' === $this->groq_api_key ) {
			return new WP_Error( 'no_groq_key', 'Groq API key is empty.' );
		}

		$model = '' !== $this->text_model ? $this->text_model : 'llama-3.3-70b-versatile';
		$url   = self::GROQ_BASE_URL . '/chat/completions';

		$messages = array();
		if ( '' !== trim( $system_prompt ) ) {
			$messages[] = array( 'role' => 'system', 'content' => trim( $system_prompt ) );
		}
		$messages[] = array( 'role' => 'user', 'content' => $prompt );

		$body = wp_json_encode(
			array(
				'model'       => $model,
				'messages'    => $messages,
				'temperature' => 0.7,
				'max_tokens'  => max( 256, (int) $max_tokens ),
				'top_p'       => 0.8,
			)
		);

		return $this->request_with_retry( $url, $body, 'text', 'groq', $this->groq_api_key );
	}

	/**
	 * Generate image via Cloudflare Workers AI (Stable Diffusion XL).
	 *
	 * @param string $prompt Image prompt.
	 * @return string|WP_Error Base64 image data.
	 */
	public function generate_image( $prompt ) {
		if ( '' === $this->gemini_api_key ) {
			return new WP_Error( 'no_image_api_key', 'Cloudflare API Token is empty.' );
		}
		if ( '' === $this->cf_account_id ) {
			return new WP_Error( 'no_cf_account_id', 'Cloudflare Account ID is empty.' );
		}

		$prompt = trim( (string) $prompt );
		if ( '' === $prompt ) {
			return new WP_Error( 'empty_prompt', 'Image prompt is empty.' );
		}

		$url = self::CF_AI_BASE_URL . '/accounts/' . rawurlencode( $this->cf_account_id ) . '/ai/run/' . self::CF_IMAGE_MODEL;

		return $this->request_cf_image_with_retry( $url, $prompt );
	}

	/**
	 * Validate Groq key by making a tiny text call.
	 *
	 * @return true|WP_Error
	 */
	public function test_groq_connection() {
		$saved             = $this->max_retries;
		$this->max_retries = 1;
		$result            = $this->generate_text( 'Respond with the single word: OK' );
		$this->max_retries = $saved;

		if ( is_wp_error( $result ) && 'rate_limit' === $result->get_error_code() ) {
			return true;
		}

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Validate Cloudflare Workers AI credentials.
	 *
	 * @return true|WP_Error
	 */
	public function test_gemini_connection() {
		if ( '' === $this->gemini_api_key ) {
			return new WP_Error( 'no_image_api_key', 'Cloudflare API Token is empty.' );
		}
		if ( '' === $this->cf_account_id ) {
			return new WP_Error( 'no_cf_account_id', 'Cloudflare Account ID is empty.' );
		}

		// Lightweight check: list AI models for the account.
		$url      = self::CF_AI_BASE_URL . '/accounts/' . rawurlencode( $this->cf_account_id ) . '/ai/models/search';
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $this->gemini_api_key ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status   = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = (string) wp_remote_retrieve_body( $response );
		$body     = json_decode( $raw_body, true );

		if ( 200 !== $status ) {
			$msg = $this->extract_error_message( $body, 'Cloudflare Workers AI error', $raw_body );
			return new WP_Error( 'api_error', $msg, array( 'status' => $status ) );
		}

		return true;
	}

	/**
	 * @param string $url Full endpoint URL.
	 * @param string $body JSON payload.
	 * @param string $type text|image.
	 * @param string $provider groq|gemini.
	 * @param string $auth_key Bearer token for provider if needed.
	 * @return string|WP_Error
	 */
	private function request_with_retry( $url, $body, $type, $provider, $auth_key ) {
		$last_error   = null;
		$skip_backoff = false;
		$provider_tag = strtoupper( $provider );

		for ( $attempt = 1; $attempt <= $this->max_retries; $attempt++ ) {
			if ( $attempt > 1 && ! $skip_backoff ) {
				$wait = (int) pow( 2, $attempt - 1 ) * 5;
				GAB_Logger::info( sprintf( '%s API retry %d/%d – waiting %d s', $provider_tag, $attempt, $this->max_retries, $wait ) );
				sleep( $wait );
			}
			$skip_backoff = false;

			$headers = array( 'Content-Type' => 'application/json' );
			if ( 'groq' === $provider ) {
				$headers['Authorization'] = 'Bearer ' . $auth_key;
			}

			$response = wp_remote_post(
				$url,
				array(
					'headers' => $headers,
					'body'    => $body,
					'timeout' => 90,
				)
			);

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				continue;
			}

			$status    = (int) wp_remote_retrieve_response_code( $response );
			$raw_body  = (string) wp_remote_retrieve_body( $response );
			$response_body = json_decode( $raw_body, true );

			if ( 429 === $status ) {
				$last_error   = new WP_Error( 'rate_limit', $provider_tag . ' API rate limit hit' );
				$retry_after  = (int) wp_remote_retrieve_header( $response, 'retry-after' );
				$wait_seconds = max( 60, $retry_after );
				sleep( $wait_seconds );
				$skip_backoff = true;
				continue;
			}

			if ( $status >= 500 ) {
				$msg        = $this->extract_error_message( $response_body, 'Server error', $raw_body );
				$last_error = new WP_Error( 'server_error', $msg );
				continue;
			}

			if ( 200 !== $status ) {
				$msg = $this->extract_error_message( $response_body, 'Unknown API error', $raw_body );
				return new WP_Error( 'api_error', $msg, array( 'status' => $status ) );
			}

			if ( 'text' === $type ) {
				return $this->parse_text_response( $response_body, $provider );
			}

			return $this->parse_image_response( $response_body, $provider );
		}

		return $last_error ?? new WP_Error( 'max_retries', $provider_tag . ' API max retries exceeded' );
	}

	/**
	 * @param array  $body Decoded response.
	 * @param string $provider Provider.
	 * @return string|WP_Error
	 */
	private function parse_text_response( $body, $provider ) {
		if ( 'groq' === $provider ) {
			$text = $body['choices'][0]['message']['content'] ?? '';
			if ( '' === $text ) {
				return new WP_Error( 'empty_content', 'Groq trả về nội dung rỗng.' );
			}
			return $text;
		}

		$parts = $body['candidates'][0]['content']['parts'] ?? array();
		if ( empty( $parts ) || ! is_array( $parts ) ) {
			return new WP_Error( 'empty_content', 'AI trả về nội dung rỗng.' );
		}

		$chunks = array();
		foreach ( $parts as $part ) {
			if ( ! empty( $part['text'] ) ) {
				$chunks[] = $part['text'];
			}
		}

		$text = trim( implode( "\n", $chunks ) );
		if ( '' === $text ) {
			return new WP_Error( 'empty_content', 'AI không trả về phần text hợp lệ.' );
		}

		return $text;
	}

	/**
	 * @param array  $body Decoded response.
	 * @param string $provider Provider.
	 * @return string|WP_Error
	 */
	private function parse_image_response( $body, $provider ) {
		if ( 'groq' === $provider ) {
			return new WP_Error( 'not_supported', 'Groq không hỗ trợ tạo ảnh.' );
		}

		if ( 'imagen' === $provider ) {
			$generated = $body['generatedImages'] ?? array();
			if ( is_array( $generated ) ) {
				foreach ( $generated as $item ) {
					if ( ! empty( $item['image']['imageBytes'] ) ) {
						return $item['image']['imageBytes'];
					}
					if ( ! empty( $item['image']['bytesBase64Encoded'] ) ) {
						return $item['image']['bytesBase64Encoded'];
					}
					if ( ! empty( $item['imageBytes'] ) ) {
						return $item['imageBytes'];
					}
					if ( ! empty( $item['bytesBase64Encoded'] ) ) {
						return $item['bytesBase64Encoded'];
					}
				}
			}

			$predictions = $body['predictions'] ?? array();
			if ( is_array( $predictions ) ) {
				foreach ( $predictions as $item ) {
					if ( ! empty( $item['bytesBase64Encoded'] ) ) {
						return $item['bytesBase64Encoded'];
					}
				}
			}

			return new WP_Error( 'empty_image', 'Imagen không trả về dữ liệu ảnh.' );
		}

		$parts = $body['candidates'][0]['content']['parts'] ?? array();
		if ( empty( $parts ) || ! is_array( $parts ) ) {
			return new WP_Error( 'empty_image', 'AI không trả về dữ liệu ảnh.' );
		}

		foreach ( $parts as $part ) {
			if ( ! empty( $part['inlineData']['data'] ) ) {
				return $part['inlineData']['data'];
			}
			if ( ! empty( $part['inline_data']['data'] ) ) {
				return $part['inline_data']['data'];
			}
		}

		return new WP_Error( 'empty_image', 'AI không trả về ảnh dạng base64.' );
	}

	/**
	 * Execute Cloudflare Workers AI (SDXL) request with retries.
	 * The API returns binary JPEG data directly.
	 *
	 * @param string $url    Endpoint URL.
	 * @param string $prompt Image prompt.
	 * @return string|WP_Error Base64-encoded image data.
	 */
	private function request_cf_image_with_retry( $url, $prompt ) {
		$last_error = null;

		$body = wp_json_encode(
			array(
				'prompt'          => (string) $prompt,
				'negative_prompt' => 'blurry, low quality, watermark, text, ugly, deformed',
				'width'           => 1024,
				'height'          => 1024,
				'num_steps'       => 20,
			)
		);

		for ( $attempt = 1; $attempt <= $this->max_retries; $attempt++ ) {
			if ( $attempt > 1 ) {
				$wait = (int) pow( 2, $attempt - 1 ) * 5;
				GAB_Logger::info( sprintf( 'CF WORKERS AI retry %d/%d - waiting %d s', $attempt, $this->max_retries, $wait ) );
				sleep( $wait );
			}

			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->gemini_api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => $body,
					'timeout' => 120,
				)
			);

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				continue;
			}

			$status   = (int) wp_remote_retrieve_response_code( $response );
			$raw_body = (string) wp_remote_retrieve_body( $response );

			if ( 429 === $status ) {
				$retry_after  = (int) wp_remote_retrieve_header( $response, 'retry-after' );
				$wait_seconds = max( 30, $retry_after );
				GAB_Logger::info( sprintf( 'CF WORKERS AI rate limit – waiting %d s.', $wait_seconds ) );
				sleep( $wait_seconds );
				$last_error = new WP_Error( 'rate_limit', 'Cloudflare Workers AI rate limit hit' );
				continue;
			}

			if ( $status >= 500 ) {
				$decoded    = json_decode( $raw_body, true );
				$msg        = $this->extract_error_message( $decoded, 'Cloudflare server error', $raw_body );
				$last_error = new WP_Error( 'server_error', $msg );
				continue;
			}

			if ( 200 !== $status ) {
				$decoded = json_decode( $raw_body, true );
				$msg     = $this->extract_error_message( $decoded, 'Cloudflare Workers AI error', $raw_body );
				return new WP_Error( 'api_error', $msg, array( 'status' => $status ) );
			}

			// CF returns raw binary image bytes directly.
			if ( '' !== $raw_body ) {
				return base64_encode( $raw_body );
			}

			$last_error = new WP_Error( 'empty_image', 'Cloudflare Workers AI did not return image data.' );
		}

		return $last_error ?: new WP_Error( 'max_retries', 'Cloudflare Workers AI max retries exceeded' );
	}

	/**
	 * Sanitize the Cloudflare API Token.
	 *
	 * @param string $raw_key Raw input.
	 * @return string
	 */
	private function normalize_image_api_key( $raw_key ) {
		return trim( sanitize_text_field( (string) $raw_key ) );
	}

	/**
	 * @param mixed  $response_body Decoded response.
	 * @param string $fallback      Fallback.
	 * @param string $raw_body      Raw response body.
	 * @return string
	 */
	private function extract_error_message( $response_body, $fallback, $raw_body = '' ) {
		if ( is_array( $response_body ) ) {
			if ( ! empty( $response_body['error']['message'] ) ) {
				return (string) $response_body['error']['message'];
			}
			if ( ! empty( $response_body['error']['details'][0]['message'] ) ) {
				return (string) $response_body['error']['details'][0]['message'];
			}
			if ( ! empty( $response_body['message'] ) ) {
				return (string) $response_body['message'];
			}
		}

		$raw_body = trim( (string) $raw_body );
		if ( '' !== $raw_body ) {
			$snippet = substr( $raw_body, 0, 280 );
			return $fallback . ': ' . $snippet;
		}

		return $fallback;
	}
}
