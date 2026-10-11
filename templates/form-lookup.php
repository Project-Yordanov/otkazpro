<?php
/**
 * Step 1 – find the order (order number + email).
 *
 * Override: yourtheme/woocommerce/wpotkaz/form-lookup.php
 *
 * @package PY\Otkaz
 *
 * @var string      $form_action    Form action URL.
 * @var string[]    $errors         Errors.
 * @var array       $fields         Field errors.
 * @var array       $values         Previous values.
 * @var string      $notice         Info notice.
 * @var string      $honeypot       Honeypot field name.
 * @var string      $intro          Intro text.
 * @var string      $start_label    Start button label.
 * @var \WC_Order[] $account_orders Logged-in customer's eligible orders.
 * @var bool        $rate_limited   Rate limited.
 */

defined( 'ABSPATH' ) || exit;

$py_number = isset( $values['order_number'] ) ? $values['order_number'] : '';
$py_email  = isset( $values['email'] ) ? $values['email'] : '';
$py_err    = static function ( $id ) use ( $fields ) {
	return isset( $fields[ $id ] ) ? $fields[ $id ] : '';
};
?>
<div class="py-otkaz" data-py-otkaz>
	<?php wc_get_template( 'parts/steps.php', array( 'current' => 1 ), 'woocommerce/wpotkaz/', PY_OTKAZ_DIR . 'templates/' ); ?>

	<?php if ( $intro ) : ?>
		<div class="py-otkaz__intro"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
	<?php endif; ?>

	<?php wc_get_template( 'parts/notices.php', compact( 'errors', 'fields', 'notice' ), 'woocommerce/wpotkaz/', PY_OTKAZ_DIR . 'templates/' ); ?>

	<?php if ( ! empty( $account_orders ) ) : ?>
		<section class="py-otkaz__card" aria-labelledby="py-otkaz-account-title">
			<h2 class="py-otkaz__h" id="py-otkaz-account-title"><?php esc_html_e( 'Your orders', 'wpotkaz' ); ?></h2>
			<ul class="py-otkaz__orders">
				<?php foreach ( $account_orders as $py_order ) : ?>
					<li>
						<span>
							<?php
							/* translators: 1: order number, 2: order date */
							echo esc_html( sprintf( __( 'Order #%1$s from %2$s', 'wpotkaz' ), $py_order->get_order_number(), wc_format_datetime( $py_order->get_date_created() ) ) );
							?>
						</span>
						<a class="py-otkaz__link" href="<?php echo esc_url( \PY\Otkaz\Form::order_url( $py_order, \PY\Otkaz\I18n::current_language() ) ); ?>"><?php esc_html_e( 'Choose', 'wpotkaz' ); ?><span class="screen-reader-text"> <?php echo esc_html( '#' . $py_order->get_order_number() ); ?></span></a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="py-otkaz__hint"><?php esc_html_e( 'Or find another order below.', 'wpotkaz' ); ?></p>
		</section>
	<?php endif; ?>

	<form class="py-otkaz__form" method="post" action="<?php echo esc_url( $form_action ); ?>" data-py-form="lookup">
		<h2 class="py-otkaz__h"><?php esc_html_e( 'Find your order', 'wpotkaz' ); ?></h2>

		<div class="py-otkaz__field<?php echo $py_err( 'py_order_number' ) ? ' has-error' : ''; ?>">
			<label for="py_order_number"><?php esc_html_e( 'Order number', 'wpotkaz' ); ?></label>
			<p class="py-otkaz__hint" id="py_order_number_hint"><?php esc_html_e( 'You will find it in the order confirmation email, e.g. 12345.', 'wpotkaz' ); ?></p>
			<?php if ( $py_err( 'py_order_number' ) ) : ?>
				<p class="py-otkaz__error" id="py_order_number_error"><?php echo esc_html( $py_err( 'py_order_number' ) ); ?></p>
			<?php endif; ?>
			<input type="text" id="py_order_number" name="py_order_number" value="<?php echo esc_attr( $py_number ); ?>" required autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="64"
				aria-describedby="py_order_number_hint<?php echo $py_err( 'py_order_number' ) ? ' py_order_number_error' : ''; ?>"<?php echo $py_err( 'py_order_number' ) ? ' aria-invalid="true"' : ''; ?> />
		</div>

		<div class="py-otkaz__field<?php echo $py_err( 'py_email' ) ? ' has-error' : ''; ?>">
			<label for="py_email"><?php esc_html_e( 'Email used for the order', 'wpotkaz' ); ?></label>
			<?php if ( $py_err( 'py_email' ) ) : ?>
				<p class="py-otkaz__error" id="py_email_error"><?php echo esc_html( $py_err( 'py_email' ) ); ?></p>
			<?php endif; ?>
			<input type="email" id="py_email" name="py_email" value="<?php echo esc_attr( $py_email ); ?>" required autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="200"
				<?php echo $py_err( 'py_email' ) ? 'aria-describedby="py_email_error" aria-invalid="true"' : ''; ?> />
		</div>

		<div class="py-otkaz__hp" aria-hidden="true">
			<label for="<?php echo esc_attr( $honeypot ); ?>"><?php esc_html_e( 'Leave this field empty', 'wpotkaz' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $honeypot ); ?>" name="<?php echo esc_attr( $honeypot ); ?>" value="" tabindex="-1" autocomplete="off" />
		</div>

		<?php
		/**
		 * Extra fields for the lookup step (e.g. CAPTCHA widget).
		 */
		do_action( 'py_otkaz_lookup_form_fields' );
		?>

		<input type="hidden" name="py_otkaz_action" value="lookup" />
		<?php wp_nonce_field( 'py_otkaz_lookup', '_py_otkaz_nonce', false ); ?>

		<div class="py-otkaz__actions">
			<button type="submit" class="py-otkaz__btn"<?php echo $rate_limited ? ' disabled' : ''; ?>><?php echo esc_html( $start_label ); ?></button>
		</div>

		<p class="py-otkaz__small">
			<?php esc_html_e( 'You do not need to give a reason. If you have an account, you can also start from My account → Orders.', 'wpotkaz' ); ?>
		</p>
	</form>
</div>
