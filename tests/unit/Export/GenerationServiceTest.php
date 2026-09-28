<?php
/**
 * GenerationService unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Export
 */

namespace SidrenaCijena\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Compliance\Finding;
use SidrenaCijena\Compliance\Validator;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\PublicationRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\Export\GenerationService;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Settings;
use SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * @covers \SidrenaCijena\Export\GenerationService
 */
class GenerationServiceTest extends TestCase {

	/**
	 * Reset test environment before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['sidrena_test_options'] = array();
		Settings::reset_cache();
		self::clean_uploads();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PluginSwap::swap( 'validator', null );
		Settings::reset_cache();
		self::clean_uploads();
		parent::tearDown();
	}

	/**
	 * Delete test upload files.
	 *
	 * @return void
	 */
	private static function clean_uploads() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'sidrena-cijena';
		if ( is_dir( $dir ) ) {
			self::delete_dir( $dir );
		}
	}

	/**
	 * Recursively remove a test directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private static function delete_dir( $dir ) {
		$files = array_diff( (array) scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				self::delete_dir( $path );
			} elseif ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
		if ( is_dir( $dir ) ) {
			rmdir( $dir );
		}
	}

	/**
	 * Generate returns WP_Error when location is not found.
	 *
	 * @return void
	 */
	public function test_generate_location_not_found() {
		$catalog      = $this->createMock( CatalogProvider::class );
		$locations    = $this->createMock( LocationRepository::class );
		$publications = $this->createMock( PublicationRepository::class );
		$history      = $this->createMock( PriceHistoryRepository::class );
		$sales        = $this->createMock( SpecialSaleRepository::class );

		$locations->method( 'get' )->with( 999 )->willReturn( null );

		$service = new GenerationService( $catalog, $locations, $publications, $history, $sales );
		$result  = $service->generate( 999, 'csv' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'location_not_found', $result->get_error_code() );
	}

	/**
	 * Generate returns WP_Error when format is invalid.
	 *
	 * @return void
	 */
	public function test_generate_invalid_format() {
		$catalog      = $this->createMock( CatalogProvider::class );
		$locations    = $this->createMock( LocationRepository::class );
		$publications = $this->createMock( PublicationRepository::class );
		$history      = $this->createMock( PriceHistoryRepository::class );
		$sales        = $this->createMock( SpecialSaleRepository::class );

		$locations->method( 'get' )->with( 1 )->willReturn(
			array(
				'id'         => 1,
				'name'       => 'Test Webshop',
				'identifier' => 'P1',
				'type'       => 'webshop',
				'address'    => 'Ilica 1',
				'city'       => 'Zagreb',
			)
		);

		$service = new GenerationService( $catalog, $locations, $publications, $history, $sales );
		$result  = $service->generate( 1, 'json' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_format', $result->get_error_code() );
	}

	/**
	 * When allow_generation_with_errors is false and not forced, blockers prevent publication.
	 *
	 * @return void
	 */
	public function test_generate_blocks_when_disallowed_and_errored() {
		$catalog      = $this->createMock( CatalogProvider::class );
		$locations    = $this->createMock( LocationRepository::class );
		$publications = $this->createMock( PublicationRepository::class );
		$history      = $this->createMock( PriceHistoryRepository::class );
		$sales        = $this->createMock( SpecialSaleRepository::class );

		$locations->method( 'get' )->with( 1 )->willReturn(
			array(
				'id'         => 1,
				'name'       => 'Test Webshop',
				'identifier' => 'P1',
				'type'       => 'webshop',
				'address'    => 'Ilica 1',
				'city'       => 'Zagreb',
			)
		);

		$validator = $this->createMock( Validator::class );
		$validator->method( 'validate' )->willReturn(
			array(
				'findings' => array(
					Finding::error( 'missing_anchor_price', 'Missing anchor' ),
				),
				'warnings' => 0,
			)
		);
		PluginSwap::swap( 'validator', $validator );

		update_option(
			Settings::OPTION,
			array(
				'allow_generation_with_errors' => false,
			)
		);
		Settings::reset_cache();

		$service = new GenerationService( $catalog, $locations, $publications, $history, $sales );
		$result  = $service->generate( 1, 'csv', array( 'force' => false ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'validation_failed', $result->get_error_code() );
	}

	/**
	 * When allow_generation_with_errors is true, generation succeeds with proposals.
	 *
	 * @return void
	 */
	public function test_generate_succeeds_with_proposals_when_errored() {
		$catalog      = $this->createMock( CatalogProvider::class );
		$locations    = $this->createMock( LocationRepository::class );
		$publications = $this->createMock( PublicationRepository::class );
		$history      = $this->createMock( PriceHistoryRepository::class );
		$sales        = $this->createMock( SpecialSaleRepository::class );

		$locations->method( 'get' )->with( 1 )->willReturn(
			array(
				'id'         => 1,
				'name'       => 'Test Webshop',
				'identifier' => 'P1',
				'type'       => 'webshop',
				'address'    => 'Ilica 1',
				'city'       => 'Zagreb',
			)
		);
		$seq1 = 0;
		$locations->method( 'next_sequence' )->willReturnCallback(
			function () use ( &$seq1 ) {
				return ++$seq1;
			}
		);

		$catalog->method( 'iterate' )->willReturn( new \ArrayIterator( array() ) );
		$publications->method( 'latest' )->willReturn( null );
		$publications->method( 'insert' )->willReturn( 1 );

		$validator = $this->createMock( Validator::class );
		$validator->method( 'validate' )->willReturn(
			array(
				'findings' => array(
					Finding::error( 'missing_anchor_price', 'Missing anchor', array( 'context' => array( 'reference_date' => '2026-09-10' ) ) ),
				),
				'warnings' => 0,
			)
		);
		PluginSwap::swap( 'validator', $validator );

		update_option(
			Settings::OPTION,
			array(
				'allow_generation_with_errors' => true,
			)
		);
		Settings::reset_cache();

		$service = new GenerationService( $catalog, $locations, $publications, $history, $sales );
		$result  = $service->generate( 1, 'csv' );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['has_errors'] );
		$this->assertNotEmpty( $result['findings'] );
		$this->assertNotEmpty( $result['proposals'] );
		$this->assertStringContainsString( '2026-09-10', $result['proposals'][0] );
	}

	/**
	 * Explicit force argument bypasses blocking even if setting is false.
	 *
	 * @return void
	 */
	public function test_generate_forced_bypasses_blocking() {
		$catalog      = $this->createMock( CatalogProvider::class );
		$locations    = $this->createMock( LocationRepository::class );
		$publications = $this->createMock( PublicationRepository::class );
		$history      = $this->createMock( PriceHistoryRepository::class );
		$sales        = $this->createMock( SpecialSaleRepository::class );

		$locations->method( 'get' )->with( 2 )->willReturn(
			array(
				'id'         => 2,
				'name'       => 'Test Webshop 2',
				'identifier' => 'P2',
				'type'       => 'webshop',
				'address'    => 'Ilica 2',
				'city'       => 'Zagreb',
			)
		);
		$seq2 = 0;
		$locations->method( 'next_sequence' )->willReturnCallback(
			function () use ( &$seq2 ) {
				return ++$seq2;
			}
		);

		$catalog->method( 'iterate' )->willReturn( new \ArrayIterator( array() ) );
		$publications->method( 'latest' )->willReturn( null );
		$publications->method( 'insert' )->willReturn( 2 );

		$validator = $this->createMock( Validator::class );
		$validator->method( 'validate' )->willReturn(
			array(
				'findings' => array(
					Finding::error( 'missing_anchor_price', 'Missing anchor' ),
				),
				'warnings' => 0,
			)
		);
		PluginSwap::swap( 'validator', $validator );

		update_option(
			Settings::OPTION,
			array(
				'allow_generation_with_errors' => false,
			)
		);
		Settings::reset_cache();

		$service = new GenerationService( $catalog, $locations, $publications, $history, $sales );
		$result  = $service->generate( 2, 'csv', array( 'force' => true ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['has_errors'] );
	}
}
