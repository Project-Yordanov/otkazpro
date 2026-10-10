<?php
/**
 * Error summary + info notice.
 *
 * Override: yourtheme/woocommerce/otkazpro/parts/notices.php
 *
 * @package PY\Otkaz
 *
 * @var string[] $errors Form-level errors.
 * @var array    $fields Field errors [field id => message].
 * @var string   $notice Info notice.
 */

defined( 'ABSPATH' ) || exit;

$py_has_errors = ! empty( $errors ) || ! empty( $fields );
?>
<div class="py-otkaz__live" aria-live="polite" aria-atomic="true">
	<?php if ( $py_has_errors ) : ?>
		<div class="py-otkaz__notice py-otkaz__notice--error" role="alert" tabindex="-1" data-py-focus>
			<p class="py-otkaz__notice-title"><strong><?php esc_html_e( 'Please check the following:', 'otkazpro' ); ?></strong></p>
			<ul>
				<?php foreach ( (array) $errors as $py_message ) : ?>
					<li><?php echo esc_html( $py_message ); ?></li>
				<?php endforeach; ?>
				<?php foreach ( (array) $fields as $py_field => $py_message ) : ?>
					<li><a href="#<?php echo esc_attr( $py_field ); ?>"><?php echo esc_html( $py_message ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php elseif ( ! empty( $notice ) ) : ?>
		<div class="py-otkaz__notice py-otkaz__notice--info" role="status">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>
</div>
