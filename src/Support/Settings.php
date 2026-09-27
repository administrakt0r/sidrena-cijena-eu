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
	 * @return array<mixed>
	 */
	public static function defaults() {
		return array(
			'display_enabled'          => true,
			'anchor_label'             => __( 'Sidrena cijena', 'sidrena-cijena-eu' ),
			'currency_display'         => 'symbol',
			'display_location'         => 'price_html',
			'product_display_style'    => 'tooltip',
			'anchor_date_mode'         => 'tooltip',
			'anchor_tooltip_text'      => __( 'Sidrena cijena je najniža cijena u zadnjih 30 dana prije početka posebnog oblika prodaje.', 'sidrena-cijena-eu' ),
			'anchor_font_weight'       => 'normal',
			'display_size'             => 40,
			'display_priority'         => 20,
			'display_product_pages'    => true,
			'display_archives'         => true,
			'public_archive_enabled'   => true,
			'archive_footer_link'      => true,
			'archive_sitemap_link'     => true,
			'display_variations'       => true,
			'only_when_different'      => false,
			'disclaimer'               => '',
			'formats'                  => array( 'csv', 'xml' ),
			'deadline'                 => '08:00',
			'retention_days'           => 30,
			'log_retention_days'       => 180,
			'delete_data_on_uninstall' => false,
			'sale_types'               => array( 'akcija', 'snizenje', 'sezonsko_snizenje', 'rasprodaja' ),
			'filename_separator'       => '_',
			'filename_case'            => 'upper',
			'thirty_day_label'         => __( 'Najniža cijena u zadnjih 30 dana', 'sidrena-cijena-eu' ),
		);
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array<mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		if ( isset( $stored['thirty_day_label'] ) && 'Lowest price in the last 30 days' === $stored['thirty_day_label'] ) {
			$stored['thirty_day_label'] = __( 'Najniža cijena u zadnjih 30 dana', 'sidrena-cijena-eu' );
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

		self::log_changes( $old, $new, $user_id );

		return $new;
	}

	/**
	 * Log changed settings and fire the change action.
	 *
	 * @param array    $old     Previous settings.
	 * @param array    $new     New settings.
	 * @param int|null $user_id Acting user.
	 * @return void
	 */
	private static function log_changes( array $old, array $new, $user_id = null ) {
		$changed = array();

		foreach ( $new as $key => $value ) {
			if ( array_key_exists( $key, $old ) && $old[ $key ] === $value ) {
				continue;
			}

			$changed[ $key ] = array(
				'old' => array_key_exists( $key, $old ) ? $old[ $key ] : null,
				'new' => $value,
			);
		}

		if ( empty( $changed ) ) {
			return;
		}

		if ( null === $user_id ) {
			$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		}

		Logger::info(
			'settings_change',
			__( 'Plugin settings changed.', 'sidrena-cijena-eu' ),
			array(
				'changed' => $changed,
				'user_id' => (int) $user_id,
			)
		);

		/**
		 * Fires after settings changed.
		 *
		 * @param array<mixed> $changed Changed keys with old/new values.
		 * @param array<mixed> $new     Complete new settings.
		 */
		do_action( 'sidrena_cijena_settings_changed', $changed, $new );
	}

	/**
	 * Sanitize a settings array submitted through the Settings API.
	 *
	 * @param mixed $input Raw input.
	 * @return array<mixed>
	 */
	public static function sanitize( $input ) {
		$old    = self::all();
		$output = $old;

		if ( ! is_array( $input ) ) {
			return $old;
		}

		if ( isset( $input['anchor_label'] ) ) {
			$output['anchor_label'] = sanitize_text_field( (string) $input['anchor_label'] );
		}

		if ( isset( $input['thirty_day_label'] ) ) {
			$output['thirty_day_label'] = sanitize_text_field( (string) $input['thirty_day_label'] );
		}

		if ( isset( $input['currency_display'] ) && in_array( $input['currency_display'], array( 'symbol', 'code', 'none' ), true ) ) {
			$output['currency_display'] = $input['currency_display'];
		}

		if ( isset( $input['display_location'] ) && in_array( $input['display_location'], array( 'price_html', 'summary' ), true ) ) {
			$output['display_location'] = $input['display_location'];
		}

		if ( isset( $input['product_display_style'] ) && is_string( $input['product_display_style'] ) && array_key_exists( $input['product_display_style'], DisplayStyles::presets() ) ) {
			$output['product_display_style'] = $input['product_display_style'];
		}

		if ( isset( $input['anchor_date_mode'] ) && in_array( $input['anchor_date_mode'], array( 'tooltip', 'inline', 'subtext', 'hidden' ), true ) ) {
			$output['anchor_date_mode'] = $input['anchor_date_mode'];
		}

		if ( isset( $input['anchor_tooltip_text'] ) ) {
			$output['anchor_tooltip_text'] = sanitize_textarea_field( (string) $input['anchor_tooltip_text'] );
		}

		if ( isset( $input['anchor_font_weight'] ) && in_array( $input['anchor_font_weight'], array( 'normal', 'semi-bold', 'bold' ), true ) ) {
			$output['anchor_font_weight'] = $input['anchor_font_weight'];
		}

		if ( isset( $input['display_size'] ) ) {
			$output['display_size'] = min( 160, max( 40, (int) $input['display_size'] ) );
		}

		if ( isset( $input['display_priority'] ) ) {
			$output['display_priority'] = (int) $input['display_priority'];
		}

		// Each settings tab submits only its own fields. A flag that is not part
		// of the submitted tab must keep its stored value; the hidden companion
		// input rendered by SettingsFields::checkbox() makes present flags
		// always visible to the sanitizer, including an unchecked one.
		foreach ( array( 'display_enabled', 'display_product_pages', 'display_archives', 'display_variations', 'only_when_different', 'delete_data_on_uninstall', 'public_archive_enabled', 'archive_footer_link', 'archive_sitemap_link' ) as $flag ) {
			if ( array_key_exists( $flag, $input ) ) {
				$output[ $flag ] = ! empty( $input[ $flag ] );
			}
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

		if ( isset( $input['deadline'] ) ) {
			$deadline = trim( (string) $input['deadline'] );
			// Normalize single-digit hour like "8:00" to "08:00".
			if ( preg_match( '/^([0-9]):([0-5][0-9])$/', $deadline, $matches ) ) {
				$deadline = '0' . $matches[1] . ':' . $matches[2];
			}
			// Convert AM/PM formatted times to 24-hour format.
			if ( preg_match( '/^([0-1]?[0-9]):([0-5][0-9])\s*(AM|PM)$/i', $deadline, $matches ) ) {
				$hour = (int) $matches[1];
				$min  = $matches[2];
				$ampm = strtoupper( $matches[3] );
				if ( 'PM' === $ampm && $hour < 12 ) {
					$hour += 12;
				} elseif ( 'AM' === $ampm && 12 === $hour ) {
					$hour = 0;
				}
				$deadline = sprintf( '%02d:%s', $hour, $min );
			}
			if ( preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $deadline ) ) {
				$output['deadline'] = $deadline;
			}
		}

		if ( isset( $input['retention_days'] ) ) {
			$output['retention_days'] = max( 30, (int) $input['retention_days'] );
		}

		if ( isset( $input['log_retention_days'] ) ) {
			$output['log_retention_days'] = max( 1, (int) $input['log_retention_days'] );
		}

		if ( isset( $input['sale_types'] ) ) {
			$types = is_array( $input['sale_types'] )
				? $input['sale_types']
				: preg_split( '/[\r\n,;]+/', (string) $input['sale_types'] );
			$types = array_filter( array_map( 'sanitize_key', (array) $types ) );

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

		self::log_changes( $old, $output );

		return $output;
	}
}
