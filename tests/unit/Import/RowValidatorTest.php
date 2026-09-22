<?php
/**
 * Unit tests for import row validation.
 *
 * @package SidrenaCijena\Tests\Unit\Import
 */

namespace SidrenaCijena\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Import\CsvParser;
use SidrenaCijena\Import\RowValidator;

/**
 * In-memory catalog provider.
 */
class FakeCatalogProvider implements CatalogProvider {

	/**
	 * Items keyed by `id:variation_id`.
	 *
	 * @var array<string,CatalogItem>
	 */
	public $items = array();

	/**
	 * Register an item.
	 *
	 * @param CatalogItem $item Item.
	 * @return void
	 */
	public function add( CatalogItem $item ) {
		$this->items[ $item->key() ] = $item;
	}

	/**
	 * Source identifier.
	 *
	 * @return string
	 */
	public function get_source() {
		return 'fake';
	}

	/**
	 * Availability.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Get an item.
	 *
	 * @param int $id Item ID.
	 * @return CatalogItem|null
	 */
	public function get_item( $id ) {
		return isset( $this->items[ $id . ':0' ] ) ? $this->items[ $id . ':0' ] : null;
	}

	/**
	 * Get a variation.
	 *
	 * @param int $parent_id    Parent ID.
	 * @param int $variation_id Variation ID.
	 * @return CatalogItem|null
	 */
	public function get_variation( $parent_id, $variation_id ) {
		return isset( $this->items[ $parent_id . ':' . $variation_id ] ) ? $this->items[ $parent_id . ':' . $variation_id ] : null;
	}

	/**
	 * Get items.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return CatalogItem[]
	 */
	public function get_items( array $args = array() ) {
		unset( $args );

		return array_values( $this->items );
	}

	/**
	 * Count items.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return int
	 */
	public function count_items( array $args = array() ) {
		unset( $args );

		return count( $this->items );
	}

	/**
	 * Iterate items.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return \Generator<int,CatalogItem>
	 */
	public function iterate( array $args = array() ) {
		unset( $args );

		foreach ( array_values( $this->items ) as $index => $item ) {
			yield $index => $item;
		}
	}

	/**
	 * Categories.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_categories() {
		return array();
	}

	/**
	 * Item categories.
	 *
	 * @param int $id Item ID.
	 * @return string[]
	 */
	public function get_item_categories( $id ) {
		unset( $id );

		return array();
	}
}

/**
 * History repository with in-memory rows.
 */
class FakeImportHistory extends PriceHistoryRepository {

	/**
	 * Stored rows keyed by `product:variation:type`.
	 *
	 * @var array<string,array<mixed>>
	 */
	public $rows = array();

	/**
	 * Latest record for an item and type.
	 *
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $price_type   Price type.
	 * @return array<mixed>|null
	 */
	public function latest( $product_id, $variation_id, $price_type ) {
		$key = (int) $product_id . ':' . (int) $variation_id . ':' . $price_type;

		return isset( $this->rows[ $key ] ) ? $this->rows[ $key ] : null;
	}
}

/**
 * Covers row classification: valid, invalid, duplicate, overwrite and unchanged.
 */
class RowValidatorTest extends TestCase {

	/**
	 * Catalog provider.
	 *
	 * @var FakeCatalogProvider
	 */
	protected $catalog;

	/**
	 * History repository.
	 *
	 * @var FakeImportHistory
	 */
	protected $history;

	/**
	 * Validator under test.
	 *
	 * @var RowValidator
	 */
	protected $validator;

	/**
	 * Set up fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->catalog = new FakeCatalogProvider();
		$this->history = new FakeImportHistory();

		$this->catalog->add( $this->item( 1, 'KAV-250', 'Kava mljevena 250 g', array( 'pića' ) ) );
		$this->catalog->add( $this->item( 2, 'CAJ-100', 'Čaj 100 g' ) );
		$this->catalog->add( $this->item( 3, 'GEL-200', 'Gel 200 ml', array(), 10 ) );

		$this->validator = new RowValidator( $this->catalog, $this->history );
	}

	/**
	 * Build a catalog item.
	 *
	 * @param int      $id         Item ID.
	 * @param string   $sku        SKU.
	 * @param string   $name       Name.
	 * @param string[] $categories Categories.
	 * @param int      $parent_id  Parent ID for variations.
	 * @return CatalogItem
	 */
	protected function item( $id, $sku, $name, array $categories = array(), $parent_id = 0 ) {
		$variation = $parent_id > 0 ? $id : 0;

		return new CatalogItem(
			array(
				'id'            => $parent_id > 0 ? $parent_id : $id,
				'variation_id'  => $variation,
				'type'          => 'product',
				'name'          => $name,
				'sku'           => $sku,
				'regular_price' => 9.99,
				'current_price' => 9.99,
				'categories'    => $categories,
			)
		);
	}

	/**
	 * Parse a CSV string and validate it.
	 *
	 * @param string $csv CSV contents.
	 * @return array<mixed>
	 */
	protected function validate( $csv ) {
		$parser = new CsvParser();
		$parsed = $parser->parse_string( $csv );

		$this->assertIsArray( $parsed );

		return $this->validator->validate_rows( $parsed['rows'] );
	}

	/**
	 * Valid rows resolve their item and normalize values.
	 *
	 * @return void
	 */
	public function test_valid_rows_resolve_and_normalize() {
		$result = $this->validate( "sku;reference_date;anchor_price;category\nKAV-250;2026-09-10;7,49;pića\n" );

		$this->assertSame( 1, $result['summary']['valid'] );
		$this->assertSame( 1, $result['rows'][0]['item']->id );
		$this->assertSame( 7.49, $result['rows'][0]['values']['price'] );
		$this->assertSame( '2026-09-10', $result['rows'][0]['values']['reference_date'] );
	}

