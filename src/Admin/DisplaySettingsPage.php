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
	 * Render the tab fields.
	 *
	 * @return void
	 */
	public static function render_fields() {
		do_settings_sections( self::PAGE );
	}
}
