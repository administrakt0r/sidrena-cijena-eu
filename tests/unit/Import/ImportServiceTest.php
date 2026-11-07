<?php
/**
 * ImportService unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Import
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Import\CsvParser;
use Titanworks\SidrenaCijena\Import\ImportService;
use Titanworks\SidrenaCijena\Import\RowValidator;
use Titanworks\SidrenaCijena\Pricing\AnchorPriceService;

/**
 * @covers \Titanworks\SidrenaCijena\Import\ImportService
 */
class ImportServiceTest extends TestCase {

	/**
	 * Previous wpdb instance.
	 *
	 * @var \wpdb|null
	 */
	private $previous_wpdb;

	/**
	 * Tracked wpdb queries.
	 *
	 * @var string[]
	 */
	private $wpdb_queries = array();

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->previous_wpdb = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;
		$this->wpdb_queries  = array();

		$mock_wpdb = $this->getMockBuilder( \wpdb::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'query' ) )
			->getMock();

		$mock_wpdb->method( 'query' )->willReturnCallback(
			function ( $query ) {
				$this->wpdb_queries[] = (string) $query;
				return true;
			}
		);

		$GLOBALS['wpdb'] = $mock_wpdb;
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->previous_wpdb;

		parent::tearDown();
	}

	/**
	 * Preview returns WP_Error when parser fails.
	 *
	 * @return void
	 */
	public function test_preview_returns_error_on_parse_failure() {
		$parser    = $this->createMock( CsvParser::class );
		$validator = $this->createMock( RowValidator::class );
		$anchors   = $this->createMock( AnchorPriceService::class );

		$parser->method( 'parse_file' )->willReturn( new \WP_Error( 'file_not_found', 'File not found' ) );

		$service = new ImportService( $parser, $validator, $anchors );
		$result  = $service->preview( '/non/existent.csv' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'file_not_found', $result->get_error_code() );
	}

	/**
	 * Preview builds validated summary and batch ID.
	 *
	 * @return void
	 */
	public function test_preview_successful() {
		$parser    = $this->createMock( CsvParser::class );
		$validator = $this->createMock( RowValidator::class );
		$anchors   = $this->createMock( AnchorPriceService::class );

		$parser->method( 'parse_file' )->willReturn(
			array(
				'rows'      => array(
					array(
						'sku'   => 'SKU1',
						'price' => '10.00',
					),
				),
				'errors'    => array(),
				'headers'   => array( 'sku', 'price' ),
				'delimiter' => ';',
			)
		);

		$validator->method( 'validate_rows' )->willReturn(
			array(
				'rows'    => array(
					array(
						'index'  => 0,
						'status' => 'valid',
						'values' => array(
							'sku'            => 'SKU1',
							'price'          => 10.00,
							'reference_date' => '2026-09-10',
						),
						'errors' => array(),
					),
				),
				'summary' => array(
					'total'     => 1,
					'valid'     => 1,
					'invalid'   => 0,
					'overwrite' => 0,
					'duplicate' => 0,
					'unchanged' => 0,
				),
			)
		);

		$service = new ImportService( $parser, $validator, $anchors );
		$preview = $service->preview( '/tmp/test.csv', array( 'batch_id' => 'custom-batch' ) );

		$this->assertIsArray( $preview );
		$this->assertSame( 'test.csv', $preview['file'] );
		$this->assertSame( 'custom-batch', $preview['batch_id'] );
		$this->assertSame( 1, $preview['summary']['valid'] );
		$this->assertSame( 0, $preview['summary']['parse_errors'] );
	}

	/**
	 * Commit halts and requires confirmation when overwrite rows exist without confirmation flag.
	 *
	 * @return void
	 */
	public function test_commit_requires_overwrite_confirmation() {
		$parser    = $this->createMock( CsvParser::class );
		$validator = $this->createMock( RowValidator::class );
		$anchors   = $this->createMock( AnchorPriceService::class );

		$parser->method( 'parse_file' )->willReturn(
			array(
				'rows'      => array( array( 'sku' => 'SKU1' ) ),
				'errors'    => array(),
				'headers'   => array( 'sku' ),
				'delimiter' => ';',
			)
		);

		$validator->method( 'validate_rows' )->willReturn(
			array(
				'rows'    => array(),
				'summary' => array(
					'total'     => 1,
					'valid'     => 0,
					'invalid'   => 0,
					'overwrite' => 1,
					'duplicate' => 0,
					'unchanged' => 0,
				),
			)
		);

		$service = new ImportService( $parser, $validator, $anchors );
		$result  = $service->commit( '/tmp/test.csv' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'overwrite_confirmation_required', $result->get_error_code() );
	}

	/**
	 * Commit dry-run reports expected counts without executing database transactions.
	 *
	 * @return void
	 */
	public function test_commit_dry_run() {
		$parser    = $this->createMock( CsvParser::class );
		$validator = $this->createMock( RowValidator::class );
		$anchors   = $this->createMock( AnchorPriceService::class );

		$parser->method( 'parse_file' )->willReturn(
			array(
				'rows'      => array( array( 'sku' => 'SKU1' ) ),
				'errors'    => array(),
				'headers'   => array( 'sku' ),
				'delimiter' => ';',
			)
		);

		$validator->method( 'validate_rows' )->willReturn(
			array(
				'rows'    => array(),
				'summary' => array(
					'total'     => 4,
					'valid'     => 2,
					'invalid'   => 1,
					'overwrite' => 1,
					'duplicate' => 0,
					'unchanged' => 0,
				),
			)
		);

		$service = new ImportService( $parser, $validator, $anchors );
		$result  = $service->commit(
			'/tmp/test.csv',
			array(
				'dry_run'           => true,
				'confirm_overwrite' => true,
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['dry_run'] );
		$this->assertSame( 3, $result['inserted'] );
		$this->assertSame( 1, $result['skipped'] );
		$this->assertEmpty( $this->wpdb_queries );
	}

	/**
	 * Commit records valid and overwritten rows in a database transaction.
	 *
	 * @return void
	 */
	public function test_commit_successful_transaction() {
		$parser    = $this->createMock( CsvParser::class );
		$validator = $this->createMock( RowValidator::class );
		$anchors   = $this->createMock( AnchorPriceService::class );

		$item = new CatalogItem(
			array(
				'id'       => 10,
				'name'     => 'Test item',
				'sku'      => 'SKU1',
				'currency' => 'EUR',
			)
		);

		$parser->method( 'parse_file' )->willReturn(
			array(
				'rows'      => array( array( 'sku' => 'SKU1' ) ),
				'errors'    => array(),
				'headers'   => array( 'sku' ),
				'delimiter' => ';',
			)
		);

		$validator->method( 'validate_rows' )->willReturn(
			array(
				'rows'    => array(
					array(
						'index'  => 0,
						'status' => 'valid',
						'item'   => $item,
						'values' => array(
							'sku'            => 'SKU1',
							'product_id'     => 10,
							'price'          => 25.50,
							'reference_date' => '2026-09-10',
						),
						'errors' => array(),
					),
					array(
						'index'  => 1,
						'status' => 'unchanged',
						'item'   => $item,
						'values' => array(
							'sku'            => 'SKU1',
							'product_id'     => 10,
							'price'          => 25.50,
							'reference_date' => '2026-09-10',
						),
						'errors' => array(),
					),
				),
				'summary' => array(
					'total'     => 2,
					'valid'     => 1,
					'invalid'   => 0,
					'overwrite' => 0,
					'duplicate' => 0,
					'unchanged' => 1,
				),
			)
		);

		$anchors->expects( $this->once() )
			->method( 'record' )
			->willReturn( 101 );

		$service = new ImportService( $parser, $validator, $anchors );
		$result  = $service->commit( '/tmp/test.csv' );

		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['inserted'] );
		$this->assertSame( 1, $result['skipped'] );
		$this->assertContains( 'START TRANSACTION', $this->wpdb_queries );
		$this->assertContains( 'COMMIT', $this->wpdb_queries );
	}

	/**
	 * Commit rolls back transaction when anchor recording returns a WP_Error.
	 *
	 * @return void
	 */
	public function test_commit_rolls_back_on_record_failure() {
		$parser    = $this->createMock( CsvParser::class );
		$validator = $this->createMock( RowValidator::class );
		$anchors   = $this->createMock( AnchorPriceService::class );

		$item = new CatalogItem(
			array(
				'id'       => 10,
				'name'     => 'Test item',
				'sku'      => 'SKU1',
				'currency' => 'EUR',
			)
		);

		$parser->method( 'parse_file' )->willReturn(
			array(
				'rows'      => array( array( 'sku' => 'SKU1' ) ),
				'errors'    => array(),
				'headers'   => array( 'sku' ),
				'delimiter' => ';',
			)
		);

		$validator->method( 'validate_rows' )->willReturn(
			array(
				'rows'    => array(
					array(
						'index'  => 0,
						'status' => 'valid',
						'item'   => $item,
						'values' => array(
							'sku'            => 'SKU1',
							'product_id'     => 10,
							'price'          => 25.50,
							'reference_date' => '2026-09-10',
						),
						'errors' => array(),
					),
				),
				'summary' => array(
					'total'     => 1,
					'valid'     => 1,
					'invalid'   => 0,
					'overwrite' => 0,
					'duplicate' => 0,
					'unchanged' => 0,
				),
			)
		);

		$anchors->method( 'record' )->willReturn( new \WP_Error( 'db_error', 'Database write error' ) );

		$service = new ImportService( $parser, $validator, $anchors );
		$result  = $service->commit( '/tmp/test.csv' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'import_rolled_back', $result->get_error_code() );
		$this->assertContains( 'START TRANSACTION', $this->wpdb_queries );
		$this->assertContains( 'ROLLBACK', $this->wpdb_queries );
	}

	/**
	 * Report_csv generates standard CSV and neutralises formula injection attacks.
	 *
	 * @return void
	 */
	public function test_report_csv_neutralises_formulas() {
		$parser    = $this->createMock( CsvParser::class );
		$validator = $this->createMock( RowValidator::class );
		$anchors   = $this->createMock( AnchorPriceService::class );

		$service = new ImportService( $parser, $validator, $anchors );

		$report = array(
			'errors' => array(
				array(
					'row'            => 2,
					'code'           => 'invalid_price',
					'message'        => '=cmd|/C calc!A0',
					'sku'            => '+SUM(A1:A10)',
					'product_id'     => 5,
					'reference_date' => '2026-09-10',
					'price'          => 'invalid',
				),
				array(
					'row'            => 3,
					'code'           => 'missing_sku',
					'message'        => '@danger',
					'sku'            => '-PROD123',
					'product_id'     => 6,
					'reference_date' => '2026-09-10',
					'price'          => '10.00',
				),
			),
		);

		$csv = $service->report_csv( $report );

		$this->assertStringContainsString( 'row;code;message;sku;product_id;reference_date;price', $csv );
		// Verify formula prefixes have been escaped with a leading single quote.
		$this->assertStringContainsString( "'=cmd|/C calc!A0", $csv );
		$this->assertStringContainsString( "'+SUM(A1:A10)", $csv );
		$this->assertStringContainsString( "'@danger", $csv );
		$this->assertStringContainsString( "'-PROD123", $csv );
	}
}
