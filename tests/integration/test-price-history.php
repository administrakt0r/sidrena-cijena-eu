<?php
/**
 * Integration tests for price history and audit trail.
 *
 * @package SidrenaCijena\Tests\Integration
 */

use SidrenaCijena\Admin\AuditPage;
use SidrenaCijena\Admin\PriceHistoryPage;
use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Database\LogRepository;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Plugin;

/**
 * Covers chronological listing, corrections preserving originals and audit entries.
 */
class Test_Price_History extends WP_UnitTestCase {

	/**
	 * History repository.
	 *
	 * @var PriceHistoryRepository
	 */
	protected $history;

	/**
	 * Set up fixtures.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		StandalonePostType::register();
		Plugin::instance()->set_catalog( new StandaloneCatalogProvider() );

		$wpdb = $GLOBALS['wpdb'];

		foreach ( array( 'sidrena_price_history', 'sidrena_logs' ) as $suffix ) {
			$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . $suffix ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$this->history = new PriceHistoryRepository();
	}

	/**
	 * Create a catalog item.
	 *
	 * @param string $name  Name.
	 * @param string $sku   SKU.
	 * @param float  $price Regular price.
	 * @return int
	 */
	protected function create_item( $name, $sku, $price ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $name,
			)
		);

		update_post_meta( $post_id, '_sidrena_sku', $sku );
		update_post_meta( $post_id, '_sidrena_regular_price', $price );

		wp_set_object_terms( $post_id, 'product', StandalonePostType::TAX_TYPE );

		return $post_id;
	}

	/**
	 * Timeline lists rows chronologically with correction links.
	 *
	 * @return void
	 */
	public function test_history_listing_and_correction_chain() {
		$post_id = $this->create_item( 'Kava mljevena 250 g', 'KAV-250', 6.99 );
		$item    = Plugin::instance()->catalog()->get_item( $post_id );

		$first = Plugin::instance()->anchors()->record( $item, 7.49, array( 'reference_date' => '2026-09-10' ) );
		$this->assertIsInt( $first );

		$rows = $this->history->query(
			array(
				'product_id' => $post_id,
				'order'      => 'ASC',
			)
		);
		$this->assertCount( 1, $rows );
		$this->assertSame( 'anchor', $rows[0]['price_type'] );
		$this->assertSame( '7.490000', $rows[0]['price'] );
		$this->assertNull( $rows[0]['correction_of'] );

		$second = Plugin::instance()->anchors()->record(
			$item,
			7.29,
			array(
				'reference_date'    => '2026-09-10',
				'correction_reason' => 'Typo in the archived flyer.',
			)
		);
		$this->assertIsInt( $second );
		$this->assertNotSame( $first, $second );

		$rows = $this->history->query(
			array(
				'product_id' => $post_id,
				'order'      => 'ASC',
			)
		);
		$this->assertCount( 2, $rows );

		$this->assertSame( '7.490000', $rows[0]['price'] );
		$this->assertNull( $rows[0]['correction_of'] );

		$this->assertSame( '7.290000', $rows[1]['price'] );
		$this->assertSame( (int) $rows[0]['id'], (int) $rows[1]['correction_of'] );
		$this->assertSame( 'Typo in the archived flyer.', $rows[1]['correction_reason'] );

		$this->assertSame( $second, $this->history->latest( $post_id, 0, 'anchor' )['id'] );
	}

	/**
	 * Direct insert_correction rejects missing metadata.
	 *
	 * @return void
	 */
	public function test_insert_correction_validates_reason_and_original() {
		$post_id = $this->create_item( 'Čaj 100 g', 'CAJ-100', 4.99 );
		$item    = Plugin::instance()->catalog()->get_item( $post_id );

		$original = $this->history->insert(
			array(
				'product_id' => $post_id,
				'price_type' => 'regular',
				'price'      => 4.99,
				'currency'   => 'EUR',
				'source'     => 'manual',
				'sku'        => $item->sku,
			)
		);
		$this->assertGreaterThan( 0, $original );

		$this->expectException( \InvalidArgumentException::class );
		$this->history->insert_correction(
			array(
				'product_id'    => $post_id,
				'price_type'    => 'regular',
				'price'         => 4.79,
				'currency'      => 'EUR',
				'source'        => 'manual',
				'correction_of' => $original,
			)
		);
	}

	/**
	 * A correction stores who recorded it and why.
	 *
	 * @return void
	 */
	public function test_correction_records_user_and_reason() {
		$post_id = $this->create_item( 'Sok 1 l', 'SOK-100', 3.00 );
		$item    = Plugin::instance()->catalog()->get_item( $post_id );

		$this->history->insert(
			array(
				'product_id' => $post_id,
				'price_type' => 'regular',
				'price'      => 3.00,
				'currency'   => 'EUR',
				'source'     => 'manual',
				'sku'        => $item->sku,
			)
		);

		$correction = $this->history->insert_correction(
			array(
				'product_id'        => $post_id,
				'price_type'        => 'regular',
				'price'             => 2.99,
				'currency'          => 'EUR',
				'source'            => 'manual',
				'sku'               => $item->sku,
				'correction_of'     => $this->history->latest( $post_id, 0, 'regular' )['id'],
				'correction_reason' => 'Supplier invoice check.',
				'created_by'        => 1,
			)
		);

		$this->assertGreaterThan( 0, $correction );

		$row = $this->history->get( $correction );

		$this->assertSame( 1, (int) $row['created_by'] );
		$this->assertSame( 'Supplier invoice check.', $row['correction_reason'] );
	}

	/**
	 * Rule configuration changes are audited.
	 *
	 * @return void
	 */
	public function test_rule_config_changes_are_audited() {
		RuleConfig::update( array( 'rule_version' => '2026-11-01' ), 1, 'Integration test change.' );

		$history = RuleConfig::history();
		$last    = end( $history );

		$this->assertSame( 'rule_version', $last['key'] );
		$this->assertSame( '2026-11-01', $last['new'] );
		$this->assertSame( 1, (int) $last['user_id'] );
		$this->assertSame( 'Integration test change.', $last['reason'] );
		$this->assertNotEmpty( $last['timestamp'] );
	}

	/**
	 * Operational log entries are queryable for the audit screen.
	 *
	 * @return void
	 */
	public function test_log_entries_are_queryable() {
		$logs = new LogRepository();
		$logs->insert( 'info', 'history_correction', 'Correction recorded.', array( 'user_id' => 1 ) );

		$rows = $logs->query( array( 'event' => 'history_correction' ) );

		$this->assertNotEmpty( $rows['items'] );
		$this->assertSame( 'history_correction', $rows['items'][0]['event'] );
		$this->assertSame( 1, (int) $rows['items'][0]['context']['user_id'] );
		$this->assertTrue( class_exists( AuditPage::class ) );
		$this->assertTrue( class_exists( PriceHistoryPage::class ) );
	}
}
