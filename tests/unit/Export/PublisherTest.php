<?php
/**
 * Publisher unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Export
 */

namespace SidrenaCijena\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Database\PublicationRepository;
use SidrenaCijena\Export\FilenameGenerator;
use SidrenaCijena\Export\Publisher;

/**
 * @covers \SidrenaCijena\Export\Publisher
 */
class PublisherTest extends TestCase {

	/**
	 * Temporary directory for publication files.
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * Set up temporary test directory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->temp_dir = sys_get_temp_dir() . '/sidrena_pub_test_' . uniqid();
		mkdir( $this->temp_dir, 0777, true );
	}

	/**
	 * Clean up temporary test directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( is_dir( $this->temp_dir ) ) {
			$files = glob( $this->temp_dir . '/*' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					unlink( $file );
				}
			}
			rmdir( $this->temp_dir );
		}
		parent::tearDown();
	}

	/**
	 * Location mock.
	 *
	 * @return array<string,mixed>
	 */
	private function location() {
		return array(
			'id'         => 1,
			'identifier' => 'P1',
			'name'       => 'Test Webshop',
			'type'       => 'webshop',
			'address'    => 'Ilica 1',
			'city'       => 'Zagreb',
		);
	}

	/**
	 * Verify publish streams callable content and calculates checksum during write.
	 *
	 * @return void
	 */
	public function test_publish_streams_callable_and_computes_checksum_during_write() {
		$locations    = $this->createMock( LocationRepository::class );
		$publications = $this->createMock( PublicationRepository::class );
		$filenames    = $this->createMock( FilenameGenerator::class );

		$locations->method( 'next_sequence' )->willReturn( 1 );
		$filenames->method( 'generate' )->willReturn( 'TEST_PRICE_LIST.CSV' );
		$publications->method( 'latest' )->willReturn( null );

		$inserted_data = null;
		$publications->method( 'insert' )->willReturnCallback(
			function ( array $data ) use ( &$inserted_data ) {
				$inserted_data = $data;
				return 42;
			}
		);
		$publications->method( 'get' )->with( 42 )->willReturn(
			array(
				'id'       => 42,
				'filename' => 'TEST_PRICE_LIST.CSV',
				'status'   => 'published',
			)
		);

		$publisher = $this->getMockBuilder( Publisher::class )
			->setConstructorArgs( array( $locations, $publications, $filenames ) )
			->onlyMethods( array( 'directory' ) )
			->getMock();

		$publisher->method( 'directory' )->willReturn(
			array(
				'path' => trailingslashit( $this->temp_dir ),
				'url'  => 'https://example.com/uploads/sidrena/p1/',
			)
		);

		$payload       = "vrsta;naziv\nproizvod;Kava\n";
		$expected_hash = hash( 'sha256', $payload );

		$callable_invoked = false;
		$content          = function ( $handle, $hash_ctx ) use ( $payload, &$callable_invoked ) {
			$callable_invoked = true;
			fwrite( $handle, $payload );
			hash_update( $hash_ctx, $payload );
			return strlen( $payload );
		};

		$result = $publisher->publish(
			$this->location(),
			'csv',
			$content,
			array(
				'fingerprint'  => 'fp123',
				'item_count'   => 1,
				'rule_version' => '2026-10-01',
				'force'        => true,
			)
		);

		$this->assertTrue( $callable_invoked );
		$this->assertIsArray( $result );
		$this->assertSame( 'TEST_PRICE_LIST.CSV', $result['filename'] );
		$this->assertNotNull( $inserted_data );
		$this->assertSame( $expected_hash, $inserted_data['checksum'] );
		$this->assertSame( strlen( $payload ), $inserted_data['file_size'] );
		$this->assertSame( 'fp123', $inserted_data['content_fingerprint'] );
		$this->assertFileExists( $this->temp_dir . '/TEST_PRICE_LIST.CSV' );
		$this->assertSame( $payload, file_get_contents( $this->temp_dir . '/TEST_PRICE_LIST.CSV' ) );
	}

	/**
	 * Verify publish skips execution when content fingerprint is unchanged and not forced.
	 *
	 * @return void
	 */
	public function test_publish_skips_when_fingerprint_matches_and_not_forced() {
		$locations    = $this->createMock( LocationRepository::class );
		$publications = $this->createMock( PublicationRepository::class );
		$filenames    = $this->createMock( FilenameGenerator::class );

		$existing_file = $this->temp_dir . '/EXISTING.CSV';
		file_put_contents( $existing_file, 'existing' );

		$publications->method( 'latest' )->willReturn(
			array(
				'id'                  => 10,
				'filename'            => 'EXISTING.CSV',
				'path'                => $existing_file,
				'content_fingerprint' => 'identical_fp',
			)
		);

		$publisher = new Publisher( $locations, $publications, $filenames );

		$callable_invoked = false;
		$content          = function () use ( &$callable_invoked ) {
			$callable_invoked = true;
		};

		$result = $publisher->publish(
			$this->location(),
			'csv',
			$content,
			array(
				'fingerprint' => 'identical_fp',
				'force'       => false,
			)
		);

		$this->assertFalse( $callable_invoked );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['skipped'] );
		$this->assertSame( 'EXISTING.CSV', $result['filename'] );
	}
}
