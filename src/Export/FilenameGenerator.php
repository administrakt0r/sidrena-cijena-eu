<?php
/**
 * Price-list filename generation.
 *
 * @package Titanworks\SidrenaCijena\Export
 */

namespace Titanworks\SidrenaCijena\Export;

use Titanworks\SidrenaCijena\Support\Dates;
use Titanworks\SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the legally structured price-list filename:
 * `{FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT}`.
 *
 * Only the separator and the letter case are configurable (FR-040); the
 * component set and order are fixed.
 */
class FilenameGenerator {

	/**
	 * Format extensions.
	 *
	 * @var array<string,string>
	 */
	const EXTENSIONS = array(
		'csv' => 'CSV',
		'xml' => 'XML',
	);

	/**
	 * Facility-form tokens per location type.
	 *
	 * @var array<string,string>
	 */
	const FACILITY_FORMS = array(
		'prodavaonica'   => 'PRODAVAONICA',
		'webshop'        => 'WEBSHOP',
		'poslovnica'     => 'POSLOVNICA',
		'usluzno_mjesto' => 'USLUZNO_MJESTO',
	);

	/**
	 * Diacritic transliteration map.
	 *
	 * @var array<string,string>
	 */
	const TRANSLITERATION = array(
		'Č' => 'C',
		'Ć' => 'C',
		'Đ' => 'D',
		'Š' => 'S',
		'Ž' => 'Z',
		'č' => 'c',
		'ć' => 'c',
		'đ' => 'd',
		'š' => 's',
		'ž' => 'z',
	);

	/**
	 * Generate a filename.
	 *
	 * @param array<mixed>            $location Location row.
	 * @param string                  $format   Format (`csv` or `xml`).
	 * @param int                     $sequence Sequence number for this location.
	 * @param \DateTimeImmutable|null $when     Submission moment; defaults to now.
	 * @return string|\WP_Error
	 */
	public function generate( array $location, $format, $sequence, $when = null ) {
		$format = strtolower( (string) $format );

		if ( ! isset( self::EXTENSIONS[ $format ] ) ) {
			return new \WP_Error( 'invalid_filename', __( 'The file extension must be CSV or XML.', 'sidrena-cijena-eu' ) );
		}

		$form = self::facility_form( isset( $location['type'] ) ? (string) $location['type'] : '' );

		if ( '' === $form ) {
			return new \WP_Error( 'invalid_filename', __( 'The facility form is missing.', 'sidrena-cijena-eu' ) );
		}

		$identifier = self::alphanumeric( isset( $location['identifier'] ) ? (string) $location['identifier'] : '' );

		if ( '' === $identifier ) {
			return new \WP_Error( 'invalid_filename', __( 'The facility identifier is missing.', 'sidrena-cijena-eu' ) );
		}

		$street = isset( $location['address'] ) ? trim( (string) $location['address'] ) : '';
		$city   = isset( $location['city'] ) ? trim( (string) $location['city'] ) : '';

		if ( '' === $street || '' === $city ) {
			return new \WP_Error( 'missing_location_address', __( 'The location street address and city are required in the price-list filename.', 'sidrena-cijena-eu' ) );
		}

		$address = self::address_token(
			$street,
			$city,
			self::separator( $location )
		);

		if ( '' === $address ) {
			return new \WP_Error( 'missing_location_address', __( 'The location street address and city are required in the price-list filename.', 'sidrena-cijena-eu' ) );
		}

		$separator = self::separator( $location );
		$parts     = array( $form );
		$parts[]   = $address;

		$parts[] = $identifier;
		$parts[] = str_pad( (string) max( 1, (int) $sequence ), 2, '0', STR_PAD_LEFT );

		$when = $when instanceof \DateTimeImmutable ? $when : Dates::now_site();

		$parts[] = $when->format( 'dmY' );
		$parts[] = $when->format( 'Hi' );

		$name = implode( $separator, $parts ) . '.' . self::EXTENSIONS[ $format ];

		$name = 'lower' === self::case( $location ) ? strtolower( $name ) : strtoupper( $name );

		if ( ! self::validate( $name ) ) {
			return new \WP_Error( 'invalid_filename', __( 'The generated filename is not valid.', 'sidrena-cijena-eu' ) );
		}

		return $name;
	}

