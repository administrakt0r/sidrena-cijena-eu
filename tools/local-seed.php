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

$plugin = \Titanworks\SidrenaCijena\Plugin::instance();
// These explicit historical demo fixtures are imported, not new live offers.
if ( ! defined( 'WP_IMPORTING' ) ) {
	define( 'WP_IMPORTING', true );
}

// Activation creates a default location without an address. The legal
// filename cannot be built without a street and a city, so the demo shop
// fills them in here; otherwise `wp sidrena-cijena generate` aborts.
$twbce_demo_address = 'Vlaška 68';
$twbce_demo_city    = 'Zagreb';

foreach ( $plugin->locations()->all() as $twbce_demo_location ) {
	if ( '' !== (string) $twbce_demo_location['address'] && '' !== (string) $twbce_demo_location['city'] ) {
		continue;
	}

	$plugin->locations()->update(
		(int) $twbce_demo_location['id'],
		array(
			'address' => $twbce_demo_address,
			'city'    => $twbce_demo_city,
		)
	);
	$twbce_demo_filled = true;
	echo "Location address set: {$twbce_demo_address}, {$twbce_demo_city}\n";
}

if ( ! $plugin->locations()->all() ) {
	$plugin->locations()->insert(
		array(
			'name'       => 'Webshop',
			'type'       => 'webshop',
			'identifier' => 'P1',
			'address'    => $twbce_demo_address,
			'city'       => $twbce_demo_city,
			'active'     => true,
		)
	);
	echo "Location created: {$twbce_demo_address}, {$twbce_demo_city}\n";
}

/**
 * Create a product_cat term if missing.
 *
 * @param string $name Name.
 * @param string $slug Slug.
 * @return void
 */
function twbce_demo_term( $name, $slug ) {
	if ( ! term_exists( $slug, 'product_cat' ) ) {
		wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
		echo "Category created: {$name}\n";
	}
}

twbce_demo_term( 'Hrana', 'hrana' );
twbce_demo_term( 'Čišćenje', 'ciscenje' );
twbce_demo_term( 'Odjeća', 'odjeca' );

// Legacy (2025) categories for the demo shop.
\Titanworks\SidrenaCijena\Compliance\RuleConfig::update(
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
function twbce_demo_product( $args ) {
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
function twbce_demo_anchor( $product_id, $variation_id, $price ) {
	$plugin = \Titanworks\SidrenaCijena\Plugin::instance();

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

/**
 * Seed the price history a shop would already have.
 *
 * The 30-day engine only counts prices that were valid before the promotion
 * starts (FR-019), so a demo shop whose products are created seconds before the
 * sale would have nothing to measure. `valid_from` is backdated for demo data
 * only; the plugin itself never invents historical prices.
 *
 * @param int   $product_id   Product ID.
 * @param int   $variation_id Variation ID (0 for none).
 * @param float $price        Regular price charged before the demo promotion.
 * @return void
 */
function twbce_demo_history( $product_id, $variation_id, $price ) {
	$plugin  = \Titanworks\SidrenaCijena\Plugin::instance();
	$history = $plugin->history();
	$today   = wp_date( 'Y-m-d' );
	$last    = $history->latest( (int) $product_id, (int) $variation_id, 'regular' );

	// Already backed by history written before today: leave it untouched.
	if ( $last && substr( (string) $last['valid_from'], 0, 10 ) < $today ) {
		return;
	}

	$item = $variation_id
		? $plugin->catalog()->get_variation( $product_id, $variation_id )
		: $plugin->catalog()->get_item( $product_id );

	if ( ! $item ) {
		return;
	}

	$history->insert(
		array(
			'product_id'    => (int) $product_id,
			'variation_id'  => (int) $variation_id,
			'item_type'     => 'product',
			'sku'           => $item->sku,
			'price_type'    => 'regular',
			'price'         => (float) $price,
			'currency'      => $item->currency,
			'tax_inclusive' => $item->tax_inclusive,
			'valid_from'    => wp_date( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			'source'        => 'cli',
			'created_by'    => get_current_user_id(),
		)
	);

	echo "History seeded: {$product_id}:{$variation_id} = {$price}\n";
}

$hrana    = get_term_by( 'slug', 'hrana', 'product_cat' );
$ciscenje = get_term_by( 'slug', 'ciscenje', 'product_cat' );
$odjeca   = get_term_by( 'slug', 'odjeca', 'product_cat' );

$kava = twbce_demo_product(
	array(
		'name'    => 'Kava mljevena 250 g',
		'sku'     => 'DEMO-KAVA',
		'regular' => 7.49,
		'cats'    => $hrana ? array( $hrana->term_id ) : array(),
	)
);
twbce_demo_anchor( $kava, 0, 6.99 );

$ulje = twbce_demo_product(
	array(
		'name'    => 'Maslinovo ulje 1 L',
		'sku'     => 'DEMO-ULJE',
		'regular' => 12.99,
		'sale'    => 10.99,
		'cats'    => $hrana ? array( $hrana->term_id ) : array(),
	)
);
twbce_demo_anchor( $ulje, 0, 12.49 );

$deterdzent = twbce_demo_product(
	array(
		'name'    => 'Deterdžent za rublje 3 kg',
		'sku'     => 'DEMO-DET',
		'regular' => 9.99,
		'cats'    => $ciscenje ? array( $ciscenje->term_id ) : array(),
	)
);
twbce_demo_anchor( $deterdzent, 0, 9.99 );

// Every demo product records an anchor: without one the price list cannot be
// published (FR-007), so the demo shop has to be fully evidenced.
$majica = twbce_demo_product(
	array(
		'name'    => 'Majica pamučna',
		'sku'     => 'DEMO-MAJICA',
		'regular' => 19.99,
		'cats'    => $odjeca ? array( $odjeca->term_id ) : array(),
	)
);
twbce_demo_anchor( $majica, 0, 17.99 );

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
		twbce_demo_anchor( (int) $zrno_id, (int) $variation_id, $pack['anchor'] );
	}
} else {
	echo "Variable product exists, skipping: DEMO-ZRNO\n";
}

// The 30-day engine sees only prices valid before the promotion (FR-019), so
// give the demo shop the history a real shop would already have.
twbce_demo_history( (int) $kava, 0, 7.49 );
twbce_demo_history( (int) $ulje, 0, 12.99 );
twbce_demo_history( (int) $deterdzent, 0, 9.99 );
twbce_demo_history( (int) $majica, 0, 19.99 );

$zrno = wc_get_product( (int) $zrno_id );

if ( $zrno ) {
	foreach ( $zrno->get_children() as $child_id ) {
		$child = wc_get_product( $child_id );

		if ( $child ) {
			twbce_demo_history( (int) $zrno_id, (int) $child_id, (float) $child->get_regular_price() );
		}
	}
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

		// A classified sale without a 30-day price is an ERROR finding and
		// blocks publication (FR-019), so heal rows stored before the
		// calculation existed instead of leaving the demo unpublishable.
		if ( empty( $existing_sale['lowest_30_day_price'] ) ) {
			$recomputed = $plugin->special_sale_service()->recompute( $ulje_item, (int) $existing_sale['id'] );

			if ( is_wp_error( $recomputed ) ) {
				echo '30-day recompute failed: ' . $recomputed->get_error_message() . "\n";
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Development seed CLI script.
				echo '30-day price recomputed: ' . var_export( $recomputed, true ) . "\n";
			}
		}
	}
}

echo "Demo seed finished.\n";
