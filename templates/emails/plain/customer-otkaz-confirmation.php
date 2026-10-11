<?php
/**
 * Customer withdrawal confirmation (plain text).
 *
 * Override: yourtheme/woocommerce/wpotkaz/emails/plain/customer-otkaz-confirmation.php
 *
 * @package PY\Otkaz
 *
 * @var string               $email_heading       Heading.
 * @var string               $additional_content  Additional content.
 * @var \PY\Otkaz\Submission $submission          Submission.
 * @var \WC_Order            $order               Order.
 * @var string               $return_instructions Return instructions.
 */

defined( 'ABSPATH' ) || exit;

echo "=================================================================\n";
echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n";
echo "=================================================================\n\n";

/* translators: %s: customer name */
echo esc_html( sprintf( __( 'Hello %s,', 'wpotkaz' ), $submission->name ) ) . "\n\n";
/* translators: 1: date, 2: time */
echo esc_html( sprintf( __( 'We confirm that we received your statement of withdrawal from the contract on %1$s at %2$s. Its content is below.', 'wpotkaz' ), $submission->date(), $submission->time() ) ) . "\n\n";

wc_get_template(
	'emails/plain/parts/otkaz-details.php',
	array(
		'submission'    => $submission,
		'order'         => $order,
		'sent_to_admin' => false,
	),
	'woocommerce/wpotkaz/',
	PY_OTKAZ_DIR . 'templates/'
);

if ( $return_instructions ) {
	echo "\n" . esc_html( mb_strtoupper( __( 'How to return the goods', 'wpotkaz' ) ) ) . "\n";
	echo esc_html( wp_strip_all_tags( wptexturize( $return_instructions ) ) ) . "\n";
}

if ( $additional_content ) {
	echo "\n----------------------------------------\n\n";
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}

echo "\n=================================================================\n\n";
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
