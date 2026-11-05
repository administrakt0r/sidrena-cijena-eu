<?php
/**
 * Frontend display settings tab.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Display settings.
 */
final class DisplaySettingsPage {

	/**
	 * Settings page slug used by do_settings_sections().
	 */
	const PAGE = 'sidrena-cijena-settings-display';

	/**
	 * Register sections and fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		add_settings_section(
			'sidrena_cijena_display',
			__( 'Anchor price display', 'sidrena-cijena-eu' ),
			array( __CLASS__, 'section_intro' ),
			self::PAGE
		);

		$fields = array(
			'display_enabled'       => array(
				'label'       => __( 'Enable frontend display', 'sidrena-cijena-eu' ),
				'type'        => 'checkbox',
				'description' => __( 'Show anchor price and 30-day lowest price on the public storefront.', 'sidrena-cijena-eu' ),
			),
			'anchor_label'          => array(
				'label'       => __( 'Anchor price label', 'sidrena-cijena-eu' ),
				'type'        => 'anchor_label_preset',
				'description' => __( 'Statutory label describing the anchor price. Choose a preset or specify a custom label with dynamic tokens ({datum}, {datum_iso}).', 'sidrena-cijena-eu' ),
			),
			'thirty_day_label'      => array(
				'label'       => __( 'Lowest 30-day price label', 'sidrena-cijena-eu' ),
				'type'        => 'text',
				'description' => __( 'Statutory label for the lowest price applied in the 30 days before a promotion.', 'sidrena-cijena-eu' ),
			),
			'currency_display'      => array(
				'label'       => __( 'Currency display', 'sidrena-cijena-eu' ),
				'type'        => 'select',
				'description' => __( 'Format used to display currency alongside the anchor price.', 'sidrena-cijena-eu' ),
			),
			'display_location'      => array(
				'label'       => __( 'Display position', 'sidrena-cijena-eu' ),
				'type'        => 'select',
				'description' => __( 'Placement of the anchor price relative to the regular product price HTML.', 'sidrena-cijena-eu' ),
			),
			'display_priority'      => array(
				'label'       => __( 'Display priority', 'sidrena-cijena-eu' ),
				'type'        => 'number',
				'description' => __( 'Action hook priority (default 15; lower values render earlier).', 'sidrena-cijena-eu' ),
			),
			'display_product_pages' => array(
				'label'       => __( 'Product pages', 'sidrena-cijena-eu' ),
				'type'        => 'checkbox',
				'description' => __( 'Render anchor price information on single product pages.', 'sidrena-cijena-eu' ),
			),
			'display_archives'      => array(
				'label'       => __( 'Archives and lists', 'sidrena-cijena-eu' ),
				'type'        => 'checkbox',
				'description' => __( 'Render anchor price information on catalog loops, archives, and search results.', 'sidrena-cijena-eu' ),
			),
			'display_variations'    => array(
				'label'       => __( 'Variations', 'sidrena-cijena-eu' ),
				'type'        => 'checkbox',
				'description' => __( 'Dynamically update the anchor price when a variation is selected.', 'sidrena-cijena-eu' ),
			),
			'only_when_different'   => array(
				'label'       => __( 'Only when the anchor differs', 'sidrena-cijena-eu' ),
				'type'        => 'checkbox',
				'description' => __( 'Hide the anchor price if it equals the current selling price.', 'sidrena-cijena-eu' ),
			),
			'disclaimer'            => array(
				'label'       => __( 'Administrative disclaimer', 'sidrena-cijena-eu' ),
				'type'        => 'textarea',
				'description' => __( 'Optional text displayed alongside anchor prices informing buyers of statutory direct price controls.', 'sidrena-cijena-eu' ),
			),
		);

		foreach ( $fields as $key => $field ) {
			add_settings_field(
				'sidrena_cijena_' . $key,
				$field['label'],
				array( __CLASS__, 'render_field' ),
				self::PAGE,
				'sidrena_cijena_display',
				array(
					'key'         => $key,
					'type'        => $field['type'],
					'description' => isset( $field['description'] ) ? $field['description'] : '',
					'label_for'   => $key,
				)
			);
		}
	}

	/**
	 * Section introduction.
	 *
	 * @return void
	 */
	public static function section_intro() {
		echo '<p>' . esc_html__( 'Choose where and how the anchor price ("Sidrena cijena") is shown next to current prices.', 'sidrena-cijena-eu' ) . '</p>';
	}

