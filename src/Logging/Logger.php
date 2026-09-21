<?php
/**
 * Structured logger.
 *
 * Logs never contain passwords, payment data or customer personal data
 * (FR-074, FR-075). Logging failures never interrupt the calling operation.
 *
 * @package SidrenaCijena\Logging
 */

namespace SidrenaCijena\Logging;

use SidrenaCijena\Database\LogRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Static logging facade.
 */
final class Logger {

	/**
	 * Repository instance.
	 *
	 * @var LogRepository|null
	 */
	private static $repository = null;

	/**
	 * Repository accessor.
	 *
	 * @return LogRepository
	 */
	public static function repository() {
		if ( null === self::$repository ) {
			self::$repository = new LogRepository();
		}

		return self::$repository;
	}

	/**
	 * Replace the repository (used by tests).
	 *
	 * @param LogRepository|null $repository Repository or null to reset.
	 * @return void
	 */
	public static function set_repository( $repository ) {
		self::$repository = $repository;
	}

	/**
	 * Write a log entry.
	 *
	 * @param string $level   Level (`info`, `warning`, `error`).
	 * @param string $event   Event code.
	 * @param string $message Message.
	 * @param array  $context Structured context.
	 * @return void
	 */
	public static function log( $level, $event, $message, array $context = array() ) {
		try {
			$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : null;

			self::repository()->insert(
				$level,
				$event,
				$message,
				self::sanitize_context( $context ),
				$user_id ? $user_id : null
			);
		} catch ( \Throwable $exception ) {
			// Logging must never break the operation it describes.
			unset( $exception );
		}
	}

	/**
	 * Info-level entry.
	 *
	 * @param string $event   Event.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public static function info( $event, $message, array $context = array() ) {
		self::log( 'info', $event, $message, $context );
	}

	/**
	 * Warning-level entry.
	 *
	 * @param string $event   Event.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public static function warning( $event, $message, array $context = array() ) {
		self::log( 'warning', $event, $message, $context );
	}

	/**
	 * Error-level entry.
	 *
	 * @param string $event   Event.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public static function error( $event, $message, array $context = array() ) {
		self::log( 'error', $event, $message, $context );
	}

	/**
	 * Remove sensitive keys and truncate values.
	 *
	 * @param array $context Context.
	 * @param int   $depth   Current recursion depth.
	 * @return array
	 */
	public static function sanitize_context( array $context, $depth = 0 ) {
		if ( $depth > 5 ) {
			return array();
		}

		$sensitive = '/(pass|secret|token|api[_-]?key|authorization|card|cvv|iban|email|phone|mobile|customer|session|cookie)/i';

		$clean = array();

		foreach ( $context as $key => $value ) {
			if ( is_string( $key ) && preg_match( $sensitive, $key ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = self::sanitize_context( $value, $depth + 1 );
			} elseif ( is_object( $value ) ) {
				$clean[ $key ] = '[object]';
			} elseif ( is_scalar( $value ) || null === $value ) {
				if ( is_string( $value ) && strlen( $value ) > 500 ) {
					$value = substr( $value, 0, 500 ) . '…';
				}

				$clean[ $key ] = $value;
			}
		}

		return $clean;
	}
}
