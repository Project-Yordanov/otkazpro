<?php
/**
 * Withdrawal details (plain text emails).
 *
 * @package PY\Otkaz
 *
 * @var \PY\Otkaz\Submission $submission    Submission.
 * @var \WC_Order            $order         Order.
 * @var bool                 $sent_to_admin Admin version.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template: variables are local to the including function (wc_get_template).

echo esc_html__( 'Statement number', 'otkazpro' ) . ': ' . esc_html( $submission->number ) . "\n";
/* translators: 1: date, 2: time */
echo esc_html__( 'Submitted on', 'otkazpro' ) . ': ' . esc_html( sprintf( __( '%1$s at %2$s', 'otkazpro' ), $submission->date(), $submission->time() ) ) . "\n";
echo esc_html__( 'Name', 'otkazpro' ) . ': ' . esc_html( $submission->name ) . "\n";
echo esc_html__( 'Email', 'otkazpro' ) . ': ' . esc_html( $submission->email ) . "\n";
echo esc_html__( 'Order', 'otkazpro' ) . ': #' . esc_html( $submission->order_number ) . "\n";

if ( $sent_to_admin ) {
	if ( true === $submission->within_deadline ) {
		$otkazpro_deadline = __( 'In time', 'otkazpro' );
	} elseif ( false === $submission->within_deadline ) {
		$otkazpro_deadline = __( 'After the deadline', 'otkazpro' );
	} else {
		$otkazpro_deadline = __( 'Period not started (order not completed)', 'otkazpro' );
	}
	echo esc_html__( 'Deadline', 'otkazpro' ) . ': ' . esc_html( $otkazpro_deadline ) . "\n";
	if ( '' !== $submission->reason ) {
		echo esc_html__( 'Reason (optional)', 'otkazpro' ) . ': ' . esc_html( $submission->reason ) . "\n";
	}
}

echo "\n" . esc_html( mb_strtoupper( __( 'Products', 'otkazpro' ) ) ) . "\n";
foreach ( $submission->items as $otkazpro_item ) {
	/* translators: 1: product name, 2: withdrawn quantity, 3: purchased quantity */
	echo '- ' . esc_html( sprintf( __( '%1$s – %2$d of %3$d', 'otkazpro' ), $otkazpro_item['name'], (int) $otkazpro_item['qty'], (int) $otkazpro_item['ordered_qty'] ) );
	if ( $sent_to_admin && ! empty( $otkazpro_item['excluded'] ) ) {
		/* translators: %s: reason */
		echo ' [' . esc_html( sprintf( __( 'Excluded product: %s', 'otkazpro' ), $otkazpro_item['excluded_reason'] ) ) . ']';
	}
	echo "\n";
}

echo "\n" . esc_html( mb_strtoupper( __( 'Text of the statement', 'otkazpro' ) ) ) . "\n";
echo esc_html( $submission->statement ) . "\n";
