<?php
/**
 * Admin Settings – provides the WordPress admin UI for configuring the connector.
 *
 * Settings page lives at: Settings → EVF API Connector
 * (admin.php?page=evf-api-connector)
 *
 * Layout:
 *  ┌────────────────────────────────────────────┐
 *  │  CẤU HÌNH THEO BIỂU MẪU                    │
 *  │  Chọn biểu mẫu: [dropdown]  [Tải]         │
 *  │                                            │
 *  │  (nếu đã chọn biểu mẫu)                   │
 *  │    • Bật tích hợp (toggle)                 │
 *  │    • Phương thức HTTP (GET/POST/PUT/...)    │
 *  │    • URL Endpoint API                      │
 *  │    • HTTP Headers (hàng lặp)               │
 *  │    • Ánh xạ trường (hàng lặp)              │
 *  │    • Thông báo tùy chỉnh (toggle + textarea)│
 *  │  [Lưu cài đặt biểu mẫu]                   │
 *  └────────────────────────────────────────────┘
 *
 * Save flow:
 *  - Saves are handled in admin_init before any HTML is emitted.
 *  - On success the user is redirected back with ?saved=1 to show a notice.
 *  - Nonces protect every POST action.
 *
 * @package EVF_API_Connector
 */

defined( 'ABSPATH' ) || exit;

class EVF_API_Admin_Settings {

	/** Admin page slug */
	const PAGE_SLUG = 'evf-api-connector';

	// ── Constructor ───────────────────────────────────────────────────────────

	public function __construct() {
		add_action( 'admin_menu',  array( $this, 'register_menu' ) );
		add_action( 'admin_init',  array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX: return fields for a selected form (used by field-mapping JS).
		add_action( 'wp_ajax_evf_api_connector_get_fields', array( $this, 'ajax_get_fields' ) );
	}

	// ── Menu ──────────────────────────────────────────────────────────────────

	public function register_menu(): void {
		add_options_page(
			__( 'EVF API Connector', 'evf-api-connector' ),
			__( 'EVF API Connector', 'evf-api-connector' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'evf-api-connector-admin',
			EVF_API_CONNECTOR_URL . 'assets/css/admin-settings.css',
			array(),
			EVF_API_CONNECTOR_VERSION
		);

		wp_enqueue_script(
			'evf-api-connector-admin',
			EVF_API_CONNECTOR_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			EVF_API_CONNECTOR_VERSION,
			true
		);

		// Pass data to JS.
		wp_localize_script(
			'evf-api-connector-admin',
			'evfApiConnector',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'evf_api_connector_get_fields' ),
				'selectedForm'  => $this->get_selected_form_id(),
				'i18n'          => array(
			'removeHeader'  => __( 'Xóa', 'evf-api-connector' ),
				'removeMapping' => __( 'Xóa', 'evf-api-connector' ),
				'addHeader'     => __( '+ Thêm Header', 'evf-api-connector' ),
				'addMapping'    => __( '+ Thêm ánh xạ', 'evf-api-connector' ),
				'selectField'   => __( '— chọn trường —', 'evf-api-connector' ),
				'loadingFields' => __( 'Đang tải…', 'evf-api-connector' ),
				),
			)
		);
	}

	// ── Save Handler (admin_init) ─────────────────────────────────────────────

	/**
	 * Handle POST saves before any HTML output.
	 */
	public function handle_save(): void {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_POST['evf_api_connector_action'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có đủ quyền hạn để thực hiện thao tác này.', 'evf-api-connector' ) );
		}

		$action = sanitize_key( $_POST['evf_api_connector_action'] ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'save_form' === $action ) {
			check_admin_referer( 'evf_api_connector_save_form' );
			$form_id = isset( $_POST['evf_form_id'] ) ? (int) $_POST['evf_form_id'] : 0;
			if ( $form_id > 0 ) {
				$this->save_form_settings( $form_id );
			}
			$this->redirect_saved( $form_id );
		}
	}

