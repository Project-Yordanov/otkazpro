<?php
/**
 * Step 2 – select products and confirm the withdrawal.
 *
 * Override: yourtheme/woocommerce/wpotkaz/form-order.php
 *
 * @package PY\Otkaz
 *
 * @var \WC_Order $order          Order.
 * @var string    $order_key      Order key.
 * @var bool      $eligible       Status allows withdrawal.
 * @var array     $groups         selectable|excluded|done => items.
 * @var bool      $any_selectable Anything selectable.
 * @var bool      $strict         Strict exclusion mode.
 * @var bool      $reason_enabled Optional reason field.
 * @var string    $confirm_label  Confirm button label.
 * @var string    $name           Name.
 * @var string    $email          Email.
 * @var string    $reason         Reason.
 * @var string    $token          Idempotency token.
 * @var string    $src            Entry source.
 * @var string    $store_email    Store contact email.
 * @var string    $form_action    Action URL.
 * @var string[]  $errors         Errors.
 * @var array     $fields         Field errors.
 * @var string    $notice         Notice.
 * @var string    $honeypot       Honeypot name.
 */

defined( 'ABSPATH' ) || exit;

$py_tpl  = PY_OTKAZ_DIR . 'templates/';
$py_path = 'woocommerce/wpotkaz/';
$py_err  = static function ( $id ) use ( $fields ) {
	return isset( $fields[ $id ] ) ? $fields[ $id ] : '';
};
$py_date = $order->get_date_created();
?>
<div class="wpotkaz" data-wpotkaz-otkaz>
	<?php wc_get_template( 'parts/steps.php', array( 'current' => 2 ), $py_path, $py_tpl ); ?>

	<?php wc_get_template( 'parts/notices.php', compact( 'errors', 'fields', 'notice' ), $py_path, $py_tpl ); ?>

	<div class="wpotkaz__summary">
		<p class="wpotkaz__summary-title">
			<?php
			/* translators: %s: order number */
			echo esc_html( sprintf( __( 'Order #%s', 'wpotkaz' ), $order->get_order_number() ) );
			?>
		</p>
		<?php if ( $py_date ) : ?>
			<p class="wpotkaz__small">
				<?php
				/* translators: %s: date */
				echo esc_html( sprintf( __( 'Placed on %s', 'wpotkaz' ), wc_format_datetime( $py_date ) ) );
				?>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( ! $eligible ) : ?>
		<div class="wpotkaz__notice wpotkaz__notice--info" role="status">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: order status, 2: store email */
						__( 'An online withdrawal is not available for this order in its current status (%1$s). Please contact us at %2$s.', 'wpotkaz' ),
						wc_get_order_status_name( $order->get_status() ),
						$store_email
					)
				);
				?>
			</p>
		</div>
	<?php elseif ( ! $any_selectable ) : ?>
		<div class="wpotkaz__notice wpotkaz__notice--info" role="status">
			<?php if ( $groups['done'] ) : ?>
				<p><?php esc_html_e( 'A withdrawal has already been submitted for all eligible products in this order. The confirmations were sent to your email.', 'wpotkaz' ); ?></p>
			<?php else : ?>
				<p>
					<?php
					/* translators: %s: store email */
					echo esc_html( sprintf( __( 'The products in this order are not subject to the right of withdrawal (see the reasons below). If you think this is a mistake, please contact us at %s.', 'wpotkaz' ), $store_email ) );
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php if ( $groups['done'] || $groups['excluded'] ) : ?>
			<ul class="wpotkaz__items">
				<?php
				foreach ( array( 'done', 'excluded' ) as $py_group ) {
					foreach ( $groups[ $py_group ] as $wpotkaz_item ) {
						wc_get_template( 'parts/item.php', array( 'item' => $wpotkaz_item, 'group' => $py_group, 'strict' => $strict, 'fields' => $fields ), $py_path, $py_tpl );
					}
				}
				?>
			</ul>
		<?php endif; ?>
	<?php else : ?>
		<form class="wpotkaz__form" method="post" action="<?php echo esc_url( $form_action ); ?>" data-wpotkaz-form="submit">

			<fieldset class="wpotkaz__fieldset">
				<legend class="wpotkaz__h"><?php esc_html_e( 'Your details', 'wpotkaz' ); ?></legend>

				<div class="wpotkaz__grid">
					<div class="wpotkaz__field<?php echo $py_err( 'wpotkaz_name' ) ? ' has-error' : ''; ?>">
						<label for="wpotkaz_name"><?php esc_html_e( 'Name', 'wpotkaz' ); ?></label>
						<?php if ( $py_err( 'wpotkaz_name' ) ) : ?>
							<p class="wpotkaz__error" id="wpotkaz_name_error"><?php echo esc_html( $py_err( 'wpotkaz_name' ) ); ?></p>
						<?php endif; ?>
						<input type="text" id="wpotkaz_name" name="wpotkaz_name" value="<?php echo esc_attr( $name ); ?>" required autocomplete="name" maxlength="200"
							<?php echo $py_err( 'wpotkaz_name' ) ? 'aria-describedby="wpotkaz_name_error" aria-invalid="true"' : ''; ?> />
					</div>

					<div class="wpotkaz__field<?php echo $py_err( 'wpotkaz_email' ) ? ' has-error' : ''; ?>">
						<label for="wpotkaz_email"><?php esc_html_e( 'Email for the confirmation', 'wpotkaz' ); ?></label>
						<?php if ( $py_err( 'wpotkaz_email' ) ) : ?>
							<p class="wpotkaz__error" id="wpotkaz_email_error"><?php echo esc_html( $py_err( 'wpotkaz_email' ) ); ?></p>
						<?php endif; ?>
						<input type="email" id="wpotkaz_email" name="wpotkaz_email" value="<?php echo esc_attr( $email ); ?>" required autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="200"
							aria-describedby="wpotkaz_email_hint<?php echo $py_err( 'wpotkaz_email' ) ? ' wpotkaz_email_error' : ''; ?>"<?php echo $py_err( 'wpotkaz_email' ) ? ' aria-invalid="true"' : ''; ?> />
						<p class="wpotkaz__hint" id="wpotkaz_email_hint"><?php esc_html_e( 'We will send the confirmation of your withdrawal here.', 'wpotkaz' ); ?></p>
					</div>
				</div>
			</fieldset>

			<fieldset class="wpotkaz__fieldset<?php echo $py_err( 'wpotkaz_items' ) ? ' has-error' : ''; ?>" id="wpotkaz_items" tabindex="-1">
				<legend class="wpotkaz__h"><?php esc_html_e( 'Products you are withdrawing from', 'wpotkaz' ); ?></legend>
				<?php if ( $py_err( 'wpotkaz_items' ) ) : ?>
					<p class="wpotkaz__error"><?php echo esc_html( $py_err( 'wpotkaz_items' ) ); ?></p>
				<?php endif; ?>

				<?php if ( count( $groups['selectable'] ) + ( $strict ? 0 : count( $groups['excluded'] ) ) > 1 ) : ?>
					<p class="wpotkaz__selectall" hidden data-wpotkaz-selectall-wrap>
						<label><input type="checkbox" data-wpotkaz-selectall /> <?php esc_html_e( 'Select all', 'wpotkaz' ); ?></label>
					</p>
				<?php endif; ?>

				<?php if ( $groups['selectable'] ) : ?>
					<ul class="wpotkaz__items">
						<?php
						foreach ( $groups['selectable'] as $wpotkaz_item ) {
							wc_get_template( 'parts/item.php', array( 'item' => $wpotkaz_item, 'group' => 'selectable', 'strict' => $strict, 'fields' => $fields ), $py_path, $py_tpl );
						}
						?>
					</ul>
				<?php endif; ?>

				<?php if ( $groups['excluded'] ) : ?>
					<h3 class="wpotkaz__subh"><?php esc_html_e( 'Products without right of withdrawal', 'wpotkaz' ); ?></h3>
					<ul class="wpotkaz__items wpotkaz__items--excluded">
						<?php
						foreach ( $groups['excluded'] as $wpotkaz_item ) {
							wc_get_template( 'parts/item.php', array( 'item' => $wpotkaz_item, 'group' => 'excluded', 'strict' => $strict, 'fields' => $fields ), $py_path, $py_tpl );
						}
						?>
					</ul>
				<?php endif; ?>

				<?php if ( $groups['done'] ) : ?>
					<h3 class="wpotkaz__subh"><?php esc_html_e( 'Withdrawal already submitted', 'wpotkaz' ); ?></h3>
					<ul class="wpotkaz__items wpotkaz__items--done">
						<?php
						foreach ( $groups['done'] as $wpotkaz_item ) {
							wc_get_template( 'parts/item.php', array( 'item' => $wpotkaz_item, 'group' => 'done', 'strict' => $strict, 'fields' => $fields ), $py_path, $py_tpl );
						}
						?>
					</ul>
				<?php endif; ?>
			</fieldset>

			<?php if ( $reason_enabled ) : ?>
				<div class="wpotkaz__field">
					<label for="wpotkaz_reason"><?php esc_html_e( 'Reason (optional)', 'wpotkaz' ); ?></label>
					<p class="wpotkaz__hint" id="wpotkaz_reason_hint"><?php esc_html_e( 'Not required. Your withdrawal is valid without a reason.', 'wpotkaz' ); ?></p>
					<textarea id="wpotkaz_reason" name="wpotkaz_reason" rows="3" maxlength="2000" aria-describedby="wpotkaz_reason_hint"><?php echo esc_textarea( $reason ); ?></textarea>
				</div>
			<?php endif; ?>

			<div class="wpotkaz__hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $honeypot ); ?>"><?php esc_html_e( 'Leave this field empty', 'wpotkaz' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $honeypot ); ?>" name="<?php echo esc_attr( $honeypot ); ?>" value="" tabindex="-1" autocomplete="off" />
			</div>

			<input type="hidden" name="wpotkaz_action" value="submit" />
			<input type="hidden" name="wpotkaz_order" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
			<input type="hidden" name="wpotkaz_key" value="<?php echo esc_attr( $order_key ); ?>" />
			<input type="hidden" name="wpotkaz_token" value="<?php echo esc_attr( $token ); ?>" />
			<?php if ( $src ) : ?>
				<input type="hidden" name="wpotkaz_src" value="<?php echo esc_attr( $src ); ?>" />
			<?php endif; ?>
			<?php wp_nonce_field( 'py_otkaz_submit_' . $order->get_id(), '_wpotkaz_nonce', false ); ?>

			<?php do_action( 'py_otkaz_order_form_before_submit', $order ); ?>

			<div class="wpotkaz__confirm">
				<p class="wpotkaz__small" id="wpotkaz_confirm_note">
					<?php esc_html_e( 'By clicking the button you send a statement of withdrawal from the contract for the selected products. You will receive a confirmation with the date and time of submission by email.', 'wpotkaz' ); ?>
				</p>
				<p class="wpotkaz__status" data-wpotkaz-status aria-live="polite"></p>
				<div class="wpotkaz__actions">
					<button type="submit" class="wpotkaz__btn" data-wpotkaz-submit aria-describedby="wpotkaz_confirm_note"><?php echo esc_html( $confirm_label ); ?></button>
				</div>
			</div>
		</form>
	<?php endif; ?>
</div>
