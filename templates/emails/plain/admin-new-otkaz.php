<?php
/**
 * Store notification – new withdrawal (plain text).
 *
 * Override: yourtheme/woocommerce/wpotkaz/emails/plain/admin-new-otkaz.php
 *
 * @package PY\Otkaz
 *
 * @var string               $email_heading      Heading.
 * @var string               $additional_content Additional content.
 * @var \PY\Otkaz\Submission $submission         Submission.
 * @var \WC_Order            $order              Order.
 */

defined( 'ABSPATH' ) || exit;

echo "=================================================================\n";
echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n";
echo "=================================================================\n\n";

/* translators: 1: customer name, 2: order number */
echo esc_html( sprintf( __( '%1$s submitted a withdrawal from the contract for order #%2$s.', 'wpotkaz' ), $submission->name, $submission->order_number ) ) . "\n\n";

if ( $submission->has_excluded ) {
	echo esc_html__( 'The statement includes products marked as having no right of withdrawal. Review the request and reply to the customer.', 'wpotkaz' ) . "\n\n";
}

wc_get_template(
	'emails/plain/parts/otkaz-details.php',
	array(
		'submission'    => $submission,
		'order'         => $order,
		'sent_to_admin' => true,
	),
	'woocommerce/wpotkaz/',
	PY_OTKAZ_DIR . 'templates/'
);

echo "\n" . esc_html__( 'Open the order', 'wpotkaz' ) . ': ' . esc_url_raw( $order->get_edit_order_url() ) . "\n";

if ( 'sent' !== $submission->email_status ) {
	echo "\n" . esc_html__( 'The confirmation email to the customer could not be sent. Use “Resend” in WooCommerce → Withdrawals.', 'wpotkaz' ) . "\n";
}

if ( $additional_content ) {
	echo "\n----------------------------------------\n\n";
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}

echo "\n=================================================================\n\n";
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
