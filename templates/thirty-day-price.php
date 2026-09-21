<?php
/**
 * 30-day lowest price block.
 *
 * Override this template by copying it to your theme as
 * `sidrena-cijena/thirty-day-price.php`.
 *
 * @package SidrenaCijena
 *
 * @var \SidrenaCijena\Catalog\CatalogItem $item    Catalog item.
 * @var float                              $price   Lowest 30-day price.
 * @var string                             $label   Display label.
 * @var string                             $display Formatted price.
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $display ) ) {
	return;
}
?>
<div class="sidrena-cijena sidrena-cijena--30-day" data-product-id="<?php echo esc_attr( (string) $item->id ); ?>" data-variation-id="<?php echo esc_attr( (string) $item->variation_id ); ?>">
	<span class="sidrena-cijena__30-day">
		<span class="sidrena-cijena__label"><?php echo esc_html( $label ); ?></span>
		<span class="sidrena-cijena__30-day-value" data-sidrena-cijena-30-day><?php echo esc_html( $display ); ?></span>
	</span>
</div>
