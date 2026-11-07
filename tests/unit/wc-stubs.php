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
 * @package Titanworks\SidrenaCijena\Tests\Unit
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['twbce_test_wc_products']       = array();
$GLOBALS['twbce_test_is_product']        = true;
$GLOBALS['twbce_test_queried_object_id'] = 0;

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
		 * Product post status.
		 *
		 * @return string
		 */
		public function get_status() {
			return isset( $this->data['status'] ) ? (string) $this->data['status'] : 'publish';
		}

		/**
		 * Product display name.
		 *
		 * @return string
		 */
		public function get_name() {
			return isset( $this->data['name'] ) ? (string) $this->data['name'] : '';
		}

		/**
		 * Product SKU.
		 *
		 * @return string
		 */
		public function get_sku() {
			return isset( $this->data['sku'] ) ? (string) $this->data['sku'] : '';
		}

		/**
		 * Product attribute value.
		 *
		 * @param string $attribute Attribute name.
		 * @return string
		 */
		public function get_attribute( $attribute ) {
			if ( isset( $this->data['attributes'][ $attribute ] ) ) {
				return (string) $this->data['attributes'][ $attribute ];
			}
			return '';
		}

		/**
		 * Child variation IDs.
		 *
		 * @return int[]
		 */
		public function get_children() {
			return isset( $this->data['children'] ) ? array_map( 'intval', (array) $this->data['children'] ) : array();
		}

		/**
		 * Visible child variation IDs.
		 *
		 * @return int[]
		 */
		public function get_visible_children() {
			if ( isset( $this->data['visible_children'] ) ) {
				return array_map( 'intval', (array) $this->data['visible_children'] );
			}
			return $this->get_children();
		}

		/**
		 * Whether the product can currently be purchased.
		 *
		 * @return bool
		 */
		public function is_purchasable() {
			if ( array_key_exists( 'purchasable', $this->data ) ) {
				return (bool) $this->data['purchasable'];
			}

			return '' !== $this->get_price( 'edit' );
		}

		/**
		 * Whether stock is available.
		 *
		 * @return bool
		 */
		public function is_in_stock() {
			return ! array_key_exists( 'in_stock', $this->data ) || (bool) $this->data['in_stock'];
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

		/**
		 * Product metadata.
		 *
		 * @param string $key Meta key.
		 * @param bool   $single Return a single value.
		 * @return mixed
		 */
		public function get_meta( $key, $single = true ) {
			unset( $single );

			return isset( $this->data['meta'][ $key ] ) ? $this->data['meta'][ $key ] : '';
		}

		/**
		 * Update product metadata.
		 *
		 * @param string $key Meta key.
		 * @param mixed  $value Meta value.
		 * @return void
		 */
		public function update_meta_data( $key, $value ) {
			if ( ! isset( $this->data['meta'] ) || ! is_array( $this->data['meta'] ) ) {
				$this->data['meta'] = array();
			}

			$this->data['meta'][ $key ] = $value;

			if ( ! empty( $this->data['id'] ) ) {
				$id = (int) $this->data['id'];
				if ( ! isset( $GLOBALS['twbce_test_wc_products'][ $id ]['meta'] ) ) {
					$GLOBALS['twbce_test_wc_products'][ $id ]['meta'] = array();
				}
				$GLOBALS['twbce_test_wc_products'][ $id ]['meta'][ $key ] = $value;
			}
		}
	}
}

if ( ! class_exists( 'WC_Product_Variation' ) && class_exists( 'WC_Product' ) ) {

	/**
	 * Variation marker used by catalog item hydration.
	 */
	class WC_Product_Variation extends WC_Product {}
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

		if ( ! isset( $GLOBALS['twbce_test_wc_products'][ $product_id ] ) ) {
			return false;
		}

		$data = $GLOBALS['twbce_test_wc_products'][ $product_id ];

		return isset( $data['type'] ) && 'variation' === $data['type']
			? new WC_Product_Variation( $data )
			: new WC_Product( $data );
	}
}

