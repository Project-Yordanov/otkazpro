<?php
/**
 * Order verification and anti-enumeration rate limiting.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies access to an order.
 */
class Verifier {

	/**
	 * Load an order (not a refund).
	 *
	 * @param int $order_id Order ID.
	 * @return \WC_Order|null
	 */
	public static function get_order( $order_id ) {
		$order = $order_id ? wc_get_order( absint( $order_id ) ) : false;
		return $order instanceof \WC_Order ? $order : null;
	}

	/**
	 * Token (order key) or ownership access.
	 *
	 * @param \WC_Order|null $order Order.
	 * @param string         $key   Order key from the URL / form.
	 * @return bool
	 */
	public static function can_access( $order, $key = '' ) {
		if ( ! $order ) {
			return false;
		}
		$key = (string) $key;
		if ( '' !== $key && hash_equals( (string) $order->get_order_key(), $key ) ) {
			return true;
		}
		return self::is_owner( $order );
	}

	/**
	 * Logged-in owner of the order.
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	public static function is_owner( $order ) {
		$user_id = get_current_user_id();
		return $user_id > 0 && (int) $order->get_customer_id() === $user_id;
	}

	/**
	 * Find an order by its (displayed) number + billing email.
	 *
	 * @param string $number Order number as shown to the customer.
	 * @param string $email  Email.
	 * @return \WC_Order|null
	 */
	public static function lookup( $number, $email ) {
		$number = trim( (string) preg_replace( '/^[\s#№]+/u', '', (string) $number ) );
		$email  = strtolower( trim( (string) $email ) );
		if ( '' === $number || '' === $email ) {
			return null;
		}

		$order = null;

		// 1. Plain ID where the displayed number matches.
		if ( ctype_digit( $number ) ) {
			$candidate = self::get_order( (int) $number );
			if ( $candidate && (string) $candidate->get_order_number() === $number ) {
				$order = $candidate;
			}
		}

		// 2. Sequential order number plugins storing _order_number.
		if ( ! $order ) {
			$found = wc_get_orders(
				array(
					'limit'      => 1,
					'type'       => 'shop_order',
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_order_number',
							'value' => $number,
						),
					),
				)
			);
			if ( $found && $found[0] instanceof \WC_Order ) {
				$order = $found[0];
			}
		}

		/**
		 * Filter the order resolved from a customer-entered number (for custom numbering plugins).
		 *
		 * @param \WC_Order|null $order  Order.
		 * @param string         $number Entered number.
		 */
		$order = apply_filters( 'py_otkaz_find_order_by_number', $order, $number );

		if ( ! $order instanceof \WC_Order ) {
			return null;
		}
		$billing = strtolower( trim( (string) $order->get_billing_email() ) );
		if ( '' === $billing || ! hash_equals( $billing, $email ) ) {
			return null;
		}
		return $order;
	}

	/* ---------------------------------------------------------------------
	 * Rate limiting (failed attempts per IP)
	 * ------------------------------------------------------------------ */

	/**
	 * Transient key for the current IP.
	 *
	 * @return string
	 */
	private static function rl_key() {
		return 'py_otkaz_rl_' . md5( Util::client_ip() . '|' . wp_salt( 'nonce' ) );
	}

	/**
	 * Limits: [attempts, window seconds].
	 *
	 * @return array
	 */
	private static function limits() {
		return array(
			(int) apply_filters( 'py_otkaz_rate_limit_attempts', 10 ),
			(int) apply_filters( 'py_otkaz_rate_limit_window', 15 * MINUTE_IN_SECONDS ),
		);
	}

	/**
	 * Is the current IP blocked?
	 *
	 * @return bool
	 */
	public static function is_rate_limited() {
		list( $max ) = self::limits();
		$data        = get_transient( self::rl_key() );
		return is_array( $data ) && (int) $data['count'] >= $max;
	}

	/**
	 * Count a failed attempt.
	 */
	public static function register_failure() {
		list( , $window ) = self::limits();
		$key              = self::rl_key();
		$data             = get_transient( $key );
		if ( ! is_array( $data ) ) {
			$data = array(
				'count' => 0,
				'start' => time(),
			);
		}
		++$data['count'];
		$ttl = max( 60, $window - ( time() - (int) $data['start'] ) );
		set_transient( $key, $data, $ttl );
	}
}
