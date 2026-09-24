<?php
/**
 * Contract tests for the canonical example price-list fixtures.
 *
 * @package SidrenaCijena\Tests\Unit\Export
 */

namespace SidrenaCijena\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Import\CsvParser;

/**
 * Verifies tests/fixtures/example-price-list.csv/.xml against the contracts.
 *
 * @coversNothing
 */
class FixturesTest extends TestCase {

	/**
	 * Fixture directory.
	 *
	 * @return string
	 */
	protected function fixtures_dir() {
		return dirname( __DIR__, 2 ) . '/fixtures';
	}

	/**
	 * The example CSV matches the contract byte for byte at the header level.
	 *
	 * @return void
	 */
	public function test_example_csv_matches_contract() {
		$contents = file_get_contents( $this->fixtures_dir() . '/example-price-list.csv' );

		$this->assertIsString( $contents );
		$this->assertStringStartsNotWith( "\xEF\xBB\xBF", $contents, 'CSV must not have a BOM.' );

		$lines = preg_split( '/\r\n|\r|\n/', trim( $contents ) );

		$this->assertSame(
			'vrsta;naziv;sifra;marka;jedinica_mjere;cijena_za_jedinicu_mjere;maloprodajna_cijena;poseban_oblik_prodaje;naziv_posebnog_oblika_prodaje;sidrena_cijena;datum_sidrene_cijene;barkod;dostupnost',
			$lines[0]
		);
		$this->assertCount( 4, $lines, 'Header plus three rows expected.' );
		$this->assertStringNotContainsString( "\r", $contents, 'LF line endings only.' );

		$parsed = ( new CsvParser() )->parse_string( $contents );

		$this->assertNotInstanceOf( \WP_Error::class, $parsed );
		$this->assertSame( ';', $parsed['delimiter'] );
		$this->assertEmpty( $parsed['errors'] );
		$this->assertCount( 3, $parsed['rows'] );

		$first = $parsed['rows'][0];

		$this->assertSame( 'KAV-250', $first['sku'] );
		$this->assertSame( 'Kava mljevena 250 g', $first['name'] );
		$this->assertSame( '7,49', $first['anchor_price'] );

		$service = $parsed['rows'][2];

		$this->assertSame( 'Šišanje & brijanje', $service['name'] );
		$this->assertSame( '39,00', $service['anchor_price'] );
	}

	/**
	 * The example XML is well-formed and carries the contracted structure.
	 *
	 * @return void
	 */
	public function test_example_xml_matches_contract() {
		$contents = file_get_contents( $this->fixtures_dir() . '/example-price-list.xml' );

		$this->assertIsString( $contents );
		$this->assertStringStartsWith( '<?xml version="1.0" encoding="UTF-8"?>', $contents );
		$this->assertStringContainsString( '&amp;', $contents, 'Ampersand must be escaped.' );

		$xml = simplexml_load_string( $contents );

		$this->assertNotFalse( $xml );
		$this->assertSame( 'cjenik', $xml->getName() );
		$this->assertSame( '1.0', (string) $xml['verzija'] );
		$this->assertSame( '2026-10-01', (string) $xml['pravilo'] );
		$this->assertNotEmpty( (string) $xml['generirano'] );
		$this->assertSame( 'P1', (string) $xml->prodajnoMjesto['oznaka'] );

		$items = $xml->prodajnoMjesto->stavka;

		$this->assertCount( 3, $items );
		$this->assertSame( 'proizvod', (string) $items[0]['vrsta'] );
		$this->assertSame( 'KAV-250', (string) $items[0]->sifra );
		$this->assertSame( 'true', (string) $items[0]->posebanOblikProdaje );
		$this->assertSame( '6.99', (string) $items[0]->maloprodajnaCijena );
		$this->assertSame( 'usluga', (string) $items[2]['vrsta'] );
		$this->assertSame( 'Šišanje & brijanje', (string) $items[2]->naziv );
		$this->assertFalse( isset( $items[2]->marka ), 'Empty elements must be omitted.' );
	}
}
