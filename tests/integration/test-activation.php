<?php
/**
 * Activation, deactivation and re-activation integration tests.
 *
 * @package SidrenaCijena\Tests\Integration
 */

use SidrenaCijena\Database\Schema;
use SidrenaCijena\Support\Deactivator;
use SidrenaCijena\Support\Install;

/**
 * Activation lifecycle.
 */
class ActivationTest extends WP_UnitTestCase {

	/**
	 * Activation creates the schema, options and default location.
	 *
	 * @return void
	 */
	public function test_activation_creates_tables_options_and_location() {
		global $wpdb;

		Install::activate();

		$tables = Schema::tables();

		foreach ( $tables as $table ) {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->assertSame( $table, $found, "Table {$table} should exist." );
		}

		$this->assertNotFalse( get_option( 'sidrena_cijena_settings' ) );
		$this->assertNotFalse( get_option( 'sidrena_cijena_rule_config' ) );
		$this->assertSame( Schema::DB_VERSION, get_option( 'sidrena_cijena_db_version' ) );

		$locations = new \SidrenaCijena\Database\LocationRepository();
		$this->assertCount( 1, $locations->all() );
		$this->assertSame( 'webshop', $locations->all()[0]['type'] );
	}

	/**
	 * Re-activation is idempotent.
	 *
	 * @return void
	 */
	public function test_reactivation_is_idempotent() {
		Install::activate();
		Install::activate();

		$locations = new \SidrenaCijena\Database\LocationRepository();
		$this->assertCount( 1, $locations->all() );
	}

	/**
	 * Deactivation preserves data.
	 *
	 * @return void
	 */
	public function test_deactivation_preserves_data() {
		Install::activate();

		$history = new \SidrenaCijena\Database\PriceHistoryRepository();
		$id      = $history->insert(
			array(
				'product_id'     => 123,
				'price_type'     => 'anchor',
				'price'          => 9.99,
				'reference_date' => '2026-09-10',
				'source'         => 'manual',
			)
		);

		Deactivator::deactivate();

		$this->assertNotFalse( get_option( 'sidrena_cijena_settings' ) );
		$this->assertNotNull( $history->get( $id ) );
		$this->assertSame( 1, $history->count() );
	}
}
