<?php
/**
 * StatusCommand unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Cli
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Cli\Cli;
use Titanworks\SidrenaCijena\Cli\StatusCommand;
use Titanworks\SidrenaCijena\Database\LocationRepository;
use Titanworks\SidrenaCijena\Database\PublicationRepository;
use Titanworks\SidrenaCijena\Database\Schema;
use Titanworks\SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * @covers \Titanworks\SidrenaCijena\Cli\StatusCommand
 */
class StatusCommandTest extends TestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Cli::reset_buffer();
		$GLOBALS['twbce_test_options'] = array();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PluginSwap::swap( 'locations', null );
		PluginSwap::swap( 'publications', null );
		Cli::reset_buffer();
		parent::tearDown();
	}

	/**
	 * Execute outputs human-readable table by default.
	 *
	 * @return void
	 */
	public function test_execute_table_format() {
		$locations = $this->createMock( LocationRepository::class );
		$locations->method( 'all' )->with( true )->willReturn(
			array(
				array(
					'id'         => 1,
					'name'       => 'Webshop',
					'identifier' => 'W1',
					'active'     => 1,
				),
			)
		);

		$publications = $this->createMock( PublicationRepository::class );
		$publications->method( 'last_successful' )->with( 1 )->willReturn(
			array(
				'published_at' => '2026-09-28 08:00:00',
				'filename'     => 'PROD_W1_28092026_0800.CSV',
			)
		);

		PluginSwap::swap( 'locations', $locations );
		PluginSwap::swap( 'publications', $publications );

		$code   = StatusCommand::execute();
		$buffer = Cli::buffer();

		$this->assertSame( 0, $code );
		$this->assertNotEmpty( $buffer );

		$output = implode( "\n", array_column( $buffer, 1 ) );
		$this->assertStringContainsString( 'Schema: ' . Schema::DB_VERSION, $output );
		$this->assertStringContainsString( 'Location #1 Webshop (W1)', $output );
	}

	/**
	 * Execute outputs valid JSON when requested.
	 *
	 * @return void
	 */
	public function test_execute_json_format() {
		$locations = $this->createMock( LocationRepository::class );
		$locations->method( 'all' )->with( true )->willReturn( array() );
		PluginSwap::swap( 'locations', $locations );

		$code   = StatusCommand::execute( array(), array( 'format' => 'json' ) );
		$buffer = Cli::buffer();

		$this->assertSame( 0, $code );
		$this->assertCount( 1, $buffer );
		$this->assertSame( 'line', $buffer[0][0] );

		$decoded = json_decode( $buffer[0][1], true );
		$this->assertIsArray( $decoded );
		$this->assertSame( Schema::DB_VERSION, $decoded['schema_version'] );
		$this->assertArrayHasKey( 'rule_version', $decoded );
		$this->assertArrayHasKey( 'locations', $decoded );
		$this->assertArrayHasKey( 'cron', $decoded );
	}

	/**
	 * Execute catches Throwable and returns code 2 on unexpected failure.
	 *
	 * @return void
	 */
	public function test_execute_catches_throwable() {
		$locations = $this->createMock( LocationRepository::class );
		$locations->method( 'all' )->willThrowException( new \Error( 'Critical database failure' ) );
		PluginSwap::swap( 'locations', $locations );

		$code   = StatusCommand::execute();
		$buffer = Cli::buffer();

		$this->assertSame( 2, $code );
		$this->assertNotEmpty( $buffer );
		$this->assertSame( 'error', $buffer[0][0] );
		$this->assertStringContainsString( 'Status failed: Critical database failure', $buffer[0][1] );
	}
}
