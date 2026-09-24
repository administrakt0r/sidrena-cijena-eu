<?php
/**
 * Deterministic price-list CSV writer.
 *
 * @package SidrenaCijena\Export
 */

namespace SidrenaCijena\Export;

use SidrenaCijena\Support\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Writes the documented CSV format (`contracts/csv-format.md`): UTF-8 without
 * BOM, `;` delimiter, comma decimals, RFC 4180 quoting, LF line endings.
 */
class CsvWriter {

	/**
	 * Field delimiter.
	 *
	 * @var string
	 */
	const DELIMITER = ';';

	/**
	 * Stable column order.
	 *
	 * @var string[]
	 */
	const COLUMNS = array(
		'vrsta',
		'naziv',
		'sifra',
		'marka',
		'jedinica_mjere',
		'cijena_za_jedinicu_mjere',
		'maloprodajna_cijena',
		'poseban_oblik_prodaje',
		'naziv_posebnog_oblika_prodaje',
		'sidrena_cijena',
		'datum_sidrene_cijene',
		'barkod',
		'dostupnost',
	);

	/**
	 * Render the whole document as a string.
	 *
	 * @param array<mixed>[] $items Canonical rows.
	 * @return string
	 */
	public function to_string( array $items ) {
		$lines = array( $this->line( self::COLUMNS ) );

		foreach ( $items as $item ) {
			$lines[] = $this->line( $this->fields( $item ) );
		}

		return implode( '', $lines );
	}

	/**
	 * Write the document to an open stream.
	 *
	 * @param resource       $handle Open stream.
	 * @param array<mixed>[] $items  Canonical rows.
	 * @return int Number of rows written (excluding the header).
	 */
	public function write( $handle, array $items ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming the generated price list.
		fwrite( $handle, $this->line( self::COLUMNS ) );

		$count = 0;

		foreach ( $items as $item ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming the generated price list.
			fwrite( $handle, $this->line( $this->fields( $item ) ) );
			++$count;
		}

		return $count;
	}

	/**
	 * Encode one CSV line.
	 *
	 * @param array<mixed> $fields Fields.
	 * @return string
	 */
	protected function line( array $fields ) {
		return implode( self::DELIMITER, array_map( array( $this, 'encode' ), $fields ) ) . "\n";
	}

	/**
	 * RFC 4180-style quoting: only delimiter, quote and newline force quotes.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public function encode( $value ) {
		$value = (string) $value;

		if ( '' !== $value && preg_match( '/[";' . "\r\n" . ']/', $value ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}

	/**
	 * Convert a canonical row to ordered CSV fields.
	 *
	 * @param array<mixed> $item Canonical row.
	 * @return array<mixed>
	 */
	public function fields( array $item ) {
		return array(
			isset( $item['vrsta'] ) ? (string) $item['vrsta'] : '',
			isset( $item['naziv'] ) ? (string) $item['naziv'] : '',
			isset( $item['sifra'] ) ? (string) $item['sifra'] : '',
			isset( $item['marka'] ) ? (string) $item['marka'] : '',
			isset( $item['jedinica_mjere'] ) ? (string) $item['jedinica_mjere'] : '',
			self::decimal_or_empty( isset( $item['cijena_za_jedinicu_mjere'] ) ? $item['cijena_za_jedinicu_mjere'] : null ),
			self::decimal( isset( $item['maloprodajna_cijena'] ) ? $item['maloprodajna_cijena'] : 0 ),
			! empty( $item['poseban_oblik_prodaje'] ) ? 'DA' : 'NE',
			isset( $item['naziv_posebnog_oblika_prodaje'] ) ? (string) $item['naziv_posebnog_oblika_prodaje'] : '',
			self::decimal_or_empty( isset( $item['sidrena_cijena'] ) ? $item['sidrena_cijena'] : null ),
			isset( $item['sidrena_cijena'], $item['anchor_reference_date'] ) ? (string) $item['anchor_reference_date'] : '',
			isset( $item['barkod'] ) ? (string) $item['barkod'] : '',
			isset( $item['dostupnost'] ) ? (string) $item['dostupnost'] : '',
		);
	}

	/**
	 * Format a decimal with comma separator.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function decimal( $value ) {
		return Currency::format_decimal( (float) $value );
	}

	/**
	 * Format an optional decimal; empty string when absent.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function decimal_or_empty( $value ) {
		if ( null === $value || '' === $value ) {
			return '';
		}

		return self::decimal( $value );
	}
}
