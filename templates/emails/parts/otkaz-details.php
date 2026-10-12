<?php
/**
 * Withdrawal details block (HTML emails).
 *
 * Override: yourtheme/woocommerce/otkazpro/emails/parts/otkaz-details.php
 *
 * @package PY\Otkaz
 *
 * @var \PY\Otkaz\Submission $submission    Submission.
 * @var \WC_Order            $order         Order.
 * @var bool                 $sent_to_admin Admin version.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template: variables are local to the including function (wc_get_template).

$otkazpro_th = 'text-align:left;padding:8px 12px;border:1px solid #e5e5e5;vertical-align:top;';
$otkazpro_td = 'text-align:left;padding:8px 12px;border:1px solid #e5e5e5;vertical-align:top;';
?>
<table class="td" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 20px;border-collapse:collapse;">
	<tbody>
		<tr><th scope="row" style="<?php echo esc_attr( $otkazpro_th ); ?>"><?php esc_html_e( 'Statement number', 'otkazpro' ); ?></th><td style="<?php echo esc_attr( $otkazpro_td ); ?>"><strong><?php echo esc_html( $submission->number ); ?></strong></td></tr>
		<tr><th scope="row" style="<?php echo esc_attr( $otkazpro_th ); ?>"><?php esc_html_e( 'Submitted on', 'otkazpro' ); ?></th><td style="<?php echo esc_attr( $otkazpro_td ); ?>">
			<?php
			/* translators: 1: date, 2: time */
			echo esc_html( sprintf( __( '%1$s at %2$s', 'otkazpro' ), $submission->date(), $submission->time() ) );
			?>
		</td></tr>
		<tr><th scope="row" style="<?php echo esc_attr( $otkazpro_th ); ?>"><?php esc_html_e( 'Name', 'otkazpro' ); ?></th><td style="<?php echo esc_attr( $otkazpro_td ); ?>"><?php echo esc_html( $submission->name ); ?></td></tr>
		<tr><th scope="row" style="<?php echo esc_attr( $otkazpro_th ); ?>"><?php esc_html_e( 'Email', 'otkazpro' ); ?></th><td style="<?php echo esc_attr( $otkazpro_td ); ?>"><?php echo esc_html( $submission->email ); ?></td></tr>
		<tr><th scope="row" style="<?php echo esc_attr( $otkazpro_th ); ?>"><?php esc_html_e( 'Order', 'otkazpro' ); ?></th><td style="<?php echo esc_attr( $otkazpro_td ); ?>">
			<?php if ( $sent_to_admin ) : ?>
				<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php echo esc_html( '#' . $submission->order_number ); ?></a>
			<?php else : ?>
				<?php echo esc_html( '#' . $submission->order_number ); ?>
			<?php endif; ?>
			<?php if ( $order->get_date_created() ) : ?>
				<?php
				/* translators: %s: order date */
				echo esc_html( ' ' . sprintf( __( '(placed on %s)', 'otkazpro' ), wc_format_datetime( $order->get_date_created() ) ) );
				?>
			<?php endif; ?>
		</td></tr>
		<?php if ( $sent_to_admin ) : ?>
			<tr><th scope="row" style="<?php echo esc_attr( $otkazpro_th ); ?>"><?php esc_html_e( 'Deadline', 'otkazpro' ); ?></th><td style="<?php echo esc_attr( $otkazpro_td ); ?>">
				<?php
				if ( true === $submission->within_deadline ) {
					echo '<strong style="color:#1e7e34;">' . esc_html__( 'In time', 'otkazpro' ) . '</strong>';
				} elseif ( false === $submission->within_deadline ) {
					echo '<strong style="color:#b45309;">' . esc_html__( 'After the deadline', 'otkazpro' ) . '</strong>';
				} else {
					echo esc_html__( 'Period not started (order not completed)', 'otkazpro' );
				}
				if ( $submission->deadline_gmt ) {
					/* translators: %s: deadline date */
					echo ' – ' . esc_html( sprintf( __( 'indicative deadline %s', 'otkazpro' ), \PY\Otkaz\Util::format_gmt( $submission->deadline_gmt, 'date' ) ) );
				}
				?>
			</td></tr>
			<?php if ( '' !== $submission->reason ) : ?>
				<tr><th scope="row" style="<?php echo esc_attr( $otkazpro_th ); ?>"><?php esc_html_e( 'Reason (optional)', 'otkazpro' ); ?></th><td style="<?php echo esc_attr( $otkazpro_td ); ?>"><?php echo nl2br( esc_html( $submission->reason ) ); ?></td></tr>
			<?php endif; ?>
		<?php endif; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'Products', 'otkazpro' ); ?></h2>
<table class="td" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 20px;border-collapse:collapse;">
	<thead>
		<tr>
			<th scope="col" style="<?php echo esc_attr( $otkazpro_th ); ?>"><?php esc_html_e( 'Product', 'otkazpro' ); ?></th>
			<th scope="col" style="<?php echo esc_attr( $otkazpro_th ); ?>"><?php esc_html_e( 'Quantity', 'otkazpro' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $submission->items as $otkazpro_item ) : ?>
			<tr>
				<td style="<?php echo esc_attr( $otkazpro_td ); ?>">
					<?php echo esc_html( $otkazpro_item['name'] ); ?>
					<?php if ( ! empty( $otkazpro_item['sku'] ) ) : ?>
						<br /><small><?php echo esc_html( sprintf( 'SKU: %s', $otkazpro_item['sku'] ) ); ?></small>
					<?php endif; ?>
					<?php if ( $sent_to_admin && ! empty( $otkazpro_item['excluded'] ) ) : ?>
						<br /><strong style="color:#b45309;">
							<?php
							/* translators: %s: reason */
							echo esc_html( sprintf( __( 'Excluded product: %s', 'otkazpro' ), $otkazpro_item['excluded_reason'] ) );
							?>
						</strong>
					<?php endif; ?>
				</td>
				<td style="<?php echo esc_attr( $otkazpro_td ); ?>">
					<?php
					/* translators: 1: withdrawn quantity, 2: purchased quantity */
					echo esc_html( sprintf( __( '%1$d of %2$d', 'otkazpro' ), (int) $otkazpro_item['qty'], (int) $otkazpro_item['ordered_qty'] ) );
					?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'Text of the statement', 'otkazpro' ); ?></h2>
<p style="margin:0 0 20px;padding:12px 16px;border-left:3px solid #e5e5e5;"><?php echo esc_html( $submission->statement ); ?></p>
