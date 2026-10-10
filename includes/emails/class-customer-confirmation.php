<?php
/**
 * Customer confirmation on a durable medium.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * "Withdrawal confirmation" – sent to the email entered in the form.
 */
class Customer_Confirmation extends Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'py_otkaz_customer_confirmation';
		$this->customer_email = true;
		$this->title          = __( 'Withdrawal confirmation', 'otkazpro' );
		$this->description    = __( 'Sent to the customer immediately after a withdrawal statement is submitted (confirmation on a durable medium). Required by law, therefore it cannot be disabled.', 'otkazpro' );
		$this->template_html  = 'emails/customer-otkaz-confirmation.php';
		$this->template_plain = 'emails/plain/customer-otkaz-confirmation.php';
		$this->setup_base();

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Confirmation of your withdrawal {otkaz_number} – order #{order_number}', 'otkazpro' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'We have received your withdrawal', 'otkazpro' );
	}

	/**
	 * Default additional content.
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return __( 'Please keep this email – it is your confirmation of the withdrawal.', 'otkazpro' );
	}

	/**
	 * Legally required – always on.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return true;
	}

	/**
	 * Remove the "enabled" toggle.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		unset( $this->form_fields['enabled'] );
	}

	/**
	 * Send.
	 *
	 * @param \PY\Otkaz\Submission $submission Submission.
	 * @param \WC_Order            $order      Order.
	 * @return bool
	 */
	public function trigger( $submission, $order = null ) {
		if ( ! $submission || ! $order ) {
			return false;
		}
		$this->prepare( $submission, $order );
		$this->recipient = $submission->email;

		if ( ! $this->get_recipient() ) {
			return false;
		}
		return (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}
}