	/** Redirect back to settings page after save. */
	private function redirect_saved( int $form_id = 0 ): void {
		$args = array(
			'page'  => self::PAGE_SLUG,
			'saved' => '1',
		);
		if ( $form_id > 0 ) {
			$args['form_id'] = $form_id;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}

	// ── Save: Per-Form Settings ───────────────────────────────────────────────

	private function save_form_settings( int $form_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification
		$settings = EVF_API_Connector::get_settings();
		if ( ! isset( $settings['forms'] ) || ! is_array( $settings['forms'] ) ) {
			$settings['forms'] = array();
		}

		// ── Basic fields ─────────────────────────────────────────────────────
		$allowed_methods = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );
		$raw_method      = strtoupper( sanitize_text_field( wp_unslash( $_POST['api_method'] ?? 'POST' ) ) );
		$api_method      = in_array( $raw_method, $allowed_methods, true ) ? $raw_method : 'POST';

		$form_settings = array(
			'enabled'             => ! empty( $_POST['enabled'] ) ? '1' : '0',
			'api_method'          => $api_method,
			'api_endpoint'        => esc_url_raw( wp_unslash( $_POST['api_endpoint'] ?? '' ) ),
			'use_custom_messages' => ! empty( $_POST['use_custom_messages'] ) ? '1' : '0',
			'success_message'     => wp_kses_post( wp_unslash( $_POST['success_message'] ?? '' ) ),
			'failure_message'     => wp_kses_post( wp_unslash( $_POST['failure_message'] ?? '' ) ),
		);

		// ── HTTP Headers (repeatable) ────────────────────────────────────────
		$header_keys   = isset( $_POST['header_key'] )   ? (array) $_POST['header_key']   : array();
		$header_values = isset( $_POST['header_value'] ) ? (array) $_POST['header_value'] : array();
		$headers       = array();
		$count         = max( count( $header_keys ), count( $header_values ) );

		for ( $i = 0; $i < $count; $i++ ) {
			$k = sanitize_text_field( wp_unslash( $header_keys[ $i ] ?? '' ) );
			$v = sanitize_text_field( wp_unslash( $header_values[ $i ] ?? '' ) );
			if ( '' !== $k ) {
				$headers[] = array(
					'key'   => $k,
					'value' => $v,
				);
			}
		}
		$form_settings['headers'] = $headers;

		// ── Field Mappings (repeatable) ──────────────────────────────────────
		$evf_fields = isset( $_POST['mapping_evf_field'] ) ? (array) $_POST['mapping_evf_field'] : array();
		$api_fields = isset( $_POST['mapping_api_field'] ) ? (array) $_POST['mapping_api_field'] : array();
		$mappings   = array();
		$count      = max( count( $evf_fields ), count( $api_fields ) );

		for ( $i = 0; $i < $count; $i++ ) {
			$evf = sanitize_text_field( wp_unslash( $evf_fields[ $i ] ?? '' ) );
			$api = sanitize_text_field( wp_unslash( $api_fields[ $i ] ?? '' ) );
			if ( '' !== $evf && '' !== $api ) {
				$mappings[] = array(
					'evf_field' => $evf,
					'api_field' => $api,
				);
			}
		}
		$form_settings['field_mappings'] = $mappings;

		// ── Persist ──────────────────────────────────────────────────────────
		$settings['forms'][ $form_id ] = $form_settings;
		update_option( EVF_API_CONNECTOR_OPTION_KEY, $settings );
		// phpcs:enable WordPress.Security.NonceVerification
	}

	// ── AJAX: Get Form Fields ─────────────────────────────────────────────────

	/**
	 * Return JSON array of {meta_key, label} pairs for the chosen form.
	 * Called from admin JS when the user selects a different form.
	 */
	public function ajax_get_fields(): void {
		check_ajax_referer( 'evf_api_connector_get_fields' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden.', 403 );
		}

		$form_id = isset( $_POST['form_id'] ) ? (int) $_POST['form_id'] : 0;
		if ( $form_id <= 0 ) {
			wp_send_json_error( 'Invalid form ID.' );
		}

		$fields = $this->get_evf_form_fields( $form_id );
		wp_send_json_success( $fields );
	}

	// ── Rendering ─────────────────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$form_id      = $this->get_selected_form_id();
		$form_cfg     = $form_id ? EVF_API_Connector::get_form_settings( $form_id ) : array();
		$saved        = isset( $_GET['saved'] ) && '1' === $_GET['saved']; // phpcs:ignore WordPress.Security.NonceVerification
		$all_forms    = $this->get_all_evf_forms();
		$form_fields  = $form_id ? $this->get_evf_form_fields( $form_id ) : array();

		?>
		<div class="wrap evf-api-connector-wrap">
			<h1><?php esc_html_e( 'EVF API Connector', 'evf-api-connector' ); ?></h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Đã lưu cài đặt.', 'evf-api-connector' ); ?></p>
				</div>
			<?php endif; ?>

