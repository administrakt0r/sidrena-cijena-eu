<?php
/**
 * Tests for StandaloneCatalogProvider.
 *
 * @package SidrenaCijena\Tests\Unit\Catalog
 */

namespace SidrenaCijena\Tests\Unit\Catalog;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use WP_Post;

/**
 * @covers \SidrenaCijena\Catalog\StandaloneCatalogProvider
 */
class StandaloneCatalogProviderTest extends TestCase {

	/**
	 * Reset global test state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['sidrena_test_posts']          = array();
		$GLOBALS['sidrena_test_post_meta']      = array();
		$GLOBALS['sidrena_test_post_terms']     = array();
		$GLOBALS['sidrena_test_post_types']     = array( StandalonePostType::POST_TYPE );
		$GLOBALS['sidrena_test_taxonomy_terms'] = array();
	}

	/**
	 * Tear down global test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['sidrena_test_posts'],
			$GLOBALS['sidrena_test_post_meta'],
			$GLOBALS['sidrena_test_post_terms'],
			$GLOBALS['sidrena_test_post_types'],
			$GLOBALS['sidrena_test_taxonomy_terms'],
			$GLOBALS['sidrena_test_wp_query_found_posts']
		);
		parent::tearDown();
	}

	/**
	 * Provider identifies source as standalone and checks post type availability.
	 *
	 * @return void
	 */
	public function test_source_and_availability(): void {
		$provider = new StandaloneCatalogProvider();
		$this->assertSame( 'standalone', $provider->get_source() );
		$this->assertTrue( $provider->is_available() );

		$GLOBALS['sidrena_test_post_types'] = array();
		$this->assertFalse( $provider->is_available() );
	}

	/**
	 * get_item returns null for missing, non-standalone, or trashed posts.
	 *
	 * @return void
	 */
	public function test_get_item_missing_or_invalid(): void {
		$provider = new StandaloneCatalogProvider();
		$this->assertNull( $provider->get_item( 999 ) );

		$GLOBALS['sidrena_test_posts'][50] = new WP_Post(
			array(
				'ID'          => 50,
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'About Us',
			)
		);
		$this->assertNull( $provider->get_item( 50 ) );

		$GLOBALS['sidrena_test_posts'][51] = new WP_Post(
			array(
				'ID'          => 51,
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'trash',
				'post_title'  => 'Trashed Item',
			)
		);
		$this->assertNull( $provider->get_item( 51 ) );
	}

	/**
	 * get_item hydates a complete CatalogItem from post data, meta, and terms.
	 *
	 * @return void
	 */
	public function test_get_item_hydrates_product(): void {
		$id = 101;
		$GLOBALS['sidrena_test_posts'][ $id ] = new WP_Post(
			array(
				'ID'          => $id,
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Kravlji sir',
			)
		);

		$GLOBALS['sidrena_test_post_meta'][ $id ] = array(
			'_sidrena_regular_price' => '10.50',
			'_sidrena_sale_price'    => '8.50',
			'_sidrena_sku'           => 'SIR-01',
			'_sidrena_barcode'       => '3850123456789',
			'_sidrena_unit'          => 'kg',
			'_sidrena_unit_price'    => '8.50',
			'_sidrena_availability'  => 'available',
			'_sidrena_currency'      => 'EUR',
			'_sidrena_first_seen'    => '2026-01-15',
			'_sidrena_tax_inclusive' => 1,
		);

		$GLOBALS['sidrena_test_post_terms'][ $id ] = array(
			StandalonePostType::TAX_TYPE  => array( 'product' ),
			StandalonePostType::TAX_BRAND => array( array( 'name' => 'Sirana Gorski', 'slug' => 'sirana-gorski' ) ),
			'category'                    => array( array( 'name' => 'Mliječni proizvodi', 'slug' => 'mlijecni-proizvodi' ) ),
		);

		$provider = new StandaloneCatalogProvider();
		$item     = $provider->get_item( $id );

		$this->assertInstanceOf( CatalogItem::class, $item );
		$this->assertSame( 101, $item->id );
		$this->assertSame( 0, $item->variation_id );
		$this->assertSame( 'product', $item->type );
		$this->assertFalse( $item->is_service() );
		$this->assertSame( 'Kravlji sir', $item->name );
		$this->assertSame( 'SIR-01', $item->sku );
		$this->assertSame( 'Sirana Gorski', $item->brand );
		$this->assertSame( '3850123456789', $item->barcode );
		$this->assertSame( 'kg', $item->unit );
		$this->assertSame( 8.50, $item->unit_price );
		$this->assertSame( 'available', $item->availability );
		$this->assertSame( 'EUR', $item->currency );
		$this->assertSame( 10.50, $item->regular_price );
		$this->assertSame( 8.50, $item->sale_price );
		$this->assertSame( 8.50, $item->current_price );
		$this->assertSame( 8.50, $item->effective_price() );
		$this->assertTrue( $item->tax_inclusive );
		$this->assertSame( '2026-01-15', $item->first_seen );
		$this->assertSame( 'standalone', $item->source );
		$this->assertSame( array( 'mlijecni-proizvodi' ), $item->categories );
	}

