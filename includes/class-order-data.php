<?php
/**
 * Order eligibility, items, history and deadline.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Order helpers (HPOS-safe – CRUD only).
 */
class Order_Data {

	const META_HISTORY = '_py_otkaz_history';

	/**
	 * Eligible status slugs (without wc- prefix).
	 *
	 * @return string[]
	 */
	public static function eligible_statuses() {
		$statuses = array_map(
			static function ( $s ) {
				return 0 === strpos( $s, 'wc-' ) ? substr( $s, 3 ) : $s;
			},
			(array) Settings::get( 'statuses' )
		);
		// Never allow these, even if misconfigured.
		$statuses = array_diff( $statuses, array( 'cancelled', 'refunded', 'failed', 'pending', 'checkout-draft' ) );
		if ( Settings::enabled( 'custom_status' ) ) {
			$statuses[] = Order_Status::STATUS;
		}
		return array_values( array_unique( apply_filters( 'py_otkaz_eligible_statuses', $statuses ) ) );
	}

	/**
	 * Whether the order status allows withdrawal.
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	public static function is_eligible_status( $order ) {
		return in_array( $order->get_status(), self::eligible_statuses(), true );
	}

	/**
	 * Withdrawal history stored on the order.
	 *
	 * @param \WC_Order $order Order.
	 * @return array[] Each: number, date_gmt, items => [item_id => qty].
	 */
	public static function history( $order ) {
		$history = $order->get_meta( self::META_HISTORY );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Append an entry to the history (caller saves the order).
	 *
	 * @param \WC_Order $order Order.
	 * @param array     $entry Entry.
	 */
	public static function add_history( $order, $entry ) {
		$history   = self::history( $order );
		$history[] = $entry;
		$order->update_meta_data( self::META_HISTORY, $history );
	}

	/**
	 * Prepared line items with remaining quantities and exclusion info.
	 *
	 * @param \WC_Order $order Order.
	 * @return array[] Keyed by item ID.
	 */
	public static function get_items( $order ) {
		$history   = self::history( $order );
		$requested = array();
		$requests  = array();
		foreach ( $history as $entry ) {
			foreach ( (array) $entry['items'] as $item_id => $qty ) {
				$requested[ $item_id ]  = ( isset( $requested[ $item_id ] ) ? $requested[ $item_id ] : 0 ) + (int) $qty;
				$requests[ $item_id ][] = array(
					'number'   => $entry['number'],
					'date_gmt' => $entry['date_gmt'],
					'qty'      => (int) $qty,
				);
			}
		}

		$strict = 'strict' === Settings::get( 'exclusion_mode' );
		$items  = array();

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			/** @var \WC_Order_Item_Product $item */
			$ordered   = (int) $item->get_quantity();
			$refunded  = absint( $order->get_qty_refunded_for_item( $item_id ) );
			$req       = isset( $requested[ $item_id ] ) ? $requested[ $item_id ] : 0;
			$remaining = max( 0, $ordered - $refunded - $req );
			$excluded  = Exclusions::for_item( $item, $order );
			$product   = $item->get_product();

			$items[ $item_id ] = array(
				'id'         => $item_id,
				'name'       => $item->get_name(),
				'sku'        => $product ? $product->get_sku() : '',
				'product_id' => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'image'      => $product ? $product->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) ) : '',
				'meta_html'  => wc_display_item_meta(
					$item,
					array(
						'echo'      => false,
						'before'    => '',
						'after'     => '',
						'separator' => ', ',
						'autop'     => false,
					)
				),
				'ordered'    => $ordered,
				'refunded'   => $refunded,
				'requested'  => $req,
				'remaining'  => $remaining,
				'requests'   => isset( $requests[ $item_id ] ) ? $requests[ $item_id ] : array(),
				'excluded'   => $excluded,
				'selectable' => $remaining > 0 && ! ( $strict && $excluded ),
				'preselect'  => $remaining > 0 && ! $excluded,
			);
		}

		/**
		 * Filter the items offered in the withdrawal form.
		 *
		 * @param array     $items Prepared items keyed by item ID.
		 * @param \WC_Order $order Order.
		 */
		return apply_filters( 'py_otkaz_eligible_items', $items, $order );
	}

	/**
	 * Whether the order has anything the customer can still withdraw from.
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	public static function has_selectable_items( $order ) {
		if ( ! self::is_eligible_status( $order ) ) {
			return false;
		}
		foreach ( self::get_items( $order ) as $item ) {
			if ( $item['selectable'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Indicative deadline (end of day, site timezone): completed date + N days.
	 *
	 * @param \WC_Order $order Order.
	 * @return \DateTimeImmutable|null Null when the order is not completed yet.
	 */
	public static function deadline( $order ) {
		$completed = $order->get_date_completed();
		if ( ! $completed ) {
			return null;
		}
		$days = max( 1, (int) Settings::get( 'deadline_days' ) );
		$date = ( new \DateTimeImmutable( '@' . $completed->getTimestamp() ) )
			->setTimezone( wp_timezone() )
			->modify( '+' . $days . ' days' )
			->setTime( 23, 59, 59 );
		return apply_filters( 'py_otkaz_deadline', $date, $order );
	}

	/**
	 * In time? true / false, or null when the period has not started (not completed).
	 *
	 * @param \WC_Order $order Order.
	 * @param int|null  $now   Timestamp.
	 * @return bool|null
	 */
	public static function is_within_deadline( $order, $now = null ) {
		$deadline = self::deadline( $order );
		if ( ! $deadline ) {
			return null;
		}
		return ( null === $now ? time() : $now ) <= $deadline->getTimestamp();
	}
}
