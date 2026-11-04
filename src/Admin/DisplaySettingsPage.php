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
				'label' => __( 'Enable frontend display', 'sidrena-cijena-eu' ),
				'type'  => 'checkbox',
			),
			'anchor_label'          => array(
				'label' => __( 'Anchor price label', 'sidrena-cijena-eu' ),
				'type'  => 'anchor_label_preset',
			),
			'thirty_day_label'      => array(
				'label' => __( 'Lowest 30-day price label', 'sidrena-cijena-eu' ),
				'type'  => 'text',
			),
			'currency_display'      => array(
				'label' => __( 'Currency display', 'sidrena-cijena-eu' ),
				'type'  => 'select',
			),
			'display_location'      => array(
				'label' => __( 'Display position', 'sidrena-cijena-eu' ),
				'type'  => 'select',
			),
			'display_priority'      => array(
				'label' => __( 'Display priority', 'sidrena-cijena-eu' ),
				'type'  => 'number',
			),
			'display_product_pages' => array(
				'label' => __( 'Product pages', 'sidrena-cijena-eu' ),
				'type'  => 'checkbox',
			),
			'display_archives'      => array(
				'label' => __( 'Archives and lists', 'sidrena-cijena-eu' ),
				'type'  => 'checkbox',
			),
			'display_variations'    => array(
				'label' => __( 'Variations', 'sidrena-cijena-eu' ),
				'type'  => 'checkbox',
			),
			'only_when_different'   => array(
				'label' => __( 'Only when the anchor differs', 'sidrena-cijena-eu' ),
				'type'  => 'checkbox',
			),
			'disclaimer'            => array(
				'label' => __( 'Administrative disclaimer', 'sidrena-cijena-eu' ),
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
		echo '<p style="margin: 0 0 6px 0; font-weight: 500;">' . esc_html__( 'Odaberite predloženi naziv ili upišite vlastiti:', 'sidrena-cijena-eu' ) . '</p>';
		echo '<div class="sidrena-label-presets" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;">';

		foreach ( $presets as $preset_key => $preset_label ) {
			$active_style = ( $value === $preset_label ) ? 'background:#2271b1; color:#fff; border-color:#2271b1;' : 'background:#f6f7f7; color:#2c3338; border:1px solid #c3c4c7;';
			echo '<button type="button" class="button sidrena-preset-btn" data-preset="' . esc_attr( $preset_label ) . '" style="' . esc_attr( $active_style ) . ' cursor:pointer; font-size:12px; padding:2px 8px; border-radius:3px;">' . esc_html( $preset_label ) . '</button>';
		}

		echo '</div>';

		echo '<div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">';
		echo '<input type="text" id="sidrena-anchor-label-input" name="' . esc_attr( $option . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" class="regular-text" style="max-width:320px;" />';
		echo '<button type="button" class="button button-secondary sidrena-token-btn" data-token="{datum}" title="' . esc_attr__( 'Umetni oznaku za datum', 'sidrena-cijena-eu' ) . '" style="cursor:pointer;">+ {datum}</button>';
		echo '</div>';

		echo '<div class="sidrena-label-preview-box" style="margin-top:8px; padding:10px 14px; background:#f0f6fc; border-left:4px solid #2271b1; border-radius:2px; font-size:13px; max-width:620px;">';
		echo '<p style="margin:0 0 6px 0; font-weight:600; color:#1d2327;">' . esc_html__( 'Prikaz u trgovini (pregled uživo):', 'sidrena-cijena-eu' ) . '</p>';
		echo '<div style="margin:2px 0;"><strong>' . esc_html__( 'Hrana / piće / kućanstvo:', 'sidrena-cijena-eu' ) . '</strong> <span id="sidrena-preview-food" style="color:#2271b1; font-weight:500;"></span>: 12,49 €</div>';
		echo '<div style="margin:2px 0;"><strong>' . esc_html__( 'Odjeća / tehnika / usluge:', 'sidrena-cijena-eu' ) . '</strong> <span id="sidrena-preview-general" style="color:#2271b1; font-weight:500;"></span>: 17,99 €</div>';
		echo '</div>';

		echo '<p class="description" style="margin-top:6px; max-width:620px;">' . esc_html__( 'Prema pojašnjenju Ministarstva gospodarstva, trgovci mogu koristiti izraze poput "Bazna cijena", "Referentna cijena" ili "Cijena na [datum]". Oznaka {datum} se automatski zamjenjuje točnim zakonskim datumom za svaki proizvod (2. 5. 2025. za hranu, 10. 9. 2026. za ostalu robu i usluge).', 'sidrena-cijena-eu' ) . '</p>';
		echo '</div>';

		echo '<script>
		(function() {
			var input = document.getElementById("sidrena-anchor-label-input");
			var previewFood = document.getElementById("sidrena-preview-food");
			var previewGeneral = document.getElementById("sidrena-preview-general");
			var presetBtns = document.querySelectorAll(".sidrena-preset-btn");
			var tokenBtn = document.querySelector(".sidrena-token-btn");

			function updatePreview() {
				if (!input) return;
				var val = input.value || "' . esc_js( __( 'Sidrena cijena', 'sidrena-cijena-eu' ) ) . '";
				if (previewFood) {
					previewFood.textContent = val.replace(/\{datum\}|\{date\}/g, "2. 5. 2025.").replace(/\{datum_iso\}/g, "2025-05-02");
				}
				if (previewGeneral) {
					previewGeneral.textContent = val.replace(/\{datum\}|\{date\}/g, "10. 9. 2026.").replace(/\{datum_iso\}/g, "2026-09-10");
				}
				presetBtns.forEach(function(btn) {
					if (btn.getAttribute("data-preset") === input.value) {
						btn.style.background = "#2271b1";
						btn.style.color = "#fff";
						btn.style.borderColor = "#2271b1";
					} else {
						btn.style.background = "#f6f7f7";
						btn.style.color = "#2c3338";
						btn.style.borderColor = "#c3c4c7";
					}
				});
			}

			if (input) {
				input.addEventListener("input", updatePreview);
				updatePreview();
			}

			presetBtns.forEach(function(btn) {
				btn.addEventListener("click", function() {
					if (input) {
						input.value = this.getAttribute("data-preset");
						updatePreview();
					}
				});
			});

			if (tokenBtn && input) {
				tokenBtn.addEventListener("click", function() {
					var token = this.getAttribute("data-token");
					if (input.value.indexOf(token) === -1) {
						input.value = input.value + " " + token;
						updatePreview();
					}
				});
			}
		})();
		</script>';
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
