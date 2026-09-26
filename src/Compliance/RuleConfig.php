<?php
/**
 * Legal-rule configuration (reference dates, legacy mapping, rule version).
 *
 * Changing rule configuration never rewrites price history; every change is
 * appended to an audit option (FR-006, FR-008).
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance;

use SidrenaCijena\Logging\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the `sidrena_cijena_rule_config` option.
 */
final class RuleConfig {
	/**
	 * Guard the Settings API callback during an explicit update.
	 *
	 * @var bool
	 */
	private static $updating = false;

	/**
	 * Option name.
	 */
	const OPTION = 'sidrena_cijena_rule_config';

	/**
	 * Audit option name.
	 */
	const HISTORY_OPTION = 'sidrena_cijena_rule_config_history';

	/**
	 * Default general reference date.
	 */
	const DEFAULT_GENERAL_DATE = '2026-09-10';

	/**
	 * Default legacy-category reference date.
	 */
	const DEFAULT_LEGACY_DATE = '2025-05-02';

	/**
	 * Default legal-rule version identifier.
	 */
	const DEFAULT_RULE_VERSION = '2026-10-01';

	/**
	 * Default rule configuration.
	 *
	 * @return array<mixed>
	 */
	public static function defaults() {
		return array(
			'general_reference_date' => self::DEFAULT_GENERAL_DATE,
			'legacy_reference_date'  => self::DEFAULT_LEGACY_DATE,
			'legacy_enabled'         => true,
			'legacy_category_ids'    => array(),
			'legacy_category_slugs'  => array(),
			'rule_version'           => self::DEFAULT_RULE_VERSION,
		);
	}

	/**
	 * All rule configuration merged over defaults.
	 *
	 * @return array<mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Single value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Update rule configuration and append an audit entry per changed key.
	 *
	 * @param array    $values  Key/value pairs.
	 * @param int|null $user_id Acting user.
	 * @param string   $reason  Optional reason for the change.
	 * @return array Updated configuration.
	 */
	public static function update( array $values, $user_id = null, $reason = '' ) {
		$new            = self::apply( $values, $user_id, $reason );
		self::$updating = true;

		try {
			update_option( self::OPTION, $new, false );
		} finally {
			self::$updating = false;
		}

		return $new;
	}

	/**
	 * Sanitize a configuration array submitted through the Settings API.
	 *
	 * @param mixed $input Raw input.
	 * @return array<mixed>
	 */
	public static function sanitize( $input ) {
		if ( self::$updating ) {
			return (array) $input;
		}

		return self::apply( (array) $input, null, '' );
	}

	/**
	 * Validate, store and audit rule configuration values.
	 *
	 * @param array    $values  Key/value pairs.
	 * @param int|null $user_id Acting user.
	 * @param string   $reason  Optional reason for the change.
	 * @return array Updated configuration.
	 */
	private static function apply( array $values, $user_id = null, $reason = '' ) {
		$old = self::all();
		$new = $old;

		foreach ( $values as $key => $value ) {
			if ( ! array_key_exists( $key, $old ) ) {
				continue;
			}

			if ( 'general_reference_date' === $key || 'legacy_reference_date' === $key ) {
				$value = self::sanitize_date( $value, $old[ $key ] );
			}

			if ( 'legacy_category_ids' === $key || 'legacy_category_slugs' === $key ) {
				$value = array_values( array_unique( array_filter( array_map( 'strval', (array) $value ) ) ) );
			}

			if ( 'legacy_enabled' === $key ) {
				$value = (bool) $value;
			}

			if ( 'rule_version' === $key ) {
				$value = sanitize_text_field( (string) $value );
			}

			$new[ $key ] = $value;
		}

		$changed = array();

		foreach ( $new as $key => $value ) {
			if ( $old[ $key ] === $value ) {
				continue;
			}

			$changed[] = $key;
		}

		if ( empty( $changed ) ) {
			return $new;
		}

		if ( null === $user_id ) {
			$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		}

		foreach ( $changed as $key ) {
			self::append_history(
				array(
					'key'       => $key,
					'old'       => $old[ $key ],
					'new'       => $new[ $key ],
					'user_id'   => (int) $user_id,
					'reason'    => sanitize_text_field( $reason ),
					'timestamp' => gmdate( 'c' ),
				)
			);
		}

		Logger::info(
			'settings_change',
			__( 'Legal rule configuration changed.', 'sidrena-cijena-eu' ),
			array(
				'user_id' => (int) $user_id,
				'reason'  => $reason,
				'changed' => $changed,
			)
		);

		/**
		 * Fires after rule configuration changed.
		 *
		 * @param array<mixed> $new Complete new configuration.
		 * @param array<mixed> $old Previous configuration.
		 */
		do_action( 'sidrena_cijena_rule_config_changed', $new, $old );

		return $new;
	}

	/**
	 * Append-only audit history.
	 *
	 * @return array<mixed>[]
	 */
	public static function history() {
		$history = get_option( self::HISTORY_OPTION, array() );

		return is_array( $history ) ? $history : array();
	}

	/**
	 * Append one audit entry.
	 *
	 * @param array<mixed> $entry Entry.
	 * @return void
	 */
	private static function append_history( array $entry ) {
		$history   = self::history();
		$history[] = $entry;

		update_option( self::HISTORY_OPTION, $history, false );
	}

	/**
	 * Validate a date string, falling back to the previous value.
	 *
	 * @param mixed  $value    Candidate date.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private static function sanitize_date( $value, $fallback ) {
		$value = trim( (string) $value );

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			return $fallback;
		}

		return $value;
	}
}
