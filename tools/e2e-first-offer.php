<?php
/**
 * Real WooCommerce lifecycle assertions for the disposable E2E shop.
 *
 * @package Titanworks\SidrenaCijena\Tests
 */

use Titanworks\SidrenaCijena\Compliance\RuleConfig;
use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Support\Dates;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 8889 !== (int) wp_parse_url( home_url(), PHP_URL_PORT ) ) {
	exit( 'This fixture requires the disposable E2E shop on port 8889.' );
}

$twbce_first_offer_ids   = array();
$twbce_first_offer_rules = RuleConfig::all();
$twbce_first_offer_check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

try {
	RuleConfig::update( array( 'general_reference_date' => '2020-01-01' ) );
	$product = new WC_Product_Simple();
	$product->set_name( 'E2E witnessed first offer' );
	$product->set_status( 'draft' );
	$product->set_regular_price( '17.25' );
	$product->save();
	$twbce_first_offer_ids[] = $product->get_id();
	$twbce_first_offer_check( ! Plugin::instance()->history()->latest( $product->get_id(), 0, 'anchor' ), 'Draft must not have an anchor.' );
	$product->set_status( 'publish' );
	$product->save();
	$anchor = Plugin::instance()->history()->latest( $product->get_id(), 0, 'anchor' );
	$twbce_first_offer_check( $anchor && 17.25 === (float) $anchor['price'], 'First publication must capture its actual retail price.' );
	$twbce_first_offer_check( Dates::today() === $anchor['reference_date'], 'First offer uses the observed site date.' );
	$product->set_regular_price( '22.00' );
	$product->save();
	$twbce_first_offer_check( $anchor['id'] === Plugin::instance()->history()->latest( $product->get_id(), 0, 'anchor' )['id'], 'Editing must preserve the original anchor.' );

	$parent = new WC_Product_Variable();
	$parent->set_name( 'E2E first-offer variations' );
	$parent->set_status( 'draft' );
	$parent->save();
	$twbce_first_offer_ids[] = $parent->get_id();
	$variations = array();
	foreach ( array( '11.00', '29.00' ) as $price ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_status( 'publish' );
		$variation->set_regular_price( $price );
		$variation->save();
		$twbce_first_offer_ids[] = $variation->get_id();
		$variations[] = $variation;
		$twbce_first_offer_check( ! Plugin::instance()->history()->latest( $parent->get_id(), $variation->get_id(), 'anchor' ), 'Draft parent must not publish variation evidence.' );
	}
	wc_delete_product_transients( $parent->get_id() );
	$parent = wc_get_product( $parent->get_id() );
	$parent->set_status( 'publish' );
	$parent->save();
	foreach ( $variations as $variation ) {
		$anchor = Plugin::instance()->history()->latest( $parent->get_id(), $variation->get_id(), 'anchor' );
		$twbce_first_offer_check( $anchor && (float) $variation->get_price() === (float) $anchor['price'], 'Each variation needs its own exact price and identity.' );
	}
	$twbce_first_offer_check( ! Plugin::instance()->history()->latest( $parent->get_id(), 0, 'anchor' ), 'Variable parent must not receive a variation anchor.' );

	$imported = new WC_Product_Simple();
	$imported->set_name( 'E2E imported historical offer' );
	$imported->set_status( 'publish' );
	$imported->set_regular_price( '40.00' );
	$imported = apply_filters( 'woocommerce_product_import_pre_insert_product_object', $imported, array() );
	$imported->save();
	$twbce_first_offer_ids[] = $imported->get_id();
	$twbce_first_offer_check( ! Plugin::instance()->history()->latest( $imported->get_id(), 0, 'anchor' ), 'Importing an existing catalog must not invent first-offer evidence.' );
	$imported = wc_get_product( $imported->get_id() );
	$imported->set_regular_price( '42.00' );
	$imported->save();
	$twbce_first_offer_check( ! Plugin::instance()->history()->latest( $imported->get_id(), 0, 'anchor' ), 'Editing an imported product must not backfill an anchor.' );
	WP_CLI::success( 'Observed first offers: simple, draft, immutable updates, variations and import exclusion verified.' );
} finally {
	RuleConfig::update( $twbce_first_offer_rules );
	foreach ( array_reverse( $twbce_first_offer_ids ) as $id ) {
		wp_delete_post( $id, true );
	}
}
