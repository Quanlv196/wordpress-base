<?php
/**
 * API Service – responsible for sending the HTTP POST request.
 *
 * Deliberately has no WordPress-admin dependency; it only uses wp_remote_post
 * so it can be tested independently.
 *
 * @package EVF_API_Connector
 */

defined( 'ABSPATH' ) || exit;

class EVF_API_Service {

	/**
	 * Send a JSON request to the configured API endpoint.
	 *
	 * @param string $endpoint Target URL.
	 * @param array  $headers  Associative or indexed array of header rows
	 *                         (each row: ['key' => '', 'value' => '']).
	 * @param array  $payload  Data to send as JSON body.
	 * @param string $method   HTTP method: GET, POST, PUT, PATCH, DELETE.
	 *
	 * @return array {
	 *     @type bool   $success     True when HTTP 2xx is received.
	 *     @type int    $status_code HTTP status code (0 on WP_Error).
	 *     @type string $body        Raw response body.
	 *     @type string $error       Human-readable error string (empty on success).
	 * }
	 */
	public function send( string $endpoint, array $headers, array $payload, string $method = 'POST' ): array {
		// Canonicalize method.
		$allowed_methods = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );
		$method          = strtoupper( $method );
		if ( ! in_array( $method, $allowed_methods, true ) ) {
			$method = 'POST';
		}

		// ── Validate endpoint ────────────────────────────────────────────────
		if ( empty( $endpoint ) ) {
			return $this->make_error( 'URL endpoint API chưa được cấu hình.' );
		}

		// Allow only http/https schemes to prevent SSRF against internal services.
		$scheme = wp_parse_url( $endpoint, PHP_URL_SCHEME );
		if ( ! filter_var( $endpoint, FILTER_VALIDATE_URL ) || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return $this->make_error( 'URL endpoint API không hợp lệ – chỉ cho phép giao thức http/https.' );
		}

		// ── Build request headers ────────────────────────────────────────────
		$request_headers = array( 'Content-Type' => 'application/json' );

		foreach ( $headers as $row ) {
			$key = sanitize_text_field( $row['key'] ?? '' );
			$val = sanitize_text_field( $row['value'] ?? '' );
			if ( '' !== $key ) {
				// Sanitize header name: allow only visible ASCII, no CRLF injection.
				$key = preg_replace( '/[^\x21-\x7E]/', '', $key );
				if ( '' !== $key ) {
					$request_headers[ $key ] = $val;
				}
			}
		}

		// ── Encode payload ───────────────────────────────────────────────────
		$body = wp_json_encode( $payload );
		if ( false === $body ) {
			return $this->make_error( 'Không thể mã hóa dữ liệu thành JSON.' );
		}

		EVF_API_Logger::info(
			'Đang gửi yêu cầu API.',
			array(
				'method'       => $method,
				'endpoint'     => $endpoint,
				'payload_keys' => array_keys( $payload ),
			)
		);

		// ── Execute request ────────────────────────────────────────────────
		// GET requests must not have a body; attach payload as query string instead.
		$request_args = array(
			'method'      => $method,
			'timeout'     => 15,
			'redirection' => 3,
			'headers'     => $request_headers,
			'sslverify'   => true,
		);

		if ( 'GET' === $method ) {
			if ( ! empty( $payload ) ) {
				$endpoint = add_query_arg( $payload, $endpoint );
			}
		} else {
			$request_args['body']        = $body;
			$request_args['data_format'] = 'body';
		}

		$response = wp_remote_request( $endpoint, $request_args );

		// ── Handle WP_Error (network-level failure) ──────────────────────────
		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			EVF_API_Logger::error( 'Yêu cầu API thất bại (lỗi mạng).', array( 'error' => $msg ) );
			return $this->make_error( $msg );
		}

		// ── Evaluate HTTP response ───────────────────────────────────────────
		$status = (int) wp_remote_retrieve_response_code( $response );
		$rbody  = wp_remote_retrieve_body( $response );

		EVF_API_Logger::info(
			'Đã nhận phản hồi API.',
			array(
				'status_code'  => $status,
				'body_preview' => mb_substr( $rbody, 0, 500 ),
			)
		);

		$success = ( $status >= 200 && $status < 300 );

		return array(
			'success'     => $success,
			'status_code' => $status,
			'body'        => $rbody,
			'error'       => $success ? '' : "API trả về HTTP {$status}.",
		);
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function make_error( string $message ): array {
		return array(
			'success'     => false,
			'status_code' => 0,
			'body'        => '',
			'error'       => $message,
		);
	}
}
