<?php
/**
 * ValidateCommand unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Cli
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Cli\Cli;
use Titanworks\SidrenaCijena\Cli\ValidateCommand;
use Titanworks\SidrenaCijena\Compliance\Finding;
use Titanworks\SidrenaCijena\Compliance\Validator;
use Titanworks\SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * @covers \Titanworks\SidrenaCijena\Cli\ValidateCommand
 */
class ValidateCommandTest extends TestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Cli::reset_buffer();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PluginSwap::swap( 'validator', null );
		Cli::reset_buffer();
		parent::tearDown();
	}

	/**
	 * Execute rejects invalid format option.
	 *
	 * @return void
	 */
	public function test_execute_invalid_format() {
		$code   = ValidateCommand::execute( array(), array( 'format' => 'invalid' ) );
		$buffer = Cli::buffer();

		$this->assertSame( 3, $code );
		$this->assertSame( 'error', $buffer[0][0] );
		$this->assertStringContainsString( 'Invalid --format.', $buffer[0][1] );
	}

	/**
	 * Execute outputs clean result and returns code 0 when no errors.
	 *
	 * @return void
	 */
	public function test_execute_clean_validation() {
		$validator = $this->createMock( Validator::class );
		$validator->method( 'validate' )->willReturn(
			array(
				'findings' => array(),
			)
		);
		PluginSwap::swap( 'validator', $validator );

		$code   = ValidateCommand::execute();
		$buffer = Cli::buffer();

		$this->assertSame( 0, $code );
		$this->assertCount( 1, $buffer );
		$this->assertStringContainsString( 'Findings: 0 error(s), 0 warning(s).', $buffer[0][1] );
	}

	/**
	 * Execute returns code 1 when compliance ERROR findings exist.
	 *
	 * @return void
	 */
	public function test_execute_with_errors() {
		$validator = $this->createMock( Validator::class );
		$finding   = Finding::error( 'missing_anchor', 'Item has no anchor price' );
		$validator->method( 'validate' )->willReturn(
			array(
				'findings' => array( $finding ),
			)
		);
		PluginSwap::swap( 'validator', $validator );

		$code   = ValidateCommand::execute();
		$buffer = Cli::buffer();

		$this->assertSame( 1, $code );
		$this->assertCount( 2, $buffer );
		$this->assertStringContainsString( 'Findings: 1 error(s), 0 warning(s).', $buffer[0][1] );
		$this->assertStringContainsString( '[ERROR] missing_anchor: Item has no anchor price', $buffer[1][1] );
	}

	/**
	 * Execute outputs valid JSON structure when requested.
	 *
	 * @return void
	 */
	public function test_execute_json_output() {
		$validator = $this->createMock( Validator::class );
		$finding   = Finding::warning( 'test_warning', 'Test warning message' );
		$validator->method( 'validate' )->willReturn(
			array(
				'findings' => array( $finding ),
			)
		);
		PluginSwap::swap( 'validator', $validator );

		$code   = ValidateCommand::execute( array(), array( 'format-output' => 'json' ) );
		$buffer = Cli::buffer();

		$this->assertSame( 0, $code );
		$this->assertCount( 1, $buffer );
		$this->assertSame( 'line', $buffer[0][0] );

		$decoded = json_decode( $buffer[0][1], true );
		$this->assertIsArray( $decoded );
		$this->assertSame( 1, $decoded['counts']['WARNING'] );
		$this->assertSame( 0, $decoded['counts']['ERROR'] );
		$this->assertCount( 1, $decoded['findings'] );
	}

	/**
	 * Execute catches Throwable and returns code 2 on unexpected failure.
	 *
	 * @return void
	 */
	public function test_execute_catches_throwable() {
		$validator = $this->createMock( Validator::class );
		$validator->method( 'validate' )->willThrowException( new \Error( 'Validation engine crashed' ) );
		PluginSwap::swap( 'validator', $validator );

		$code   = ValidateCommand::execute();
		$buffer = Cli::buffer();

		$this->assertSame( 2, $code );
		$this->assertSame( 'error', $buffer[0][0] );
		$this->assertStringContainsString( 'Validation failed: Validation engine crashed', $buffer[0][1] );
	}
}
