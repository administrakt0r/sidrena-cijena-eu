<?php
/**
 * Integration tests for sales locations.
 *
 * @package SidrenaCijena\Tests\Integration
 */

use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Export\GenerationService;
use SidrenaCijena\Export\PriceListBuilder;
use SidrenaCijena\Plugin;

/**
 * Covers location CRUD, uniqueness, applicability and per-location generation.
 */
class Test_Locations extends WP_UnitTestCase {

	/**
	 * Location repository.
	 *
	 * @var LocationRepository
	 */
	protected $locations;

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

		foreach ( array( 'sidrena_price_history', 'sidrena_locations', 'sidrena_special_sales', 'sidrena_publications' ) as $suffix ) {
			$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . $suffix ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$this->locations = new LocationRepository();
	}

	/**
	 * Create a catalog item with a category.
	 *
	 * @param string $name     Name.
	 * @param string $sku      SKU.
	 * @param float  $price    Regular price.
	 * @param string $category Category slug.
	 * @return int
	 */
	protected function create_item( $name, $sku, $price, $category = '' ) {
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

		if ( '' !== $category ) {
			wp_set_object_terms( $post_id, $category, 'category' );
		}

		// FR-007: publication is blocked until the anchor price is recorded.
		$item = Plugin::instance()->catalog()->get_item( $post_id );
		Plugin::instance()->anchors()->record( $item, $price, array( 'source' => 'test' ) );

		return $post_id;
	}

	/**
	 * Build a location row.
	 *
	 * @param string       $identifier Identifier.
	 * @param array<mixed> $overrides  Overrides.
	 * @return array<mixed>
	 */
	protected function location_data( $identifier, array $overrides = array() ) {
		return array_merge(
			array(
				'name'       => 'Prodavaonica ' . $identifier,
				'type'       => 'prodavaonica',
				'address'    => 'Vlaška 68',
				'city'       => 'Zagreb',
				'identifier' => $identifier,
				'active'     => 1,
			),
			$overrides
		);
	}

	/**
	 * Locations can be created, updated and found by identifier.
	 *
	 * @return void
	 */
	public function test_location_crud_and_identifier_uniqueness() {
		$id = $this->locations->insert( $this->location_data( 'p1' ) );

		$this->assertGreaterThan( 0, $id );

		$location = $this->locations->get( $id );

		$this->assertSame( 'P1', $location['identifier'] );
		$this->assertSame( 'prodavaonica', $location['type'] );

		$found = $this->locations->find_by_identifier( 'P1' );

		$this->assertSame( $id, (int) $found['id'] );

		$this->assertTrue( $this->locations->update( $id, array( 'name' => 'Novi naziv' ) ) );
		$this->assertSame( 'Novi naziv', $this->locations->get( $id )['name'] );

		$this->assertSame( 0, $this->locations->insert( $this->location_data( 'P1' ) ) );
		$this->assertSame( 1, count( $this->locations->all() ) );
	}

	/**
	 * Sequence counters are independent per location.
	 *
	 * @return void
	 */
	public function test_sequence_counters_are_per_location() {
		$first  = $this->locations->insert( $this->location_data( 'P1' ) );
		$second = $this->locations->insert( $this->location_data( 'P2' ) );

		$this->assertSame( 1, $this->locations->next_sequence( $first ) );
		$this->assertSame( 2, $this->locations->next_sequence( $first ) );
		$this->assertSame( 1, $this->locations->next_sequence( $second ) );
	}

	/**
	 * Applicability filtering honours include and exclude modes.
	 *
	 * @return void
	 */
	public function test_applicability_filters_items() {
		$this->create_item( 'Kava', 'KAV-1', 5.00, 'kava' );
		$this->create_item( 'Čaj', 'CAJ-1', 4.00, 'caj' );

		$builder = new PriceListBuilder( Plugin::instance()->catalog(), Plugin::instance()->history(), Plugin::instance()->special_sales() );

		$include = $builder->build(
			$this->location_data(
				'P1',
				array(
					'applies_to' => array(
						'mode'       => 'include',
						'categories' => array( 'kava' ),
					),
				)
			)
		);

		$this->assertSame( 1, $include['meta']['count'] );
		$this->assertSame( 'KAV-1', $include['items'][0]['sifra'] );

		$exclude = $builder->build(
			$this->location_data(
				'P2',
				array(
					'applies_to' => array(
						'mode'       => 'exclude',
						'categories' => array( 'kava' ),
					),
				)
			)
		);

		$this->assertSame( 1, $exclude['meta']['count'] );
		$this->assertSame( 'CAJ-1', $exclude['items'][0]['sifra'] );
	}

	/**
	 * Inactive locations are excluded and every active location gets its own file.
	 *
	 * @return void
	 */
	public function test_generation_skips_inactive_locations() {
		$this->create_item( 'Kava', 'KAV-1', 5.00, 'kava' );

		$active   = $this->locations->insert( $this->location_data( 'P1' ) );
		$webshop  = $this->locations->insert(
			$this->location_data(
				'P2',
				array(
					'name'              => 'Webshop',
					'type'              => 'webshop',
					'price_list_config' => array(
						'formats'   => array( 'csv' ),
						'separator' => '-',
						'case'      => 'lower',
					),
				)
			)
		);
		$inactive = $this->locations->insert( $this->location_data( 'P3', array( 'active' => 0 ) ) );

		$service = new GenerationService(
			Plugin::instance()->catalog(),
			Plugin::instance()->locations(),
			Plugin::instance()->publications(),
			Plugin::instance()->history(),
			Plugin::instance()->special_sales()
		);

		$results = $service->generate_all(
			array(
				'source' => 'manual',
				'force'  => true,
			)
		);

		$this->assertArrayHasKey( $active, $results );
		$this->assertArrayHasKey( $webshop, $results );
		$this->assertArrayNotHasKey( $inactive, $results );

		$first = $results[ $active ]['csv'];

		$this->assertIsArray( $first );
		$this->assertMatchesRegularExpression( '/^PRODAVAONICA_VLASKA68_ZAGREB_P1_[0-9]{2}_[0-9]{8}_[0-9]{4}\.CSV$/', $first['filename'] );

		$this->assertArrayNotHasKey( 'xml', $results[ $webshop ] );
		$this->assertMatchesRegularExpression( '/^webshop-vlaska68-zagreb-p2-[0-9]{2}-[0-9]{8}-[0-9]{4}\.csv$/', $results[ $webshop ]['csv']['filename'] );
	}

	/**
	 * Deleting a location without publications removes it.
	 *
	 * @return void
	 */
	public function test_delete_removes_location_without_publications() {
		$id = $this->locations->insert( $this->location_data( 'P1' ) );

		$this->assertTrue( $this->locations->delete( $id ) );
		$this->assertNull( $this->locations->get( $id ) );
	}
}