			<?php /* ── CẤU HÌNH THEO BIỂU MẪU ── */ ?>
			<div class="evf-api-section">
				<h2><?php esc_html_e( 'Cấu hình theo biểu mẫu', 'evf-api-connector' ); ?></h2>

				<?php /* Form selector (GET – causes page reload) */ ?>
				<form method="get" class="evf-api-form-selector">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
					<label for="evf_form_select">
						<strong><?php esc_html_e( 'Chọn biểu mẫu:', 'evf-api-connector' ); ?></strong>
					</label>
					<select id="evf_form_select" name="form_id">
						<option value=""><?php esc_html_e( '— Chọn một biểu mẫu —', 'evf-api-connector' ); ?></option>
						<?php foreach ( $all_forms as $form ) : ?>
							<option value="<?php echo esc_attr( $form->ID ); ?>"
								<?php selected( $form_id, $form->ID ); ?>>
								<?php echo esc_html( $form->post_title ); ?> (ID: <?php echo esc_html( $form->ID ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Tải biểu mẫu', 'evf-api-connector' ), 'secondary', '', false ); ?>
				</form>

				<?php if ( $form_id > 0 ) : ?>
					<?php $this->render_form_settings( $form_id, $form_cfg, $form_fields ); ?>
				<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'Chọn một biểu mẫu ở trên để cấu hình tích hợp API.', 'evf-api-connector' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// ── Per-Form Settings Form ────────────────────────────────────────────────

	private function render_form_settings( int $form_id, array $cfg, array $form_fields ): void {
		$headers  = $cfg['headers']       ?? array();
		$mappings = $cfg['field_mappings'] ?? array();
		?>
		<form method="post" class="evf-api-form-settings">
			<?php wp_nonce_field( 'evf_api_connector_save_form' ); ?>
			<input type="hidden" name="evf_api_connector_action" value="save_form">
			<input type="hidden" name="evf_form_id" value="<?php echo esc_attr( $form_id ); ?>">

			<table class="form-table" role="presentation">

				<?php /* Enable Toggle */ ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Bật tích hợp', 'evf-api-connector' ); ?></th>
					<td>
						<label class="evf-toggle">
							<input type="checkbox" name="enabled" value="1"
								<?php checked( '1', $cfg['enabled'] ?? '0' ); ?>>
							<span><?php esc_html_e( 'Gửi dữ liệu lên API khi biểu mẫu được nộp', 'evf-api-connector' ); ?></span>
						</label>
					</td>
				</tr>
			<?php /* Send IP address */ ?>
			<!-- <tr>
				<th scope="row"><?php esc_html_e( 'Địa chỉ IP người gửi', 'evf-api-connector' ); ?></th>
				<td>
					<label class="evf-toggle">
						<input type="checkbox" name="send_ip" value="1"
							<?php checked( '1', $cfg['send_ip'] ?? '0' ); ?>>
						<span><?php esc_html_e( 'Đính kèm địa chỉ IP của người nộp vào payload', 'evf-api-connector' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Khi bật, địa chỉ IP sẽ được gửi kèm trong trường "_ip_address".', 'evf-api-connector' ); ?></p>
				</td>
			</tr> -->
				<?php /* HTTP Method */ ?>
				<tr>
					<th scope="row">
						<label for="api_method"><?php esc_html_e( 'Phương thức HTTP', 'evf-api-connector' ); ?></label>
					</th>
					<td>
						<select id="api_method" name="api_method">
							<?php
							$methods         = array( 'POST', 'GET', 'PUT', 'PATCH', 'DELETE' );
							$current_method  = $cfg['api_method'] ?? 'POST';
							foreach ( $methods as $m ) :
								?>
								<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $current_method, $m ); ?>>
									<?php echo esc_html( $m ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Phương thức HTTP sẽ được dùng khi gọi API bên thứ ba.', 'evf-api-connector' ); ?></p>
					</td>
				</tr>

				<?php /* API Endpoint */ ?>
				<tr>
					<th scope="row">
						<label for="api_endpoint"><?php esc_html_e( 'URL Endpoint API', 'evf-api-connector' ); ?></label>
					</th>
					<td>
						<input type="url" id="api_endpoint" name="api_endpoint"
							value="<?php echo esc_attr( $cfg['api_endpoint'] ?? '' ); ?>"
							class="regular-text"
							placeholder="https://api.example.com/endpoint"
							required>
						<p class="description"><?php esc_html_e( 'URL đầy đủ (HTTPS) của endpoint API bên thứ ba.', 'evf-api-connector' ); ?></p>
					</td>
				</tr>

				<?php /* HTTP Headers */ ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'HTTP Headers (Tiêu đề)', 'evf-api-connector' ); ?></th>
					<td>
						<div id="evf-headers-wrap">
							<table class="evf-repeatable-table widefat">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Tên Header', 'evf-api-connector' ); ?></th>
										<th><?php esc_html_e( 'Giá trị', 'evf-api-connector' ); ?></th>
										<th></th>
									</tr>
								</thead>
								<tbody id="evf-headers-list">
									<?php if ( empty( $headers ) ) : ?>
										<?php $this->render_header_row(); ?>
									<?php else : ?>
										<?php foreach ( $headers as $row ) : ?>
											<?php $this->render_header_row( $row['key'] ?? '', $row['value'] ?? '' ); ?>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
							<button type="button" class="button evf-add-header">
								<?php esc_html_e( '+ Thêm Header', 'evf-api-connector' ); ?>
							</button>
						</div>
						<p class="description">
							<?php esc_html_e( 'Không bắt buộc. Ví dụ: Authorization: Bearer your-token', 'evf-api-connector' ); ?>
						</p>
					</td>
				</tr>

				<?php /* Field Mappings */ ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Ánh xạ trường dữ liệu', 'evf-api-connector' ); ?></th>
					<td>
						<div id="evf-mappings-wrap">
							<table class="evf-repeatable-table widefat">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Trường biểu mẫu', 'evf-api-connector' ); ?></th>
										<th><?php esc_html_e( 'Tên trường API', 'evf-api-connector' ); ?></th>
										<th></th>
									</tr>
								</thead>
								<tbody id="evf-mappings-list">
									<?php if ( empty( $mappings ) ) : ?>
										<?php $this->render_mapping_row( '', '', $form_fields ); ?>
									<?php else : ?>
										<?php foreach ( $mappings as $row ) : ?>
											<?php $this->render_mapping_row( $row['evf_field'] ?? '', $row['api_field'] ?? '', $form_fields ); ?>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
							<button type="button" class="button evf-add-mapping">
								<?php esc_html_e( '+ Thêm ánh xạ', 'evf-api-connector' ); ?>
							</button>
						</div>
						<p class="description">
							<?php esc_html_e( 'Ánh xạ trường biểu mẫu (trái) tương ứng với khóa JSON mà API yêu cầu (phải). Các trường không được ánh xạ sẽ bị bỏ qua.', 'evf-api-connector' ); ?>
						</p>
					</td>
				</tr>

				<?php /* Custom Messages */ ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Thông báo tùy chỉnh', 'evf-api-connector' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="use_custom_messages" name="use_custom_messages" value="1"
								<?php checked( '1', $cfg['use_custom_messages'] ?? '0' ); ?>>
							<?php esc_html_e( 'Ghi đè thông báo thành công của biểu mẫu dựa trên kết quả API', 'evf-api-connector' ); ?>
						</label>
					</td>
				</tr>

				<tr class="evf-custom-msg-row" style="<?php echo empty( $cfg['use_custom_messages'] ) ? 'display:none' : ''; ?>">
					<th scope="row">
						<label for="success_message"><?php esc_html_e( 'Thông báo thành công', 'evf-api-connector' ); ?></label>
					</th>
					<td>
						<textarea id="success_message" name="success_message"
							class="large-text" rows="3"
							placeholder="<?php esc_attr_e( 'Thông tin của bạn đã được gửi thành công.', 'evf-api-connector' ); ?>"><?php echo esc_textarea( $cfg['success_message'] ?? '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Hiển thị khi API trả về mã HTTP 2xx.', 'evf-api-connector' ); ?></p>
					</td>
				</tr>

				<tr class="evf-custom-msg-row" style="<?php echo empty( $cfg['use_custom_messages'] ) ? 'display:none' : ''; ?>">
					<th scope="row">
						<label for="failure_message"><?php esc_html_e( 'Thông báo thất bại', 'evf-api-connector' ); ?></label>
					</th>
					<td>
						<textarea id="failure_message" name="failure_message"
							class="large-text" rows="3"
							placeholder="<?php esc_attr_e( 'Có lỗi xảy ra khi gửi thông tin của bạn. Vui lòng thử lại sau.', 'evf-api-connector' ); ?>"><?php echo esc_textarea( $cfg['failure_message'] ?? '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Hiển thị khi API gọi thất bại hoặc trả về mã không phải 2xx.', 'evf-api-connector' ); ?></p>
					</td>
				</tr>

			</table>

			<?php
			submit_button(
				sprintf(
					/* translators: %s: tên biểu mẫu */
					__( 'Lưu cài đặt cho "%s"', 'evf-api-connector' ),
					esc_html( get_the_title( $form_id ) )
				)
			);
			?>
		</form>
		<?php
	}

	// ── Row Renderers (server-side defaults) ──────────────────────────────────

	/**
	 * Render a single HTTP header row.
	 * The JS template uses the same HTML structure.
	 */
	private function render_header_row( string $key = '', string $value = '' ): void {
		?>
		<tr class="evf-header-row">
			<td>
				<input type="text" name="header_key[]"
					value="<?php echo esc_attr( $key ); ?>"
					placeholder="<?php esc_attr_e( 'Authorization', 'evf-api-connector' ); ?>"
					class="regular-text">
			</td>
			<td>
				<input type="text" name="header_value[]"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php esc_attr_e( 'Bearer token-của-bạn', 'evf-api-connector' ); ?>"
					class="regular-text">
			</td>
			<td>
				<button type="button" class="button button-small evf-remove-row">
					<?php esc_html_e( 'Xóa', 'evf-api-connector' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a single field mapping row.
	 *
	 * @param string $selected_evf  Currently saved EVF meta-key value.
	 * @param string $api_field     Currently saved API field name.
	 * @param array  $form_fields   Available EVF fields for the <select>.
	 */
	private function render_mapping_row( string $selected_evf, string $api_field, array $form_fields ): void {
		?>
		<tr class="evf-mapping-row">
			<td>
				<select name="mapping_evf_field[]" class="evf-field-select">
					<option value=""><?php esc_html_e( '— chọn trường —', 'evf-api-connector' ); ?></option>
					<?php foreach ( $form_fields as $field ) : ?>
						<option value="<?php echo esc_attr( $field['meta_key'] ); ?>"
							<?php selected( $selected_evf, $field['meta_key'] ); ?>>
							<?php echo esc_html( $field['label'] ); ?>
							(<?php echo esc_html( $field['meta_key'] ); ?>)
						</option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<input type="text" name="mapping_api_field[]"
					value="<?php echo esc_attr( $api_field ); ?>"
					placeholder="<?php esc_attr_e( 'api_field_name', 'evf-api-connector' ); ?>"
					class="regular-text">
			</td>
			<td>
				<button type="button" class="button button-small evf-remove-row">
					<?php esc_html_e( 'Xóa', 'evf-api-connector' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}

	// ── Data Helpers ──────────────────────────────────────────────────────────

	/**
	 * Get the form_id from the query string (validated as existing post).
	 */
	private function get_selected_form_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification
		$form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;
		if ( $form_id > 0 && 'everest_form' === get_post_type( $form_id ) ) {
			return $form_id;
		}
		return 0;
	}

	/**
	 * Return all published Everest Forms posts.
	 *
	 * @return WP_Post[]
	 */
	private function get_all_evf_forms(): array {
		return get_posts(
			array(
				'post_type'      => 'everest_form',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
	}

	/**
	 * Return a simplified array of fields for the given form.
	 *
	 * Each element: ['meta_key' => string, 'label' => string, 'type' => string]
	 *
	 * Uses EVF's own evf_get_form_fields() helper which handles the JSON decoding.
	 *
	 * @param  int $form_id
	 * @return array
	 */
	private function get_evf_form_fields( int $form_id ): array {
		if ( ! function_exists( 'evf_get_form_fields' ) ) {
			return array();
		}

		$raw = evf_get_form_fields( $form_id );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return array();
		}

		$fields = array();
		foreach ( $raw as $field ) {
			$meta_key = $field['meta-key'] ?? '';
			$label    = $field['label']    ?? '';
			$type     = $field['type']     ?? '';

			if ( '' === $meta_key ) {
				continue;
			}

			$fields[] = array(
				'meta_key' => sanitize_key( $meta_key ),
				'label'    => sanitize_text_field( $label ),
				'type'     => sanitize_key( $type ),
			);
		}

		// Add pseudo-field for client IP address.
		$fields[] = array(
			'meta_key' => '__ip_address__',
			'label'    => __( 'Địa chỉ IP người gửi', 'evf-api-connector' ),
			'type'     => 'ip',
		);

		return $fields;
	}
}
