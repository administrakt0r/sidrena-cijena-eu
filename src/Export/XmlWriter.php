<?php
/**
 * Deterministic price-list XML writer.
 *
 * @package SidrenaCijena\Export
 */

namespace SidrenaCijena\Export;

use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Writes the documented XML format (`contracts/xml-format.md`): UTF-8 without
 * BOM, versioned root element, escaped values, omitted empty elements.
 */
class XmlWriter {

	/**
	 * Format version written to the root element.
	 *
	 * @var string
	 */
	const FORMAT_VERSION = '1.0';

	/**
	 * Render the document as a string.
	 *
	 * @param array<mixed>[] $items    Canonical rows.
	 * @param array<mixed>   $location Location row.
	 * @param array<mixed>   $meta     Meta (rule version, generation time).
	 * @return string
	 */
	public function to_string( array $items, array $location, array $meta = array() ) {
		$writer = new \XMLWriter();
		$writer->openMemory();
		$this->fill( $writer, $items, $location, $meta );

		return (string) $writer->outputMemory();
	}

	/**
	 * Write the document to a file.
	 *
	 * @param string         $path     Target path.
	 * @param array<mixed>[] $items    Canonical rows.
	 * @param array<mixed>   $location Location row.
	 * @param array<mixed>   $meta     Meta.
	 * @return bool
	 */
	public function write( $path, array $items, array $location, array $meta = array() ) {
		$writer = new \XMLWriter();

		if ( ! $writer->openURI( (string) $path ) ) {
			return false;
		}

		$this->fill( $writer, $items, $location, $meta );

		return (bool) $writer->flush();
	}

	/**
	 * Fill an XMLWriter with the document.
	 *
	 * @param \XMLWriter     $writer   Writer.
	 * @param array<mixed>[] $items    Canonical rows.
	 * @param array<mixed>   $location Location row.
	 * @param array<mixed>   $meta     Meta.
	 * @return void
	 */
	protected function fill( \XMLWriter $writer, array $items, array $location, array $meta ) {
		$writer->setIndent( true );
		$writer->setIndentString( '  ' );
		$writer->startDocument( '1.0', 'UTF-8' );

		$writer->startElement( 'cjenik' );
		$writer->writeAttribute( 'verzija', self::FORMAT_VERSION );
		$writer->writeAttribute( 'pravilo', isset( $meta['rule_version'] ) ? (string) $meta['rule_version'] : '' );
		$writer->writeAttribute(
			'generirano',
			isset( $meta['generated_at'] ) ? (string) $meta['generated_at'] : Dates::now_site()->format( 'c' )
		);

		$writer->startElement( 'prodajnoMjesto' );
		$writer->writeAttribute( 'oznaka', isset( $location['identifier'] ) ? (string) $location['identifier'] : '' );

		foreach ( array(
			'naziv'  => 'name',
			'oblik'  => 'type',
			'adresa' => 'address',
			'grad'   => 'city',
		) as $element => $key ) {
			if ( ! empty( $location[ $key ] ) ) {
				$writer->writeAttribute( $element, (string) $location[ $key ] );
			}
		}

		foreach ( $items as $item ) {
			$this->write_item( $writer, $item );
		}

		$writer->endElement();
		$writer->endElement();
		$writer->endDocument();
	}

	/**
	 * Write a single `stavka` element.
	 *
	 * @param \XMLWriter   $writer Writer.
	 * @param array<mixed> $item   Canonical row.
	 * @return void
	 */
	protected function write_item( \XMLWriter $writer, array $item ) {
		$writer->startElement( 'stavka' );
		$writer->writeAttribute( 'vrsta', isset( $item['vrsta'] ) ? (string) $item['vrsta'] : 'proizvod' );

		$this->element( $writer, 'naziv', isset( $item['naziv'] ) ? $item['naziv'] : '' );
		$this->element( $writer, 'sifra', isset( $item['sifra'] ) ? $item['sifra'] : '' );
		$this->element( $writer, 'marka', isset( $item['marka'] ) ? $item['marka'] : '' );
		$this->element( $writer, 'jedinicaMjere', isset( $item['jedinica_mjere'] ) ? $item['jedinica_mjere'] : '' );
		$this->element(
			$writer,
			'cijenaZaJedinicuMjere',
			isset( $item['cijena_za_jedinicu_mjere'] ) ? Currency::format_decimal_xml( $item['cijena_za_jedinicu_mjere'] ) : ''
		);
		$this->element( $writer, 'maloprodajnaCijena', Currency::format_decimal_xml( isset( $item['maloprodajna_cijena'] ) ? $item['maloprodajna_cijena'] : 0 ) );
		$writer->writeElement( 'posebanOblikProdaje', ! empty( $item['poseban_oblik_prodaje'] ) ? 'true' : 'false' );
		$this->element( $writer, 'nazivPosebnogOblikaProdaje', isset( $item['naziv_posebnog_oblika_prodaje'] ) ? $item['naziv_posebnog_oblika_prodaje'] : '' );
		$this->element(
			$writer,
			'sidrenaCijena',
			isset( $item['sidrena_cijena'] ) ? Currency::format_decimal_xml( $item['sidrena_cijena'] ) : ''
		);
		$this->element(
			$writer,
			'datumSidreneCijene',
			isset( $item['sidrena_cijena'], $item['anchor_reference_date'] ) ? $item['anchor_reference_date'] : ''
		);
		$this->element( $writer, 'barkod', isset( $item['barkod'] ) ? $item['barkod'] : '' );
		$this->element( $writer, 'dostupnost', isset( $item['dostupnost'] ) ? $item['dostupnost'] : '' );

		$writer->endElement();
	}

	/**
	 * Write an element unless the value is empty.
	 *
	 * @param \XMLWriter $writer Writer.
	 * @param string     $name   Element name.
	 * @param mixed      $value  Value.
	 * @return void
	 */
	protected function element( \XMLWriter $writer, $name, $value ) {
		if ( null === $value || '' === $value ) {
			return;
		}

		$writer->writeElement( $name, (string) $value );
	}
}
