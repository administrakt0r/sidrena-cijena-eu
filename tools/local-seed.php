<?php
/**
 * Demo seed for ./local.sh - run inside wp-env, never on production.
 *
 * Usage: wp-env run cli wp eval-file /var/www/html/wp-content/plugins/sidrena-cijena/tools/local-seed.php
 *
 * Creates Croatian demo products (WooCommerce), records anchor prices,
 * starts one classified sale and generates the price lists.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	echo "WooCommerce is not active - aborting demo seed.\n";
	exit( 1 );
}

$plugin = \SidrenaCijena\Plugin::instance();

/**
 * Create a product_cat term if missing.
 *
 * @param string $name Name.
 * @param string $slug Slug.
 * @return void
 */
function sidrena_demo_term( $name, $slug ) {
	if ( ! term_exists( $slug, 'product_cat' ) ) {
		wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
		echo "Category created: {$name}\n";
	}
}

sidrena_demo_term( 'Hrana', 'hrana' );
sidrena_demo_term( 'Čišćenje', 'ciscenje' );
sidrena_demo_term( 'Odjeća', 'odjeca' );

// Legacy (2025) categories for the demo shop.
\SidrenaCijena\Compliance\RuleConfig::update(
	array( 'legacy_category_slugs' => array( 'hrana', 'ciscenje' ) ),
	null,
	'local demo seed'
);

/**
 * Create a simple product unless its SKU already exists.
 *
 * @param array $args Keys: name, sku, regular, sale, cats, stock.
 * @return int Product ID.
 */
function sidrena_demo_product( $args ) {
	$existing = wc_get_product_id_by_sku( $args['sku'] );

	if ( $existing ) {
		echo "Product exists, skipping: {$args['sku']}\n";
		return (int) $existing;
	}

	$product = new WC_Product_Simple();
	$product->set_name( $args['name'] );
	$product->set_sku( $args['sku'] );
	$product->set_regular_price( (string) $args['regular'] );

	if ( isset( $args['sale'] ) ) {
		$product->set_sale_price( (string) $args['sale'] );
	}

	$product->set_stock_status( isset( $args['stock'] ) ? $args['stock'] : 'instock' );

	if ( ! empty( $args['cats'] ) ) {
		$product->set_category_ids( array_map( 'intval', $args['cats'] ) );
	}

	$id = $product->save();
	echo "Product created: {$args['name']} (#{$id})\n";

	return (int) $id;
}

/**
 * Record an anchor unless one already exists.
 *
 * @param int   $product_id  Product ID.
 * @param int   $variation_id Variation ID.
 * @param float $price Anchor price.
 * @return void
 */
function sidrena_demo_anchor( $product_id, $variation_id, $price ) {
	$plugin = \SidrenaCijena\Plugin::instance();

	if ( $variation_id ) {
		$item = $plugin->catalog()->get_variation( $product_id, $variation_id );
	} else {
		$item = $plugin->catalog()->get_item( $product_id );
	}

	if ( ! $item ) {
		echo "Cannot load catalog item {$product_id}:{$variation_id}\n";
		return;
	}

	if ( null !== $plugin->anchors()->get_anchor_price( $item ) ) {
		echo "Anchor exists, skipping: {$product_id}:{$variation_id}\n";
		return;
	}

	$result = $plugin->anchors()->record( $item, $price, array( 'source' => 'manual' ) );

	if ( is_wp_error( $result ) ) {
		echo 'Anchor failed for ' . $product_id . ':' . $variation_id . ' - ' . $result->get_error_message() . "\n";
	} else {
		echo "Anchor recorded: {$product_id}:{$variation_id} = {$price}\n";
	}
}

$hrana    = get_term_by( 'slug', 'hrana', 'product_cat' );
$ciscenje = get_term_by( 'slug', 'ciscenje', 'product_cat' );
$odjeca   = get_term_by( 'slug', 'odjeca', 'product_cat' );

