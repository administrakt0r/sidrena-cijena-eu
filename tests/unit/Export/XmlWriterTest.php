<?php
/**
 * XML writer tests.
 *
 * @package SidrenaCijena\Tests\Unit\Export
 */

namespace SidrenaCijena\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Export\XmlWriter;

/**
 * @covers \SidrenaCijena\Export\XmlWriter
 */
class XmlWriterTest extends TestCase {

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
	 * Location fixture.
	 *
	 * @return array<mixed>
	 */
	protected function location() {
		return array(
			'id'         => 1,
			'name'       => 'Prodavaonica Vlaška',
			'type'       => 'prodavaonica',
			'address'    => 'Vlaška 68',
			'city'       => 'Zagreb',
			'identifier' => 'P1',
		);
	}

	/**
	 * Meta fixture.
	 *
	 * @return array<mixed>
	 */
	protected function meta() {
		return array(
			'rule_version' => '2026-10-01',
			'generated_at' => '2026-10-01T07:50:00+02:00',
		);
	}

	/**
	 * The document parses and carries the documented attributes.
	 *
	 * @return void
	 */
	public function test_document_structure() {
		$writer = new XmlWriter();

		$xml = $writer->to_string( array( $this->item() ), $this->location(), $this->meta() );

		$this->assertStringStartsWith( '<?xml version="1.0" encoding="UTF-8"?>', $xml );

		$document = simplexml_load_string( $xml );

		$this->assertNotFalse( $document );
		$this->assertSame( '1.0', (string) $document['verzija'] );
		$this->assertSame( '2026-10-01', (string) $document['pravilo'] );
		$this->assertSame( '2026-10-01T07:50:00+02:00', (string) $document['generirano'] );

		$place = $document->prodajnoMjesto;
		$this->assertSame( 'P1', (string) $place['oznaka'] );
		$this->assertSame( 'Prodavaonica Vlaška', (string) $place['naziv'] );

		$item = $place->stavka;
		$this->assertSame( 'proizvod', (string) $item['vrsta'] );
		$this->assertSame( 'Kava mljevena 250 g', (string) $item->naziv );
		$this->assertSame( '6.99', (string) $item->maloprodajnaCijena );
		$this->assertSame( 'true', (string) $item->posebanOblikProdaje );
		$this->assertSame( '7.49', (string) $item->sidrenaCijena );
		$this->assertSame( '2026-09-10', (string) $item->datumSidreneCijene );
		$this->assertSame( '28.00', (string) $item->cijenaZaJedinicuMjere );
	}

	/**
	 * Values are escaped.
	 *
	 * @return void
	 */
	public function test_escaping() {
		$writer = new XmlWriter();

		$xml = $writer->to_string(
			array(
				$this->item(
					array(
						'naziv' => 'Kava & "mljevena" <250 g>',
					)
				),
			),
			$this->location(),
			$this->meta()
		);

		$this->assertStringContainsString( 'Kava &amp; &quot;mljevena&quot; &lt;250 g&gt;', $xml );

		$document = simplexml_load_string( $xml );
		$this->assertSame( 'Kava & "mljevena" <250 g>', (string) $document->prodajnoMjesto->stavka->naziv );
	}

	/**
	 * Empty elements are omitted.
	 *
	 * @return void
	 */
	public function test_empty_elements_omitted() {
		$writer = new XmlWriter();

		$xml = $writer->to_string(
			array(
				$this->item(
					array(
						'marka'                         => '',
						'barkod'                        => '',
						'sidrena_cijena'                => null,
						'cijena_za_jedinicu_mjere'      => null,
						'naziv_posebnog_oblika_prodaje' => '',
					)
				),
			),
			$this->location(),
			$this->meta()
		);

		$this->assertStringNotContainsString( '<marka>', $xml );
		$this->assertStringNotContainsString( '<barkod>', $xml );
		$this->assertStringNotContainsString( '<sidrenaCijena>', $xml );
		$this->assertStringNotContainsString( '<datumSidreneCijene>', $xml );
		$this->assertStringNotContainsString( '<nazivPosebnogOblikaProdaje>', $xml );
	}

	/**
	 * Output is byte-identical for identical input and meta.
	 *
	 * @return void
	 */
	public function test_determinism() {
		$writer = new XmlWriter();

		$items = array( $this->item(), $this->item( array( 'vrsta' => 'usluga' ) ) );

		$this->assertSame(
			$writer->to_string( $items, $this->location(), $this->meta() ),
			$writer->to_string( $items, $this->location(), $this->meta() )
		);
	}

	/**
	 * Streaming XML output produces byte-identical results to to_string and computes correct hash.
	 *
	 * @return void
	 */
	public function test_write_stream_byte_identical_to_to_string_and_hashes_incrementally() {
		$writer = new XmlWriter();
		$items  = array( $this->item(), $this->item( array( 'vrsta' => 'usluga' ) ) );

		$expected_string = $writer->to_string( $items, $this->location(), $this->meta() );
		$expected_hash   = hash( 'sha256', $expected_string );

		$stream   = fopen( 'php://memory', 'w+b' );
		$hash_ctx = hash_init( 'sha256' );
		$bytes    = $writer->write_stream( $stream, $items, $this->location(), $this->meta(), $hash_ctx );
		$actual_hash = hash_final( $hash_ctx );

		rewind( $stream );
		$streamed_content = stream_get_contents( $stream );
		fclose( $stream );

		$this->assertSame( $expected_string, $streamed_content );
		$this->assertSame( strlen( $expected_string ), $bytes );
		$this->assertSame( $expected_hash, $actual_hash );
	}
}
