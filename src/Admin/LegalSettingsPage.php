<?php
/**
 * Legal rule settings tab.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Reference dates, legacy mapping and rule version.
 */
final class LegalSettingsPage {

	/**
	 * Settings page slug used by do_settings_sections().
	 */
	const PAGE = 'sidrena-cijena-settings-legal';

	/**
	 * Register sections and fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		add_settings_section(
			'sidrena_cijena_legal',
			__( 'Reference dates and legal rules', 'sidrena-cijena' ),
			array( __CLASS__, 'section_intro' ),
			self::PAGE
		);

		$fields = array(
			'general_reference_date' => array(
				'label' => __( 'General reference date', 'sidrena-cijena' ),
				'type'  => 'date',
			),
			'legacy_reference_date'  => array(
				'label' => __( 'Legacy reference date', 'sidrena-cijena' ),
				'type'  => 'date',
			),
			'legacy_enabled'         => array(
				'label' => __( 'Enable legacy treatment', 'sidrena-cijena' ),
				'type'  => 'checkbox',
			),
			'legacy_category_slugs'  => array(
				'label' => __( 'Legacy categories', 'sidrena-cijena' ),
				'type'  => 'categories',
			),
			'rule_version'           => array(
				'label' => __( 'Legal-rule version', 'sidrena-cijena' ),
				'type'  => 'text',
			),
		);

		foreach ( $fields as $key => $field ) {
			add_settings_field(
				'sidrena_cijena_' . $key,
				$field['label'],
				array( __CLASS__, 'render_field' ),
				self::PAGE,
				'sidrena_cijena_legal',
				array(
					'key'  => $key,
					'type' => $field['type'],
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
		echo '<p>' . esc_html__( 'Changing these values never rewrites existing historical records; every change is recorded in the audit trail.', 'sidrena-cijena' ) . '</p>';
	}

	/**
	 * Render one field.
	 *
	 * @param array<mixed> $args Field arguments.
	 * @return void
	 */
	public static function render_field( $args ) {
		$key    = $args['key'];
		$fields = new SettingsFields( RuleConfig::OPTION );
		$value  = RuleConfig::get( $key );

		switch ( $args['type'] ) {
			case 'checkbox':
				$fields->checkbox( $key, $value );
				break;
			case 'date':
				$fields->text(
					$key,
					$value,
					array(
						'type'  => 'date',
						'class' => '',
					)
				);
				break;
			case 'categories':
				$choices = array();

				foreach ( Plugin::instance()->catalog()->get_categories() as $category ) {
					$choices[ (string) $category['slug'] ] = $category['name'] . ' (' . $category['slug'] . ')';
				}

				if ( empty( $choices ) ) {
					echo '<em>' . esc_html__( 'No catalog categories are available.', 'sidrena-cijena' ) . '</em>';

					return;
				}

				$fields->checkboxes( $key, (array) $value, $choices );
				break;
			default:
				$fields->text( $key, $value );
				break;
		}
	}

	/**
	 * Render the tab fields.
	 *
	 * @return void
	 */
	public static function render_fields() {
		do_settings_sections( self::PAGE );

		$history = RuleConfig::history();

		if ( empty( $history ) ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Recent rule changes', 'sidrena-cijena' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'When', 'sidrena-cijena' ) . '</th>';
		echo '<th>' . esc_html__( 'Setting', 'sidrena-cijena' ) . '</th>';
		echo '<th>' . esc_html__( 'Old value', 'sidrena-cijena' ) . '</th>';
		echo '<th>' . esc_html__( 'New value', 'sidrena-cijena' ) . '</th>';
		echo '<th>' . esc_html__( 'User', 'sidrena-cijena' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( array_slice( array_reverse( $history ), 0, 20 ) as $entry ) {
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td></tr>',
				esc_html( isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '' ),
				esc_html( isset( $entry['key'] ) ? (string) $entry['key'] : '' ),
				esc_html( self::format_value( isset( $entry['old'] ) ? $entry['old'] : null ) ),
				esc_html( self::format_value( isset( $entry['new'] ) ? $entry['new'] : null ) ),
				esc_html( isset( $entry['user_id'] ) ? (string) $entry['user_id'] : '' )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * Human-readable audit value.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function format_value( $value ) {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}

		return (string) $value;
	}
}
