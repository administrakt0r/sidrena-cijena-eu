<?php
/**
 * HistoryCommand unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Cli
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Cli\Cli;
use Titanworks\SidrenaCijena\Cli\HistoryCommand;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;
use Titanworks\SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * @covers \Titanworks\SidrenaCijena\Cli\HistoryCommand
 */
class HistoryCommandTest extends TestCase {

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
		PluginSwap::swap( 'history', null );
		Cli::reset_buffer();
		parent::tearDown();
	}

	/**
	 * Execute without product_id returns usage error and code 1.
	 *
	 * @return void
	 */
	public function test_execute_missing_product_id() {
		$code   = HistoryCommand::execute( array() );
		$buffer = Cli::buffer();

		$this->assertSame( 1, $code );
		$this->assertSame( 'error', $buffer[0][0] );
		$this->assertStringContainsString( 'Usage: wp sidrena-cijena history', $buffer[0][1] );
	}

	/**
	 * Execute when no history exists returns warning and code 1.
	 *
	 * @return void
	 */
	public function test_execute_empty_history() {
		$history = $this->createMock( PriceHistoryRepository::class );
		$history->method( 'query' )->willReturn( array() );
		PluginSwap::swap( 'history', $history );

		$code   = HistoryCommand::execute( array( 10 ) );
		$buffer = Cli::buffer();

		$this->assertSame( 1, $code );
		$this->assertSame( 'warning', $buffer[0][0] );
		$this->assertStringContainsString( 'No price history for product #10.', $buffer[0][1] );
	}

	/**
	 * Execute outputs history records in table format.
	 *
	 * @return void
	 */
	public function test_execute_table_format() {
		$history = $this->createMock( PriceHistoryRepository::class );
		$history->method( 'query' )->willReturn(
			array(
				array(
					'id'             => 1,
					'created_at'     => '2026-09-28 10:00:00',
					'price_type'     => 'anchor',
					'price'          => 15.50,
					'source'         => 'manual',
					'reference_date' => '2026-09-10',
				),
			)
		);
		PluginSwap::swap( 'history', $history );

		$code   = HistoryCommand::execute( array( 10 ) );
		$buffer = Cli::buffer();

		$this->assertSame( 0, $code );
		$this->assertCount( 1, $buffer );
		$this->assertStringContainsString( '#1 |', $buffer[0][1] );
		$this->assertStringContainsString( '| anchor | 15,50 | manual | ref 2026-09-10', $buffer[0][1] );
	}

	/**
	 * Execute outputs JSON when format-output=json.
	 *
	 * @return void
	 */
	public function test_execute_json_format() {
		$history = $this->createMock( PriceHistoryRepository::class );
		$history->method( 'query' )->willReturn(
			array(
				array(
					'id'         => 1,
					'price_type' => 'regular',
					'price'      => 20.00,
				),
			)
		);
		PluginSwap::swap( 'history', $history );

		$code   = HistoryCommand::execute( array( 10 ), array( 'format-output' => 'json' ) );
		$buffer = Cli::buffer();

		$this->assertSame( 0, $code );
		$this->assertCount( 1, $buffer );
		$this->assertSame( 'line', $buffer[0][0] );
		$this->assertJson( $buffer[0][1] );
	}

	/**
	 * Execute catches Throwable and returns code 2 on unexpected failure.
	 *
	 * @return void
	 */
	public function test_execute_catches_throwable() {
		$history = $this->createMock( PriceHistoryRepository::class );
		$history->method( 'query' )->willThrowException( new \Error( 'Database query failed' ) );
		PluginSwap::swap( 'history', $history );

		$code   = HistoryCommand::execute( array( 10 ) );
		$buffer = Cli::buffer();

		$this->assertSame( 2, $code );
		$this->assertSame( 'error', $buffer[0][0] );
		$this->assertStringContainsString( 'History lookup failed: Database query failed', $buffer[0][1] );
	}
}
