<?php
/**
 * Hook Handler – bridges Everest Forms submission hooks with the API Service.
 *
 * Hooks used:
 *  - everest_forms_process_complete        (action, priority 10)
 *      Fired AFTER entry is saved to DB and admin emails are sent.
 *      Parameters: $fields, $entry, $form_data, $entry_id
 *
 *  - everest_forms_after_success_ajax_message  (filter, priority 10)
 *      Fired just before the AJAX JSON response is returned to the browser.
 *      Allows injecting a custom success/failure message.
 *      Parameters: $response_data, $form_data, $entry
 *
 * @package EVF_API_Connector
 */

defined( 'ABSPATH' ) || exit;

class EVF_API_Hook_Handler {

	/**
	 * API service instance.
	 *
	 * @var EVF_API_Service
	 */
	private $api_service;

	/**
	 * Per-form API results, keyed by form_id.
	 * Used by inject_ajax_message() to override the success message.
	 *
	 * @var array<int, array>
	 */
	private $api_results = array();

	// ── Constructor ───────────────────────────────────────────────────────────

	public function __construct( EVF_API_Service $api_service ) {
		$this->api_service = $api_service;

		// After entry is saved and emails are dispatched – safe to call API.
		add_action( 'everest_forms_process_complete', array( $this, 'handle_submission' ), 10, 4 );

		// Modify the AJAX response body (for AJAX-submitted forms).
		add_filter( 'everest_forms_after_success_ajax_message', array( $this, 'inject_ajax_message' ), 10, 3 );
	}

	// ── Submission Handler ────────────────────────────────────────────────────

	/**
	 * Main entry point triggered after a successful form submission.
	 *
	 * @param array    $fields    Processed form fields (flat, keyed by field_id).
	 *                            Each item: ['id', 'name', 'meta_key', 'type', 'value'].
	 * @param array    $entry     Raw submitted entry data from $_POST.
	 * @param array    $form_data Full form configuration (from post_content JSON).
	 * @param int|string $entry_id  ID of the newly created entry row.
	 */
	public function handle_submission( $fields, $entry, $form_data, $entry_id ): void {
		$form_id      = (int) ( $form_data['id'] ?? 0 );
		$form_cfg     = EVF_API_Connector::get_form_settings( $form_id );

		// Skip forms that have no connector config or are explicitly disabled.
		if ( empty( $form_cfg ) || empty( $form_cfg['enabled'] ) ) {
			return;
		}

		$endpoint = sanitize_text_field( $form_cfg['api_endpoint'] ?? '' );
		$method   = sanitize_text_field( $form_cfg['api_method']   ?? 'POST' );
		$headers  = isset( $form_cfg['headers'] ) && is_array( $form_cfg['headers'] )
			? $form_cfg['headers']
			: array();
		$mappings = isset( $form_cfg['field_mappings'] ) && is_array( $form_cfg['field_mappings'] )
			? $form_cfg['field_mappings']
			: array();

		// Build payload from configured field mappings.
		$payload = $this->build_payload( (array) $fields, $mappings );

		if ( empty( $payload ) ) {
			EVF_API_Logger::info(
				"Biểu mẫu {$form_id}: Không có trường nào được ánh xạ – bỏ qua gọi API.",
				array( 'entry_id' => $entry_id )
			);
			return;
		}

		/**
		 * Filter the payload before it is sent to the API.
		 *
		 * @param array  $payload  Mapped key→value data.
		 * @param int    $form_id  Everest Forms ID.
		 * @param mixed  $entry_id Entry database ID.
		 * @param array  $fields   All processed EVF fields.
		 */
		$payload = (array) apply_filters( 'evf_api_connector_payload', $payload, $form_id, $entry_id, $fields );

		// Call the API – failure must NOT break form submission (no exceptions thrown).
		try {
			$result = $this->api_service->send( $endpoint, $headers, $payload, $method );
		} catch ( Throwable $e ) {
			$result = array(
				'success'     => false,
				'status_code' => 0,
				'body'        => '',
				'error'       => $e->getMessage(),
			);
			EVF_API_Logger::error(
					"Biểu mẫu {$form_id}: Ngoại lệ không mong muốn trong quá trình gọi API.",
				array( 'exception' => $e->getMessage() )
			);
		}

		// Store for later use by inject_ajax_message().
		$this->api_results[ $form_id ] = $result;

		if ( $result['success'] ) {
			EVF_API_Logger::info(
				"Biểu mẫu {$form_id}: Gọi API thành công.",
				array( 'entry_id' => $entry_id, 'status' => $result['status_code'] )
			);
		} else {
			EVF_API_Logger::error(
				"Biểu mẫu {$form_id}: Gọi API thất bại.",
				array(
					'entry_id' => $entry_id,
					'error'    => $result['error'],
					'status'   => $result['status_code'],
				)
			);
		}
	}

