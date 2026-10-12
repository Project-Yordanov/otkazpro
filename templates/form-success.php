<?php
/**
 * Step 3 – success screen.
 *
 * Override: yourtheme/woocommerce/wpotkaz/form-success.php
 *
 * @package PY\Otkaz
 *
 * @var \PY\Otkaz\Submission $submission          Submission.
 * @var \WC_Order|null       $order               Order.
 * @var string               $return_instructions Return instructions (HTML).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wpotkaz" data-wpotkaz-otkaz>
	<?php wc_get_template( 'parts/steps.php', array( 'current' => 3 ), 'woocommerce/wpotkaz/', PY_OTKAZ_DIR . 'templates/' ); ?>

	<div class="wpotkaz__success" role="status" tabindex="-1" data-wpotkaz-focus>
		<svg class="wpotkaz__success-icon" width="40" height="40" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="11" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
		<h2 class="wpotkaz__h"><?php esc_html_e( 'Your withdrawal has been submitted', 'wpotkaz' ); ?></h2>

		<?php if ( 'sent' === $submission->email_status ) : ?>
			<p>
				<?php
				/* translators: %s: email address */
				echo wp_kses( sprintf( __( 'We have sent a confirmation to <strong>%s</strong>. Please keep it.', 'wpotkaz' ), esc_html( $submission->email ) ), array( 'strong' => array() ) );
				?>
			</p>
		<?php else : ?>
			<p class="wpotkaz__warn">
				<?php esc_html_e( 'Your withdrawal is registered, but we could not send the confirmation email right now. The store has been notified and will resend it. Please note the statement number below or print this page.', 'wpotkaz' ); ?>
			</p>
		<?php endif; ?>
	</div>

	<dl class="wpotkaz__dl">
		<div><dt><?php esc_html_e( 'Statement number', 'wpotkaz' ); ?></dt><dd><strong><?php echo esc_html( $submission->number ); ?></strong></dd></div>
		<div><dt><?php esc_html_e( 'Submitted on', 'wpotkaz' ); ?></dt><dd>
			<?php
			/* translators: 1: date, 2: time */
			echo esc_html( sprintf( __( '%1$s at %2$s', 'wpotkaz' ), $submission->date(), $submission->time() ) );
			?>
		</dd></div>
		<div><dt><?php esc_html_e( 'Order', 'wpotkaz' ); ?></dt><dd><?php echo esc_html( '#' . $submission->order_number ); ?></dd></div>
		<div><dt><?php esc_html_e( 'Name', 'wpotkaz' ); ?></dt><dd><?php echo esc_html( $submission->name ); ?></dd></div>
	</dl>

	<h3 class="wpotkaz__subh"><?php esc_html_e( 'Products', 'wpotkaz' ); ?></h3>
	<ul class="wpotkaz__list">
		<?php foreach ( $submission->items as $wpotkaz_item ) : ?>
			<li>
				<?php
				/* translators: 1: product name, 2: quantity */
				echo esc_html( sprintf( __( '%1$s × %2$d', 'wpotkaz' ), $wpotkaz_item['name'], (int) $wpotkaz_item['qty'] ) );
				?>
			</li>
		<?php endforeach; ?>
	</ul>

	<h3 class="wpotkaz__subh"><?php esc_html_e( 'Text of the statement', 'wpotkaz' ); ?></h3>
	<blockquote class="wpotkaz__statement"><?php echo esc_html( $submission->statement ); ?></blockquote>

	<?php if ( $return_instructions ) : ?>
		<section class="wpotkaz__card">
			<h3 class="wpotkaz__subh"><?php esc_html_e( 'How to return the goods', 'wpotkaz' ); ?></h3>
			<?php echo wp_kses_post( wpautop( $return_instructions ) ); ?>
		</section>
	<?php endif; ?>

	<p class="wpotkaz__actions wpotkaz__actions--split">
		<button type="button" class="wpotkaz__btn wpotkaz__btn--ghost" data-wpotkaz-print hidden><?php esc_html_e( 'Print', 'wpotkaz' ); ?></button>
		<a class="wpotkaz__link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the shop', 'wpotkaz' ); ?></a>
	</p>
</div>
