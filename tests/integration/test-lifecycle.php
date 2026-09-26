<?php
/**
 * Integration tests for deactivation and uninstall lifecycle behaviour.
 *
 * @package SidrenaCijena\Tests\Integration
 */

use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Cron\Scheduler;
use SidrenaCijena\Database\Migrator;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Deactivator;
use SidrenaCijena\Support\Install;
use SidrenaCijena\Support\Settings;
use SidrenaCijena\Support\Uninstaller;

/**
 * Verifies data preservation on deactivation and opt-in deletion on uninstall.
 */
class Test_Lifecycle extends WP_UnitTestCase {

	/**
	 * Prepare the catalog.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		StandalonePostType::register();
		Plugin::instance()->set_catalog( new StandaloneCatalogProvider() );
	}

	/**
	 * Plugin table names.
	 *
	 * @return string[]
	 */
	protected function tables() {
		global $wpdb;

		return array(
			$wpdb->prefix . 'sidrena_price_history',
			$wpdb->prefix . 'sidrena_special_sales',
			$wpdb->prefix . 'sidrena_locations',
			$wpdb->prefix . 'sidrena_publications',
			$wpdb->prefix . 'sidrena_logs',
		);
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $table Table name.
	 * @return bool
	 */
	protected function table_exists( $table ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test-only schema assertion.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Activation creates schema and schedules; deactivation keeps all data.
	 *
	 * @return void
	 */
	public function test_deactivation_preserves_data() {
		Install::activate();

		$post_id = wp_insert_post(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_title'  => 'Lifecycle item',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, '_sidrena_sku', 'LIFE-1' );
		update_post_meta( $post_id, '_sidrena_regular_price', '9.99' );
		wp_set_object_terms( $post_id, 'product', StandalonePostType::TAX_TYPE );

		$item = Plugin::instance()->catalog()->get_item( $post_id );
		Plugin::instance()->anchors()->record( $item, 9.99, array( 'source' => 'cli' ) );

		$this->assertTrue( (bool) wp_next_scheduled( Scheduler::HOOK_DAILY ) );

		Deactivator::deactivate();

		$this->assertFalse( (bool) wp_next_scheduled( Scheduler::HOOK_DAILY ) );
		$this->assertSame( 1, Plugin::instance()->history()->count( array( 'product_id' => $post_id ) ) );
		$this->assertNotNull( get_post( $post_id ) );

		foreach ( $this->tables() as $table ) {
			$this->assertTrue( $this->table_exists( $table ), "Table $table should survive deactivation." );
		}

		Scheduler::schedule_all();
	}

	/**
	 * Upgrade adds performance indexes and remains idempotent.
	 *
	 * @return void
	 */
	public function test_upgrade_adds_indexes_and_is_idempotent() {
		global $wpdb;

		Install::activate();
		update_option( Migrator::OPTION, '1.0.0' );
		Migrator::migrate();

		$expected = array(
			$wpdb->prefix . 'sidrena_price_history' => array( 'created_at', 'item_type', 'correction_of', 'product_type_id' ),
			$wpdb->prefix . 'sidrena_special_sales' => array( 'status_start' ),
			$wpdb->prefix . 'sidrena_publications'  => array( 'location_format_status_id' ),
		);

		foreach ( $expected as $table => $indexes ) {
			$found = $wpdb->get_col( 'SHOW INDEX FROM `' . esc_sql( $table ) . '`', 2 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( $indexes as $index ) {
				$this->assertContains( $index, $found, $table . ' should have index ' . $index );
			}
		}

		$version = Migrator::installed_version();
		Migrator::migrate();
		$this->assertSame( $version, Migrator::installed_version() );
		$this->assertSame( '1.1.0', $version );
	}

	/**
	 * Uninstall without opt-in deletes nothing.
	 *
	 * @return void
	 */
	public function test_uninstall_without_optin_preserves_everything() {
		Install::activate();
		update_option( Settings::OPTION, array_merge( Settings::all(), array( 'delete_data_on_uninstall' => false ) ) );

		$summary = Uninstaller::run();

		$this->assertFalse( $summary['deleted'] );

		foreach ( $this->tables() as $table ) {
			$this->assertTrue( $this->table_exists( $table ), "Table $table should survive uninstall without opt-in." );
		}
	}

	/**
	 * Uninstall with opt-in deletes plugin data but never foreign data or the
	 * anchor evidence.
	 *
	 * Note: DROP TABLE commits implicitly on MySQL, so this test rebuilds
	 * the schema before finishing to leave a clean environment behind.
	 *
	 * @return void
	 */
	public function test_uninstall_with_optin_deletes_only_plugin_data() {
		// The WordPress test suite rewrites CREATE/DROP TABLE into their
		// TEMPORARY variants (via the `query` filter) so that DDL stays inside
		// the test transaction, and `SHOW TABLES` only ever lists base tables.
		// Uninstall has to drop the real tables, so run this case with that
		// rewriting disabled and without leftover shadows.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$this->drop_all_tables();

		Install::activate();

		$history = Plugin::instance()->history();

		$history->insert(
			array(
				'product_id'     => 1,
				'variation_id'   => 0,
				'item_type'      => 'product',
				'sku'            => 'LIFE-ANCHOR',
				'price_type'     => 'anchor',
				'price'          => 9.99,
				'currency'       => 'EUR',
				'reference_date' => '2026-09-10',
				'source'         => 'cli',
				'created_by'     => 0,
			)
		);

		$history->insert(
			array(
				'product_id'   => 1,
				'variation_id' => 0,
				'item_type'    => 'product',
				'sku'          => 'LIFE-ANCHOR',
				'price_type'   => 'regular',
				'price'        => 9.99,
				'currency'     => 'EUR',
				'source'       => 'cli',
				'created_by'   => 0,
			)
		);

		$post_id = wp_insert_post(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_title'  => 'Doomed item',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, '_sidrena_sku', 'LIFE-DOOMED' );

		$foreign_post = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Foreign post',
				'post_status' => 'publish',
			)
		);

		update_option( 'sidrena_lifecycle_foreign_option', 'keep me', false );
		update_option( Settings::OPTION, array_merge( Settings::all(), array( 'delete_data_on_uninstall' => true ) ) );

		$summary = Uninstaller::run();

		$this->assertTrue( $summary['deleted'] );

		global $wpdb;

		$history_table = $this->tables()[0];

		foreach ( $this->tables() as $table ) {
			if ( $table === $history_table ) {
				$this->assertTrue( $this->table_exists( $table ), 'The price history table keeps the legal evidence.' );
				continue;
			}

			$this->assertFalse( $this->table_exists( $table ), "Table $table should be dropped." );
		}

		// Ordinary price history is deleted; the anchor evidence stays.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name comes from tables().
		$remaining = $wpdb->get_col( "SELECT price_type FROM `{$history_table}` ORDER BY id" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$this->assertSame( array( 'anchor' ), $remaining );

		$this->assertNull( get_post( $post_id ) );
		$this->assertFalse( get_option( Settings::OPTION, false ) );

		// Foreign data must survive.
		$this->assertNotNull( get_post( $foreign_post ) );
		$this->assertSame( 'keep me', get_option( 'sidrena_lifecycle_foreign_option' ) );

		// Leave a clean environment behind (DDL is not transactional on MySQL).
		Migrator::migrate();
		Install::activate();
		delete_option( 'sidrena_lifecycle_foreign_option' );
		wp_delete_post( $foreign_post, true );

		// Restore the test suite's DDL rewriting for the remaining test cases.
		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}

	/**
	 * Drop every plugin table: a temporary shadow first, then the base table.
	 *
	 * @return void
	 */
	protected function drop_all_tables() {
		global $wpdb;

		foreach ( $this->tables() as $table ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names come from tables().
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		}
	}
}
