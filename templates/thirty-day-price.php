<?php
/**
 * 30-day lowest price block.
 *
 * Override this template by copying it to your theme as
 * `sidrena-cijena/thirty-day-price.php`.
 *
 * @package SidrenaCijena
 *
 * @var \Titanworks\SidrenaCijena\Catalog\CatalogItem $item    Catalog item.
 * @var float                              $price   Lowest 30-day price.
 * @var string                             $label   Display label.
 * @var string                             $display Formatted price.
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $display ) ) {
	return;
}

$twbce_style = isset( $style ) && array_key_exists( $style, \Titanworks\SidrenaCijena\Support\DisplayStyles::presets() ) ? $style : 'plain';
$twbce_size  = min( 160, max( 40, (int) \Titanworks\SidrenaCijena\Support\Settings::get( 'display_size', 40 ) ) );
?>
<div class="sidrena-cijena sidrena-cijena--30-day sidrena-cijena--style-<?php echo esc_attr( $twbce_style ); ?>" style="--sidrena-size:<?php echo esc_attr( (string) ( $twbce_size / 100 ) ); ?>" data-product-id="<?php echo esc_attr( (string) $item->id ); ?>" data-variation-id="<?php echo esc_attr( (string) $item->variation_id ); ?>">
	<span class="sidrena-cijena__30-day">
		<span class="sidrena-cijena__label"><?php echo esc_html( $label ); ?></span>
		<span class="sidrena-cijena__30-day-value" data-sidrena-cijena-30-day><?php echo esc_html( $display ); ?></span>
	</span>
</div>
