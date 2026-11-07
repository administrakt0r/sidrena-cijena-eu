<?php
/**
 * Legal rule settings tab.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Labels;

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
			__( 'Reference dates and legal rules', 'titanworks-bazna-cijena' ),
			array( __CLASS__, 'section_intro' ),
			self::PAGE
		);

		$fields = array(
			'general_reference_date' => array(
				'label'       => __( 'General reference date', 'titanworks-bazna-cijena' ),
				'type'        => 'date',
				'description' => __( 'Statutory anchor reference date (default: 10.09.2026. / 2026-09-10).', 'titanworks-bazna-cijena' ),
			),
			'legacy_reference_date'  => array(
				'label'       => __( 'Legacy reference date', 'titanworks-bazna-cijena' ),
				'type'        => 'date',
				'description' => __( 'Statutory anchor reference date for legacy categories (default: 02.05.2025. / 2025-05-02).', 'titanworks-bazna-cijena' ),
			),
			'legacy_enabled'         => array(
				'label'       => __( 'Enable legacy treatment', 'titanworks-bazna-cijena' ),
				'type'        => 'checkbox',
				'description' => __( 'Apply the legacy reference date to items in designated legacy categories.', 'titanworks-bazna-cijena' ),
			),
			'legacy_category_slugs'  => array(
				'label'       => __( 'Legacy categories', 'titanworks-bazna-cijena' ),
				'type'        => 'categories',
				'description' => __( 'Product categories governed by the legacy reference date.', 'titanworks-bazna-cijena' ),
			),
			'rule_version'           => array(
				'label'       => __( 'Legal-rule version', 'titanworks-bazna-cijena' ),
				'type'        => 'text',
				'description' => __( 'Identifier for the legal rule-set configuration (e.g. 10.09.2026.).', 'titanworks-bazna-cijena' ),
			),
		);

		foreach ( $fields as $key => $field ) {
			$field_args = array(
				'key'         => $key,
				'type'        => $field['type'],
				'label'       => $field['label'],
				'description' => $field['description'],
			);

			if ( 'categories' !== $field['type'] ) {
				$field_args['label_for'] = $key;
			}

			add_settings_field(
				'sidrena_cijena_' . $key,
				$field['label'],
				array( __CLASS__, 'render_field' ),
				self::PAGE,
				'sidrena_cijena_legal',
				$field_args
			);
		}
	}

	/**
	 * Section introduction.
	 *
	 * @return void
	 */
	public static function section_intro() {
		echo '<p>' . esc_html__( 'Changing these values never rewrites existing historical records; every change is recorded in the audit trail.', 'titanworks-bazna-cijena' ) . '</p>';
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
					Dates::format_date_display( $value ),
					array(
						'type'         => 'text',
						'class'        => 'regular-text sidrena-datepicker',
						'placeholder'  => 'd.m.Y.',
						'autocomplete' => 'off',
						'inputmode'    => 'numeric',
					)
				);
				break;
			case 'categories':
				$choices = array();

				foreach ( Plugin::instance()->catalog()->get_categories() as $category ) {
					$choices[ (string) $category['slug'] ] = $category['name'] . ' (' . $category['slug'] . ')';
				}

				if ( empty( $choices ) ) {
					echo '<em>' . esc_html__( 'No catalog categories are available.', 'titanworks-bazna-cijena' ) . '</em>';

					return;
				}

				$legend = isset( $args['label'] ) ? (string) $args['label'] : '';
				$fields->checkboxes( $key, (array) $value, $choices, array( 'legend' => $legend ) );
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

		$history = RuleConfig::history();

		if ( empty( $history ) ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Recent rule changes', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Recent legal rule changes', 'titanworks-bazna-cijena' ) . '</caption>';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'When', 'titanworks-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Setting', 'titanworks-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Old value', 'titanworks-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'New value', 'titanworks-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'User', 'titanworks-bazna-cijena' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( array_slice( array_reverse( $history ), 0, 20 ) as $entry ) {
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td></tr>',
				esc_html( isset( $entry['timestamp'] ) ? Dates::format_datetime_display( $entry['timestamp'] ) : '' ),
				esc_html( Labels::rule_setting( isset( $entry['key'] ) ? (string) $entry['key'] : '' ) ),
				esc_html( self::format_value( isset( $entry['old'] ) ? $entry['old'] : null ) ),
				esc_html( self::format_value( isset( $entry['new'] ) ? $entry['new'] : null ) ),
				esc_html( self::user_label( isset( $entry['user_id'] ) ? (int) $entry['user_id'] : 0 ) )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * Display name for a user ID.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function user_label( $user_id ) {
		if ( $user_id <= 0 ) {
			return __( 'system', 'titanworks-bazna-cijena' );
		}

		$user = get_userdata( $user_id );

		return $user ? $user->display_name : '#' . $user_id;
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

		if ( is_string( $value ) && Dates::is_valid_date( $value ) ) {
			return Dates::format_date_display( $value );
		}

		if ( is_bool( $value ) || 'true' === $value || 'false' === $value ) {
			$bool = is_bool( $value ) ? $value : ( 'true' === $value );
			return $bool ? __( 'Yes', 'titanworks-bazna-cijena' ) : __( 'No', 'titanworks-bazna-cijena' );
		}

		return (string) $value;
	}
}
