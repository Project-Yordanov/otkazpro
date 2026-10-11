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

echo esc_html__( 'Statement number', 'wpotkaz' ) . ': ' . esc_html( $submission->number ) . "\n";
/* translators: 1: date, 2: time */
echo esc_html__( 'Submitted on', 'wpotkaz' ) . ': ' . esc_html( sprintf( __( '%1$s at %2$s', 'wpotkaz' ), $submission->date(), $submission->time() ) ) . "\n";
echo esc_html__( 'Name', 'wpotkaz' ) . ': ' . esc_html( $submission->name ) . "\n";
echo esc_html__( 'Email', 'wpotkaz' ) . ': ' . esc_html( $submission->email ) . "\n";
echo esc_html__( 'Order', 'wpotkaz' ) . ': #' . esc_html( $submission->order_number ) . "\n";

if ( $sent_to_admin ) {
	if ( true === $submission->within_deadline ) {
		$py_deadline = __( 'In time', 'wpotkaz' );
	} elseif ( false === $submission->within_deadline ) {
		$py_deadline = __( 'After the deadline', 'wpotkaz' );
	} else {
		$py_deadline = __( 'Period not started (order not completed)', 'wpotkaz' );
	}
	echo esc_html__( 'Deadline', 'wpotkaz' ) . ': ' . esc_html( $py_deadline ) . "\n";
	if ( '' !== $submission->reason ) {
		echo esc_html__( 'Reason (optional)', 'wpotkaz' ) . ': ' . esc_html( $submission->reason ) . "\n";
	}
}

echo "\n" . esc_html( mb_strtoupper( __( 'Products', 'wpotkaz' ) ) ) . "\n";
foreach ( $submission->items as $py_item ) {
	/* translators: 1: product name, 2: withdrawn quantity, 3: purchased quantity */
	echo '- ' . esc_html( sprintf( __( '%1$s – %2$d of %3$d', 'wpotkaz' ), $py_item['name'], (int) $py_item['qty'], (int) $py_item['ordered_qty'] ) );
	if ( $sent_to_admin && ! empty( $py_item['excluded'] ) ) {
		/* translators: %s: reason */
		echo ' [' . esc_html( sprintf( __( 'Excluded product: %s', 'wpotkaz' ), $py_item['excluded_reason'] ) ) . ']';
	}
	echo "\n";
}

echo "\n" . esc_html( mb_strtoupper( __( 'Text of the statement', 'wpotkaz' ) ) ) . "\n";
echo esc_html( $submission->statement ) . "\n";
