<?php
/**
 * Optional custom order status "Withdrawal requested".
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Custom status wc-otkaz-zayaven.
 */
class Order_Status {

	const STATUS = 'otkaz-zayaven';

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( ! Settings::enabled( 'custom_status' ) ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'add_status' ) );
		add_filter( 'woocommerce_order_is_paid_statuses', array( __CLASS__, 'add_slug' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( __CLASS__, 'add_slug' ) );
	}

	/**
	 * Register the post status (needed for both HPOS and legacy storage).
	 */
	public static function register() {
		register_post_status(
			'wc-' . self::STATUS,
			array(
				'label'                     => _x( 'Withdrawal requested', 'Order status', 'otkazpro' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Withdrawal requested <span class="count">(%s)</span>', 'Withdrawal requested <span class="count">(%s)</span>', 'otkazpro' ),
			)
		);
	}

	/**
	 * Add to WooCommerce statuses list.
	 *
	 * @param array $statuses Statuses.
	 * @return array
	 */
	public static function add_status( $statuses ) {
		$statuses[ 'wc-' . self::STATUS ] = _x( 'Withdrawal requested', 'Order status', 'otkazpro' );
		return $statuses;
	}

	/**
	 * Treat as paid / reportable.
	 *
	 * @param array $statuses Status slugs without prefix.
	 * @return array
	 */
	public static function add_slug( $statuses ) {
		if ( is_array( $statuses ) ) {
			$statuses[] = self::STATUS;
		}
		return $statuses;
	}
}
