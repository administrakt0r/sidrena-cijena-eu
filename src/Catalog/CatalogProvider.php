<?php
/**
 * Catalog provider abstraction.
 *
 * The compliance core depends only on this interface, so WooCommerce is never
 * a hard requirement (FR-084, FR-091).
 *
 * @package Titanworks\SidrenaCijena\Catalog
 */

namespace Titanworks\SidrenaCijena\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies catalog items from a concrete source.
 */
interface CatalogProvider {

	/**
	 * Provider source identifier: `woocommerce` or `standalone`.
	 *
	 * @return string
	 */
	public function get_source();

	/**
	 * Whether the provider can currently serve items.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Fetch a single item by ID.
	 *
	 * @param int $id Item ID.
	 * @return CatalogItem|null
	 */
	public function get_item( $id );

	/**
	 * Fetch a variation.
	 *
	 * @param int $parent_id    Parent item ID.
	 * @param int $variation_id Variation ID.
	 * @return CatalogItem|null
	 */
	public function get_variation( $parent_id, $variation_id );

	/**
	 * Paginated item query.
	 *
	 * Supported args: `page`, `per_page`, `type` (`product|service|all`),
	 * `include` (IDs), `exclude` (IDs), `category` (slug), `search`,
	 * `orderby`, `order`, `parent` (parent ID), `include_variations`.
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return CatalogItem[]
	 */
	public function get_items( array $args = array() );

	/**
	 * Total number of items matching a query.
	 *
	 * @param array<mixed> $args Query arguments (without pagination).
	 * @return int
	 */
	public function count_items( array $args = array() );

	/**
	 * Stream items in batches.
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return \Generator<int,CatalogItem>
	 */
	public function iterate( array $args = array() );

	/**
	 * Categories available for legacy-treatment mapping.
	 *
	 * @return array<int,array{id:int,slug:string,name:string}>
	 */
	public function get_categories();

	/**
	 * Category slugs of an item.
	 *
	 * @param int $id Item ID.
	 * @return string[]
	 */
	public function get_item_categories( $id );
}
