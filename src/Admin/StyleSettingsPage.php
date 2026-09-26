<?php
/**
 * Storefront anchor styling controls.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Support\DisplayStyles;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Preset gallery and size control.
 */
final class StyleSettingsPage {
	/**
	 * Render the styling tab.
	 *
	 * @return void
	 */
	public static function render_fields() {
		$selected = (string) Settings::get( 'product_display_style', 'plain' );
		$size     = (int) Settings::get( 'display_size', 40 );

		echo '<h2>' . esc_html__( 'Anchor display styles', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p>' . esc_html__( 'Choose a compact preset. Each preview shows a current price, its anchor and the reference date.', 'sidrena-cijena-eu' ) . '</p>';
		echo '<div class="sidrena-style-gallery" role="group" aria-label="' . esc_attr__( 'Anchor display styles', 'sidrena-cijena-eu' ) . '">';

		foreach ( DisplayStyles::presets() as $style => $label ) {
			echo '<label class="sidrena-style-choice">';
			echo '<span class="sidrena-style-choice__heading"><input type="radio" name="' . esc_attr( Settings::OPTION . '[product_display_style]' ) . '" value="' . esc_attr( $style ) . '" ' . checked( $selected, $style, false ) . ' /> ' . esc_html( $label ) . '</span>';
			echo '<span class="sidrena-style-choice__price">12,00 EUR</span>';
			echo '<span class="sidrena-cijena sidrena-cijena--style-' . esc_attr( $style ) . ' sidrena-style-preview" style="--sidrena-size:' . esc_attr( (string) ( $size / 100 ) ) . '">';
			echo '<span class="sidrena-cijena__anchor"><span class="sidrena-cijena__label">' . esc_html( (string) Settings::get( 'anchor_label', __( 'Sidrena cijena', 'sidrena-cijena-eu' ) ) ) . '</span> <span class="sidrena-cijena__anchor-value">9,99 EUR</span> <span class="sidrena-cijena__reference-date">10. 9. 2026.</span></span>';
			echo '</span></label>';
		}

		echo '</div>';
		echo '<p><label for="sidrena-display-size"><strong>' . esc_html__( 'Display size', 'sidrena-cijena-eu' ) . '</strong></label></p>';
		echo '<input id="sidrena-display-size" type="range" min="40" max="160" step="5" name="' . esc_attr( Settings::OPTION . '[display_size]' ) . '" value="' . esc_attr( (string) $size ) . '" /> ';
		echo '<output for="sidrena-display-size" id="sidrena-display-size-value">' . esc_html( (string) $size ) . '%</output>';
		echo '<p class="description">' . esc_html__( 'The default is 40% of the former display size. Text stays at least 12 pixels for readability; increase the size here when needed.', 'sidrena-cijena-eu' ) . '</p>';
	}
}