$kava = sidrena_demo_product(
	array(
		'name'    => 'Kava mljevena 250 g',
		'sku'     => 'DEMO-KAVA',
		'regular' => 7.49,
		'cats'    => $hrana ? array( $hrana->term_id ) : array(),
	)
);
sidrena_demo_anchor( $kava, 0, 6.99 );

$ulje = sidrena_demo_product(
	array(
		'name'    => 'Maslinovo ulje 1 L',
		'sku'     => 'DEMO-ULJE',
		'regular' => 12.99,
		'sale'    => 10.99,
		'cats'    => $hrana ? array( $hrana->term_id ) : array(),
	)
);
sidrena_demo_anchor( $ulje, 0, 12.49 );

$deterdzent = sidrena_demo_product(
	array(
		'name'    => 'Deterdžent za rublje 3 kg',
		'sku'     => 'DEMO-DET',
		'regular' => 9.99,
		'cats'    => $ciscenje ? array( $ciscenje->term_id ) : array(),
	)
);
sidrena_demo_anchor( $deterdzent, 0, 9.99 );

// Intentionally left WITHOUT an anchor so the dashboard shows a finding.
sidrena_demo_product(
	array(
		'name'    => 'Majica pamučna',
		'sku'     => 'DEMO-MAJICA',
		'regular' => 19.99,
		'cats'    => $odjeca ? array( $odjeca->term_id ) : array(),
	)
);

// Variable product with per-variation anchors.
$zrno_id = wc_get_product_id_by_sku( 'DEMO-ZRNO' );

if ( ! $zrno_id ) {
	$parent = new WC_Product_Variable();
	$parent->set_name( 'Kava u zrnu' );
	$parent->set_sku( 'DEMO-ZRNO' );

	$attribute = new WC_Product_Attribute();
	$attribute->set_name( 'Pakiranje' );
	$attribute->set_options( array( '500 g', '1 kg' ) );
	$attribute->set_visible( true );
	$attribute->set_variation( true );
	$parent->set_attributes( array( $attribute ) );
	$zrno_id = $parent->save();
	echo "Variable product created: Kava u zrnu (#{$zrno_id})\n";

	$packs = array(
		array( 'option' => '500 g', 'sku' => 'DEMO-ZRNO-500', 'price' => 8.99, 'anchor' => 8.49 ),
		array( 'option' => '1 kg', 'sku' => 'DEMO-ZRNO-1000', 'price' => 15.99, 'anchor' => 15.49 ),
	);

	foreach ( $packs as $pack ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $zrno_id );
		$variation->set_attributes( array( 'pakiranje' => $pack['option'] ) );
		$variation->set_sku( $pack['sku'] );
		$variation->set_regular_price( (string) $pack['price'] );
		$variation->set_stock_status( 'instock' );
		$variation_id = $variation->save();
		echo "Variation created: {$pack['option']} (#{$variation_id})\n";
		sidrena_demo_anchor( (int) $zrno_id, (int) $variation_id, $pack['anchor'] );
	}
} else {
	echo "Variable product exists, skipping: DEMO-ZRNO\n";
}

// One classified sale so the 30-day price shows on the demo shop.
$ulje_item = $plugin->catalog()->get_item( $ulje );

if ( $ulje_item ) {
	$existing_sale = $plugin->special_sales()->active_for_item( $ulje, 0 );

	if ( ! $existing_sale ) {
		$result = $plugin->special_sale_service()->create(
			$ulje_item,
			array(
				'sale_type'  => 'akcija',
				'sale_name'  => 'Jesenska akcija',
				'start_date' => wp_date( 'Y-m-d', strtotime( '-2 days' ) ),
				'end_date'   => wp_date( 'Y-m-d', strtotime( '+5 days' ) ),
				'promo_price' => 10.99,
			)
		);

		if ( is_wp_error( $result ) ) {
			echo 'Sale failed: ' . $result->get_error_message() . "\n";
		} else {
			echo "Sale recorded: akcija for product #{$ulje}\n";
		}
	} else {
		echo "Sale exists, skipping: product #{$ulje}\n";
	}
}

echo "Demo seed finished.\n";
