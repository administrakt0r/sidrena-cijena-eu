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

	/**
	 * A quoted field containing a line break stays one record.
	 *
	 * @return void
	 */
	public function test_quoted_multiline_field_is_one_record() {
		$csv   = 'sku;naziv;anchor_price' . "\n" . 'KAV;"Čokolada' . "\n" . '65% kakaa";7,49' . "\n" . 'KAV2;Kava;2,00' . "\n";
		$parse = ( new CsvParser() )->parse_string( $csv );

		$this->assertIsArray( $parse );
		$this->assertCount( 2, $parse['rows'] );
		$this->assertSame( "Čokolada\n65% kakaa", $parse['rows'][0]['name'] );
		$this->assertSame( 'Kava', $parse['rows'][1]['name'] );
	}

	/**
	 * Doubled quotes inside a quoted field survive the record splitter.
	 *
	 * @return void
	 */
	public function test_doubled_quotes_are_preserved() {
		$csv   = 'sku;naziv;anchor_price' . "\n" . 'KAV;"Kava ""Premium""";5,00' . "\n";
		$parse = ( new CsvParser() )->parse_string( $csv );

		$this->assertSame( 'Kava "Premium"', $parse['rows'][0]['name'] );
	}

	/**
	 * The date column name used by the plugin's own price list is accepted, so
	 * re-importing an exported list keeps the reference date.
	 *
	 * @return void
	 */
	public function test_export_date_column_alias_is_accepted() {
		$csv   = 'sku;naziv;maloprodajna_cijena;sidrena_cijena;datum_sidrene_cijene' . "\n" . 'KAV;Kava;7,49;6,99;2026-09-10' . "\n";
		$parse = ( new CsvParser() )->parse_string( $csv );

		$this->assertSame( '6,99', $parse['rows'][0]['anchor_price'] );
		$this->assertSame( '2026-09-10', $parse['rows'][0]['reference_date'] );
	}

	/**
	 * Empty path is rejected.
	 *
	 * @return void
	 */
	public function test_parse_file_rejects_empty_path() {
		$parser = new CsvParser();
		$result = $parser->parse_file( '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'import_file_not_allowed', $result->get_error_code() );
	}

	/**
	 * Path traversal is rejected.
	 *
	 * @return void
	 */
	public function test_parse_file_rejects_path_traversal() {
		$parser = new CsvParser();
		$result = $parser->parse_file( '../some/relative/file.csv' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'import_file_not_allowed', $result->get_error_code() );
	}

	/**
	 * Non-existent file is rejected.
	 *
	 * @return void
	 */
	public function test_parse_file_rejects_nonexistent_file() {
		$parser = new CsvParser();
		$result = $parser->parse_file( sys_get_temp_dir() . '/sidrena_nonexistent_xyz123.csv' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'import_file_not_allowed', $result->get_error_code() );
	}

	/**
	 * Files outside allowed roots are rejected.
	 *
	 * @return void
	 */
	public function test_parse_file_rejects_file_outside_allowed_roots() {
		$parser = new CsvParser();
		$result = $parser->parse_file( __FILE__ );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'import_file_not_allowed', $result->get_error_code() );
	}

	/**
	 * Paths containing directory traversal inside temp directory are rejected.
	 *
	 * @return void
	 */
	public function test_parse_file_rejects_temp_dir_path_traversal() {
		$parser = new CsvParser();
		$result = $parser->parse_file( sys_get_temp_dir() . '/../something.csv' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'import_file_not_allowed', $result->get_error_code() );
	}


	/**
	 * File inside allowed root parses successfully.
	 *
	 * @return void
	 */
	public function test_parse_file_accepts_file_in_allowed_root() {
		$tmp = tempnam( sys_get_temp_dir(), 'sidrena_test_' );
		$this->assertNotFalse( $tmp );

		file_put_contents( $tmp, "sku;naziv;anchor_price\nKAV;Kava;7,49\n" );

		$parser = new CsvParser();
		$result = $parser->parse_file( $tmp );

		unlink( $tmp );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['rows'] );
		$this->assertSame( 'KAV', $result['rows'][0]['sku'] );
		$this->assertSame( 'Kava', $result['rows'][0]['name'] );
		$this->assertSame( '7,49', $result['rows'][0]['anchor_price'] );
	}
}