	/**
	 * Service type classification sets is_service to true.
	 *
	 * @return void
	 */
	public function test_get_item_service_classification(): void {
		$id = 102;
		$GLOBALS['sidrena_test_posts'][ $id ] = new WP_Post(
			array(
				'ID'          => $id,
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Servis bicikla',
			)
		);

		$GLOBALS['sidrena_test_post_meta'][ $id ] = array(
			'_sidrena_regular_price' => '35.00',
			'_sidrena_sku'           => 'SRV-01',
		);

		$GLOBALS['sidrena_test_post_terms'][ $id ] = array(
			StandalonePostType::TAX_TYPE => array( 'service' ),
		);

		$provider = new StandaloneCatalogProvider();
		$item     = $provider->get_item( $id );

		$this->assertInstanceOf( CatalogItem::class, $item );
		$this->assertSame( 'service', $item->type );
		$this->assertTrue( $item->is_service() );
		$this->assertSame( 35.00, $item->regular_price );
		$this->assertNull( $item->sale_price );
		$this->assertSame( 35.00, $item->current_price );
	}

	/**
	 * Standalone provider does not support variations.
	 *
	 * @return void
	 */
	public function test_get_variation_always_returns_null(): void {
		$provider = new StandaloneCatalogProvider();
		$this->assertNull( $provider->get_variation( 101, 102 ) );
	}

	/**
	 * get_items filters and returns catalog items.
	 *
	 * @return void
	 */
	public function test_get_items_include_and_exclude(): void {
		$GLOBALS['sidrena_test_posts'][10] = new WP_Post(
			array(
				'ID'          => 10,
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Item A',
			)
		);
		$GLOBALS['sidrena_test_posts'][20] = new WP_Post(
			array(
				'ID'          => 20,
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Item B',
			)
		);

		$provider = new StandaloneCatalogProvider();

		$all = $provider->get_items();
		$this->assertCount( 2, $all );

		$included = $provider->get_items( array( 'include' => array( 10 ) ) );
		$this->assertCount( 1, $included );
		$this->assertSame( 10, $included[0]->id );

		$excluded = $provider->get_items( array( 'exclude' => array( 10 ) ) );
		$this->assertCount( 1, $excluded );
		$this->assertSame( 20, $excluded[0]->id );
	}

	/**
	 * count_items delegates to WP_Query.
	 *
	 * @return void
	 */
	public function test_count_items(): void {
		$GLOBALS['sidrena_test_wp_query_found_posts'] = 42;

		$provider = new StandaloneCatalogProvider();
		$this->assertSame( 42, $provider->count_items() );
	}

	/**
	 * get_categories returns mapped term descriptors.
	 *
	 * @return void
	 */
	public function test_get_categories(): void {
		$GLOBALS['sidrena_test_taxonomy_terms'] = array(
			(object) array(
				'term_id' => 3,
				'slug'    => 'pekara',
				'name'    => 'Pekara',
			),
		);

		$provider = new StandaloneCatalogProvider();
		$cats     = $provider->get_categories();

		$this->assertCount( 1, $cats );
		$this->assertSame( 3, $cats[0]['id'] );
		$this->assertSame( 'pekara', $cats[0]['slug'] );
		$this->assertSame( 'Pekara', $cats[0]['name'] );
	}

	/**
	 * iterate yields items across pagination pages.
	 *
	 * @return void
	 */
	public function test_iterate_yields_items(): void {
		$GLOBALS['sidrena_test_posts'][1] = new WP_Post(
			array(
				'ID'          => 1,
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'First',
			)
		);

		$provider = new StandaloneCatalogProvider();
		$yielded  = array();

		foreach ( $provider->iterate() as $item ) {
			$yielded[] = $item->id;
		}

		$this->assertSame( array( 1 ), $yielded );
	}
}
