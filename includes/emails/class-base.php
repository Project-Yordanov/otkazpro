<?php
/**
 * Shared base for OtkazPro emails.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz\Emails;

use PY\Otkaz\Settings;
use PY\Otkaz\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * Base email.
 */
abstract class Base extends \WC_Email {

	/**
	 * Current submission.
	 *
	 * @var Submission|null
	 */
	public $submission = null;

	/**
	 * Common setup.
	 */
	protected function setup_base() {
		$this->template_base = PY_OTKAZ_DIR . 'templates/';
		$this->placeholders  = array_merge(
			$this->placeholders,
			array(
				'{order_number}'  => '',
				'{order_date}'    => '',
				'{otkaz_number}'  => '',
				'{customer_name}' => '',
			)
		);
	}

	/**
	 * Fill placeholders from the submission.
	 *
	 * @param Submission $submission Submission.
	 * @param \WC_Order  $order      Order.
	 */
	protected function prepare( $submission, $order ) {
		$this->submission = $submission;
		$this->object     = $order;

		$this->placeholders['{order_number}']  = $order->get_order_number();
		$this->placeholders['{order_date}']    = $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '';
		$this->placeholders['{otkaz_number}']  = $submission->number;
		$this->placeholders['{customer_name}'] = $submission->name;
	}

	/**
	 * Template args.
	 *
	 * @param bool $plain Plain text.
	 * @return array
	 */
	protected function template_args( $plain ) {
		return array(
			'email_heading'       => $this->get_heading(),
			'additional_content'  => $this->get_additional_content(),
			'submission'          => $this->submission,
			'order'               => $this->object,
			'sent_to_admin'       => ! $this->is_customer_email(),
			'plain_text'          => $plain,
			'email'               => $this,
			'return_instructions' => Settings::text( 'text_return_instructions' ),
		);
	}

	/**
	 * HTML content (theme override path: woocommerce/otkazpro/emails/...).
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), 'woocommerce/otkazpro/', $this->template_base );
	}

	/**
	 * Plain content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), 'woocommerce/otkazpro/', $this->template_base );
	}

	/**
	 * Filterable content.
	 *
	 * @return string
	 */
	public function get_content() {
		/**
		 * Filter the full email content.
		 *
		 * @param string          $content    Content.
		 * @param string          $email_id   Email ID.
		 * @param Submission|null $submission Submission.
		 * @param Base            $email      Email object.
		 */
		return apply_filters( 'py_otkaz_email_content', parent::get_content(), $this->id, $this->submission, $this );
	}
}
