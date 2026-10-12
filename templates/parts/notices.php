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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template: variables are local to the including function (wc_get_template).

$otkazpro_has_errors = ! empty( $errors ) || ! empty( $fields );
?>
<div class="otkazpro__live" aria-live="polite" aria-atomic="true">
	<?php if ( $otkazpro_has_errors ) : ?>
		<div class="otkazpro__notice otkazpro__notice--error" role="alert" tabindex="-1" data-otkazpro-focus>
			<p class="otkazpro__notice-title"><strong><?php esc_html_e( 'Please check the following:', 'otkazpro' ); ?></strong></p>
			<ul>
				<?php foreach ( (array) $errors as $otkazpro_message ) : ?>
					<li><?php echo esc_html( $otkazpro_message ); ?></li>
				<?php endforeach; ?>
				<?php foreach ( (array) $fields as $otkazpro_field => $otkazpro_message ) : ?>
					<li><a href="#<?php echo esc_attr( $otkazpro_field ); ?>"><?php echo esc_html( $otkazpro_message ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php elseif ( ! empty( $notice ) ) : ?>
		<div class="otkazpro__notice otkazpro__notice--info" role="status">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>
</div>
