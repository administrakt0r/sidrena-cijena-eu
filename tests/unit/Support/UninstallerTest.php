<?php
/**
 * Uninstaller tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Support\Settings;
use Titanworks\SidrenaCijena\Support\Uninstaller;

/**
 * @covers \Titanworks\SidrenaCijena\Support\Uninstaller
 */
class UninstallerTest extends TestCase {

	/**
	 * Reset option state between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_options']    = array();
		$GLOBALS['twbce_test_transients'] = array();
	}

	/**
	 * Evidence types are anchor and lowest_30_day to safeguard legal records.
	 *
	 * @return void
	 */
	public function test_evidence_types_preserve_legal_records() {
		$this->assertContains( 'anchor', Uninstaller::EVIDENCE_TYPES );
		$this->assertContains( 'lowest_30_day', Uninstaller::EVIDENCE_TYPES );
		$this->assertCount( 2, Uninstaller::EVIDENCE_TYPES );
	}

	/**
	 * Verify should_delete defaults to false unless explicitly configured.
	 *
	 * @return void
	 */
	public function test_should_delete_defaults_to_false() {
		$this->assertFalse( Uninstaller::should_delete() );

		update_option( Settings::OPTION, array_merge( Settings::all(), array( 'delete_data_on_uninstall' => true ) ) );
		$this->assertTrue( Uninstaller::should_delete() );

		update_option( Settings::OPTION, array_merge( Settings::all(), array( 'delete_data_on_uninstall' => false ) ) );
		$this->assertFalse( Uninstaller::should_delete() );
	}

	/**
	 * Verify run returns early without deleting when the option is disabled.
	 *
	 * @return void
	 */
	public function test_run_returns_early_when_disabled() {
		$summary = Uninstaller::run();

		$this->assertFalse( $summary['deleted'] );
		$this->assertSame( array(), $summary['tables'] );
		$this->assertSame( array(), $summary['options'] );
		$this->assertSame( 0, $summary['posts'] );
		$this->assertSame( 0, $summary['files'] );
	}

	/**
	 * Options list contains all known plugin settings and operational options.
	 *
	 * @return void
	 */
	public function test_options_list_covers_plugin_options() {
		$options = Uninstaller::options();

		$this->assertContains( 'twbce_cijena_settings', $options );
		$this->assertContains( 'twbce_cijena_rule_config', $options );
		$this->assertContains( 'twbce_cijena_rule_config_history', $options );
		$this->assertContains( 'twbce_cijena_db_version', $options );
		$this->assertContains( 'twbce_cijena_last_generation', $options );
		$this->assertContains( 'twbce_cijena_last_generation_failure', $options );
		$this->assertContains( 'twbce_cijena_dismiss_gen_failure', $options );
		$this->assertContains( 'twbce_cijena_rest_generation', $options );
		$this->assertContains( 'twbce_cijena_public_feed_last_queue', $options );
		$this->assertContains( 'twbce_cijena_terms_seeded', $options );
		$this->assertContains( 'twbce_cijena_activation_redirect', $options );
		$this->assertContains( 'twbce_cijena_welcome_dismissed', $options );
		$this->assertContains( 'twbce_cijena_setup_complete', $options );
		$this->assertContains( 'twbce_cijena_delete_data_on_uninstall', $options );
	}

	/**
	 * Verify delete_transients runs query against the options table.
	 *
	 * @return void
	 */
	public function test_delete_transients_executes_cleanup_query() {
		$deleted = Uninstaller::delete_transients();

		$this->assertIsInt( $deleted );
	}

	/**
	 * Verify run executes full cleanup when opt-in is enabled, preserving evidence tables.
	 *
	 * @return void
	 */
	public function test_run_when_enabled_prunes_history_and_preserves_evidence_tables() {
		update_option( Settings::OPTION, array_merge( Settings::all(), array( 'delete_data_on_uninstall' => true ) ) );

		update_option( 'sidrena_cijena_settings', array( 'merchant_name' => 'Legacy backup' ) );
		update_option( \Titanworks\SidrenaCijena\Support\IdentifierMigration::OPTION, 1 );
		$summary = Uninstaller::run();

		$this->assertFalse( get_option( 'sidrena_cijena_settings' ) );
		$this->assertFalse( get_option( \Titanworks\SidrenaCijena\Support\IdentifierMigration::OPTION ) );
		$this->assertTrue( $summary['deleted'] );
		$this->assertArrayHasKey( 'pruned', $summary );
		$this->assertArrayHasKey( 'transients', $summary );
		$this->assertContains( 'wp_sidrena_price_history', $summary['preserved'] );
		$this->assertContains( 'wp_sidrena_special_sales', $summary['tables'] );
		$this->assertContains( 'wp_sidrena_locations', $summary['tables'] );
		$this->assertContains( 'wp_sidrena_publications', $summary['tables'] );
		$this->assertContains( 'wp_sidrena_logs', $summary['tables'] );
	}
}
