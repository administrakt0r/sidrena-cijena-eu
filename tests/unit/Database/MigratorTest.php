<?php
/**
 * Migration runner tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Database\Migrator;
use Titanworks\SidrenaCijena\Database\Schema;

/**
 * @covers \Titanworks\SidrenaCijena\Database\Migrator
 * @covers \Titanworks\SidrenaCijena\Database\Schema
 */
class MigratorTest extends TestCase {

	/**
	 * Fake WordPress upgrade API path.
	 *
	 * @var string
	 */
	private $upgrade_file = '';

	/**
	 * Prepare option state and a fake dbDelta().
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_options'] = array();
		$GLOBALS['twbce_test_dbdelta'] = array();

		$this->upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( ! is_dir( dirname( $this->upgrade_file ) ) ) {
			mkdir( dirname( $this->upgrade_file ), 0777, true );
		}

		file_put_contents(
			$this->upgrade_file,
			"<?php\nif ( ! function_exists( 'dbDelta' ) ) { function dbDelta( \$sql ) { \$GLOBALS['twbce_test_dbdelta'][] = \$sql; return array(); } }\n"
		);
	}

	/**
	 * Remove the fake upgrade file.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( is_file( $this->upgrade_file ) ) {
			unlink( $this->upgrade_file );
		}

		parent::tearDown();
	}

	/**
	 * The schema version is a non-empty string.
	 *
	 * @return void
	 */
	public function test_schema_version() {
		$this->assertIsString( Schema::DB_VERSION );
		$this->assertNotSame( '', Schema::DB_VERSION );
	}

	/**
	 * Table names use the WordPress prefix.
	 *
	 * @return void
	 */
	public function test_table_names() {
		$tables = Schema::tables();

		$this->assertSame( 'wp_sidrena_price_history', $tables['price_history'] );
		$this->assertSame( 'wp_sidrena_special_sales', $tables['special_sales'] );
		$this->assertSame( 'wp_sidrena_locations', $tables['locations'] );
		$this->assertSame( 'wp_sidrena_publications', $tables['publications'] );
		$this->assertSame( 'wp_sidrena_logs', $tables['logs'] );
	}

	/**
	 * Five CREATE TABLE statements are produced.
	 *
	 * @return void
	 */
	public function test_sql_contains_five_tables() {
		$sql = Schema::sql();

		$this->assertCount( 5, $sql );

		foreach ( $sql as $statement ) {
			$this->assertStringContainsString( 'CREATE TABLE', $statement );
			$this->assertStringContainsString( 'PRIMARY KEY', $statement );
		}
	}

	/**
	 * Migration records the version and runs dbDelta once per table.
	 *
	 * @return void
	 */
	public function test_migrate_runs_dbdelta_and_stores_version() {
		$version = Migrator::migrate();

		$this->assertSame( Schema::DB_VERSION, $version );
		$this->assertSame( Schema::DB_VERSION, get_option( Migrator::OPTION ) );
		$this->assertCount( 5, $GLOBALS['twbce_test_dbdelta'] );
	}

	/**
	 * Re-running when the version is current does nothing.
	 *
	 * @return void
	 */
	public function test_migrate_is_idempotent() {
		Migrator::migrate();
		$GLOBALS['twbce_test_dbdelta'] = array();

		$version = Migrator::migrate();

		$this->assertSame( Schema::DB_VERSION, $version );
		$this->assertCount( 0, $GLOBALS['twbce_test_dbdelta'] );
	}

	/**
	 * Verifies that maybe_upgrade() only migrates when behind.
	 *
	 * @return void
	 */
	public function test_maybe_upgrade() {
		Migrator::maybe_upgrade();

		$this->assertSame( Schema::DB_VERSION, Migrator::installed_version() );

		$GLOBALS['twbce_test_dbdelta'] = array();
		Migrator::maybe_upgrade();

		$this->assertCount( 0, $GLOBALS['twbce_test_dbdelta'] );
	}

	/**
	 * Verifies that installed_version() reflects the option.
	 *
	 * @return void
	 */
	public function test_installed_version_defaults_to_empty() {
		$this->assertSame( '', Migrator::installed_version() );

		update_option( Migrator::OPTION, '0.9.0' );

		$this->assertSame( '0.9.0', Migrator::installed_version() );
	}
}
