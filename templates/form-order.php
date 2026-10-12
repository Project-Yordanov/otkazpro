<?php
/**
 * Step 2 – select products and confirm the withdrawal.
 *
 * Override: yourtheme/woocommerce/otkazpro/form-order.php
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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template: variables are local to the including function (wc_get_template).

$otkazpro_tpl  = PY_OTKAZ_DIR . 'templates/';
$otkazpro_path = 'woocommerce/otkazpro/';
$otkazpro_err  = static function ( $id ) use ( $fields ) {
	return isset( $fields[ $id ] ) ? $fields[ $id ] : '';
};
$otkazpro_date = $order->get_date_created();
?>
<div class="otkazpro" data-otkazpro>
	<?php wc_get_template( 'parts/steps.php', array( 'current' => 2 ), $otkazpro_path, $otkazpro_tpl ); ?>

	<?php wc_get_template( 'parts/notices.php', compact( 'errors', 'fields', 'notice' ), $otkazpro_path, $otkazpro_tpl ); ?>

	<div class="otkazpro__summary">
		<p class="otkazpro__summary-title">
			<?php
			/* translators: %s: order number */
			echo esc_html( sprintf( __( 'Order #%s', 'otkazpro' ), $order->get_order_number() ) );
			?>
		</p>
		<?php if ( $otkazpro_date ) : ?>
			<p class="otkazpro__small">
				<?php
				/* translators: %s: date */
				echo esc_html( sprintf( __( 'Placed on %s', 'otkazpro' ), wc_format_datetime( $otkazpro_date ) ) );
				?>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( ! $eligible ) : ?>
		<div class="otkazpro__notice otkazpro__notice--info" role="status">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: order status, 2: store email */
						__( 'An online withdrawal is not available for this order in its current status (%1$s). Please contact us at %2$s.', 'otkazpro' ),
						wc_get_order_status_name( $order->get_status() ),
						$store_email
					)
				);
				?>
			</p>
		</div>
	<?php elseif ( ! $any_selectable ) : ?>
		<div class="otkazpro__notice otkazpro__notice--info" role="status">
			<?php if ( $groups['done'] ) : ?>
				<p><?php esc_html_e( 'A withdrawal has already been submitted for all eligible products in this order. The confirmations were sent to your email.', 'otkazpro' ); ?></p>
			<?php else : ?>
				<p>
					<?php
					/* translators: %s: store email */
					echo esc_html( sprintf( __( 'The products in this order are not subject to the right of withdrawal (see the reasons below). If you think this is a mistake, please contact us at %s.', 'otkazpro' ), $store_email ) );
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php if ( $groups['done'] || $groups['excluded'] ) : ?>
			<ul class="otkazpro__items">
				<?php
				foreach ( array( 'done', 'excluded' ) as $otkazpro_group ) {
					foreach ( $groups[ $otkazpro_group ] as $otkazpro_item ) {
						wc_get_template( 'parts/item.php', array( 'item' => $otkazpro_item, 'group' => $otkazpro_group, 'strict' => $strict, 'fields' => $fields ), $otkazpro_path, $otkazpro_tpl );
					}
				}
				?>
			</ul>
		<?php endif; ?>
	<?php else : ?>
		<form class="otkazpro__form" method="post" action="<?php echo esc_url( $form_action ); ?>" data-otkazpro-form="submit">

			<fieldset class="otkazpro__fieldset">
				<legend class="otkazpro__h"><?php esc_html_e( 'Your details', 'otkazpro' ); ?></legend>

				<div class="otkazpro__grid">
					<div class="otkazpro__field<?php echo $otkazpro_err( 'otkazpro_name' ) ? ' has-error' : ''; ?>">
						<label for="otkazpro_name"><?php esc_html_e( 'Name', 'otkazpro' ); ?></label>
						<?php if ( $otkazpro_err( 'otkazpro_name' ) ) : ?>
							<p class="otkazpro__error" id="otkazpro_name_error"><?php echo esc_html( $otkazpro_err( 'otkazpro_name' ) ); ?></p>
						<?php endif; ?>
						<input type="text" id="otkazpro_name" name="otkazpro_name" value="<?php echo esc_attr( $name ); ?>" required autocomplete="name" maxlength="200"
							<?php echo $otkazpro_err( 'otkazpro_name' ) ? 'aria-describedby="otkazpro_name_error" aria-invalid="true"' : ''; ?> />
					</div>

					<div class="otkazpro__field<?php echo $otkazpro_err( 'otkazpro_email' ) ? ' has-error' : ''; ?>">
						<label for="otkazpro_email"><?php esc_html_e( 'Email for the confirmation', 'otkazpro' ); ?></label>
						<?php if ( $otkazpro_err( 'otkazpro_email' ) ) : ?>
							<p class="otkazpro__error" id="otkazpro_email_error"><?php echo esc_html( $otkazpro_err( 'otkazpro_email' ) ); ?></p>
						<?php endif; ?>
						<input type="email" id="otkazpro_email" name="otkazpro_email" value="<?php echo esc_attr( $email ); ?>" required autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="200"
							aria-describedby="otkazpro_email_hint<?php echo $otkazpro_err( 'otkazpro_email' ) ? ' otkazpro_email_error' : ''; ?>"<?php echo $otkazpro_err( 'otkazpro_email' ) ? ' aria-invalid="true"' : ''; ?> />
						<p class="otkazpro__hint" id="otkazpro_email_hint"><?php esc_html_e( 'We will send the confirmation of your withdrawal here.', 'otkazpro' ); ?></p>
					</div>
				</div>
			</fieldset>

			<fieldset class="otkazpro__fieldset<?php echo $otkazpro_err( 'otkazpro_items' ) ? ' has-error' : ''; ?>" id="otkazpro_items" tabindex="-1">
				<legend class="otkazpro__h"><?php esc_html_e( 'Products you are withdrawing from', 'otkazpro' ); ?></legend>
				<?php if ( $otkazpro_err( 'otkazpro_items' ) ) : ?>
					<p class="otkazpro__error"><?php echo esc_html( $otkazpro_err( 'otkazpro_items' ) ); ?></p>
				<?php endif; ?>

				<?php if ( count( $groups['selectable'] ) + ( $strict ? 0 : count( $groups['excluded'] ) ) > 1 ) : ?>
					<p class="otkazpro__selectall" hidden data-otkazpro-selectall-wrap>
						<label><input type="checkbox" data-otkazpro-selectall /> <?php esc_html_e( 'Select all', 'otkazpro' ); ?></label>
					</p>
				<?php endif; ?>

				<?php if ( $groups['selectable'] ) : ?>
					<ul class="otkazpro__items">
						<?php
						foreach ( $groups['selectable'] as $otkazpro_item ) {
							wc_get_template( 'parts/item.php', array( 'item' => $otkazpro_item, 'group' => 'selectable', 'strict' => $strict, 'fields' => $fields ), $otkazpro_path, $otkazpro_tpl );
						}
						?>
					</ul>
				<?php endif; ?>

				<?php if ( $groups['excluded'] ) : ?>
					<h3 class="otkazpro__subh"><?php esc_html_e( 'Products without right of withdrawal', 'otkazpro' ); ?></h3>
					<ul class="otkazpro__items otkazpro__items--excluded">
						<?php
						foreach ( $groups['excluded'] as $otkazpro_item ) {
							wc_get_template( 'parts/item.php', array( 'item' => $otkazpro_item, 'group' => 'excluded', 'strict' => $strict, 'fields' => $fields ), $otkazpro_path, $otkazpro_tpl );
						}
						?>
					</ul>
				<?php endif; ?>

				<?php if ( $groups['done'] ) : ?>
					<h3 class="otkazpro__subh"><?php esc_html_e( 'Withdrawal already submitted', 'otkazpro' ); ?></h3>
					<ul class="otkazpro__items otkazpro__items--done">
						<?php
						foreach ( $groups['done'] as $otkazpro_item ) {
							wc_get_template( 'parts/item.php', array( 'item' => $otkazpro_item, 'group' => 'done', 'strict' => $strict, 'fields' => $fields ), $otkazpro_path, $otkazpro_tpl );
						}
						?>
					</ul>
				<?php endif; ?>
			</fieldset>

			<?php if ( $reason_enabled ) : ?>
				<div class="otkazpro__field">
					<label for="otkazpro_reason"><?php esc_html_e( 'Reason (optional)', 'otkazpro' ); ?></label>
					<p class="otkazpro__hint" id="otkazpro_reason_hint"><?php esc_html_e( 'Not required. Your withdrawal is valid without a reason.', 'otkazpro' ); ?></p>
					<textarea id="otkazpro_reason" name="otkazpro_reason" rows="3" maxlength="2000" aria-describedby="otkazpro_reason_hint"><?php echo esc_textarea( $reason ); ?></textarea>
				</div>
			<?php endif; ?>

			<div class="otkazpro__hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $honeypot ); ?>"><?php esc_html_e( 'Leave this field empty', 'otkazpro' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $honeypot ); ?>" name="<?php echo esc_attr( $honeypot ); ?>" value="" tabindex="-1" autocomplete="off" />
			</div>

			<input type="hidden" name="otkazpro_action" value="submit" />
			<input type="hidden" name="otkazpro_order" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
			<input type="hidden" name="otkazpro_key" value="<?php echo esc_attr( $order_key ); ?>" />
			<input type="hidden" name="otkazpro_token" value="<?php echo esc_attr( $token ); ?>" />
			<?php if ( $src ) : ?>
				<input type="hidden" name="otkazpro_src" value="<?php echo esc_attr( $src ); ?>" />
			<?php endif; ?>
			<?php wp_nonce_field( 'py_otkaz_submit_' . $order->get_id(), '_otkazpro_nonce', false ); ?>

			<?php do_action( 'py_otkaz_order_form_before_submit', $order ); ?>

			<div class="otkazpro__confirm">
				<p class="otkazpro__small" id="otkazpro_confirm_note">
					<?php esc_html_e( 'By clicking the button you send a statement of withdrawal from the contract for the selected products. You will receive a confirmation with the date and time of submission by email.', 'otkazpro' ); ?>
				</p>
				<p class="otkazpro__status" data-otkazpro-status aria-live="polite"></p>
				<div class="otkazpro__actions">
					<button type="submit" class="otkazpro__btn" data-otkazpro-submit aria-describedby="otkazpro_confirm_note"><?php echo esc_html( $confirm_label ); ?></button>
				</div>
			</div>
		</form>
	<?php endif; ?>
</div>