	/**
	 * Croatian dates are normalized to Y-m-d.
	 *
	 * @return void
	 */
	public function test_croatian_dates_are_normalized() {
		$result = $this->validate( "sku;reference_date;anchor_price\nCAJ-100;10.09.2026.;5,00\n" );

		$this->assertSame( '2026-09-10', $result['rows'][0]['values']['reference_date'] );
		$this->assertSame( 'valid', $result['rows'][0]['status'] );
	}

	/**
	 * Unknown products are invalid.
	 *
	 * @return void
	 */
	public function test_unknown_product_is_invalid() {
		$result = $this->validate( "sku;reference_date;anchor_price\nNEPOZNAT-1;2026-09-10;9,99\n" );

		$this->assertSame( 'invalid', $result['rows'][0]['status'] );
		$this->assertSame( 'unknown_product', $result['rows'][0]['errors'][0]['code'] );
	}

	/**
	 * Missing identifiers, dates and prices are invalid.
	 *
	 * @return void
	 */
	public function test_missing_fields_are_invalid() {
		$result = $this->validate( "sku;reference_date;anchor_price\n;;\n" );

		$codes = array();

		foreach ( $result['rows'][0]['errors'] as $error ) {
			$codes[] = $error['code'];
		}

		$this->assertContains( 'missing_identifier', $codes );
		$this->assertContains( 'invalid_reference_date', $codes );
		$this->assertContains( 'invalid_anchor_price', $codes );
	}

	/**
	 * Duplicate rows inside the file are flagged.
	 *
	 * @return void
	 */
	public function test_duplicate_rows_are_detected() {
		$result = $this->validate(
			"sku;reference_date;anchor_price\nKAV-250;2026-09-10;7,49\nKAV-250;2026-09-10;7,49\n"
		);

		$this->assertSame( 'valid', $result['rows'][0]['status'] );
		$this->assertSame( 'invalid', $result['rows'][1]['status'] );
		$this->assertSame( 'duplicate_row', $result['rows'][1]['errors'][0]['code'] );
	}

	/**
	 * Rows that would replace an existing anchor are classified as overwrite.
	 *
	 * @return void
	 */
	public function test_overwrite_is_detected() {
		$this->history->rows['1:0:anchor'] = array(
			'id'             => 4,
			'price'          => '5.00',
			'reference_date' => '2026-09-10',
		);

		$result = $this->validate( "sku;reference_date;anchor_price\nKAV-250;2026-09-10;7,49\n" );

		$this->assertSame( 'overwrite', $result['rows'][0]['status'] );
		$this->assertSame( 'would_overwrite', $result['rows'][0]['warnings'][0]['code'] );
		$this->assertSame( 4, $result['rows'][0]['existing']['id'] );
	}

	/**
	 * Identical existing records are skipped instead of overwritten.
	 *
	 * @return void
	 */
	public function test_identical_record_is_unchanged() {
		$this->history->rows['1:0:anchor'] = array(
			'id'             => 5,
			'price'          => '7.49',
			'reference_date' => '2026-09-10',
		);

		$result = $this->validate( "sku;reference_date;anchor_price\nKAV-250;2026-09-10;7,49\n" );

		$this->assertSame( 'unchanged', $result['rows'][0]['status'] );
		$this->assertSame( 'already_recorded', $result['rows'][0]['warnings'][0]['code'] );
	}

	/**
	 * Variations resolve through parent plus variation ID.
	 *
	 * @return void
	 */
	public function test_variations_resolve_by_id() {
		$this->catalog->add( $this->item( 30, 'GEL-200-V2', 'Gel 200 ml - veliko', array(), 3 ) );

		$result = $this->validate( "product_id;variation_id;reference_date;anchor_price\n3;30;2026-09-10;12,00\n" );

		$this->assertSame( 'valid', $result['rows'][0]['status'] );
		$this->assertSame( 30, $result['rows'][0]['item']->variation_id );
	}

	/**
	 * Category mismatches are warnings, not errors.
	 *
	 * @return void
	 */
	public function test_category_mismatch_is_warning() {
		$result = $this->validate( "sku;reference_date;anchor_price;category\nKAV-250;2026-09-10;7,49;nepoznata\n" );

		$this->assertSame( 'valid', $result['rows'][0]['status'] );
		$this->assertSame( 'category_mismatch', $result['rows'][0]['warnings'][0]['code'] );
	}

	/**
	 * The parser accepts comma and tab delimited files.
	 *
	 * @return void
	 */
	public function test_parser_accepts_other_delimiters() {
		$parser = new CsvParser();

		$comma = $parser->parse_string( "sku,reference_date,anchor_price\nKAV-250,2026-09-10,7.49\n" );
		$this->assertSame( ',', $comma['delimiter'] );
		$this->assertSame( 'KAV-250', $comma['rows'][0]['sku'] );

		$tab = $parser->parse_string( "sku\treference_date\tanchor_price\nKAV-250\t2026-09-10\t7.49\n" );
		$this->assertSame( "\t", $tab['delimiter'] );
		$this->assertSame( 'KAV-250', $tab['rows'][0]['sku'] );
	}

	/**
	 * A file without identifier columns is rejected.
	 *
	 * @return void
	 */
	public function test_parser_requires_identifier_column() {
		$parser = new CsvParser();
		$result = $parser->parse_string( "name;reference_date;anchor_price\nKava;2026-09-10;7.49\n" );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'import_missing_identifier_column', $result->get_error_code() );
	}
}