if ( ! function_exists( 'is_product' ) ) {

	/**
	 * Whether the current request is a single product page.
	 *
	 * @return bool
	 */
	function is_product() {
		return ! empty( $GLOBALS['twbce_test_is_product'] );
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {

	/**
	 * Current queried object ID.
	 *
	 * @return int
	 */
	function get_queried_object_id() {
		return isset( $GLOBALS['twbce_test_queried_object_id'] ) ? (int) $GLOBALS['twbce_test_queried_object_id'] : 0;
	}
}

if ( ! function_exists( 'wc_get_products' ) ) {

	/**
	 * Retrieve products matching query.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array<int,int|WC_Product>|object
	 */
	function wc_get_products( $args = array() ) {
		$items = isset( $GLOBALS['twbce_test_wc_products'] ) ? (array) $GLOBALS['twbce_test_wc_products'] : array();
		$ids   = array_keys( $items );

		if ( isset( $args['include'] ) ) {
			$ids = array_values( array_intersect( $ids, array_map( 'intval', (array) $args['include'] ) ) );
		}

		if ( isset( $args['exclude'] ) ) {
			$ids = array_values( array_diff( $ids, array_map( 'intval', (array) $args['exclude'] ) ) );
		}

		if ( isset( $args['parent'] ) ) {
			$parent_id = (int) $args['parent'];
			$ids       = array_values(
				array_filter(
					$ids,
					static function ( $id ) use ( $items, $parent_id ) {
						return isset( $items[ $id ]['parent_id'] ) && (int) $items[ $id ]['parent_id'] === $parent_id;
					}
				)
			);
		}

		if ( isset( $args['type'] ) ) {
			$type = (array) $args['type'];
			$ids  = array_values(
				array_filter(
					$ids,
					static function ( $id ) use ( $items, $type ) {
						return isset( $items[ $id ]['type'] ) && in_array( $items[ $id ]['type'], $type, true );
					}
				)
			);
		} else {
			$ids = array_values(
				array_filter(
					$ids,
					static function ( $id ) use ( $items ) {
						return ! isset( $items[ $id ]['type'] ) || 'variation' !== $items[ $id ]['type'];
					}
				)
			);
		}

		if ( isset( $args['status'] ) ) {
			$statuses = (array) $args['status'];
			$ids      = array_values(
				array_filter(
					$ids,
					static function ( $id ) use ( $items, $statuses ) {
						$status = isset( $items[ $id ]['status'] ) ? $items[ $id ]['status'] : 'publish';
						return in_array( $status, $statuses, true );
					}
				)
			);
		}

		if ( 'DESC' === strtoupper( isset( $args['order'] ) ? (string) $args['order'] : 'ASC' ) ) {
			$ids = array_reverse( $ids );
		}

		$total = count( $ids );
		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : -1;
		$page  = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		if ( $limit > 0 ) {
			$ids = array_slice( $ids, ( $page - 1 ) * $limit, $limit );
		}

		if ( ! empty( $args['paginate'] ) ) {
			return (object) array(
				'total'         => $total,
				'max_num_pages' => $limit > 0 ? (int) ceil( $total / $limit ) : 1,
				'products'      => $ids,
			);
		}

		if ( isset( $args['return'] ) && 'ids' === $args['return'] ) {
			return array_map( 'intval', $ids );
		}

		return array_map(
			static function ( $id ) use ( $items ) {
				$data = $items[ $id ];

				return isset( $data['type'] ) && 'variation' === $data['type']
					? new WC_Product_Variation( $data )
					: new WC_Product( $data );
			},
			$ids
		);
	}
}

if ( ! function_exists( 'update_meta_cache' ) ) {
	function update_meta_cache( $meta_type, $object_ids ) {
		unset( $meta_type, $object_ids );
		return array();
	}
}

if ( ! function_exists( 'update_object_term_cache' ) ) {
	function update_object_term_cache( $object_ids, $object_type ) {
		unset( $object_ids, $object_type );
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$products = isset( $GLOBALS['twbce_test_wc_products'] ) ? (array) $GLOBALS['twbce_test_wc_products'] : array();
		$value    = isset( $products[ (int) $post_id ]['meta'][ $key ] ) ? $products[ (int) $post_id ]['meta'][ $key ] : '';
		return $single ? $value : array( $value );
	}
}

if ( ! function_exists( 'get_the_terms' ) ) {
	function get_the_terms( $post_id, $taxonomy ) {
		unset( $post_id, $taxonomy );
		return array();
	}
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( $post_id, $taxonomy, $args = array() ) {
		unset( $post_id, $taxonomy, $args );
		return array();
	}
}

if ( ! function_exists( 'get_the_date' ) ) {
	function get_the_date( $format = '', $post = null ) {
		unset( $format, $post );
		return '2026-01-01';
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	function get_woocommerce_currency() {
		return 'EUR';
	}
}

if ( ! function_exists( 'wc_prices_include_tax' ) ) {
	function wc_prices_include_tax() {
		return true;
	}
}

if ( ! function_exists( 'wc_get_price_to_display' ) ) {
	/**
	 * Tax-adjusted price stub for testing.
	 *
	 * @param WC_Product   $product Product.
	 * @param array<mixed> $args    Price arguments.
	 * @return float
	 */
	function wc_get_price_to_display( $product, $args = array() ) {
		$price      = isset( $args['price'] ) ? (float) $args['price'] : (float) $product->get_price();
		$multiplier = isset( $GLOBALS['twbce_test_tax_multiplier'] ) ? (float) $GLOBALS['twbce_test_tax_multiplier'] : 1.0;
		return (float) ( $price * $multiplier );
	}
}
