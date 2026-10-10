<?php
/**
 * One order item row in the withdrawal form.
 *
 * Override: yourtheme/woocommerce/otkazpro/parts/item.php
 *
 * @package PY\Otkaz
 *
 * @var array  $item   Prepared item (see Order_Data::get_items) + checked, qty.
 * @var string $group  selectable|excluded|done.
 * @var bool   $strict Strict exclusion mode.
 * @var array  $fields Field errors.
 */

defined( 'ABSPATH' ) || exit;

$py_id       = (int) $item['id'];
$py_cb_id    = 'py_item_' . $py_id;
$py_qty_id   = 'py_qty_' . $py_id;
$py_error    = isset( $fields[ $py_cb_id ] ) ? $fields[ $py_cb_id ] : '';
$py_can      = 'done' !== $group && $item['selectable'];
$py_describe = array( 'py_item_info_' . $py_id );
if ( $py_error ) {
	$py_describe[] = 'py_item_error_' . $py_id;
}
$py_classes = array( 'py-otkaz-item', 'py-otkaz-item--' . $group );
if ( ! $py_can ) {
	$py_classes[] = 'is-disabled';
}
if ( $py_error ) {
	$py_classes[] = 'has-error';
}
?>
<li class="<?php echo esc_attr( implode( ' ', $py_classes ) ); ?>" data-py-item>
	<div class="py-otkaz-item__check">
		<?php if ( $py_can ) : ?>
			<input type="checkbox" id="<?php echo esc_attr( $py_cb_id ); ?>" name="py_items[<?php echo esc_attr( (string) $py_id ); ?>][selected]" value="1" <?php checked( ! empty( $item['checked'] ) ); ?>
				data-py-check aria-describedby="<?php echo esc_attr( implode( ' ', $py_describe ) ); ?>"<?php echo $py_error ? ' aria-invalid="true"' : ''; ?> />
		<?php else : ?>
			<input type="checkbox" id="<?php echo esc_attr( $py_cb_id ); ?>" disabled aria-describedby="<?php echo esc_attr( implode( ' ', $py_describe ) ); ?>" />
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $item['image'] ) ) : ?>
		<div class="py-otkaz-item__img" aria-hidden="true"><?php echo $item['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC_Product::get_image() markup. ?></div>
	<?php endif; ?>

	<div class="py-otkaz-item__body">
		<label class="py-otkaz-item__name" for="<?php echo esc_attr( $py_cb_id ); ?>"><?php echo esc_html( $item['name'] ); ?></label>
		<?php if ( ! empty( $item['meta_html'] ) ) : ?>
			<div class="py-otkaz-item__meta"><?php echo wp_kses_post( $item['meta_html'] ); ?></div>
		<?php endif; ?>

		<div class="py-otkaz-item__info" id="py_item_info_<?php echo esc_attr( (string) $py_id ); ?>">
			<span>
				<?php
				/* translators: %d: purchased quantity */
				echo esc_html( sprintf( _n( 'Purchased: %d pc.', 'Purchased: %d pcs.', $item['ordered'], 'otkazpro' ), $item['ordered'] ) );
				?>
			</span>
			<?php foreach ( $item['requests'] as $py_req ) : ?>
				<span class="py-otkaz-item__done">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: quantity, 2: date, 3: statement number */
							__( 'Withdrawal submitted on %2$s for %1$d pcs. (%3$s)', 'otkazpro' ),
							$py_req['qty'],
							\PY\Otkaz\Util::format_gmt( $py_req['date_gmt'], 'date' ),
							$py_req['number']
						)
					);
					?>
				</span>
			<?php endforeach; ?>
			<?php if ( ! empty( $item['refunded'] ) ) : ?>
				<span>
					<?php
					/* translators: %d: refunded quantity */
					echo esc_html( sprintf( __( 'Already refunded: %d pcs.', 'otkazpro' ), $item['refunded'] ) );
					?>
				</span>
			<?php endif; ?>
			<?php if ( $item['excluded'] && 'done' !== $group ) : ?>
				<span class="py-otkaz-item__warn">
					<?php if ( $strict ) : ?>
						<?php
						/* translators: %s: reason */
						echo esc_html( sprintf( __( 'No right of withdrawal: %s.', 'otkazpro' ), $item['excluded']['label'] ) );
						?>
					<?php else : ?>
						<?php
						/* translators: %s: reason */
						echo esc_html( sprintf( __( 'This product is marked as having no right of withdrawal (%s). You can still select it – the store will review your request.', 'otkazpro' ), $item['excluded']['label'] ) );
						?>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( $py_error ) : ?>
			<p class="py-otkaz__error" id="py_item_error_<?php echo esc_attr( (string) $py_id ); ?>"><?php echo esc_html( $py_error ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $py_can ) : ?>
		<div class="py-otkaz-item__qty">
			<?php if ( $item['remaining'] > 1 ) : ?>
				<label for="<?php echo esc_attr( $py_qty_id ); ?>"><?php esc_html_e( 'Qty', 'otkazpro' ); ?></label>
				<input type="number" id="<?php echo esc_attr( $py_qty_id ); ?>" name="py_items[<?php echo esc_attr( (string) $py_id ); ?>][qty]" value="<?php echo esc_attr( (string) $item['qty'] ); ?>" min="1" max="<?php echo esc_attr( (string) $item['remaining'] ); ?>" step="1" inputmode="numeric" data-py-qty />
				<span class="py-otkaz-item__max">
					<?php
					/* translators: %d: max quantity */
					echo esc_html( sprintf( __( 'of %d', 'otkazpro' ), $item['remaining'] ) );
					?>
				</span>
			<?php else : ?>
				<input type="hidden" name="py_items[<?php echo esc_attr( (string) $py_id ); ?>][qty]" value="1" data-py-qty />
				<span class="py-otkaz-item__max"><?php esc_html_e( '1 pc.', 'otkazpro' ); ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</li>
