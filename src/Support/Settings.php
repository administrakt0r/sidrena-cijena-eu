<?php
/**
 * Typed plugin settings with defaults.
 *
 * @package SidrenaCijena\Support
 */

namespace SidrenaCijena\Support;

use SidrenaCijena\Logging\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the `sidrena_cijena_settings` option.
 */
final class Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'sidrena_cijena_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'display_enabled'      => true,
			'anchor_label'         => __( 'Sidrena cijena', 'sidrena-cijena' ),
			'currency_display'     => 'symbol',
			'display_location'     => 'price_html',
			'display_priority'     => 20,
			'display_product_pages' => true,
			'display_archives'     => true,
			'display_variations'   => true,
			'only_when_different'  => false,
			'disclaimer'           => '',
			'formats'              => array( 'csv', 'xml' ),
			'deadline'             => '08:00',
			'retention_days'       => 30,
			'log_retention_days'   => 180,
			'delete_data_on_uninstall' => false,
			'sale_types'           => array( 'akcija', 'snizenje', 'sezonsko_snizenje', 'rasprodaja' ),
			'filename_separator'   => '_',
			'filename_case'        => 'upper',
			'thirty_day_label'     => __( 'Lowest price in the last 30 days', 'sidrena-cijena' ),
		);
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Update settings. Only known keys are stored.
	 *
	 * @param array    $values  Key/value pairs.
	 * @param int|null $user_id Acting user; defaults to the current user.
	 * @return array The updated settings.
	 */
	public static function update( array $values, $user_id = null ) {
		$old = self::all();
		$new = $old;

		foreach ( $values as $key => $value ) {
			if ( array_key_exists( $key, $old ) ) {
				$new[ $key ] = $value;
			}
		}

		update_option( self::OPTION, $new, false );

		$changed = array();
		foreach ( $new as $key => $value ) {
			if ( $old[ $key ] !== $value ) {
				$changed[ $key ] = array(
					'old' => $old[ $key ],
					'new' => $value,
				);
			}
		}

		if ( ! empty( $changed ) ) {
			if ( null === $user_id ) {
				$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
			}

			Logger::info(
				'settings_change',
				__( 'Plugin settings changed.', 'sidrena-cijena' ),
				array(
					'changed' => $changed,
					'user_id' => (int) $user_id,
				)
			);

			/**
			 * Fires after settings changed.
			 *
			 * @param array $changed Changed keys with old/new values.
			 * @param array $new     Complete new settings.
			 */
			do_action( 'sidrena_cijena_settings_changed', $changed, $new );
		}

		return $new;
	}

	/**
	 * Sanitize a settings array submitted through the Settings API.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( array $input ) {
		$defaults = self::defaults();
		$output   = self::all();

		if ( isset( $input['anchor_label'] ) ) {
			$output['anchor_label'] = sanitize_text_field( (string) $input['anchor_label'] );
		}

		if ( isset( $input['thirty_day_label'] ) ) {
			$output['thirty_day_label'] = sanitize_text_field( (string) $input['thirty_day_label'] );
		}

		if ( isset( $input['currency_display'] ) && in_array( $input['currency_display'], array( 'symbol', 'code', 'none' ), true ) ) {
			$output['currency_display'] = $input['currency_display'];
		}

		if ( isset( $input['display_location'] ) && in_array( $input['display_location'], array( 'price_html', 'summary', 'both' ), true ) ) {
			$output['display_location'] = $input['display_location'];
		}

		if ( isset( $input['display_priority'] ) ) {
			$output['display_priority'] = (int) $input['display_priority'];
		}

		foreach ( array( 'display_enabled', 'display_product_pages', 'display_archives', 'display_variations', 'only_when_different', 'delete_data_on_uninstall' ) as $flag ) {
			$output[ $flag ] = ! empty( $input[ $flag ] );
		}

		if ( isset( $input['disclaimer'] ) ) {
			$output['disclaimer'] = wp_kses_post( (string) $input['disclaimer'] );
		}

		if ( isset( $input['formats'] ) && is_array( $input['formats'] ) ) {
			$formats = array_values( array_intersect( array( 'csv', 'xml' ), $input['formats'] ) );

			if ( ! empty( $formats ) ) {
				$output['formats'] = $formats;
			}
		}

		if ( isset( $input['deadline'] ) && preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', (string) $input['deadline'] ) ) {
			$output['deadline'] = (string) $input['deadline'];
		}

		if ( isset( $input['retention_days'] ) ) {
			$output['retention_days'] = max( 30, (int) $input['retention_days'] );
		}

		if ( isset( $input['log_retention_days'] ) ) {
			$output['log_retention_days'] = max( 1, (int) $input['log_retention_days'] );
		}

		if ( isset( $input['sale_types'] ) ) {
			$types = is_array( $input['sale_types'] ) ? $input['sale_types'] : explode( ',', (string) $input['sale_types'] );
			$types = array_filter( array_map( 'sanitize_key', $types ) );

			if ( ! empty( $types ) ) {
				$output['sale_types'] = array_values( array_unique( $types ) );
			}
		}

		if ( isset( $input['filename_separator'] ) && in_array( $input['filename_separator'], array( '_', '-' ), true ) ) {
			$output['filename_separator'] = $input['filename_separator'];
		}

		if ( isset( $input['filename_case'] ) && in_array( $input['filename_case'], array( 'upper', 'lower' ), true ) ) {
			$output['filename_case'] = $input['filename_case'];
		}

		unset( $defaults );

		return $output;
	}
}
