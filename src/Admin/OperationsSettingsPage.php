<?php
/**
 * Operational settings tab.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Publication deadline, retention and lifecycle settings.
 */
final class OperationsSettingsPage {

	/**
	 * Settings page slug used by do_settings_sections().
	 */
	const PAGE = 'sidrena-cijena-settings-operations';

	/**
	 * Register sections and fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		add_settings_section(
			'sidrena_cijena_operations',
			__( 'Publication and lifecycle', 'sidrena-cijena' ),
			array( __CLASS__, 'section_intro' ),
			self::PAGE
		);

		$fields = array(
			'deadline'                 => array(
				'label' => __( 'Daily publication deadline', 'sidrena-cijena' ),
				'type'  => 'time',
			),
			'retention_days'           => array(
				'label' => __( 'Price-list retention (days)', 'sidrena-cijena' ),
				'type'  => 'number',
			),
			'log_retention_days'       => array(
				'label' => __( 'Log retention (days)', 'sidrena-cijena' ),
				'type'  => 'number',
			),
			'formats'                  => array(
				'label' => __( 'Export formats', 'sidrena-cijena' ),
				'type'  => 'formats',
			),
			'delete_data_on_uninstall' => array(
				'label' => __( 'Delete data on uninstall', 'sidrena-cijena' ),
				'type'  => 'checkbox',
			),
		);

		foreach ( $fields as $key => $field ) {
			add_settings_field(
				'sidrena_cijena_' . $key,
				$field['label'],
				array( __CLASS__, 'render_field' ),
				self::PAGE,
				'sidrena_cijena_operations',
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
		echo '<p>' . esc_html__( 'Control when daily price lists are generated, how long publications and logs are kept, and what happens to compliance data when the plugin is uninstalled. Deactivation never deletes data.', 'sidrena-cijena' ) . '</p>';
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
			case 'time':
				$fields->text(
					$key,
					$value,
					array(
						'type'        => 'time',
						'description' => __( 'Working-day deadline in 24-hour site time (default 08:00).', 'sidrena-cijena' ),
					)
				);
				break;
			case 'number':
				$fields->number(
					$key,
					$value,
					array(
						'min'         => 'retention_days' === $key ? 30 : 1,
						'description' => 'retention_days' === $key
							? __( 'Price lists stay publicly available at least 30 days; lower values are raised to 30.', 'sidrena-cijena' )
							: __( 'Operational log entries older than this are pruned by the daily cleanup.', 'sidrena-cijena' ),
					)
				);
				break;
			case 'formats':
				$fields->checkboxes(
					$key,
					(array) $value,
					array(
						'csv' => __( 'CSV', 'sidrena-cijena' ),
						'xml' => __( 'XML', 'sidrena-cijena' ),
					),
					array(
						'description' => __( 'Both formats are published for every location unless the location restricts them.', 'sidrena-cijena' ),
					)
				);
				break;
			case 'checkbox':
				$fields->checkbox(
					$key,
					$value,
					array(
						'label'       => __( 'Yes, delete all plugin data on uninstall', 'sidrena-cijena' ),
						'description' => __( 'Danger: with this enabled, uninstalling deletes price history, publications and settings. Without it, uninstall keeps the historical and audit data. Never touches WooCommerce or WordPress data.', 'sidrena-cijena' ),
					)
				);
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
	}
}
