<?php
/**
 * Catalog provider backed by the WooCommerce CRUD API.
 *
 * Reads products and variations exclusively through `wc_get_product()` and
 * `wc_get_products()`; native price fields are never written (FR-085).
 *
 * @package SidrenaCijena\WooCommerce
 */

namespace SidrenaCijena\WooCommerce;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies catalog items from WooCommerce.
 */
class WooCommerceCatalogProvider implements CatalogProvider {

	/**
	 * Meta keys checked for a barcode, in order.
	 *
	 * @var string[]
	 */
	protected $barcode_keys = array(
		'_sidrena_barcode',
		'_barcode',
		'barcode',
		'_ean',
		'ean',
		'_gtin',
		'gtin',
		'_alg_ean',
		'_wpm_gtin_code',
		'_ywbc_barcode_display_value',
		'_ts_gtin',
		'hwp_product_gtin',
		'hwp_var_gtin',
		'_upc',
		'upc',
		'_barkod',
		'barkod',
	);

	/**
	 * Resolved barcode values for items already hydrated in this request.
	 *
	 * @var array<int,string>
	 */
	protected $barcode_cache = array();

	/**
	 * {@inheritDoc}
	 */
	public function get_source() {
		return 'woocommerce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available() {
		return WooCommerceDetector::is_active();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $id Item ID.
	 * @return CatalogItem|null
	 */
	public function get_item( $id ) {
		if ( ! $this->is_available() ) {
			return null;
		}

		$product = wc_get_product( (int) $id );

		if ( ! $product ) {
			return null;
		}

		if ( 'trash' === $product->get_status() ) {
			return null;
		}

		return $this->build_item( $product );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $parent_id    Parent ID.
	 * @param int $variation_id Variation ID.
	 * @return CatalogItem|null
	 */
	public function get_variation( $parent_id, $variation_id ) {
		if ( ! $this->is_available() ) {
			return null;
		}

		$variation = wc_get_product( (int) $variation_id );

		if ( ! $variation || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== (int) $parent_id ) {
			return null;
		}

		return $this->build_item( $variation );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return CatalogItem[]
	 */
	public function get_items( array $args = array() ) {
		$page = $this->query_items_page( $args );

		return $page['items'];
	}

	/**
	 * Query one page while retaining the number of WooCommerce products fetched.
	 *
	 * The catalog can expand one variable product into many variation items or
	 * omit a variable parent that has no purchasable variations. Pagination must
	 * therefore follow the WooCommerce page size, not the expanded item count.
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return array{items:CatalogItem[],product_count:int}
	 */
	protected function query_items_page( array $args = array() ) {
		if ( ! $this->is_available() ) {
			return array(
				'items'         => array(),
				'product_count' => 0,
			);
		}

		$args = wp_parse_args(
			$args,
			array(
				'page'               => 1,
				'per_page'           => 100,
				'type'               => 'all',
				'include'            => array(),
				'exclude'            => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- WooCommerce wc_get_products argument.
				'category'           => '',
				'search'             => '',
				'orderby'            => 'title',
				'order'              => 'ASC',
				'parent'             => 0,
				'include_variations' => false,
			)
		);

		$query = array(
			'limit'    => max( 1, (int) $args['per_page'] ),
			'page'     => max( 1, (int) $args['page'] ),
			'status'   => 'publish',
			'orderby'  => 'title' === $args['orderby'] ? 'title' : 'ID',
			'order'    => 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC',
			'paginate' => false,
			'return'   => 'objects',
		);

		if ( ! empty( $args['parent'] ) ) {
			$query['parent'] = (int) $args['parent'];
			$query['type']   = 'variation';
		}

		if ( ! empty( $args['include'] ) ) {
			$query['include'] = array_map( 'intval', (array) $args['include'] );
		}

		if ( ! empty( $args['exclude'] ) ) {
			$query['exclude'] = array_map( 'intval', (array) $args['exclude'] ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- WooCommerce wc_get_products argument.
		}

		if ( ! empty( $args['search'] ) ) {
			$query['search'] = (string) $args['search'];
		}

		if ( ! empty( $args['category'] ) ) {
			$query['category'] = array( (string) $args['category'] );
		}

		$products = wc_get_products( $query );
		$products = is_array( $products ) ? $products : array();

		// Prime WordPress's metadata and term caches once for the full batch.
		// WooCommerce product getters and the fields below then share these caches.
		$cache_ids = array();
		foreach ( $products as $product ) {
			$cache_ids[] = (int) $product->get_id();

			if ( ! empty( $args['include_variations'] ) && $product->is_type( 'variable' ) ) {
				$cache_ids = array_merge( $cache_ids, array_map( 'intval', $product->get_children() ) );
			}
		}

		$cache_ids = array_values( array_unique( array_filter( $cache_ids ) ) );
		if ( ! empty( $cache_ids ) ) {
			update_meta_cache( 'post', $cache_ids );
			update_object_term_cache( $cache_ids, 'product' );
		}

		$items = array();

		foreach ( $products as $product ) {
			if ( ! $this->type_matches( $product, $args['type'] ) ) {
				continue;
			}

			if ( ! empty( $args['include_variations'] ) && $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( (int) $variation_id );

					if ( ! $variation || 'publish' !== $variation->get_status() ) {
						continue;
					}

					// A variation without a retail price cannot be purchased and is not an offer to publish.
					if ( '' === $variation->get_price( 'edit' ) && ! $variation->is_purchasable() ) {
						continue;
					}

					$items[] = $this->build_item( $variation );
				}

				continue;
			}

			$items[] = $this->build_item( $product );
		}

		return array(
			'items'         => $items,
			'product_count' => count( $products ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return int
	 */
	public function count_items( array $args = array() ) {
		if ( ! $this->is_available() ) {
			return 0;
		}

		$args = wp_parse_args(
			$args,
			array(
				'type'     => 'all',
				'include'  => array(),
				'exclude'  => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- WooCommerce count query parameter.
				'category' => '',
				'search'   => '',
			)
		);

		if ( 'all' === $args['type'] ) {
			$query = array(
				'limit'    => 1,
				'page'     => 1,
				'paginate' => true,
				'return'   => 'ids',
				'status'   => 'publish',
			);

			if ( ! empty( $args['include'] ) ) {
				$query['include'] = array_map( 'intval', (array) $args['include'] );
			}

			if ( ! empty( $args['exclude'] ) ) {
				$query['exclude'] = array_map( 'intval', (array) $args['exclude'] ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- WooCommerce count query parameter.
			}

			if ( ! empty( $args['search'] ) ) {
				$query['search'] = (string) $args['search'];
			}

			if ( ! empty( $args['category'] ) ) {
				$query['category'] = array( (string) $args['category'] );
			}

			$result = wc_get_products( $query );

			if ( is_object( $result ) && isset( $result->total ) ) {
				return (int) $result->total;
			}

			return is_array( $result ) ? count( $result ) : 0;
		}

		$count = 0;

		foreach ( $this->iterate( array_merge( $args, array( 'per_page' => 200 ) ) ) as $item ) {
			++$count;
		}

		return $count;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return \Generator<CatalogItem>
	 */
	public function iterate( array $args = array() ) {
		$args     = wp_parse_args( $args, array( 'per_page' => 200 ) );
		$per_page = max( 1, (int) $args['per_page'] );
		$page     = 1;

		do {
			$result = $this->query_items_page(
				array_merge(
					$args,
					array(
						'page'     => $page,
						'per_page' => $per_page,
					)
				)
			);
			$items  = $result['items'];

			foreach ( $items as $item ) {
				yield $item;
			}

			$found = (int) $result['product_count'];

			++$page;
		} while ( $found >= $per_page );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_categories() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$categories = array();

		foreach ( $terms as $term ) {
			$categories[] = array(
				'id'   => (int) $term->term_id,
				'slug' => (string) $term->slug,
				'name' => (string) $term->name,
			);
		}

		return $categories;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $id Item ID.
	 * @return string[]
	 */
	public function get_item_categories( $id ) {
		$slugs = wp_get_post_terms( (int) $id, 'product_cat', array( 'fields' => 'slugs' ) );

		return is_wp_error( $slugs ) ? array() : array_map( 'strval', $slugs );
	}

	/**
	 * Build a catalog item from a WooCommerce product or variation.
	 *
	 * @param \WC_Product $product Product.
	 * @return CatalogItem
	 */
	protected function build_item( $product ) {
		$id           = (int) $product->get_id();
		$parent_id    = (int) $product->get_parent_id();
		$is_variation = $product->is_type( 'variation' );

		$regular = $product->get_regular_price( 'edit' );
		$sale    = $product->get_sale_price( 'edit' );
		$current = $product->get_price( 'edit' );

		$regular = '' === $regular ? null : (float) $regular;
		$sale    = '' === $sale ? null : (float) $sale;
		$current = '' === $current ? null : (float) $current;

		$name = $product->get_name();

		if ( $product instanceof \WC_Product_Variation && function_exists( 'wc_get_formatted_variation' ) ) {
			$attributes = wc_get_formatted_variation( $product, true, false, true );

			if ( '' !== (string) $attributes ) {
				$name .= ' — ' . wp_strip_all_tags( (string) $attributes );
			}
		}

		$sku = (string) $product->get_sku();

		if ( '' === trim( $sku ) ) {
			$sku = $is_variation ? sprintf( 'WC-%d-V%d', $parent_id, $id ) : sprintf( 'WC-%d', $id );
		}

		$type = $this->get_item_type( $product );

		$brand       = '';
		$brand_terms = get_the_terms( $is_variation ? $parent_id : $id, 'product_brand' );

		if ( is_array( $brand_terms ) && ! empty( $brand_terms ) ) {
			$brand = (string) $brand_terms[0]->name;
		}

		$first_seen = get_post_meta( $id, '_sidrena_first_seen', true );
		$first_seen = $first_seen ? (string) $first_seen : get_the_date( 'Y-m-d', $id );

		return new CatalogItem(
			array(
				'id'            => $is_variation ? $parent_id : $id,
				'variation_id'  => $is_variation ? $id : 0,
				'parent_id'     => $is_variation ? $parent_id : 0,
				'type'          => $type,
				'name'          => (string) $name,
				'sku'           => $sku,
				'brand'         => $brand,
				'barcode'       => $this->get_barcode( $product ),
				'unit'          => (string) $this->get_meta_first( $product, array( '_sidrena_unit', '_unit' ) ),
				'unit_price'    => $this->get_meta_first( $product, array( '_sidrena_unit_price', '_unit_price' ) ),
				'currency'      => function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : 'EUR',
				'availability'  => $product->is_in_stock() ? 'available' : 'unavailable',
				'regular_price' => $regular,
				'sale_price'    => $sale,
				'current_price' => $current,
				'tax_inclusive' => function_exists( 'wc_prices_include_tax' ) ? (bool) wc_prices_include_tax() : true,
				'first_seen'    => $first_seen ? $first_seen : null,
				'categories'    => $this->get_item_categories( $is_variation ? $parent_id : $id ),
				'source'        => 'woocommerce',
			)
		);
	}

	/**
	 * Item type for a WooCommerce product.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	protected function get_item_type( $product ) {
		$id = (int) $product->get_id();

		if ( $product->is_type( 'variation' ) ) {
			$id = (int) $product->get_parent_id();
		}

		$type = get_post_meta( $id, '_sidrena_item_type', true );

		if ( 'service' === $type ) {
			return 'service';
		}

		/**
		 * Filters the catalog item type for a WooCommerce product.
		 *
		 * @param string      $type    `product` or `service`.
		 * @param \WC_Product $product Product.
		 */
		return 'service' === apply_filters( 'sidrena_cijena_wc_item_type', 'product', $product ) ? 'service' : 'product';
	}

	/**
	 * First non-empty barcode meta value.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	protected function get_barcode( $product ) {
		$id = (int) $product->get_id();
		if ( array_key_exists( $id, $this->barcode_cache ) ) {
			return $this->barcode_cache[ $id ];
		}

		/**
		 * Filters the meta keys inspected for a barcode.
		 *
		 * @param string[]    $keys    Meta keys.
		 * @param \WC_Product $product Product.
		 */
		$keys = apply_filters( 'sidrena_cijena_barcode_meta_keys', $this->barcode_keys, $product );

		foreach ( (array) $keys as $key ) {
			$value = $product->get_meta( $key );

			if ( ( '' === $value || null === $value ) && function_exists( 'get_post_meta' ) ) {
				$value = get_post_meta( $id, $key, true );
			}

			if ( '' !== $value && null !== $value ) {
				$this->barcode_cache[ $id ] = trim( (string) $value );
				return $this->barcode_cache[ $id ];
			}
		}

		foreach ( array( 'ean', 'barcode', 'gtin', 'upc', 'barkod' ) as $attr_name ) {
			$attr_val = $product->get_attribute( $attr_name );
			if ( '' !== trim( (string) $attr_val ) ) {
				$this->barcode_cache[ $id ] = trim( (string) $attr_val );
				return $this->barcode_cache[ $id ];
			}
		}

		$sku = trim( (string) $product->get_sku() );
		if ( '' !== $sku ) {
			/**
			 * Filters whether to fall back to the product SKU when no dedicated barcode meta is found.
			 *
			 * @param bool        $fallback Whether to fall back to SKU.
			 * @param string      $sku      Product SKU.
			 * @param \WC_Product $product  Product instance.
			 */
			$allow_sku_fallback = apply_filters( 'sidrena_cijena_barcode_fallback_to_sku', true, $sku, $product );
			if ( $allow_sku_fallback ) {
				$this->barcode_cache[ $id ] = $sku;
				return $this->barcode_cache[ $id ];
			}
		}

		$this->barcode_cache[ $id ] = '';
		return $this->barcode_cache[ $id ];
	}

	/**
	 * First non-empty meta value from a list of keys.
	 *
	 * @param \WC_Product $product Product.
	 * @param string[]    $keys    Meta keys.
	 * @return mixed
	 */
	protected function get_meta_first( $product, array $keys ) {
		$id = (int) $product->get_id();

		foreach ( $keys as $key ) {
			$value = get_post_meta( $id, $key, true );

			if ( '' !== $value && null !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Whether a product matches a requested type filter.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $type    Requested type.
	 * @return bool
	 */
	protected function type_matches( $product, $type ) {
		if ( ! $type || 'all' === $type ) {
			return true;
		}

		return $this->get_item_type( $product ) === $type;
	}
}
