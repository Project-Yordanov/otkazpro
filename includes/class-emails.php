<?php
/**
 * Email registration, sending and the link in WooCommerce order emails.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Emails module.
 */
class Emails {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register' ) );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'order_email_link' ), 20, 4 );
	}

	/**
	 * Register WC_Email classes.
	 *
	 * @param array $emails Emails.
	 * @return array
	 */
	public static function register( $emails ) {
		$emails['PY_Otkaz_Customer_Confirmation'] = new Emails\Customer_Confirmation();
		$emails['PY_Otkaz_Admin_Notification']    = new Emails\Admin_Notification();
		return $emails;
	}

	/**
	 * Get one of our email objects.
	 *
	 * @param string $key Class key.
	 * @return \WC_Email|null
	 */
	private static function email( $key ) {
		$emails = WC()->mailer()->get_emails();
		return isset( $emails[ $key ] ) ? $emails[ $key ] : null;
	}

	/**
	 * Send both emails right after a submission.
	 *
	 * @param Submission $submission Submission.
	 * @param \WC_Order  $order      Order.
	 */
	public static function send_for_submission( $submission, $order ) {
		self::send_customer( $submission, $order );

		$admin = self::email( 'PY_Otkaz_Admin_Notification' );
		if ( ! $admin || ! $admin->is_enabled() || ! $admin->get_recipient() ) {
			$submission->update_email_status( '', 'disabled' );
			return;
		}
		$sent = (bool) $admin->trigger( $submission, $order );
		$submission->update_email_status( '', $sent ? 'sent' : 'failed' );
		if ( ! $sent ) {
			wc_get_logger()->error( sprintf( 'Store notification for %s could not be sent.', $submission->number ), array( 'source' => 'otkazpro' ) );
		}
	}

	/**
	 * Send (or resend) the customer confirmation and record the result.
	 *
	 * @param Submission     $submission Submission.
	 * @param \WC_Order|null $order      Order.
	 * @return bool
	 */
	public static function send_customer( $submission, $order = null ) {
		$order = $order ? $order : Verifier::get_order( $submission->order_id );
		$email = self::email( 'PY_Otkaz_Customer_Confirmation' );
		$sent  = false;

		if ( $email && $order ) {
			$state = I18n::switch_to_order_language( $order, $submission->language );
			try {
				$sent = (bool) $email->trigger( $submission, $order );
			} finally {
				I18n::restore( $state );
			}
		}

		$submission->update_email_status( $sent ? 'sent' : 'failed' );

		if ( ! $sent ) {
			wc_get_logger()->error(
				sprintf( 'Withdrawal confirmation %s could not be sent to %s (wp_mail returned false).', $submission->number, $submission->email ),
				array( 'source' => 'otkazpro' )
			);
			/**
			 * Fires when the customer confirmation email fails.
			 *
			 * @param Submission $submission Submission.
			 */
			do_action( 'py_otkaz_mail_failed', $submission );
		}
		return $sent;
	}

	/**
	 * Link block in WooCommerce customer order emails.
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Admin email.
	 * @param bool      $plain_text    Plain text.
	 * @param \WC_Email $email         Email.
	 */
	public static function order_email_link( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		if ( $sent_to_admin || ! $order instanceof \WC_Order || ! $email instanceof \WC_Email ) {
			return;
		}
		if ( ! in_array( $email->id, (array) Settings::get( 'email_link_emails' ), true ) ) {
			return;
		}
		if ( ! Order_Data::has_selectable_items( $order ) ) {
			return;
		}

		$url   = Form::order_url( $order, I18n::order_language( $order ) );
		$label = Settings::text( 'text_start_button' );
		$intro = __( 'You can withdraw from the contract within 14 days of receiving the goods, without giving a reason:', 'otkazpro' );

		if ( $plain_text ) {
			echo "\n" . esc_html( wp_strip_all_tags( $intro ) ) . "\n" . esc_html( $label ) . ': ' . esc_url_raw( $url ) . "\n\n";
			return;
		}

		printf(
			'<div class="py-otkaz-email-link" style="margin:0 0 24px;padding:12px 16px;border:1px solid #e5e5e5;border-radius:4px;"><p style="margin:0 0 6px;">%1$s</p><p style="margin:0;"><a href="%2$s" style="font-weight:600;text-decoration:underline;">%3$s</a></p></div>',
			esc_html( $intro ),
			esc_url( $url ),
			esc_html( $label )
		);
	}
}
