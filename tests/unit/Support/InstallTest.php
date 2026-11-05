<?php
/**
 * Install unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Support
 */

namespace SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\Capabilities;
use SidrenaCijena\Admin\WelcomeBanner;
use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Database\Migrator;
use SidrenaCijena\Support\Install;
use SidrenaCijena\Support\Settings;

/**
 * @covers \SidrenaCijena\Support\Install
 */
class InstallTest extends TestCase {

	/**
	 * Reset test environment before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['sidrena_test_options']    = array();
		$GLOBALS['sidrena_test_actions']    = array();
		$GLOBALS['sidrena_test_post_types'] = array();
		$GLOBALS['sidrena_test_taxonomies'] = array();
		$GLOBALS['sidrena_test_terms']      = array();
		$GLOBALS['sidrena_test_dbdelta']    = array();
		unset( $GLOBALS['sidrena_test_roles'] );

		$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
		if ( ! is_dir( dirname( $upgrade_file ) ) ) {
			mkdir( dirname( $upgrade_file ), 0777, true );
		}
		if ( ! file_exists( $upgrade_file ) ) {
			file_put_contents(
				$upgrade_file,
				"<?php\nif ( ! function_exists( 'dbDelta' ) ) {\n\tfunction dbDelta( \$sql ) {\n\t\t\$GLOBALS['sidrena_test_dbdelta'][] = \$sql;\n\t\treturn array();\n\t}\n}\n"
			);
		}
	}

	/**
	 * Tear down test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['sidrena_test_options'],
			$GLOBALS['sidrena_test_actions'],
			$GLOBALS['sidrena_test_post_types'],
			$GLOBALS['sidrena_test_taxonomies'],
			$GLOBALS['sidrena_test_terms'],
			$GLOBALS['sidrena_test_roles']
		);
		parent::tearDown();
	}

	/**
	 * Seed options writes defaults when options do not exist.
	 *
	 * @return void
	 */
	public function test_seed_options_writes_defaults(): void {
		$this->assertFalse( get_option( Settings::OPTION, false ) );
		$this->assertFalse( get_option( RuleConfig::OPTION, false ) );
		$this->assertFalse( get_option( RuleConfig::HISTORY_OPTION, false ) );

		Install::seed_options();

		$this->assertIsArray( get_option( Settings::OPTION, false ) );
		$this->assertIsArray( get_option( RuleConfig::OPTION, false ) );
		$this->assertIsArray( get_option( RuleConfig::HISTORY_OPTION, false ) );
	}

	/**
	 * Seed options preserves existing configuration.
	 *
	 * @return void
	 */
	public function test_seed_options_preserves_existing(): void {
		update_option( Settings::OPTION, array( 'custom_key' => 'custom_val' ) );
		update_option( RuleConfig::OPTION, array( 'rule_version' => 'custom-v1' ) );
		update_option( RuleConfig::HISTORY_OPTION, array( 'history_entry' => true ) );

		Install::seed_options();

		$settings = get_option( Settings::OPTION, false );
		$this->assertSame( 'custom_val', $settings['custom_key'] );

		$rules = get_option( RuleConfig::OPTION, false );
		$this->assertSame( 'custom-v1', $rules['rule_version'] );

		$history = get_option( RuleConfig::HISTORY_OPTION, false );
		$this->assertTrue( $history['history_entry'] );
	}

	/**
	 * Activate grants capabilities, seeds options, sets redirect, and fires action.
	 *
	 * @return void
	 */
	public function test_activate_executes_activation_routine(): void {
		$activated_fired = false;
		add_action(
			'sidrena_cijena_activated',
			static function () use ( &$activated_fired ) {
				$activated_fired = true;
			}
		);

		Install::activate();

		// Capability granted.
		$admin = get_role( 'administrator' );
		$this->assertNotNull( $admin );
		$this->assertTrue( $admin->has_cap( Capabilities::CAPABILITY ) );

		// Options seeded.
		$this->assertNotEmpty( get_option( Settings::OPTION, false ) );
		$this->assertNotEmpty( get_option( RuleConfig::OPTION, false ) );

		// Welcome redirect set.
		$this->assertSame( 1, get_option( WelcomeBanner::REDIRECT_OPTION, 0 ) );

		// Hook fired.
		$this->assertTrue( $activated_fired );
	}
}
