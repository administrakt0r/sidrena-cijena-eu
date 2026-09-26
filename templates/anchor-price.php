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

/* translators: %s: date of the reference price. */
$sidrena_date_template = __( 'Cijena na dan %s', 'sidrena-cijena' );
$sidrena_style         = isset( $style ) && in_array( $style, array( 'plain', 'badge', 'card' ), true ) ? $style : 'plain';
$sidrena_classes       = 'sidrena-cijena sidrena-cijena--' . sanitize_html_class( $context ) . ' sidrena-cijena--style-' . $sidrena_style;
?>
<div class="<?php echo esc_attr( $sidrena_classes ); ?>"
	title="<?php echo esc_attr( $anchor_title ); ?>"
	data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
	data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
	data-sidrena-cijena-date-template="<?php echo esc_attr( $sidrena_date_template ); ?>">
	<?php if ( $show_current && $current_display ) : ?>
		<span class="sidrena-cijena__current">
			<span class="sidrena-cijena__current-value"><?php echo esc_html( $current_display ); ?></span>
		</span>
	<?php endif; ?>

	<?php if ( $show_anchor ) : ?>
		<span class="sidrena-cijena__anchor">
			<span class="sidrena-cijena__label"><?php echo esc_html( $label ); ?></span>
			<span class="sidrena-cijena__anchor-value" data-sidrena-cijena-anchor><?php echo esc_html( $anchor_display ); ?></span>
			<?php if ( $reference_date_display ) : ?>
				<span class="sidrena-cijena__reference-date" data-sidrena-cijena-reference-date><?php echo esc_html( sprintf( $sidrena_date_template, $reference_date_display ) ); ?></span>
			<?php endif; ?>
		</span>
	<?php endif; ?>

	<?php if ( $disclaimer ) : ?>
		<span class="sidrena-cijena__disclaimer"><?php echo esc_html( $disclaimer ); ?></span>
	<?php endif; ?>
</div>
