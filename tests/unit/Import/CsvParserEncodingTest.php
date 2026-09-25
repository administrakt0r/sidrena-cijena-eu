<?php
/**
 * CSV parser encoding tests.
 *
 * @package SidrenaCijena\Tests\Unit\Import
 */

namespace SidrenaCijena\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Import\CsvParser;

/**
 * @covers \SidrenaCijena\Import\CsvParser
 */
class CsvParserEncodingTest extends TestCase {

	/**
	 * Valid UTF-8 is returned untouched.
	 *
	 * @return void
	 */
	public function test_utf8_is_untouched() {
		$csv = "sku;naziv;anchor_price;reference_date\nKAV;Čokolada 65%;7,49;2026-09-10\n";

		$this->assertSame( $csv, CsvParser::to_utf8( $csv ) );
	}

	/**
	 * ISO-8859-2 input is converted (č, š, ž live in the 0xE0/0xF8/0xFE range).
	 *
	 * @return void
	 */
	public function test_iso_8859_2_is_converted() {
		$latin2 = "sku;naziv\nKAV;\xE8e\xB9e\xBE \xE8okolada\n";
		$result = CsvParser::to_utf8( $latin2 );

		$this->assertSame( "sku;naziv\nKAV;\u{010D}e\u{0161}e\u{017E} \u{010D}okolada\n", $result );
		$this->assertTrue( (bool) preg_match( '//u', $result ) );
	}

	/**
	 * Windows-1250 input is converted (0x9A/0x9E are letters only in CP1250).
	 *
	 * @return void
	 */
	public function test_cp1250_is_converted() {
		$cp1250 = "sku;naziv\nKAV;\x9Aaj\x9Eam kava\n";
		$result = CsvParser::to_utf8( $cp1250 );

		$this->assertSame( "sku;naziv\nKAV;\u{0161}aj\u{017E}am kava\n", $result );
		$this->assertTrue( (bool) preg_match( '//u', $result ) );
	}

	/**
	 * An empty file is returned as-is.
	 *
	 * @return void
	 */
	public function test_empty_is_untouched() {
		$this->assertSame( '', CsvParser::to_utf8( '' ) );
	}

	/**
	 * Converted content parses: a legacy file gives the same rows as UTF-8.
	 *
	 * @return void
	 */
	public function test_legacy_file_parses_to_same_rows() {
		$utf8   = "sku;naziv;anchor_price;reference_date\nKAV;Čokolada;7,49;2026-09-10\n";
		$latin2 = "sku;naziv;anchor_price;reference_date\nKAV;\xC8okolada;7,49;2026-09-10\n";
		$parser = new CsvParser();

		$utf8_rows   = $parser->parse_string( $utf8 );
		$legacy_rows = $parser->parse_string( CsvParser::to_utf8( $latin2 ) );

		$this->assertIsArray( $utf8_rows );
		$this->assertSame( $utf8_rows['rows'], $legacy_rows['rows'] );
	}
}
