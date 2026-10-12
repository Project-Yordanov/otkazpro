<?php
/**
 * My Account integration.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Buttons in My Account → Orders and the single order view.
 */
class Account {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'orders_action' ), 20, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'view_order_block' ), 20 );
	}

	/**
	 * Action button in the orders list.
	 *
	 * @param array     $actions Actions.
	 * @param \WC_Order $order   Order.
	 * @return array
	 */
	public static function orders_action( $actions, $order ) {
		if ( $order instanceof \WC_Order && Order_Data::has_selectable_items( $order ) ) {
			$actions['wpotkaz'] = array(
				'url'        => Form::order_url( $order, I18n::current_language() ),
				'name'       => Settings::text( 'text_start_button' ),
				/* translators: %s: order number */
				'aria-label' => sprintf( __( 'Withdraw from the contract for order #%s', 'wpotkaz' ), $order->get_order_number() ),
			);
		}
		return $actions;
	}

	/**
	 * Block on the view-order page.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function view_order_block( $order ) {
		if ( ! $order instanceof \WC_Order || ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'view-order' ) ) {
			return;
		}
		if ( ! Verifier::is_owner( $order ) ) {
			return;
		}

		$history    = Order_Data::history( $order );
		$selectable = Order_Data::has_selectable_items( $order );
		if ( ! $history && ! $selectable ) {
			return;
		}
		Form::enqueue_assets();
		?>
		<section class="wpotkaz-account">
			<h2><?php esc_html_e( 'Withdrawal from contract', 'wpotkaz' ); ?></h2>
			<?php if ( $history ) : ?>
				<ul>
					<?php foreach ( $history as $entry ) : ?>
						<li>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: statement number, 2: date */
									__( 'Withdrawal %1$s submitted on %2$s', 'wpotkaz' ),
									$entry['number'],
									Util::format_gmt( $entry['date_gmt'], 'datetime' )
								)
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $selectable ) : ?>
				<p><?php esc_html_e( 'You can withdraw from the contract online without giving a reason.', 'wpotkaz' ); ?></p>
				<p><a class="button wpotkaz-account__btn" href="<?php echo esc_url( Form::order_url( $order, I18n::current_language() ) ); ?>"><?php echo esc_html( Settings::text( 'text_start_button' ) ); ?></a></p>
			<?php endif; ?>
		</section>
		<?php
	}
}
