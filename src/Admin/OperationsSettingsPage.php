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
			__( 'Publication and lifecycle', 'titanworks-bazna-cijena' ),
			array( __CLASS__, 'section_intro' ),
			self::PAGE
		);

		$fields = array(
			'public_archive_enabled'       => array(
				'label' => __( 'Public price-list archive', 'titanworks-bazna-cijena' ),
				'type'  => 'checkbox',
			),
			'archive_footer_link'          => array(
				'label' => __( 'Link from the site footer', 'titanworks-bazna-cijena' ),
				'type'  => 'checkbox',
			),
			'archive_sitemap_link'         => array(
				'label' => __( 'Include in the WordPress sitemap', 'titanworks-bazna-cijena' ),
				'type'  => 'checkbox',
			),
			'deadline'                     => array(
				'label' => __( 'Daily publication deadline', 'titanworks-bazna-cijena' ),
				'type'  => 'time',
			),
			'retention_days'               => array(
				'label' => __( 'Price-list retention (days)', 'titanworks-bazna-cijena' ),
				'type'  => 'number',
			),
			'log_retention_days'           => array(
				'label' => __( 'Log retention (days)', 'titanworks-bazna-cijena' ),
				'type'  => 'number',
			),
			'formats'                      => array(
				'label' => __( 'Export formats', 'titanworks-bazna-cijena' ),
				'type'  => 'formats',
			),
			'allow_generation_with_errors' => array(
				'label' => __( 'Mandatory price-list generation', 'titanworks-bazna-cijena' ),
				'type'  => 'checkbox',
			),
			'delete_data_on_uninstall'     => array(
				'label' => __( 'Delete data on uninstall', 'titanworks-bazna-cijena' ),
				'type'  => 'checkbox',
			),
		);

		foreach ( $fields as $key => $field ) {
			$field_args = array(
				'key'   => $key,
				'type'  => $field['type'],
				'label' => $field['label'],
			);

			if ( 'formats' !== $field['type'] ) {
				$field_args['label_for'] = $key;
			}

			add_settings_field(
				'sidrena_cijena_' . $key,
				$field['label'],
				array( __CLASS__, 'render_field' ),
				self::PAGE,
				'sidrena_cijena_operations',
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
		echo '<p>' . esc_html__( 'Control when daily price lists are generated, how long publications and logs are kept, and what happens to compliance data when the plugin is uninstalled. Deactivation never deletes data.', 'titanworks-bazna-cijena' ) . '</p>';
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
						'type'        => 'text',
						'class'       => 'small-text sidrena-time-input',
						'placeholder' => '08:00',
						'pattern'     => '^([01]?[0-9]|2[0-3]):[0-5][0-9]$',
						'maxlength'   => 5,
						'inputmode'   => 'numeric',
						'description' => __( '24-hour format (HH:MM, e.g. 08:00 or 14:30) in Zagreb time (Europe/Zagreb).', 'titanworks-bazna-cijena' ),
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
							? __( 'Price lists stay publicly available at least 30 days; lower values are raised to 30.', 'titanworks-bazna-cijena' )
							: __( 'Operational log entries older than this are pruned by the daily cleanup.', 'titanworks-bazna-cijena' ),
					)
				);
				break;
			case 'formats':
				$fields->checkboxes(
					$key,
					(array) $value,
					array(
						'csv' => __( 'CSV', 'titanworks-bazna-cijena' ),
						'xml' => __( 'XML', 'titanworks-bazna-cijena' ),
					),
					array(
						'description' => __( 'Both formats are published for every location unless the location restricts them.', 'titanworks-bazna-cijena' ),
						'legend'      => isset( $args['label'] ) ? (string) $args['label'] : '',
					)
				);
				break;
			case 'checkbox':
				if ( 'public_archive_enabled' === $key ) {
					$label = __( 'Show the generated price-list archive at /arhiva-sidrene-cijene/', 'titanworks-bazna-cijena' );
				} elseif ( 'archive_footer_link' === $key ) {
					$label = __( 'Show a link to the archive in the site footer', 'titanworks-bazna-cijena' );
				} elseif ( 'archive_sitemap_link' === $key ) {
					$label = __( 'Add the archive URL to the WordPress XML sitemap', 'titanworks-bazna-cijena' );
				} elseif ( 'allow_generation_with_errors' === $key ) {
					$label = __( 'Generate and publish daily price lists even if catalog validation errors exist', 'titanworks-bazna-cijena' );
				} else {
					$label = __( 'Yes, delete all plugin data on uninstall', 'titanworks-bazna-cijena' );
				}

				$description = '';
				if ( 'delete_data_on_uninstall' === $key ) {
					$description = __( 'Danger: with this enabled, uninstalling deletes price history, publications and settings. Without it, uninstall keeps the historical and audit data. The anchor prices and the 30-day lowest prices are the legal evidence and are kept either way; delete the wp_sidrena_price_history table by hand to remove them too. Never touches WooCommerce or WordPress data.', 'titanworks-bazna-cijena' );
				} elseif ( 'allow_generation_with_errors' === $key ) {
					$description = __( 'Mandatory under Croatian law: daily price lists must be published every morning before 08:00. When enabled, generation will never be blocked by catalog compliance errors; valid data is exported and actionable remediation proposals are attached.', 'titanworks-bazna-cijena' );
				}

				$fields->checkbox(
					$key,
					$value,
					array(
						'label'       => $label,
						'description' => $description,
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
