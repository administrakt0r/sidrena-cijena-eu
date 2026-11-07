<?php
/**
 * Global wrapper functions for themes and snippets.
 *
 * The only global functions the plugin introduces; all of them delegate to the
 * namespaced API in `src/Api/functions.php`.
 *
 * @package Titanworks\SidrenaCijena\Api
 */

defined( 'ABSPATH' ) || exit;

/**
 * Anchor price for an item.
 *
 * @param int $product_id   Product ID.
 * @param int $variation_id Variation ID.
 * @return float|null
 */
function twbce_cijena_get_anchor_price( $product_id, $variation_id = 0 ) {
	return \Titanworks\SidrenaCijena\Api\TemplateFunctions::get_anchor_price( $product_id, $variation_id );
}

/**
 * Lowest 30-day price for an item in an active special sale.
 *
 * @param int $product_id   Product ID.
 * @param int $variation_id Variation ID.
 * @return float|null
 */
function twbce_cijena_get_lowest_30_day_price( $product_id, $variation_id = 0 ) {
	return \Titanworks\SidrenaCijena\Api\TemplateFunctions::get_lowest_30_day_price( $product_id, $variation_id );
}

/**
 * Render the anchor price block.
 *
 * @param int          $product_id Product ID.
 * @param array<mixed> $args       Rendering arguments.
 * @return string
 */
function twbce_cijena_render_anchor_price( $product_id, array $args = array() ) {
	return \Titanworks\SidrenaCijena\Api\TemplateFunctions::render_anchor_price( $product_id, $args );
}
