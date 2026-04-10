<?php
/**
 * Email notifications for new applications.
 */

defined( 'ABSPATH' ) || exit;

class NTQ_Mailer {

	/**
	 * Send notification emails when a new application is received.
	 *
	 * @param int $application_id Application row ID.
	 */
	public static function send_new_application_notification( $application_id ) {
		$app = NTQ_Database::get_application( $application_id );
		if ( ! $app ) {
			return;
		}

		self::notify_admin( $app );
		self::notify_applicant( $app );
	}

	// ─── Admin notification ───────────────────────────────────────────────────

	private static function notify_admin( $app ) {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: 1: Site name, 2: Job title */
			__( '[%1$s] Hồ Sơ Mới: %2$s', 'ntq-recruitment' ),
			$site_name,
			$app->job_title
		);

		$cv_url = ! empty( $app->cv_file_url ) ? $app->cv_file_url : __( 'Không có file đính kèm', 'ntq-recruitment' );

		$admin_url = add_query_arg(
			array(
				'page'   => 'ntq-rec-applications',
				'action' => 'view',
				'id'     => $app->id,
			),
			admin_url( 'admin.php' )
		);

		$message = sprintf(
			__( "Một hồ sơ ứng tuyển mới vừa được gửi.\n\nVị trí: %s\nỨng viên: %s\nĐiện thoại: %s\nEmail: %s\nCV: %s\nNgày: %s\n\nXem hồ sơ: %s", 'ntq-recruitment' ),
			$app->job_title,
			$app->applicant_name,
			$app->phone,
			$app->email,
			$cv_url,
			$app->created_at,
			$admin_url
		);

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		wp_mail( $admin_email, $subject, $message, $headers );
	}

	// ─── Applicant confirmation ───────────────────────────────────────────────

	private static function notify_applicant( $app ) {
		$site_name = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: 1: Site name */
			__( '[%s] Chúng tôi đã nhận được hồ sơ của bạn!', 'ntq-recruitment' ),
			$site_name
		);

		$message = sprintf(
			__( "Xin chào %s,\n\nCảm ơn bạn đã ứng tuyển vào vị trí \"%s\" tại %s.\n\nChúng tôi đã nhận được hồ sơ và sẽ xem xét trong thời gian sớm nhất. Chúng tôi sẽ liên hệ với bạn nếu hồ sơ phù hợp với yêu cầu của chúng tôi.\n\nTrân trọng,\n%s", 'ntq-recruitment' ),
			$app->applicant_name,
			$app->job_title,
			$site_name,
			$site_name
		);

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		wp_mail( $app->email, $subject, $message, $headers );
	}
}
