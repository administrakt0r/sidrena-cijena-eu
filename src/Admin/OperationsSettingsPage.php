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
			'public_archive_enabled'   => array(
				'label' => __( 'Public price-list archive', 'sidrena-cijena' ),
				'type'  => 'checkbox',
			),
			'archive_footer_link'      => array(
				'label' => __( 'Link from the site footer', 'sidrena-cijena' ),
				'type'  => 'checkbox',
			),
			'archive_sitemap_link'     => array(
				'label' => __( 'Include in the WordPress sitemap', 'sidrena-cijena' ),
				'type'  => 'checkbox',
			),
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
				if ( 'public_archive_enabled' === $key ) {
					$label = __( 'Show the generated price-list archive at /arhiva-sidrene-cijene/', 'sidrena-cijena' );
				} elseif ( 'archive_footer_link' === $key ) {
					$label = __( 'Show a link to the archive in the site footer', 'sidrena-cijena' );
				} elseif ( 'archive_sitemap_link' === $key ) {
					$label = __( 'Add the archive URL to the WordPress XML sitemap', 'sidrena-cijena' );
				} else {
					$label = __( 'Yes, delete all plugin data on uninstall', 'sidrena-cijena' );
				}
				$fields->checkbox(
					$key,
					$value,
					array(
						'label'       => $label,
						'description' => 'delete_data_on_uninstall' === $key ? __( 'Danger: with this enabled, uninstalling deletes price history, publications and settings. Without it, uninstall keeps the historical and audit data. The anchor prices and the 30-day lowest prices are the legal evidence and are kept either way; delete the wp_sidrena_price_history table by hand to remove them too. Never touches WooCommerce or WordPress data.', 'sidrena-cijena' ) : '',
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