	// ── AJAX Message Injection ────────────────────────────────────────────────

	/**
	 * Inject custom success/failure message into the EVF AJAX JSON response.
	 *
	 * EVF sends back a JSON object; the `message` key is shown to the user.
	 * We override it with the admin-configured text when custom messages are on.
	 *
	 * @param array $response_data  Current AJAX response array.
	 * @param array $form_data      Form configuration.
	 * @param array $entry          Submitted entry data.
	 * @return array Modified response data.
	 */
	public function inject_ajax_message( $response_data, $form_data, $entry ): array {
		$response_data = (array) $response_data;
		$form_id       = (int) ( $form_data['id'] ?? 0 );
		$form_cfg      = EVF_API_Connector::get_form_settings( $form_id );

		// Only override message when enabled and API was actually called for this form.
		if ( empty( $form_cfg['use_custom_messages'] ) || ! isset( $this->api_results[ $form_id ] ) ) {
			return $response_data;
		}

		$api_result = $this->api_results[ $form_id ];

		if ( $api_result['success'] && ! empty( $form_cfg['success_message'] ) ) {
			$response_data['message'] = wp_kses_post( $form_cfg['success_message'] );
		} elseif ( ! $api_result['success'] && ! empty( $form_cfg['failure_message'] ) ) {
			$response_data['message'] = wp_kses_post( $form_cfg['failure_message'] );
		}

		// Expose API status to the frontend without leaking sensitive details.
		$response_data['evf_api_connector_status'] = $api_result['success'] ? 'success' : 'error';

		return $response_data;
	}

	// ── Payload Building ──────────────────────────────────────────────────────

	/**
	 * Build the JSON payload by applying the admin-configured field mappings
	 * to the processed submission fields.
	 *
	 * Only mapped fields are included; unrecognised field keys are silently ignored.
	 *
	 * @param array $fields   Flat field array (keyed by EVF field_id).
	 *                        Each element: ['meta_key' => '...', 'value' => '...'].
	 * @param array $mappings Admin mapping rows: [['evf_field'=>'...','api_field'=>'...'],...]
	 * @return array
	 */
	private function build_payload( array $fields, array $mappings ): array {
		if ( empty( $mappings ) ) {
			return array();
		}

		// Flatten fields to a simple meta_key => value lookup.
		$values = $this->flatten_fields( $fields );

		$payload = array();
		foreach ( $mappings as $row ) {
			$evf_field = sanitize_text_field( $row['evf_field'] ?? '' );
			$api_field = sanitize_text_field( $row['api_field'] ?? '' );

			if ( '' === $evf_field || '' === $api_field ) {
				continue;
			}

			// Pseudo-field: inject the submitter's IP address.
			if ( '__ip_address__' === $evf_field ) {
				$ip = $this->get_client_ip();
				if ( '' !== $ip ) {
					$payload[ $api_field ] = $ip;
				}
				continue;
			}

			if ( isset( $values[ $evf_field ] ) ) {
				$payload[ $api_field ] = $values[ $evf_field ];
			}
		}

		return $payload;
	}

	/**
	 * Retrieve the client IP address from the server environment.
	 *
	 * Only REMOTE_ADDR is used (not X-Forwarded-For) to prevent spoofing.
	 *
	 * @return string Validated IPv4/IPv6 address, or empty string on failure.
	 */
	private function get_client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Convert the flat EVF fields array into a simple meta_key → value map.
	 *
	 * EVF stores processed fields as:
	 *   $fields[ $field_id ] = [ 'meta_key' => '...', 'value' => '...', ... ]
	 *
	 * @param  array $fields
	 * @return array<string, mixed>
	 */
	private function flatten_fields( array $fields ): array {
		$flat = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$meta_key = $field['meta_key'] ?? '';
			if ( '' !== $meta_key ) {
				$flat[ $meta_key ] = $field['value'] ?? '';
			}
		}
		return $flat;
	}
}
