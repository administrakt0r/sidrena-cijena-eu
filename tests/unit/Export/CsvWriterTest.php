<?php
/**
 * CSV writer tests.
 *
 * @package SidrenaCijena\Tests\Unit\Export
 */

namespace SidrenaCijena\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Export\CsvWriter;

/**
 * @covers \SidrenaCijena\Export\CsvWriter
 */
class CsvWriterTest extends TestCase {

	/**
	 * Canonical row fixture.
	 *
	 * @param array<mixed> $overrides Overrides.
	 * @return array<mixed>
	 */
	protected function item( array $overrides = array() ) {
		return array_merge(
			array(
				'vrsta'                         => 'proizvod',
				'naziv'                         => 'Kava mljevena 250 g',
				'sifra'                         => 'KAV-250',
				'marka'                         => 'Franck',
				'jedinica_mjere'                => 'kg',
				'cijena_za_jedinicu_mjere'      => 28.0,
				'maloprodajna_cijena'           => 6.99,
				'poseban_oblik_prodaje'         => true,
				'naziv_posebnog_oblika_prodaje' => 'akcija',
				'sidrena_cijena'                => 7.49,
				'anchor_reference_date'         => '2026-09-10',
				'barkod'                        => '3851234567890',
				'dostupnost'                    => 'dostupno',
			),
			$overrides
		);
	}

	/**
	 * The exact documented header and row format.
	 *
	 * @return void
	 */
	public function test_document_matches_contract_example() {
		$writer = new CsvWriter();

		$csv = $writer->to_string( array( $this->item() ) );

		$expected = "vrsta;naziv;sifra;marka;jedinica_mjere;cijena_za_jedinicu_mjere;maloprodajna_cijena;poseban_oblik_prodaje;naziv_posebnog_oblika_prodaje;sidrena_cijena;datum_sidrene_cijene;barkod;dostupnost\n"
			. "proizvod;Kava mljevena 250 g;KAV-250;Franck;kg;28,00;6,99;DA;akcija;7,49;2026-09-10;3851234567890;dostupno\n";

		$this->assertSame( $expected, $csv );
		$this->assertSame( 'v', substr( $csv, 0, 1 ), 'The document must not contain a BOM.' );
	}

	/**
	 * Fields containing the delimiter, quotes or newlines are RFC 4180 quoted.
	 *
	 * @return void
	 */
	public function test_quotes_special_characters() {
		$writer = new CsvWriter();

		$csv = $writer->to_string(
			array(
				$this->item(
					array(
						'naziv' => 'Kava "premium"; 250 g',
					)
				),
			)
		);

		$this->assertStringContainsString( '"Kava ""premium""; 250 g"', $csv );
	}

	/**
	 * Empty optional values stay empty and booleans use DA/NE.
	 *
	 * @return void
	 */
	public function test_empty_values_and_booleans() {
		$writer = new CsvWriter();

		$row = $writer->fields(
			$this->item(
				array(
					'poseban_oblik_prodaje'         => false,
					'naziv_posebnog_oblika_prodaje' => '',
					'sidrena_cijena'                => null,
					'cijena_za_jedinicu_mjere'      => null,
					'barkod'                        => '',
					'marka'                         => '',
				)
			)
		);

		$this->assertSame( 'NE', $row[7] );
		$this->assertSame( '', $row[9] );
		$this->assertSame( '', $row[10] );
		$this->assertSame( '', $row[5] );
		$this->assertSame( '', $row[3] );
	}

	/**
	 * Decimal formatting uses comma and two places, half-up.
	 *
	 * @return void
	 */
	public function test_decimal_formatting() {
		$this->assertSame( '1234,50', CsvWriter::decimal( 1234.5 ) );
		$this->assertSame( '0,00', CsvWriter::decimal( 0 ) );
		$this->assertSame( '6,99', CsvWriter::decimal( 6.99 ) );
		$this->assertSame( '7,49', CsvWriter::decimal( 7.485 ) );
		$this->assertSame( '', CsvWriter::decimal_or_empty( null ) );
	}

	/**
	 * Output is byte-identical for identical input.
	 *
	 * @return void
	 */
	public function test_determinism() {
		$writer = new CsvWriter();

		$items = array(
			$this->item(),
			$this->item(
				array(
					'vrsta' => 'usluga',
					'naziv' => 'Šišanje',
				)
			),
		);

		$this->assertSame( $writer->to_string( $items ), $writer->to_string( $items ) );
	}

	/**
	 * Values a spreadsheet would evaluate as a formula are neutralised.
	 *
	 * @return void
	 */
	public function test_formula_injection_is_neutralised() {
		$writer = new CsvWriter();

		$this->assertSame( '"\'=HYPERLINK(""http://evil"",""Kava"")"', $writer->encode( '=HYPERLINK("http://evil","Kava")' ) );
		$this->assertSame( "'+1234", $writer->encode( '+1234' ) );
		$this->assertSame( "'-50% popust", $writer->encode( '-50% popust' ) );
		$this->assertSame( "'@SUM(A1)", $writer->encode( '@SUM(A1)' ) );
		$this->assertSame( "'\t=1+1", $writer->encode( "\t=1+1" ) );
		$this->assertSame( 'Cafe 100% arabica', $writer->encode( 'Cafe 100% arabica' ) );
		$this->assertSame( '6,99', $writer->encode( '6,99' ) );
	}

	/**
	 * A hostile product name is neutralised in the rendered document.
	 *
	 * @return void
	 */
	public function test_formula_injection_in_row() {
		$writer = new CsvWriter();
		$line   = $writer->to_string( array( $this->item( array( 'naziv' => '=cmd|calc' ) ) ) );

		$this->assertStringContainsString( "'=cmd|calc", $line );
		$this->assertStringNotContainsString( ';=', $line );
	}
}
