<?php
/**
 * Anchor price template.
 *
 * Overridable from a theme at `sidrena-cijena/anchor-price.php`.
 *
 * @package SidrenaCijena
 *
 * @var array $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

$sidrena_classes = 'sidrena-cijena sidrena-cijena--' . sanitize_html_class( $context );
?>
<div class="<?php echo esc_attr( $sidrena_classes ); ?>"
	data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
	data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>">
	<?php if ( $show_current && $current_display ) : ?>
		<span class="sidrena-cijena__current">
			<span class="sidrena-cijena__current-value"><?php echo esc_html( $current_display ); ?></span>
		</span>
	<?php endif; ?>

	<?php if ( $show_anchor ) : ?>
		<span class="sidrena-cijena__anchor">
			<span class="sidrena-cijena__label"><?php echo esc_html( $label ); ?></span>
			<span class="sidrena-cijena__anchor-value" data-sidrena-cijena-anchor><?php echo esc_html( $anchor_display ); ?></span>
		</span>
	<?php endif; ?>

	<?php if ( $disclaimer ) : ?>
		<span class="sidrena-cijena__disclaimer"><?php echo esc_html( $disclaimer ); ?></span>
	<?php endif; ?>
</div>