	/**
	 * Validate a generated filename.
	 *
	 * @param string $filename Filename.
	 * @return bool
	 */
	public static function validate( $filename ) {
		$filename = (string) $filename;

		if ( '' === $filename || ! preg_match( '/^[A-Za-z0-9._-]+$/', $filename ) ) {
			return false;
		}

		if ( ! preg_match( '/\.(CSV|XML)$/i', $filename ) ) {
			return false;
		}

		$extension = strtolower( substr( $filename, -4 ) );
		$stem      = substr( $filename, 0, -4 );
		$separator = false !== strpos( $stem, '_' ) ? '_' : '-';

		if ( false !== strpos( $stem, '_' ) && false !== strpos( $stem, '-' ) ) {
			return false;
		}

		$parts = explode( $separator, $stem );
		$count = count( $parts );

		if ( $count < 6 ) {
			return false;
		}

		$date       = $parts[ $count - 2 ];
		$time       = $parts[ $count - 1 ];
		$sequence   = $parts[ $count - 3 ];
		$identifier = $parts[ $count - 4 ];

		if ( ! ctype_alnum( implode( '', $parts ) ) || ! preg_match( '/^[0-9]{8}$/', $date ) || ! preg_match( '/^[0-9]{4}$/', $time ) || ! preg_match( '/^[0-9]{2,}$/', $sequence ) || ! preg_match( '/^[A-Za-z0-9]+$/', $identifier ) ) {
			return false;
		}

		$day    = (int) substr( $date, 0, 2 );
		$month  = (int) substr( $date, 2, 2 );
		$year   = (int) substr( $date, 4, 4 );
		$hour   = (int) substr( $time, 0, 2 );
		$minute = (int) substr( $time, 2, 2 );

		if ( ! checkdate( $month, $day, $year ) || $hour > 23 || $minute > 59 ) {
			return false;
		}

		return true;
	}

	/**
	 * Normalized facility-form token.
	 *
	 * @param string $type Location type.
	 * @return string
	 */
	public static function facility_form( $type ) {
		$type = (string) $type;

		if ( isset( self::FACILITY_FORMS[ $type ] ) ) {
			return self::FACILITY_FORMS[ $type ];
		}

		if ( 0 === strpos( $type, 'other:' ) ) {
			$custom = self::alphanumeric( substr( $type, 6 ) );

			return '' === $custom ? '' : strtoupper( $custom );
		}

		return self::alphanumeric( $type );
	}

	/**
	 * Transliterate a value to ASCII.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function transliterate( $value ) {
		return strtr( (string) $value, self::TRANSLITERATION );
	}

	/**
	 * Reduce a value to ASCII alphanumerics.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function alphanumeric( $value ) {
		return (string) preg_replace( '/[^A-Za-z0-9]+/', '', self::transliterate( $value ) );
	}

	/**
	 * Normalized address component (street+number and city).
	 *
	 * @param string $address   Address.
	 * @param string $city      City.
	 * @param string $separator Component separator.
	 * @return string
	 */
	public static function address_token( $address, $city, $separator = '_' ) {
		$parts = array();

		$street = self::alphanumeric( $address );

		if ( '' !== $street ) {
			$parts[] = $street;
		}

		$town = self::alphanumeric( $city );

		if ( '' !== $town ) {
			$parts[] = $town;
		}

		return implode( $separator, $parts );
	}

	/**
	 * Configured separator, with per-location override.
	 *
	 * @param array<mixed> $location Optional location row.
	 * @return string
	 */
	public static function separator( array $location = array() ) {
		$config    = isset( $location['price_list_config'] ) && is_array( $location['price_list_config'] ) ? $location['price_list_config'] : array();
		$separator = isset( $config['separator'] ) ? (string) $config['separator'] : (string) Settings::get( 'filename_separator', '_' );

		return in_array( $separator, array( '_', '-' ), true ) ? $separator : '_';
	}

	/**
	 * Configured letter case, with per-location override.
	 *
	 * @param array<mixed> $location Optional location row.
	 * @return string
	 */
	public static function case( array $location = array() ) {
		$config = isset( $location['price_list_config'] ) && is_array( $location['price_list_config'] ) ? $location['price_list_config'] : array();
		$case   = isset( $config['case'] ) ? (string) $config['case'] : (string) Settings::get( 'filename_case', 'upper' );

		return in_array( $case, array( 'upper', 'lower' ), true ) ? $case : 'upper';
	}

	/**
	 * Human-readable pattern with the active configuration.
	 *
	 * @return string
	 */
	public static function pattern() {
		$pattern = '{FACILITY_FORM}' . self::separator() . '{ADDRESS}' . self::separator() . '{FACILITY_ID}'
			. self::separator() . '{SEQUENCE}' . self::separator() . '{DDMMYYYY}' . self::separator() . '{HHMM}.{EXT}';

		return 'lower' === self::case() ? strtolower( $pattern ) : $pattern;
	}
}
