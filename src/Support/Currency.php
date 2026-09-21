<?php
/**
 * Currency and monetary formatting.
 *
 * Storage uses six decimal places; display and CSV export use two decimals
 * with Croatian conventions; XML uses two decimals with a point.
 *
 * @package SidrenaCijena\Support
 */

namespace SidrenaCijena\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Monetary helpers.
 */
final class Currency {

	/**
	 * Default currency.
	 */
	const DEFAULT_CURRENCY = 'EUR';

	/**
	 * Number of decimals used for storage.
	 */
	const STORAGE_DECIMALS = 6;

	/**
	 * Number of decimals used for display and export.
	 */
	const DISPLAY_DECIMALS = 2;

	/**
	 * Currency symbols.
	 *
	 * @return array<string,string>
	 */
	public static function symbols() {
		return array(
			'EUR' => '€',
			'USD' => '$',
			'GBP' => '£',
			'HRK' => 'kn',
		);
	}

	/**
	 * Normalize a monetary value for storage.
	 *
	 * @param mixed $value Value.
	 * @return float|null Null for empty or non-numeric input.
	 */
	public static function to_storage( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( is_string( $value ) ) {
			$value = str_replace( array( ' ', "\u{00A0}" ), '', $value );
			$value = str_replace( ',', '.', $value );
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		return round( (float) $value, self::STORAGE_DECIMALS );
	}

	/**
	 * Round a stored value to the display precision.
	 *
	 * @param float $amount Amount.
	 * @return float
	 */
	public static function to_display( $amount ) {
		return round( (float) $amount, self::DISPLAY_DECIMALS );
	}

	/**
	 * CSV/display decimal: two decimals, comma separator, no thousands.
	 *
	 * @param float|null $amount Amount.
	 * @return string
	 */
	public static function format_decimal( $amount ) {
		if ( null === $amount ) {
			return '';
		}

		return number_format( (float) $amount, self::DISPLAY_DECIMALS, ',', '' );
	}

	/**
	 * XML decimal: two decimals, point separator.
	 *
	 * @param float|null $amount Amount.
	 * @return string
	 */
	public static function format_decimal_xml( $amount ) {
		if ( null === $amount ) {
			return '';
		}

		return number_format( (float) $amount, self::DISPLAY_DECIMALS, '.', '' );
	}

	/**
	 * Human-readable price including the configured currency presentation.
	 *
	 * @param float|null $amount  Amount.
	 * @param string     $currency ISO code.
	 * @param array      $args     Optional `display` (symbol|code|none).
	 * @return string
	 */
	public static function format( $amount, $currency = self::DEFAULT_CURRENCY, array $args = array() ) {
		$display = isset( $args['display'] ) ? $args['display'] : Settings::get( 'currency_display', 'symbol' );

		$number = number_format( (float) $amount, self::DISPLAY_DECIMALS, ',', '.' );

		$symbols = self::symbols();
		$code    = strtoupper( (string) $currency );

		if ( 'none' === $display ) {
			return $number;
		}

		if ( 'code' === $display ) {
			return $number . ' ' . $code;
		}

		if ( isset( $symbols[ $code ] ) ) {
			return $number . ' ' . $symbols[ $code ];
		}

		return $number . ' ' . $code;
	}

	/**
	 * Whether a value is a valid ISO 4217-style currency code.
	 *
	 * @param mixed $currency Candidate.
	 * @return bool
	 */
	public static function is_valid_code( $currency ) {
		return is_string( $currency ) && 1 === preg_match( '/^[A-Z]{3}$/', $currency );
	}
}
