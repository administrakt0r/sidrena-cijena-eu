<?php
/**
 * Global wrapper functions for themes and snippets.
 *
 * The only global functions the plugin introduces; all of them delegate to the
 * namespaced API in `src/Api/functions.php`.
 *
 * @package SidrenaCijena\Api
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'sidrena_cijena_get_anchor_price' ) ) {
	/**
	 * Anchor price for an item.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return float|null
	 */
	function sidrena_cijena_get_anchor_price( $product_id, $variation_id = 0 ) {
		return \SidrenaCijena\Api\TemplateFunctions::get_anchor_price( $product_id, $variation_id );
	}
}

if ( ! function_exists( 'sidrena_cijena_get_lowest_30_day_price' ) ) {
	/**
	 * Lowest 30-day price for an item in an active special sale.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return float|null
	 */
	function sidrena_cijena_get_lowest_30_day_price( $product_id, $variation_id = 0 ) {
		return \SidrenaCijena\Api\TemplateFunctions::get_lowest_30_day_price( $product_id, $variation_id );
	}
}

if ( ! function_exists( 'sidrena_cijena_render_anchor_price' ) ) {
	/**
	 * Render the anchor price block.
	 *
	 * @param int          $product_id Product ID.
	 * @param array<mixed> $args       Rendering arguments.
	 * @return string
	 */
	function sidrena_cijena_render_anchor_price( $product_id, array $args = array() ) {
		return \SidrenaCijena\Api\TemplateFunctions::render_anchor_price( $product_id, $args );
	}
}
