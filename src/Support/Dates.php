<?php
/**
 * Date and time helpers.
 *
 * All business rules (reference dates, sale windows, deadlines) are evaluated
 * in the site time zone; stored timestamps are UTC.
 *
 * @package SidrenaCijena\Support
 */

namespace SidrenaCijena\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Date helpers.
 */
final class Dates {

	/**
	 * Site time zone.
	 *
	 * @return \DateTimeZone
	 */
	public static function timezone() {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}

		return new \DateTimeZone( 'UTC' );
	}

	/**
	 * Current UTC time as a MySQL datetime.
	 *
	 * @return string
	 */
	public static function now_utc() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Current site-local date (Y-m-d).
	 *
	 * @return string
	 */
	public static function today() {
		return self::now_site()->format( 'Y-m-d' );
	}

	/**
	 * Current site-local datetime.
	 *
	 * @return \DateTimeImmutable
	 */
	public static function now_site() {
		return new \DateTimeImmutable( 'now', self::timezone() );
	}

	/**
	 * Convert a UTC MySQL datetime to a site-time DateTimeImmutable.
	 *
	 * @param string $datetime UTC datetime.
	 * @return \DateTimeImmutable|null
	 */
	public static function from_utc( $datetime ) {
		if ( empty( $datetime ) ) {
			return null;
		}

		$utc = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $datetime, new \DateTimeZone( 'UTC' ) );

		if ( ! $utc ) {
			return null;
		}

		return $utc->setTimezone( self::timezone() );
	}

	/**
	 * Convert a site-local datetime to a UTC MySQL datetime.
	 *
	 * @param \DateTimeInterface $datetime Site-local datetime.
	 * @return string
	 */
	public static function to_utc( \DateTimeInterface $datetime ) {
		return $datetime->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Parse a site-local date or datetime into a DateTimeImmutable.
	 *
	 * @param string $value Date (`Y-m-d`) or datetime (`Y-m-d H:i:s`).
	 * @return \DateTimeImmutable|null
	 */
	public static function parse_site( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return null;
		}

		$formats = array( 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d' );

		foreach ( $formats as $format ) {
			$date = \DateTimeImmutable::createFromFormat( '!' . $format, $value, self::timezone() );

			if ( $date && $date->format( $format ) === $value ) {
				return $date;
			}
		}

		return null;
	}

	/**
	 * Whether a string is a valid `Y-m-d` date.
	 *
	 * @param mixed $value Candidate.
	 * @return bool
	 */
	public static function is_valid_date( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return false;
		}

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		return (bool) ( $date && $date->format( 'Y-m-d' ) === $value );
	}

	/**
	 * Add calendar days to a `Y-m-d` date.
	 *
	 * @param string $date Date.
	 * @param int    $days Days (may be negative).
	 * @return string
	 */
	public static function add_days( $date, $days ) {
		$date = self::parse_site( $date );

		if ( ! $date ) {
			return '';
		}

		return $date->modify( ( $days >= 0 ? '+' : '' ) . (int) $days . ' days' )->format( 'Y-m-d' );
	}

	/**
	 * Number of whole calendar days from `$from` to `$to`.
	 *
	 * @param string $from Start date.
	 * @param string $to   End date.
	 * @return int|null
	 */
	public static function diff_days( $from, $to ) {
		$start = self::parse_site( $from );
		$end   = self::parse_site( $to );

		if ( ! $start || ! $end ) {
			return null;
		}

		$start = $start->setTime( 0, 0, 0 );
		$end   = $end->setTime( 0, 0, 0 );

		return (int) $start->diff( $end )->format( '%r%a' );
	}

	/**
	 * Timestamp of the next occurrence of a site-local time.
	 *
	 * Used by the scheduler: `08:00` today when still ahead, otherwise tomorrow.
	 *
	 * @param string $time_hm Time in `H:i`.
	 * @param bool   $working_days_only Skip Saturday and Sunday.
	 * @return int UTC timestamp.
	 */
	public static function next_occurrence( $time_hm, $working_days_only = false ) {
		$parts = explode( ':', $time_hm );
		$hour  = isset( $parts[0] ) ? (int) $parts[0] : 8;
		$min   = isset( $parts[1] ) ? (int) $parts[1] : 0;

		$now   = self::now_site();
		$today = $now->setTime( $hour, $min, 0 );

		if ( $today <= $now ) {
			$today = $today->modify( '+1 day' );
		}

		if ( $working_days_only ) {
			while ( in_array( (int) $today->format( 'N' ), array( 6, 7 ), true ) ) {
				$today = $today->modify( '+1 day' );
			}
		}

		return $today->getTimestamp();
	}

	/**
	 * Whether a site-local date is a working day (Monday-Friday).
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	public static function is_working_day( $date ) {
		$parsed = self::parse_site( $date );

		if ( ! $parsed ) {
			return false;
		}

		return ! in_array( (int) $parsed->format( 'N' ), array( 6, 7 ), true );
	}
}
