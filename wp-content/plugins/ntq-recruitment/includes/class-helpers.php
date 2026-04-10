<?php
/**
 * Utility / helper functions used across the plugin.
 */

defined( 'ABSPATH' ) || exit;

class NTQ_Helpers {

	/**
	 * Upload a CV file directly to a private subfolder in wp-content/uploads.
	 * Files are NOT registered as WP attachments so they never appear in the Media Library.
	 *
	 * @param array $file  $_FILES['cv_file'] element.
	 * @return array|WP_Error  On success: array with keys 'attachment_id' (always 0), 'url', 'file'.
	 */
	public static function upload_cv( array $file ) {
		if ( ! isset( $file['name'], $file['tmp_name'], $file['size'] ) ) {
			return new WP_Error( 'missing_file', __( 'Không có file nào được tải lên.', 'ntq-recruitment' ) );
		}

		// Size check
		if ( (int) $file['size'] > NTQ_REC_MAX_UPLOAD_SIZE ) {
			return new WP_Error( 'file_too_large', __( 'Kích thước file không được vượt quá 5 MB.', 'ntq-recruitment' ) );
		}

		// Extension check
		$filename = sanitize_file_name( $file['name'] );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, NTQ_REC_ALLOWED_FILE_EXTS, true ) ) {
			return new WP_Error(
				'invalid_file_type',
				__( 'Định dạng không hợp lệ. Chỉ chấp nhận PDF, DOC và DOCX.', 'ntq-recruitment' )
			);
		}

		// MIME check (server-side, cannot be spoofed by the client)
		$check = wp_check_filetype( $filename, NTQ_REC_ALLOWED_MIME_TYPES );
		if ( empty( $check['type'] ) ) {
			return new WP_Error( 'invalid_mime', __( 'Định dạng MIME của file không được phép.', 'ntq-recruitment' ) );
		}

		// Also verify the actual detected MIME from the temp file (if finfo is available).
		if ( class_exists( 'finfo' ) ) {
			$finfo         = new finfo( FILEINFO_MIME_TYPE );
			$detected_mime = $finfo->file( $file['tmp_name'] );

			if ( ! in_array( $detected_mime, NTQ_REC_ALLOWED_MIME_TYPES, true ) ) {
				return new WP_Error(
					'invalid_mime_detected',
					__( 'Nội dung file không khớp với định dạng được phép.', 'ntq-recruitment' )
				);
			}
		}

		// ── Save directly to uploads/ntq-cv/YYYY/MM/ (no WP attachment created) ──
		$upload_dir = wp_upload_dir();
		$sub_path   = 'ntq-cv/' . gmdate( 'Y/m' );
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . $sub_path;

		if ( ! wp_mkdir_p( $target_dir ) ) {
			return new WP_Error( 'upload_dir_error', __( 'Không thể tạo thư mục lưu trữ.', 'ntq-recruitment' ) );
		}

		// Drop an index.php in the root ntq-cv folder to block directory listing.
		$index_file = trailingslashit( $upload_dir['basedir'] ) . 'ntq-cv/index.php';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index_file, '<?php // Silence is golden.' );
		}

		// Generate a unique filename to prevent collisions.
		$filename    = wp_unique_filename( $target_dir, $filename );
		$target_path = trailingslashit( $target_dir ) . $filename;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
			return new WP_Error( 'upload_failed', __( 'Không thể lưu file đã tải lên.', 'ntq-recruitment' ) );
		}

		// Restrict file permissions.
		chmod( $target_path, 0644 );

		$target_url = trailingslashit( $upload_dir['baseurl'] ) . $sub_path . '/' . $filename;

		return array(
			'attachment_id' => 0, // intentionally 0 – not a WP media attachment
			'url'           => $target_url,
			'file'          => $target_path,
		);
	}

	/**
	 * Get all terms for a given taxonomy as an array keyed by slug.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return WP_Term[]
	 */
	public static function get_term_options( $taxonomy ) {
		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Render an HTML pagination bar for use in AJAX responses.
	 *
	 * @param int $total_pages Total number of pages.
	 * @param int $current     Current page.
	 */
	public static function render_pagination( $total_pages, $current ) {
		if ( $total_pages <= 1 ) {
			return;
		}
		echo '<nav class="ntq-pagination">';
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			$active = ( $i === (int) $current ) ? ' ntq-pagination__item--active' : '';
			printf(
				'<a href="#" class="ntq-pagination__item%s" data-page="%d">%d</a>',
				esc_attr( $active ),
				$i,
				$i
			);
		}
		echo '</nav>';
	}

	/**
	 * Return the download URL for an attachment, formatted for admin display.
	 *
	 * @param int $attachment_id WP attachment ID.
	 * @return string HTML anchor or empty string.
	 */
	public static function cv_download_link( $attachment_id, $cv_file_url = '' ) {
		if ( $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
		} else {
			$url = $cv_file_url;
		}

		if ( empty( $url ) ) {
			return '—';
		}

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" download>%s</a>',
			esc_url( $url ),
			esc_html__( 'Tải CV', 'ntq-recruitment' )
		);
	}

	/**
	 * Sanitize and return allowed status labels.
	 *
	 * @return array slug => label
	 */
	public static function application_statuses() {
		return array(
			'new'       => __( 'Mới', 'ntq-recruitment' ),
			'reviewing' => __( 'Đang Xem Xét', 'ntq-recruitment' ),
			'accepted'  => __( 'Chấp Nhận', 'ntq-recruitment' ),
			'rejected'  => __( 'Từ Chối', 'ntq-recruitment' ),
		);
	}

	/**
	 * Return a human-readable label for a status slug.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	public static function status_label( $status ) {
		$statuses = self::application_statuses();
		return $statuses[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Return a CSS class name suffix for badge styling.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	public static function status_class( $status ) {
		$map = array(
			'new'       => 'info',
			'reviewing' => 'warning',
			'accepted'  => 'success',
			'rejected'  => 'danger',
		);
		return $map[ $status ] ?? 'secondary';
	}

	/**
	 * Build taxonomy terms display string (comma-separated names).
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return string
	 */
	public static function get_terms_string( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '—';
		}
		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}

	/**
	 * Safely get a GET/POST parameter.
	 *
	 * @param string $key     Parameter name.
	 * @param string $source  'get' or 'post'.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function request( $key, $source = 'get', $default = '' ) {
		// phpcs:disable WordPress.Security.NonceVerification
		$source_array = strtolower( $source ) === 'post' ? $_POST : $_GET;
		// phpcs:enable
		return isset( $source_array[ $key ] ) ? sanitize_text_field( wp_unslash( $source_array[ $key ] ) ) : $default;
	}
}
