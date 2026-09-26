<?php
/**
 * Minimal WooCommerce runtime stubs for the unit test suite.
 *
 * WooCommerce is never installed for unit tests; the display and recorder
 * classes only need a handful of functions and the `WC_Product` type. Loaded
 * on demand by the test files that need it so the rest of the suite stays
 * provider-neutral. Each definition is guarded: WordPress and WooCommerce may
 * already be loaded (PHPStan bootstrap, custom runners).
 *
 * @package SidrenaCijena\Tests\Unit
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['sidrena_test_wc_products']       = array();
$GLOBALS['sidrena_test_is_product']        = true;
$GLOBALS['sidrena_test_queried_object_id'] = 0;

if ( ! class_exists( 'WooCommerce' ) ) {

	/**
	 * Marker class so `WooCommerceDetector::is_active()` reports true.
	 */
	class WooCommerce {}
}

if ( ! class_exists( 'WC_Product' ) ) {

	/**
	 * Data-driven product stub covering the fields the plugin reads.
	 */
	class WC_Product {

		/**
		 * Product field values.
		 *
		 * @var array<string,mixed>
		 */
		protected $data = array();

		/**
		 * Constructor.
		 *
		 * @param array<string,mixed> $data Field values.
		 */
		public function __construct( array $data = array() ) {
			$this->data = $data;
		}

		/**
		 * Product ID.
		 *
		 * @return int
		 */
		public function get_id() {
			return isset( $this->data['id'] ) ? (int) $this->data['id'] : 0;
		}

		/**
		 * Parent product ID.
		 *
		 * @return int
		 */
		public function get_parent_id() {
			return isset( $this->data['parent_id'] ) ? (int) $this->data['parent_id'] : 0;
		}

		/**
		 * Whether the product is of a type.
		 *
		 * @param string $type Product type.
		 * @return bool
		 */
		public function is_type( $type ) {
			return isset( $this->data['type'] ) && $type === $this->data['type'];
		}

		/**
		 * Raw regular price.
		 *
		 * @param string $context Price context.
		 * @return string
		 */
		public function get_regular_price( $context = 'view' ) {
			unset( $context );

			return isset( $this->data['regular_price'] ) ? (string) $this->data['regular_price'] : '';
		}

		/**
		 * Raw sale price.
		 *
		 * @param string $context Price context.
		 * @return string
		 */
		public function get_sale_price( $context = 'view' ) {
			unset( $context );

			return isset( $this->data['sale_price'] ) ? (string) $this->data['sale_price'] : '';
		}

		/**
		 * Current product price.
		 *
		 * @param string $context Price context.
		 * @return string
		 */
		public function get_price( $context = 'view' ) {
			unset( $context );

			return isset( $this->data['price'] ) ? (string) $this->data['price'] : $this->get_regular_price( 'edit' );
		}

		/**
		 * Whether WooCommerce currently applies the sale price.
		 *
		 * @param string $context Price context.
		 * @return bool
		 */
		public function is_on_sale( $context = 'view' ) {
			unset( $context );

			return ! empty( $this->data['on_sale'] );
		}

		/**
		 * Sale start date.
		 *
		 * @param string $context Price context.
		 * @return mixed
		 */
		public function get_date_on_sale_from( $context = 'view' ) {
			unset( $context );

			return isset( $this->data['date_on_sale_from'] ) ? $this->data['date_on_sale_from'] : null;
		}

		/**
		 * Sale end date.
		 *
		 * @param string $context Price context.
		 * @return mixed
		 */
		public function get_date_on_sale_to( $context = 'view' ) {
			unset( $context );

			return isset( $this->data['date_on_sale_to'] ) ? $this->data['date_on_sale_to'] : null;
		}
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {

	/**
	 * Fetch a product from the test registry.
	 *
	 * @param int $product_id Product ID.
	 * @return WC_Product|false
	 */
	function wc_get_product( $product_id ) {
		$product_id = (int) $product_id;

		if ( ! isset( $GLOBALS['sidrena_test_wc_products'][ $product_id ] ) ) {
			return false;
		}

		return new WC_Product( $GLOBALS['sidrena_test_wc_products'][ $product_id ] );
	}
}

if ( ! function_exists( 'is_product' ) ) {

	/**
	 * Whether the current request is a single product page.
	 *
	 * @return bool
	 */
	function is_product() {
		return ! empty( $GLOBALS['sidrena_test_is_product'] );
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {

	/**
	 * Current queried object ID.
	 *
	 * @return int
	 */
	function get_queried_object_id() {
		return isset( $GLOBALS['sidrena_test_queried_object_id'] ) ? (int) $GLOBALS['sidrena_test_queried_object_id'] : 0;
	}
}

if ( ! function_exists( 'wc_get_products' ) ) {

	/**
	 * Retrieve products matching query.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array<int,int>|object
	 */
	function wc_get_products( $args = array() ) {
		$items = isset( $GLOBALS['sidrena_test_wc_products'] ) ? (array) $GLOBALS['sidrena_test_wc_products'] : array();

		if ( ! empty( $args['paginate'] ) ) {
			return (object) array(
				'total'         => count( $items ),
				'max_num_pages' => 1,
				'products'      => array_keys( $items ),
			);
		}

		return array_keys( $items );
	}
}
