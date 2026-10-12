<?php
/**
 * Step indicator.
 *
 * Override: yourtheme/woocommerce/otkazpro/parts/steps.php
 *
 * @package PY\Otkaz
 *
 * @var int $current Current step (1–3).
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template: variables are local to the including function (wc_get_template).

$otkazpro_steps = array(
	1 => __( 'Find order', 'otkazpro' ),
	2 => __( 'Select & confirm', 'otkazpro' ),
	3 => __( 'Done', 'otkazpro' ),
);
?>
<ol class="otkazpro__steps">
	<?php foreach ( $otkazpro_steps as $otkazpro_n => $otkazpro_label ) : ?>
		<?php
		$otkazpro_class = $otkazpro_n < $current ? 'is-done' : ( $otkazpro_n === $current ? 'is-active' : '' );
		?>
		<li class="otkazpro__step <?php echo esc_attr( $otkazpro_class ); ?>"<?php echo $otkazpro_n === $current ? ' aria-current="step"' : ''; ?>>
			<span class="otkazpro__step-num" aria-hidden="true"><?php echo esc_html( (string) $otkazpro_n ); ?></span>
			<span class="otkazpro__step-label">
				<span class="screen-reader-text">
					<?php
					/* translators: 1: step number, 2: total steps */
					echo esc_html( sprintf( __( 'Step %1$d of %2$d:', 'otkazpro' ), $otkazpro_n, count( $otkazpro_steps ) ) );
					?>
				</span>
				<?php echo esc_html( $otkazpro_label ); ?>
			</span>
		</li>
	<?php endforeach; ?>
</ol>
