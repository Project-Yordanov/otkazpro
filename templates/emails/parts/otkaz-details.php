<?php
/**
 * Withdrawal details block (HTML emails).
 *
 * Override: yourtheme/woocommerce/wpotkaz/emails/parts/otkaz-details.php
 *
 * @package PY\Otkaz
 *
 * @var \PY\Otkaz\Submission $submission    Submission.
 * @var \WC_Order            $order         Order.
 * @var bool                 $sent_to_admin Admin version.
 */

defined( 'ABSPATH' ) || exit;

$py_th = 'text-align:left;padding:8px 12px;border:1px solid #e5e5e5;vertical-align:top;';
$py_td = 'text-align:left;padding:8px 12px;border:1px solid #e5e5e5;vertical-align:top;';
?>
<table class="td" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 20px;border-collapse:collapse;">
	<tbody>
		<tr><th scope="row" style="<?php echo esc_attr( $py_th ); ?>"><?php esc_html_e( 'Statement number', 'wpotkaz' ); ?></th><td style="<?php echo esc_attr( $py_td ); ?>"><strong><?php echo esc_html( $submission->number ); ?></strong></td></tr>
		<tr><th scope="row" style="<?php echo esc_attr( $py_th ); ?>"><?php esc_html_e( 'Submitted on', 'wpotkaz' ); ?></th><td style="<?php echo esc_attr( $py_td ); ?>">
			<?php
			/* translators: 1: date, 2: time */
			echo esc_html( sprintf( __( '%1$s at %2$s', 'wpotkaz' ), $submission->date(), $submission->time() ) );
			?>
		</td></tr>
		<tr><th scope="row" style="<?php echo esc_attr( $py_th ); ?>"><?php esc_html_e( 'Name', 'wpotkaz' ); ?></th><td style="<?php echo esc_attr( $py_td ); ?>"><?php echo esc_html( $submission->name ); ?></td></tr>
		<tr><th scope="row" style="<?php echo esc_attr( $py_th ); ?>"><?php esc_html_e( 'Email', 'wpotkaz' ); ?></th><td style="<?php echo esc_attr( $py_td ); ?>"><?php echo esc_html( $submission->email ); ?></td></tr>
		<tr><th scope="row" style="<?php echo esc_attr( $py_th ); ?>"><?php esc_html_e( 'Order', 'wpotkaz' ); ?></th><td style="<?php echo esc_attr( $py_td ); ?>">
			<?php if ( $sent_to_admin ) : ?>
				<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php echo esc_html( '#' . $submission->order_number ); ?></a>
			<?php else : ?>
				<?php echo esc_html( '#' . $submission->order_number ); ?>
			<?php endif; ?>
			<?php if ( $order->get_date_created() ) : ?>
				<?php
				/* translators: %s: order date */
				echo esc_html( ' ' . sprintf( __( '(placed on %s)', 'wpotkaz' ), wc_format_datetime( $order->get_date_created() ) ) );
				?>
			<?php endif; ?>
		</td></tr>
		<?php if ( $sent_to_admin ) : ?>
			<tr><th scope="row" style="<?php echo esc_attr( $py_th ); ?>"><?php esc_html_e( 'Deadline', 'wpotkaz' ); ?></th><td style="<?php echo esc_attr( $py_td ); ?>">
				<?php
				if ( true === $submission->within_deadline ) {
					echo '<strong style="color:#1e7e34;">' . esc_html__( 'In time', 'wpotkaz' ) . '</strong>';
				} elseif ( false === $submission->within_deadline ) {
					echo '<strong style="color:#b45309;">' . esc_html__( 'After the deadline', 'wpotkaz' ) . '</strong>';
				} else {
					echo esc_html__( 'Period not started (order not completed)', 'wpotkaz' );
				}
				if ( $submission->deadline_gmt ) {
					/* translators: %s: deadline date */
					echo ' – ' . esc_html( sprintf( __( 'indicative deadline %s', 'wpotkaz' ), \PY\Otkaz\Util::format_gmt( $submission->deadline_gmt, 'date' ) ) );
				}
				?>
			</td></tr>
			<?php if ( '' !== $submission->reason ) : ?>
				<tr><th scope="row" style="<?php echo esc_attr( $py_th ); ?>"><?php esc_html_e( 'Reason (optional)', 'wpotkaz' ); ?></th><td style="<?php echo esc_attr( $py_td ); ?>"><?php echo nl2br( esc_html( $submission->reason ) ); ?></td></tr>
			<?php endif; ?>
		<?php endif; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'Products', 'wpotkaz' ); ?></h2>
<table class="td" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 20px;border-collapse:collapse;">
	<thead>
		<tr>
			<th scope="col" style="<?php echo esc_attr( $py_th ); ?>"><?php esc_html_e( 'Product', 'wpotkaz' ); ?></th>
			<th scope="col" style="<?php echo esc_attr( $py_th ); ?>"><?php esc_html_e( 'Quantity', 'wpotkaz' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $submission->items as $wpotkaz_item ) : ?>
			<tr>
				<td style="<?php echo esc_attr( $py_td ); ?>">
					<?php echo esc_html( $wpotkaz_item['name'] ); ?>
					<?php if ( ! empty( $wpotkaz_item['sku'] ) ) : ?>
						<br /><small><?php echo esc_html( sprintf( 'SKU: %s', $wpotkaz_item['sku'] ) ); ?></small>
					<?php endif; ?>
					<?php if ( $sent_to_admin && ! empty( $wpotkaz_item['excluded'] ) ) : ?>
						<br /><strong style="color:#b45309;">
							<?php
							/* translators: %s: reason */
							echo esc_html( sprintf( __( 'Excluded product: %s', 'wpotkaz' ), $wpotkaz_item['excluded_reason'] ) );
							?>
						</strong>
					<?php endif; ?>
				</td>
				<td style="<?php echo esc_attr( $py_td ); ?>">
					<?php
					/* translators: 1: withdrawn quantity, 2: purchased quantity */
					echo esc_html( sprintf( __( '%1$d of %2$d', 'wpotkaz' ), (int) $wpotkaz_item['qty'], (int) $wpotkaz_item['ordered_qty'] ) );
					?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'Text of the statement', 'wpotkaz' ); ?></h2>
<p style="margin:0 0 20px;padding:12px 16px;border-left:3px solid #e5e5e5;"><?php echo esc_html( $submission->statement ); ?></p>
