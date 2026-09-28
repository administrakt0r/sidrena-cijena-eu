<?php
/**
 * CleanupCommand unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Cli
 */

namespace SidrenaCijena\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Cli\CleanupCommand;
use SidrenaCijena\Cli\Cli;
use SidrenaCijena\Database\PublicationRepository;
use SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * @covers \SidrenaCijena\Cli\CleanupCommand
 */
class CleanupCommandTest extends TestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Cli::reset_buffer();
		$GLOBALS['sidrena_test_options'] = array();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PluginSwap::swap( 'publications', null );
		Cli::reset_buffer();
		parent::tearDown();
	}

	/**
	 * execute in dry-run mode previews expired candidates.
	 *
	 * @return void
	 */
	public function test_execute_dry_run() {
		$publications = $this->createMock( PublicationRepository::class );
		$publications->method( 'expired_candidates' )->willReturn(
			array(
				array( 'id' => 1 ),
				array( 'id' => 2 ),
			)
		);
		PluginSwap::swap( 'publications', $publications );

		$code   = CleanupCommand::execute( array(), array( 'dry-run' => true ) );
		$buffer = Cli::buffer();

		$this->assertSame( 0, $code );
		$this->assertCount( 1, $buffer );
		$this->assertStringContainsString( 'Dry run: 2 publication(s) would expire now.', $buffer[0][1] );
	}

	/**
	 * execute runs cleanup job and reports success.
	 *
	 * @return void
	 */
	public function test_execute_runs_cleanup() {
		$publications = $this->createMock( PublicationRepository::class );
		$publications->method( 'expired_candidates' )->willReturn( array() );
		PluginSwap::swap( 'publications', $publications );

		$code   = CleanupCommand::execute();
		$buffer = Cli::buffer();

		$this->assertSame( 0, $code );
		$this->assertCount( 1, $buffer );
		$this->assertSame( 'success', $buffer[0][0] );
		$this->assertStringContainsString( 'Cleanup finished: 0 publication(s) expired.', $buffer[0][1] );
	}

	/**
	 * execute catches Throwable and returns code 2 on unexpected failure.
	 *
	 * @return void
	 */
	public function test_execute_catches_throwable() {
		$publications = $this->createMock( PublicationRepository::class );
		$publications->method( 'expired_candidates' )->willThrowException( new \Error( 'Database connection dropped' ) );
		PluginSwap::swap( 'publications', $publications );

		$code   = CleanupCommand::execute();
		$buffer = Cli::buffer();

		$this->assertSame( 2, $code );
		$this->assertNotEmpty( $buffer );
		$this->assertSame( 'error', $buffer[0][0] );
		$this->assertStringContainsString( 'Cleanup failed: Database connection dropped', $buffer[0][1] );
	}
}
