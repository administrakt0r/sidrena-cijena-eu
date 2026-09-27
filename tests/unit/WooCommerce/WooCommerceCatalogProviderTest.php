<?php
/**
 * Tests for WooCommerce catalog product and variation identities.
 *
 * @package SidrenaCijenaEU
 */

use SidrenaCijena\WooCommerce\WooCommerceCatalogProvider;

/**
 * WooCommerce catalog provider tests.
 */
class WooCommerceCatalogProviderTest extends PHPUnit\Framework\TestCase {

	/**
	 * Reset the small WooCommerce product registry used by the unit stubs.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		require_once __DIR__ . '/../wc-stubs.php';
		$GLOBALS['sidrena_test_wc_products'] = array();
	}

	/**
	 * Variation expansion uses each variation's identity and price fields.
	 *
	 * @return void
	 */
	public function test_variable_catalog_expands_to_exact_variation_offers() {
		$this->register_product(
			90,
			array(
				'type'          => 'simple',
				'name'          => 'Simple item',
				'regular_price' => '6.00',
				'price'         => '6.00',
			)
		);
		$this->register_product(
			100,
			array(
				'type'     => 'variable',
				'name'     => 'Parent range',
				'price'    => '5.00',
				'children' => array( 101, 102 ),
			)
		);
		$this->register_product(
			101,
			array(
				'type'          => 'variation',
				'parent_id'     => 100,
				'name'          => 'Small',
				'sku'           => 'SMALL',
				'regular_price' => '12.00',
				'sale_price'    => '9.00',
				'price'         => '9.00',
			)
		);
		$this->register_product(
			102,
			array(
				'type'          => 'variation',
				'parent_id'     => 100,
				'name'          => 'Large',
				'sku'           => 'LARGE',
				'regular_price' => '20.00',
				'sale_price'    => '',
				'price'         => '20.00',
			)
		);

		$provider = new WooCommerceCatalogProvider();
		$simple   = $provider->get_items( array( 'include' => array( 90 ) ) );
		$parent   = $provider->get_items( array( 'include' => array( 100 ) ) );
		$items    = $provider->get_items(
			array(
				'include'            => array( 100 ),
				'per_page'           => 1,
				'include_variations' => true,
			)
		);

		$this->assertCount( 1, $simple );
		$this->assertSame( 90, $simple[0]->id );
		$this->assertSame( 0, $simple[0]->variation_id );
		$this->assertSame( 6.0, $simple[0]->current_price );
		$this->assertCount( 1, $parent );
		$this->assertSame( 100, $parent[0]->id );
		$this->assertSame( 0, $parent[0]->variation_id );
		$this->assertCount( 2, $items );
		$this->assertSame( 101, $items[0]->variation_id );
		$this->assertSame( 100, $items[0]->parent_id );
		$this->assertSame( 'SMALL', $items[0]->sku );
		$this->assertSame( 12.0, $items[0]->regular_price );
		$this->assertSame( 9.0, $items[0]->sale_price );
		$this->assertSame( 9.0, $items[0]->current_price );
		$this->assertSame( 102, $items[1]->variation_id );
		$this->assertSame( 20.0, $items[1]->current_price );
		$this->assertNull( $provider->get_variation( 999, 101 ) );
	}

	/**
	 * A variable parent with no purchasable child is not exposed as an offer.
	 *
	 * @return void
	 */
	public function test_variable_parent_without_purchasable_variations_is_omitted() {
		$this->register_product(
			200,
			array(
				'type'     => 'variable',
				'name'     => 'Unpurchasable parent',
				'price'    => '4.00',
				'children' => array( 201 ),
			)
		);
		$this->register_product(
			201,
			array(
				'type'        => 'variation',
				'parent_id'   => 200,
				'name'        => 'Unavailable size',
				'price'       => '',
				'purchasable' => false,
			)
		);

		$provider = new WooCommerceCatalogProvider();

		$this->assertSame(
			array(),
			$provider->get_items(
				array(
					'include'            => array( 200 ),
					'include_variations' => true,
				)
			)
		);
	}

	/**
	 * Iteration advances by WooCommerce product pages after an empty expansion.
	 *
	 * @return void
	 */
	public function test_iteration_continues_after_parent_expands_to_no_offers() {
		$this->register_product(
			300,
			array(
				'type'     => 'variable',
				'name'     => 'No offers',
				'price'    => '4.00',
				'children' => array( 301 ),
			)
		);
		$this->register_product(
			301,
			array(
				'type'        => 'variation',
				'parent_id'   => 300,
				'name'        => 'Unavailable',
				'price'       => '',
				'purchasable' => false,
			)
		);
		$this->register_product(
			400,
			array(
				'type'     => 'variable',
				'name'     => 'Has offers',
				'price'    => '5.00',
				'children' => array( 401 ),
			)
		);
		$this->register_product(
			401,
			array(
				'type'          => 'variation',
				'parent_id'     => 400,
				'name'          => 'Available',
				'regular_price' => '7.00',
				'price'         => '7.00',
			)
		);

		$provider = new WooCommerceCatalogProvider();
		$items    = iterator_to_array(
			$provider->iterate(
				array(
					'per_page'           => 1,
					'include_variations' => true,
				)
			)
		);

		$this->assertCount( 1, $items );
		$this->assertSame( 401, $items[0]->variation_id );
	}

	/**
	 * Register a product in the test WooCommerce registry.
	 *
	 * @param int                 $id   Product ID.
	 * @param array<string,mixed> $data Product data.
	 * @return void
	 */
	private function register_product( $id, array $data ) {
		$GLOBALS['sidrena_test_wc_products'][ (int) $id ] = array_merge(
			$data,
			array( 'id' => (int) $id )
		);
	}
}
