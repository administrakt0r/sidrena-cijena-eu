<?php
/**
 * Special sale type settings.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the configurable special sale types (FR-023).
 */
class SaleTypesSettings {

	/**
	 * Settings page ID.
	 *
	 * @var string
	 */
	const PAGE = 'sidrena-cijena-settings-sales';

	/**
	 * Section ID.
	 *
	 * @var string
	 */
	const SECTION = 'sidrena_cijena_sales';

	/**
	 * Register the settings section and fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		add_settings_section(
			self::SECTION,
			__( 'Special forms of sale', 'sidrena-cijena-eu' ),
			array( __CLASS__, 'section_intro' ),
			self::PAGE
		);

		add_settings_field(
			'sidrena_cijena_sale_types',
			__( 'Sale types', 'sidrena-cijena-eu' ),
			array( __CLASS__, 'render_field' ),
			self::PAGE,
			self::SECTION,
			array( 'label_for' => 'sidrena_cijena_sale_types' )
		);
	}

	/**
	 * Section introduction.
	 *
	 * @return void
	 */
	public static function section_intro() {
		echo '<p>' . esc_html__( 'A promotion without a classified sale type is never treated as a special form of sale and is reported by the compliance check.', 'sidrena-cijena-eu' ) . '</p>';
	}

	/**
	 * Render the sale types field.
	 *
	 * @return void
	 */
	public static function render_field() {
		$fields = new SettingsFields( Settings::OPTION );
		$types  = (array) Settings::get( 'sale_types', array() );

		$fields->textarea(
			'sale_types',
			implode( "\n", array_map( 'strval', $types ) ),
			array(
				'rows'        => 6,
				'description' => __( 'One type per line. Each type is used to classify a special sale and is published in the price list. Defaults: akcija, snizenje, sezonsko_snizenje, rasprodaja.', 'sidrena-cijena-eu' ),
			)
		);
	}

	/**
	 * Render the settings fields.
	 *
	 * @return void
	 */
	public static function render_fields() {
		do_settings_sections( self::PAGE );
	}
}
