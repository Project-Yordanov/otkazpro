<?php
/**
 * Step 1 – find the order (order number + email).
 *
 * Override: yourtheme/woocommerce/otkazpro/form-lookup.php
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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template: variables are local to the including function (wc_get_template).

$otkazpro_number = isset( $values['order_number'] ) ? $values['order_number'] : '';
$otkazpro_email  = isset( $values['email'] ) ? $values['email'] : '';
$otkazpro_err    = static function ( $id ) use ( $fields ) {
	return isset( $fields[ $id ] ) ? $fields[ $id ] : '';
};
?>
<div class="otkazpro" data-otkazpro>
	<?php wc_get_template( 'parts/steps.php', array( 'current' => 1 ), 'woocommerce/otkazpro/', PY_OTKAZ_DIR . 'templates/' ); ?>

	<?php if ( $intro ) : ?>
		<div class="otkazpro__intro"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
	<?php endif; ?>

	<?php wc_get_template( 'parts/notices.php', compact( 'errors', 'fields', 'notice' ), 'woocommerce/otkazpro/', PY_OTKAZ_DIR . 'templates/' ); ?>

	<?php if ( ! empty( $account_orders ) ) : ?>
		<section class="otkazpro__card" aria-labelledby="otkazpro-account-title">
			<h2 class="otkazpro__h" id="otkazpro-account-title"><?php esc_html_e( 'Your orders', 'otkazpro' ); ?></h2>
			<ul class="otkazpro__orders">
				<?php foreach ( $account_orders as $otkazpro_order ) : ?>
					<li>
						<span>
							<?php
							/* translators: 1: order number, 2: order date */
							echo esc_html( sprintf( __( 'Order #%1$s from %2$s', 'otkazpro' ), $otkazpro_order->get_order_number(), wc_format_datetime( $otkazpro_order->get_date_created() ) ) );
							?>
						</span>
						<a class="otkazpro__link" href="<?php echo esc_url( \PY\Otkaz\Form::order_url( $otkazpro_order, \PY\Otkaz\I18n::current_language() ) ); ?>"><?php esc_html_e( 'Choose', 'otkazpro' ); ?><span class="screen-reader-text"> <?php echo esc_html( '#' . $otkazpro_order->get_order_number() ); ?></span></a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="otkazpro__hint"><?php esc_html_e( 'Or find another order below.', 'otkazpro' ); ?></p>
		</section>
	<?php endif; ?>

	<form class="otkazpro__form" method="post" action="<?php echo esc_url( $form_action ); ?>" data-otkazpro-form="lookup">
		<h2 class="otkazpro__h"><?php esc_html_e( 'Find your order', 'otkazpro' ); ?></h2>

		<div class="otkazpro__field<?php echo $otkazpro_err( 'otkazpro_order_number' ) ? ' has-error' : ''; ?>">
			<label for="otkazpro_order_number"><?php esc_html_e( 'Order number', 'otkazpro' ); ?></label>
			<p class="otkazpro__hint" id="otkazpro_order_number_hint"><?php esc_html_e( 'You will find it in the order confirmation email, e.g. 12345.', 'otkazpro' ); ?></p>
			<?php if ( $otkazpro_err( 'otkazpro_order_number' ) ) : ?>
				<p class="otkazpro__error" id="otkazpro_order_number_error"><?php echo esc_html( $otkazpro_err( 'otkazpro_order_number' ) ); ?></p>
			<?php endif; ?>
			<input type="text" id="otkazpro_order_number" name="otkazpro_order_number" value="<?php echo esc_attr( $otkazpro_number ); ?>" required autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="64"
				aria-describedby="otkazpro_order_number_hint<?php echo $otkazpro_err( 'otkazpro_order_number' ) ? ' otkazpro_order_number_error' : ''; ?>"<?php echo $otkazpro_err( 'otkazpro_order_number' ) ? ' aria-invalid="true"' : ''; ?> />
		</div>

		<div class="otkazpro__field<?php echo $otkazpro_err( 'otkazpro_email' ) ? ' has-error' : ''; ?>">
			<label for="otkazpro_email"><?php esc_html_e( 'Email used for the order', 'otkazpro' ); ?></label>
			<?php if ( $otkazpro_err( 'otkazpro_email' ) ) : ?>
				<p class="otkazpro__error" id="otkazpro_email_error"><?php echo esc_html( $otkazpro_err( 'otkazpro_email' ) ); ?></p>
			<?php endif; ?>
			<input type="email" id="otkazpro_email" name="otkazpro_email" value="<?php echo esc_attr( $otkazpro_email ); ?>" required autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="200"
				<?php echo $otkazpro_err( 'otkazpro_email' ) ? 'aria-describedby="otkazpro_email_error" aria-invalid="true"' : ''; ?> />
		</div>

		<div class="otkazpro__hp" aria-hidden="true">
			<label for="<?php echo esc_attr( $honeypot ); ?>"><?php esc_html_e( 'Leave this field empty', 'otkazpro' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $honeypot ); ?>" name="<?php echo esc_attr( $honeypot ); ?>" value="" tabindex="-1" autocomplete="off" />
		</div>

		<?php
		/**
		 * Extra fields for the lookup step (e.g. CAPTCHA widget).
		 */
		do_action( 'py_otkaz_lookup_form_fields' );
		?>

		<input type="hidden" name="otkazpro_action" value="lookup" />
		<?php wp_nonce_field( 'py_otkaz_lookup', '_otkazpro_nonce', false ); ?>

		<div class="otkazpro__actions">
			<button type="submit" class="otkazpro__btn"<?php echo $rate_limited ? ' disabled' : ''; ?>><?php echo esc_html( $start_label ); ?></button>
		</div>

		<p class="otkazpro__small">
			<?php esc_html_e( 'You do not need to give a reason. If you have an account, you can also start from My account → Orders.', 'otkazpro' ); ?>
		</p>
	</form>
</div>
