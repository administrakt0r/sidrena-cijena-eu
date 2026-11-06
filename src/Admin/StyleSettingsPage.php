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
		$selected         = (string) Settings::get( 'product_display_style', 'tooltip' );
		$date_mode        = (string) Settings::get( 'anchor_date_mode', 'tooltip' );
		$font_weight      = (string) Settings::get( 'anchor_font_weight', 'normal' );
		$size             = (int) Settings::get( 'display_size', 40 );
		$card_style       = (string) Settings::get( 'card_display_style', 'same_as_product' );
		$card_date_mode   = (string) Settings::get( 'card_date_mode', 'tooltip' );
		$card_font_weight = (string) Settings::get( 'card_font_weight', 'normal' );
		$card_size        = (int) Settings::get( 'card_display_size', 40 );
		$label_text       = (string) Settings::get( 'anchor_label', __( 'Sidrena cijena', 'titanworks-bazna-cijena' ) );
		$tooltip_txt      = (string) Settings::get( 'anchor_tooltip_text', __( 'Sidrena (bazna) cijena je redovna maloprodajna cijena na propisani referentni datum prema mjerama izravne kontrole cijena.', 'titanworks-bazna-cijena' ) );

		echo '<h2>' . esc_html__( 'Anchor display styles', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<p>' . esc_html__( 'Choose a compact preset for single product pages. Each preview shows a current price, its anchor and the reference date.', 'titanworks-bazna-cijena' ) . '</p>';
		echo '<div class="sidrena-style-gallery" role="group" aria-label="' . esc_attr__( 'Anchor display styles', 'titanworks-bazna-cijena' ) . '">';

		foreach ( DisplayStyles::presets() as $style => $label ) {
			echo '<label class="sidrena-style-choice">';
			echo '<span class="sidrena-style-choice__heading"><input type="radio" name="' . esc_attr( Settings::OPTION . '[product_display_style]' ) . '" value="' . esc_attr( $style ) . '" ' . checked( $selected, $style, false ) . ' /> ' . esc_html( $label ) . '</span>';
			echo '<span class="sidrena-style-choice__price">12,00 EUR</span>';
			echo '<span class="sidrena-cijena sidrena-cijena--style-' . esc_attr( $style ) . ' sidrena-style-preview" style="--sidrena-size:' . esc_attr( (string) ( $size / 100 ) ) . '">';
			if ( 'tooltip' === $style || 'minimal' === $style ) {
				echo '<span class="sidrena-cijena__anchor"><span class="sidrena-cijena__label">' . esc_html( $label_text ) . '</span> <span class="sidrena-cijena__tooltip-wrap"><span class="sidrena-cijena__help-trigger">?</span></span> <span class="sidrena-cijena__anchor-value">9,99 EUR</span></span>';
			} elseif ( 'subtle' === $style ) {
				echo '<span class="sidrena-cijena__anchor"><span class="sidrena-cijena__label">' . esc_html( $label_text ) . '</span> <span class="sidrena-cijena__anchor-value">9,99 EUR</span></span>';
			} else {
				echo '<span class="sidrena-cijena__anchor"><span class="sidrena-cijena__label">' . esc_html( $label_text ) . '</span> <span class="sidrena-cijena__anchor-value">9,99 EUR</span> <span class="sidrena-cijena__reference-date">10.09.2026.</span></span>';
			}
			echo '</span></label>';
		}

		echo '</div>';

		echo '<hr class="sidrena-settings-divider" />';
		echo '<h2>' . esc_html__( 'Product cards and archives display style', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<p>' . esc_html__( 'Choose the style preset used for product cards in shop loops, category archives, search results, related products, and minicards.', 'titanworks-bazna-cijena' ) . '</p>';
		echo '<div class="sidrena-style-gallery sidrena-style-gallery--cards" role="group" aria-label="' . esc_attr__( 'Product cards display styles', 'titanworks-bazna-cijena' ) . '">';

		foreach ( DisplayStyles::card_presets() as $style => $label ) {
			$preview_style = 'same_as_product' === $style ? $selected : $style;
			echo '<label class="sidrena-style-choice">';
			echo '<span class="sidrena-style-choice__heading"><input type="radio" name="' . esc_attr( Settings::OPTION . '[card_display_style]' ) . '" value="' . esc_attr( $style ) . '" ' . checked( $card_style, $style, false ) . ' /> ' . esc_html( $label ) . '</span>';
			echo '<span class="sidrena-style-choice__price">12,00 EUR</span>';
			echo '<span class="sidrena-cijena sidrena-cijena--archive sidrena-cijena--style-' . esc_attr( $preview_style ) . ' sidrena-card-style-preview" style="--sidrena-size:' . esc_attr( (string) ( $card_size / 100 ) ) . '">';
			if ( 'tooltip' === $preview_style || 'minimal' === $preview_style ) {
				echo '<span class="sidrena-cijena__anchor"><span class="sidrena-cijena__label">' . esc_html( $label_text ) . '</span> <span class="sidrena-cijena__tooltip-wrap"><span class="sidrena-cijena__help-trigger">?</span></span> <span class="sidrena-cijena__anchor-value">9,99 EUR</span></span>';
			} elseif ( 'subtle' === $preview_style ) {
				echo '<span class="sidrena-cijena__anchor"><span class="sidrena-cijena__label">' . esc_html( $label_text ) . '</span> <span class="sidrena-cijena__anchor-value">9,99 EUR</span></span>';
			} else {
				echo '<span class="sidrena-cijena__anchor"><span class="sidrena-cijena__label">' . esc_html( $label_text ) . '</span> <span class="sidrena-cijena__anchor-value">9,99 EUR</span> <span class="sidrena-cijena__reference-date">10.09.2026.</span></span>';
			}
			echo '</span></label>';
		}

		echo '</div>';

		echo '<hr class="sidrena-settings-divider" />';
		echo '<h2>' . esc_html__( 'Display customization options', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';

		echo '<tr>';
		echo '<th scope="row"><label for="sidrena-date-mode">' . esc_html__( 'Reference date display', 'titanworks-bazna-cijena' ) . '</label></th>';
		echo '<td>';
		echo '<select id="sidrena-date-mode" name="' . esc_attr( Settings::OPTION . '[anchor_date_mode]' ) . '">';
		$date_modes = array(
			'tooltip' => __( 'Interactive tooltip / icon (?) next to price (Recommended – keeps product box clean)', 'titanworks-bazna-cijena' ),
			'inline'  => __( 'Inline text next to price (e.g. Cijena na dan 10.09.2026.)', 'titanworks-bazna-cijena' ),
			'subtext' => __( 'Subtext below anchor price', 'titanworks-bazna-cijena' ),
			'hidden'  => __( 'Hidden from product box (accessible in title / description)', 'titanworks-bazna-cijena' ),
		);
		foreach ( $date_modes as $mode_key => $mode_label ) {
			echo '<option value="' . esc_attr( $mode_key ) . '" ' . selected( $date_mode, $mode_key, false ) . '>' . esc_html( $mode_label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'When set to tooltip, users can click the (?) icon to see a popup with full explanation and reference date.', 'titanworks-bazna-cijena' ) . '</p>';
		echo '</td></tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="sidrena-card-date-mode">' . esc_html__( 'Card reference date display', 'titanworks-bazna-cijena' ) . '</label></th>';
		echo '<td>';
		echo '<select id="sidrena-card-date-mode" name="' . esc_attr( Settings::OPTION . '[card_date_mode]' ) . '">';
		foreach ( $date_modes as $mode_key => $mode_label ) {
			echo '<option value="' . esc_attr( $mode_key ) . '" ' . selected( $card_date_mode, $mode_key, false ) . '>' . esc_html( $mode_label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Reference date mode specifically for product cards and archives.', 'titanworks-bazna-cijena' ) . '</p>';
		echo '</td></tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="sidrena-tooltip-text">' . esc_html__( 'Tooltip explanation text', 'titanworks-bazna-cijena' ) . '</label></th>';
		echo '<td>';
		echo '<textarea id="sidrena-tooltip-text" name="' . esc_attr( Settings::OPTION . '[anchor_tooltip_text]' ) . '" rows="2" class="large-text">' . esc_textarea( $tooltip_txt ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Clear explanation displayed in the popup when customers click or hover over the (?) icon.', 'titanworks-bazna-cijena' ) . '</p>';
		echo '</td></tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="sidrena-font-weight">' . esc_html__( 'Anchor price font weight', 'titanworks-bazna-cijena' ) . '</label></th>';
		echo '<td>';
		echo '<select id="sidrena-font-weight" name="' . esc_attr( Settings::OPTION . '[anchor_font_weight]' ) . '">';
		$weights = array(
			'normal'    => __( 'Normal (400)', 'titanworks-bazna-cijena' ),
			'semi-bold' => __( 'Semi-bold (600)', 'titanworks-bazna-cijena' ),
			'bold'      => __( 'Bold (700)', 'titanworks-bazna-cijena' ),
		);
		foreach ( $weights as $weight_key => $weight_label ) {
			echo '<option value="' . esc_attr( $weight_key ) . '" ' . selected( $font_weight, $weight_key, false ) . '>' . esc_html( $weight_label ) . '</option>';
		}
		echo '</select>';
		echo '</td></tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="sidrena-card-font-weight">' . esc_html__( 'Card anchor font weight', 'titanworks-bazna-cijena' ) . '</label></th>';
		echo '<td>';
		echo '<select id="sidrena-card-font-weight" name="' . esc_attr( Settings::OPTION . '[card_font_weight]' ) . '">';
		foreach ( $weights as $weight_key => $weight_label ) {
			echo '<option value="' . esc_attr( $weight_key ) . '" ' . selected( $card_font_weight, $weight_key, false ) . '>' . esc_html( $weight_label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Font weight for anchor prices rendered on product cards and archives.', 'titanworks-bazna-cijena' ) . '</p>';
		echo '</td></tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="sidrena-display-size">' . esc_html__( 'Display size', 'titanworks-bazna-cijena' ) . '</label></th>';
		echo '<td>';
		echo '<input id="sidrena-display-size" type="range" min="40" max="160" step="5" name="' . esc_attr( Settings::OPTION . '[display_size]' ) . '" value="' . esc_attr( (string) $size ) . '" /> ';
		echo '<output for="sidrena-display-size" id="sidrena-display-size-value">' . esc_html( (string) $size ) . '%</output>';
		echo '<p class="description">' . esc_html__( 'The default is 40% of the former display size. Text stays at least 12 pixels for readability; increase the size here when needed.', 'titanworks-bazna-cijena' ) . '</p>';
		echo '</td></tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="sidrena-card-display-size">' . esc_html__( 'Card display size', 'titanworks-bazna-cijena' ) . '</label></th>';
		echo '<td>';
		echo '<input id="sidrena-card-display-size" type="range" min="40" max="160" step="5" name="' . esc_attr( Settings::OPTION . '[card_display_size]' ) . '" value="' . esc_attr( (string) $card_size ) . '" /> ';
		echo '<output for="sidrena-card-display-size" id="sidrena-card-display-size-value">' . esc_html( (string) $card_size ) . '%</output>';
		echo '<p class="description">' . esc_html__( 'Display size scale specifically for product cards and archives.', 'titanworks-bazna-cijena' ) . '</p>';
		echo '</td></tr>';

		echo '</table>';
	}
}
