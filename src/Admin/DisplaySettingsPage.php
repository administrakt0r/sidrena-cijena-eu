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
			__( 'Anchor price display', 'titanworks-sidrena-bazna-cijena' ),
			array( __CLASS__, 'section_intro' ),
			self::PAGE
		);

		$fields = array(
			'display_enabled'       => array(
				'label' => __( 'Enable frontend display', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'checkbox',
			),
			'anchor_label'          => array(
				'label' => __( 'Anchor price label', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'anchor_label_preset',
			),
			'thirty_day_label'      => array(
				'label' => __( 'Lowest 30-day price label', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'text',
			),
			'currency_display'      => array(
				'label' => __( 'Currency display', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'select',
			),
			'display_location'      => array(
				'label' => __( 'Display position', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'select',
			),
			'display_priority'      => array(
				'label' => __( 'Display priority', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'number',
			),
			'display_product_pages' => array(
				'label' => __( 'Product pages', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'checkbox',
			),
			'display_archives'      => array(
				'label' => __( 'Archives and lists', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'checkbox',
			),
			'display_variations'    => array(
				'label' => __( 'Variations', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'checkbox',
			),
			'only_when_different'   => array(
				'label' => __( 'Only when the anchor differs', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'checkbox',
			),
			'disclaimer'            => array(
				'label' => __( 'Administrative disclaimer', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'textarea',
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
					'description' => '',
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
		echo '<p>' . esc_html__( 'Choose where and how the anchor price ("Sidrena cijena") is shown next to current prices.', 'titanworks-sidrena-bazna-cijena' ) . '</p>';
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
						'symbol' => __( 'Currency symbol (6,99 EUR)', 'titanworks-sidrena-bazna-cijena' ),
						'code'   => __( 'Currency code (6,99 EUR)', 'titanworks-sidrena-bazna-cijena' ),
						'none'   => __( 'Amount only (6,99)', 'titanworks-sidrena-bazna-cijena' ),
					);
				} else {
					$choices = array(
						'price_html' => __( 'Inline, next to the price', 'titanworks-sidrena-bazna-cijena' ),
						'summary'    => __( 'Separate block below the price', 'titanworks-sidrena-bazna-cijena' ),
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
		$value   = (string) ( null !== $value && '' !== $value ? $value : __( 'Sidrena cijena', 'titanworks-sidrena-bazna-cijena' ) );
		$presets = Settings::anchor_label_presets();
		$option  = Settings::OPTION;

		echo '<div class="sidrena-label-presets-wrap">';
		echo '<p class="sidrena-label-presets-heading">' . esc_html__( 'Odaberite predloženi naziv ili upišite vlastiti:', 'titanworks-sidrena-bazna-cijena' ) . '</p>';
		echo '<div class="sidrena-label-presets">';

		foreach ( $presets as $preset_key => $preset_label ) {
			$active_class = ( $value === $preset_label ) ? ' is-active' : '';
			echo '<button type="button" class="button sidrena-preset-btn' . esc_attr( $active_class ) . '" data-preset="' . esc_attr( $preset_label ) . '">' . esc_html( $preset_label ) . '</button>';
		}

		echo '</div>';

		echo '<div class="sidrena-label-input-row">';
		echo '<input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $option . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . esc_attr__( 'Sidrena cijena', 'titanworks-sidrena-bazna-cijena' ) . '" />';
		echo '<button type="button" class="button button-secondary sidrena-token-btn" data-token="{datum}" title="' . esc_attr__( 'Umetni oznaku za datum', 'titanworks-sidrena-bazna-cijena' ) . '">+ {datum}</button>';
		echo '</div>';

		echo '<div class="sidrena-label-preview-box">';
		echo '<p class="sidrena-label-preview-title">' . esc_html__( 'Prikaz u trgovini (pregled uživo):', 'titanworks-sidrena-bazna-cijena' ) . '</p>';
		echo '<div class="sidrena-label-preview-row"><strong>' . esc_html__( 'Hrana / piće / kućanstvo:', 'titanworks-sidrena-bazna-cijena' ) . '</strong> <span id="sidrena-preview-food" class="sidrena-label-preview-val"></span>: 12,49 €</div>';
		echo '<div class="sidrena-label-preview-row"><strong>' . esc_html__( 'Odjeća / tehnika / usluge:', 'titanworks-sidrena-bazna-cijena' ) . '</strong> <span id="sidrena-preview-general" class="sidrena-label-preview-val"></span>: 17,99 €</div>';
		echo '</div>';

		echo '<p class="description sidrena-label-presets-help">' . esc_html__( 'Prema pojašnjenju Ministarstva gospodarstva, trgovci mogu koristiti izraze poput "Bazna cijena", "Referentna cijena" ili "Cijena na [datum]". Oznaka {datum} se automatski zamjenjuje točnim zakonskim datumom za svaki proizvod (2. 5. 2025. za hranu, 10. 9. 2026. za ostalu robu i usluge).', 'titanworks-sidrena-bazna-cijena' ) . '</p>';
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