	/**
	 * Render one field.
	 *
	 * @param array<mixed> $args Field arguments.
	 * @return void
	 */
	public static function render_field( $args ) {
		$key    = $args['key'];
		$fields = new SettingsFields( Settings::OPTION );
		$value  = Settings::get( $key );

		switch ( $args['type'] ) {
			case 'anchor_label_preset':
				self::render_anchor_label_field( $key, $value );
				break;
			case 'checkbox':
				$fields->checkbox( $key, $value );
				break;
			case 'number':
				$fields->number(
					$key,
					$value,
					array(
						'min' => 1,
						'max' => 100,
					)
				);
				break;
			case 'select':
				if ( 'currency_display' === $key ) {
					$choices = array(
						'symbol' => __( 'Currency symbol (6,99 EUR)', 'sidrena-cijena-eu' ),
						'code'   => __( 'Currency code (6,99 EUR)', 'sidrena-cijena-eu' ),
						'none'   => __( 'Amount only (6,99)', 'sidrena-cijena-eu' ),
					);
				} else {
					$choices = array(
						'price_html' => __( 'Inline, next to the price', 'sidrena-cijena-eu' ),
						'summary'    => __( 'Separate block below the price', 'sidrena-cijena-eu' ),
					);
				}

				$fields->select( $key, $value, $choices );
				break;
			case 'textarea':
				$fields->textarea( $key, $value );
				break;
			default:
				$fields->text( $key, $value );
				break;
		}

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Renders the anchor price label field with presets and live preview.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Stored value.
	 * @return void
	 */
	public static function render_anchor_label_field( $key, $value ) {
		$value   = (string) ( null !== $value && '' !== $value ? $value : __( 'Sidrena cijena', 'sidrena-cijena-eu' ) );
		$presets = Settings::anchor_label_presets();
		$option  = Settings::OPTION;

		echo '<div class="sidrena-label-presets-wrap">';
		echo '<p class="sidrena-label-presets-heading">' . esc_html__( 'Odaberite predloženi naziv ili upišite vlastiti:', 'sidrena-cijena-eu' ) . '</p>';
		echo '<div class="sidrena-label-presets">';

		foreach ( $presets as $preset_key => $preset_label ) {
			$active_class = ( $value === $preset_label ) ? ' is-active' : '';
			echo '<button type="button" class="button sidrena-preset-btn' . esc_attr( $active_class ) . '" data-preset="' . esc_attr( $preset_label ) . '">' . esc_html( $preset_label ) . '</button>';
		}

		echo '</div>';

		echo '<div class="sidrena-label-input-row">';
		echo '<input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $option . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . esc_attr__( 'Sidrena cijena', 'sidrena-cijena-eu' ) . '" />';
		echo '<button type="button" class="button button-secondary sidrena-token-btn" data-token="{datum}" title="' . esc_attr__( 'Umetni oznaku za datum', 'sidrena-cijena-eu' ) . '">+ {datum}</button>';
		echo '</div>';

		echo '<div class="sidrena-label-preview-box">';
		echo '<p class="sidrena-label-preview-title">' . esc_html__( 'Prikaz u trgovini (pregled uživo):', 'sidrena-cijena-eu' ) . '</p>';
		echo '<div class="sidrena-label-preview-row"><strong>' . esc_html__( 'Hrana / piće / kućanstvo:', 'sidrena-cijena-eu' ) . '</strong> <span id="sidrena-preview-food" class="sidrena-label-preview-val"></span>: 12,49 €</div>';
		echo '<div class="sidrena-label-preview-row"><strong>' . esc_html__( 'Odjeća / tehnika / usluge:', 'sidrena-cijena-eu' ) . '</strong> <span id="sidrena-preview-general" class="sidrena-label-preview-val"></span>: 17,99 €</div>';
		echo '</div>';

		echo '<p class="description sidrena-label-presets-help">' . esc_html__( 'Prema pojašnjenju Ministarstva gospodarstva, trgovci mogu koristiti izraze poput "Bazna cijena", "Referentna cijena" ili "Cijena na [datum]". Oznaka {datum} se automatski zamjenjuje točnim zakonskim datumom za svaki proizvod (2. 5. 2025. za hranu, 10. 9. 2026. za ostalu robu i usluge).', 'sidrena-cijena-eu' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render the tab fields.
	 *
	 * @return void
	 */
	public static function render_fields() {
		do_settings_sections( self::PAGE );
	}
}
