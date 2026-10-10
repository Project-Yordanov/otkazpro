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

$py_steps = array(
	1 => __( 'Find order', 'otkazpro' ),
	2 => __( 'Select & confirm', 'otkazpro' ),
	3 => __( 'Done', 'otkazpro' ),
);
?>
<ol class="py-otkaz__steps">
	<?php foreach ( $py_steps as $py_n => $py_label ) : ?>
		<?php
		$py_class = $py_n < $current ? 'is-done' : ( $py_n === $current ? 'is-active' : '' );
		?>
		<li class="py-otkaz__step <?php echo esc_attr( $py_class ); ?>"<?php echo $py_n === $current ? ' aria-current="step"' : ''; ?>>
			<span class="py-otkaz__step-num" aria-hidden="true"><?php echo esc_html( (string) $py_n ); ?></span>
			<span class="py-otkaz__step-label">
				<span class="screen-reader-text">
					<?php
					/* translators: 1: step number, 2: total steps */
					echo esc_html( sprintf( __( 'Step %1$d of %2$d:', 'otkazpro' ), $py_n, count( $py_steps ) ) );
					?>
				</span>
				<?php echo esc_html( $py_label ); ?>
			</span>
		</li>
	<?php endforeach; ?>
</ol>
