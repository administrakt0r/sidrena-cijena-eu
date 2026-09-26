<?php
/**
 * Uninstaller tests.
 *
 * @package SidrenaCijena\Tests\Unit
 */

namespace SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Support\Settings;
use SidrenaCijena\Support\Uninstaller;

/**
 * @covers \SidrenaCijena\Support\Uninstaller
 */
class UninstallerTest extends TestCase {

	/**
	 * Reset option state between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_options']    = array();
		$GLOBALS['sidrena_test_transients'] = array();
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

		$this->assertContains( 'sidrena_cijena_settings', $options );
		$this->assertContains( 'sidrena_cijena_rule_config', $options );
		$this->assertContains( 'sidrena_cijena_rule_config_history', $options );
		$this->assertContains( 'sidrena_cijena_db_version', $options );
		$this->assertContains( 'sidrena_cijena_last_generation', $options );
		$this->assertContains( 'sidrena_cijena_last_generation_failure', $options );
		$this->assertContains( 'sidrena_cijena_dismiss_gen_failure', $options );
		$this->assertContains( 'sidrena_cijena_rest_generation', $options );
		$this->assertContains( 'sidrena_cijena_public_feed_last_queue', $options );
		$this->assertContains( 'sidrena_cijena_terms_seeded', $options );
		$this->assertContains( 'sidrena_cijena_activation_redirect', $options );
		$this->assertContains( 'sidrena_cijena_welcome_dismissed', $options );
		$this->assertContains( 'sidrena_cijena_setup_complete', $options );
		$this->assertContains( 'sidrena_cijena_delete_data_on_uninstall', $options );
	}
}
