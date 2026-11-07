<?php
/**
 * GenerateCommand unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Cli
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Cli\Cli;
use Titanworks\SidrenaCijena\Cli\GenerateCommand;
use Titanworks\SidrenaCijena\Database\LocationRepository;
use Titanworks\SidrenaCijena\Export\GenerationService;
use Titanworks\SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * @covers \Titanworks\SidrenaCijena\Cli\GenerateCommand
 */
class GenerateCommandTest extends TestCase {

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
		PluginSwap::swap( 'generation', null );
		PluginSwap::swap( 'locations', null );
		Cli::reset_buffer();
		parent::tearDown();
	}

	/**
	 * Execute generates price lists for all locations and reports success.
	 *
	 * @return void
	 */
	public function test_execute_generate_all_success() {
		$generation = $this->createMock( GenerationService::class );
		$generation->method( 'generate_all' )->willReturn(
			array(
				1 => array(
					'csv' => array(
						'filename'   => 'PROD_W1_28092026_0800.CSV',
						'rows_count' => 10,
					),
					'xml' => array(
						'filename'   => 'PROD_W1_28092026_0800.XML',
						'rows_count' => 10,
					),
				),
			)
		);
		PluginSwap::swap( 'generation', $generation );

		$code   = GenerateCommand::execute();
		$buffer = Cli::buffer();

		$this->assertSame( 0, $code );
		$this->assertCount( 3, $buffer );
		$this->assertSame( 'success', $buffer[2][0] );
		$this->assertStringContainsString( 'Generation finished successfully.', $buffer[2][1] );
	}

	/**
	 * Execute generates price list for a single specified location.
	 *
	 * @return void
	 */
	public function test_execute_single_location_success() {
		$generation = $this->createMock( GenerationService::class );
		$generation->method( 'generate' )->willReturn(
			array(
				'filename'   => 'PROD_W1_28092026_0800.CSV',
				'rows_count' => 5,
			)
		);
		PluginSwap::swap( 'generation', $generation );

		$code   = GenerateCommand::execute(
			array(),
			array(
				'location' => 1,
				'format'   => 'csv',
			)
		);
		$buffer = Cli::buffer();

		$this->assertSame( 0, $code );
		$this->assertCount( 2, $buffer );
		$this->assertStringContainsString( 'PROD_W1_28092026_0800.CSV', $buffer[0][1] );
		$this->assertStringContainsString( 'Generation finished successfully.', $buffer[1][1] );
	}

	/**
	 * Execute handles generation failure and returns code 1.
	 *
	 * @return void
	 */
	public function test_execute_generate_failure() {
		$generation = $this->createMock( GenerationService::class );
		$generation->method( 'generate_all' )->willReturn(
			array(
				1 => new \WP_Error( 'catalog_empty', 'Catalog is empty' ),
			)
		);
		PluginSwap::swap( 'generation', $generation );

		$code   = GenerateCommand::execute();
		$buffer = Cli::buffer();

		$this->assertSame( 1, $code );
		$this->assertSame( 'error', $buffer[0][0] );
		$this->assertSame( 'warning', $buffer[1][0] );
		$this->assertStringContainsString( 'Generation finished with 1 failure(s).', $buffer[1][1] );
	}

	/**
	 * Execute catches Throwable and returns code 2 on unexpected failure.
	 *
	 * @return void
	 */
	public function test_execute_catches_throwable() {
		$generation = $this->createMock( GenerationService::class );
		$generation->method( 'generate_all' )->willThrowException( new \Error( 'Filesystem out of space' ) );
		PluginSwap::swap( 'generation', $generation );

		$code   = GenerateCommand::execute();
		$buffer = Cli::buffer();

		$this->assertSame( 2, $code );
		$this->assertSame( 'error', $buffer[0][0] );
		$this->assertStringContainsString( 'Generation failed: Filesystem out of space', $buffer[0][1] );
	}

	/**
	 * Execute generation with compliance findings reports warnings and remediation proposals.
	 *
	 * @return void
	 */
	public function test_execute_generate_with_findings_and_proposals() {
		$generation = $this->createMock( GenerationService::class );
		$generation->method( 'generate' )->willReturn(
			array(
				'location_id' => 1,
				'format'      => 'csv',
				'filename'    => 'PROD_W1_28092026_0800.CSV',
				'item_count'  => 5,
				'has_errors'  => true,
				'findings'    => array(
					array( 'code' => 'missing_anchor_price' ),
				),
				'proposals'   => array(
					'Record an anchor price for this item for reference date 2026-09-10.',
				),
			)
		);
		PluginSwap::swap( 'generation', $generation );

		$code   = GenerateCommand::execute(
			array(),
			array(
				'location' => 1,
				'format'   => 'csv',
			)
		);
		$buffer = Cli::buffer();

		$this->assertSame( 0, $code );
		$output = implode( "\n", array_column( $buffer, 1 ) );
		$this->assertStringContainsString( 'PROD_W1_28092026_0800.CSV', $output );
		$this->assertStringContainsString( '1 compliance finding(s)', $output );
		$this->assertStringContainsString( 'Proposed changes to resolve compliance findings:', $output );
		$this->assertStringContainsString( 'Record an anchor price', $output );
		$this->assertStringContainsString( 'Generation finished successfully.', $output );
	}
}
