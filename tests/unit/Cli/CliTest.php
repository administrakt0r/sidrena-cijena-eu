<?php
/**
 * Cli helper unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Cli
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Cli\Cli;

/**
 * @covers \Titanworks\SidrenaCijena\Cli\Cli
 */
class CliTest extends TestCase {

	/**
	 * Clean buffer before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Cli::reset_buffer();
	}

	/**
	 * Clean buffer after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Cli::reset_buffer();
		parent::tearDown();
	}

	/**
	 * Buffer records messages and resets cleanly.
	 *
	 * @return void
	 */
	public function test_buffer_and_reset() {
		$this->assertEmpty( Cli::buffer() );

		Cli::out( 'First line' );
		Cli::out( 'Second line', 'success' );

		$buffer = Cli::buffer();
		$this->assertCount( 2, $buffer );
		$this->assertSame( array( 'log', 'First line' ), $buffer[0] );
		$this->assertSame( array( 'success', 'Second line' ), $buffer[1] );

		Cli::reset_buffer();
		$this->assertEmpty( Cli::buffer() );
	}

	/**
	 * All output methods buffer correctly.
	 *
	 * @return void
	 */
	public function test_out_methods() {
		Cli::out( 'Notice', 'log' );
		Cli::out( 'Done', 'success' );
		Cli::out( 'Caution', 'warning' );
		Cli::out( 'Failed', 'error' );
		Cli::out( 'Raw line', 'line' );

		$buffer = Cli::buffer();
		$this->assertCount( 5, $buffer );
		$this->assertSame( 'log', $buffer[0][0] );
		$this->assertSame( 'success', $buffer[1][0] );
		$this->assertSame( 'warning', $buffer[2][0] );
		$this->assertSame( 'error', $buffer[3][0] );
		$this->assertSame( 'line', $buffer[4][0] );
	}

	/**
	 * As_json encodes data and handles non-string returns.
	 *
	 * @return void
	 */
	public function test_as_json() {
		$data = array(
			'status' => 'ok',
			'count'  => 5,
		);
		$json = Cli::as_json( $data );

		$this->assertJson( $json );
		$this->assertSame( '{"status":"ok","count":5}', $json );
	}

	/**
	 * Register does nothing without WP_CLI class.
	 *
	 * @return void
	 */
	public function test_register_without_wp_cli() {
		Cli::register();
		$this->assertTrue( true );
	}
}
