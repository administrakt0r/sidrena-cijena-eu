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
	 * Canonical legal timezone for Croatian compliance: Europe/Zagreb.
	 *
	 * @return \DateTimeZone
	 */
	public static function zagreb_timezone() {
		return new \DateTimeZone( 'Europe/Zagreb' );
	}

	/**
	 * Site time zone. Falls back to Europe/Zagreb when unconfigured or UTC.
	 *
	 * @return \DateTimeZone
	 */
	public static function timezone() {
		if ( function_exists( 'wp_timezone' ) ) {
			$tz = wp_timezone();
			if ( 'UTC' !== $tz->getName() && '+00:00' !== $tz->getName() ) {
				return $tz;
			}
		}

		return self::zagreb_timezone();
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
	 * @param \DateTimeImmutable $datetime Site-local datetime.
	 * @return string
	 */
	public static function to_utc( \DateTimeImmutable $datetime ) {
		return $datetime->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Parse a site-local date or datetime into a DateTimeImmutable.
	 *
	 * Supports both ISO (`Y-m-d H:i:s`, `Y-m-d H:i`, `Y-m-d`) and Croatian
	 * formats (`d.m.Y. H:i`, `d.m.Y.`, `d.m.Y`).
	 *
	 * @param string $value Date or datetime.
	 * @return \DateTimeImmutable|null
	 */
	public static function parse_site( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?(\s+(\d{1,2}):(\d{2})(:(\d{2}))?)?$/', $value, $matches ) ) {
			$day   = sprintf( '%02d', (int) $matches[1] );
			$month = sprintf( '%02d', (int) $matches[2] );
			$year  = $matches[3];
			$time  = ! empty( $matches[4] ) ? sprintf( '%02d:%02d:%02d', (int) $matches[5], (int) $matches[6], isset( $matches[8] ) ? (int) $matches[8] : 0 ) : '00:00:00';
			$iso   = "$year-$month-$day $time";

			$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $iso, self::timezone() );
			if ( $date ) {
				return $date;
			}
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
	 * Normalize a date string (ISO `Y-m-d` or Croatian `d.m.Y.`) to ISO `Y-m-d`.
	 *
	 * @param mixed $value Input date.
	 * @return string Normalized `YYYY-MM-DD` or empty string if invalid.
	 */
	public static function normalize_date( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );
		$clean = rtrim( $value, '.' );

		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $clean, $matches ) ) {
			$y = (int) $matches[1];
			$m = (int) $matches[2];
			$d = (int) $matches[3];
			if ( checkdate( $m, $d, $y ) ) {
				return sprintf( '%04d-%02d-%02d', $y, $m, $d );
			}
			return '';
		}

		if ( preg_match( '/^(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{4})$/', $clean, $matches ) ) {
			$y = (int) $matches[3];
			$m = (int) $matches[2];
			$d = (int) $matches[1];
			if ( checkdate( $m, $d, $y ) ) {
				return sprintf( '%04d-%02d-%02d', $y, $m, $d );
			}
			return '';
		}

		return '';
	}

	/**
	 * Whether a string is a valid ISO date (YYYY-MM-DD).
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
	 * Format a date-only value for display as Croatian `d.m.Y.`.
	 *
	 * Reference dates are legal date-only values (`Y-m-d`). Passing them through
	 * a timestamp would shift the calendar day on sites west of UTC, so the
	 * components are formatted directly (`10.09.2026.`).
	 *
	 * @param mixed $value Date (`Y-m-d`), datetime string, or integer timestamp.
	 * @return string Display date (e.g. `10.09.2026.`), or an empty string when the value is not a date.
	 */
	public static function format_date_display( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( is_numeric( $value ) ) {
			$dt = ( new \DateTimeImmutable( '@' . (int) $value ) )->setTimezone( self::zagreb_timezone() );

			return $dt ? $dt->format( 'd.m.Y.' ) : '';
		}

		$value = self::date_only( $value );

		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );

		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches ) ) {
			if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
				return '';
			}
			return sprintf( '%02d.%02d.%04d.', (int) $matches[3], (int) $matches[2], (int) $matches[1] );
		}

		if ( preg_match( '/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?$/', $value, $matches ) ) {
			if ( ! checkdate( (int) $matches[2], (int) $matches[1], (int) $matches[3] ) ) {
				return '';
			}
			return sprintf( '%02d.%02d.%04d.', (int) $matches[1], (int) $matches[2], (int) $matches[3] );
		}

		return '';
	}

	/**
	 * Format a UTC datetime for display in Europe/Zagreb 24-hour time (`d.m.Y. H:i`).
	 *
	 * Consistent with format_date_display: outputs Croatian `d.m.Y. H:i` in 24-hour time.
	 *
	 * @param mixed $value UTC datetime string or integer timestamp.
	 * @return string Formatted Zagreb datetime (e.g. `10.09.2026. 08:00`) or empty string if invalid.
	 */
	public static function format_datetime_display( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$tz = self::zagreb_timezone();

		if ( is_numeric( $value ) ) {
			$dt = ( new \DateTimeImmutable( '@' . (int) $value ) )->setTimezone( $tz );

			return $dt ? $dt->format( 'd.m.Y. H:i' ) : '';
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		$dt = self::from_utc( 16 === strlen( $value ) ? $value . ':00' : $value );

		if ( $dt ) {
			return $dt->setTimezone( $tz )->format( 'd.m.Y. H:i' );
		}

		try {
			$parsed = new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );

			return $parsed->setTimezone( $tz )->format( 'd.m.Y. H:i' );
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * The `Y-m-d` portion of a date or datetime string.
	 *
	 * Values read back from `datetime` columns arrive as `Y-m-d H:i:s` and must
	 * be reduced to their date part before they are compared or re-validated.
	 *
	 * @param mixed $value Candidate.
	 * @return mixed The date part when the value is a datetime, otherwise the value unchanged.
	 */
	public static function date_only( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		return strlen( $value ) > 10 ? substr( $value, 0, 10 ) : $value;
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
		$parts = array_pad( explode( ':', $time_hm ), 2, '0' );
		$hour  = (int) $parts[0];
		$min   = (int) $parts[1];

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
