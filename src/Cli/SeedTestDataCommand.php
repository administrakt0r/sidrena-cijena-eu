<?php
/**
 * `wp sidrena-cijena seed-test-data` command.
 *
 * @package SidrenaCijena\Cli
 */

namespace SidrenaCijena\Cli;

use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\WooCommerce\WooCommerceDetector;

defined( 'ABSPATH' ) || exit;

/**
 * Seeds the 17 required test-product scenarios (FR-089 / SC-011).
 */
final class SeedTestDataCommand {

	/**
	 * Available scenarios.
	 *
	 * @var string[]
	 */
	const SCENARIOS = array(
		'normal',
		'sale',
		'no-sku',
		'barcode',
		'no-barcode',
		'variable',
		'legacy-category',
		'introduced-after',
		'frequently-repriced',
		'consecutive-promotions',
		'out-of-stock',
		'unit-pricing',
		'missing-anchor',
		'price-above',
		'price-equal',
		'price-below',
		'untyped-sale',
	);

	/**
	 * WP-CLI entry point.
	 *
	 * @param array<mixed> $args       Positional arguments.
	 * @param array<mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public static function run( $args, $assoc_args ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Exit code, not output.
		exit( self::execute( (array) $args, (array) $assoc_args ) );
	}

	/**
	 * Seed test data.
	 *
	 * Exit codes: 0 success, 3 refused (pass --yes on production-like systems).
	 *
	 * @param array<mixed> $args       Positional arguments.
	 * @param array<mixed> $assoc_args Named arguments (--scenario, --yes).
	 * @return int Exit code.
	 */
	public static function execute( $args = array(), $assoc_args = array() ) {
		unset( $args );

		$confirmed = isset( $assoc_args['yes'] ) || ( defined( 'WP_ENV' ) && WP_ENV ) || getenv( 'WP_ENV' );

		if ( ! $confirmed ) {
			Cli::out( 'Refusing to seed: pass --yes to confirm writing test data (or set WP_ENV).', 'error' );

			return 3;
		}

		$wanted = isset( $assoc_args['scenario'] ) ? strtolower( (string) $assoc_args['scenario'] ) : 'all';

		if ( 'all' !== $wanted && ! in_array( $wanted, self::SCENARIOS, true ) ) {
			Cli::out( 'Unknown scenario. Available: all, ' . implode( ', ', self::SCENARIOS ) . '.', 'error' );

			return 3;
		}

		try {
			if ( ! post_type_exists( StandalonePostType::POST_TYPE ) ) {
				StandalonePostType::register();
			}

			$created = 0;
			$skipped = 0;

			foreach ( self::SCENARIOS as $scenario ) {
				if ( 'all' !== $wanted && $scenario !== $wanted ) {
					continue;
				}

				$method = 'seed_' . str_replace( '-', '_', $scenario );

				if ( self::$method() ) {
					++$created;
				} else {
					++$skipped;
				}
			}

			Cli::out( sprintf( 'Seeding finished: %d scenario(s) created, %d skipped (already present or unavailable).', $created, $skipped ), 'success' );

			return 0;
		} catch ( \Throwable $exception ) {
			Cli::out( 'Seeding failed: ' . $exception->getMessage(), 'error' );

			return 2;
		}
	}

	/**
	 * Whether a seed SKU already exists.
	 *
	 * @param string $sku SKU.
	 * @return bool
	 */
	protected static function exists( $sku ) {
		// WooCommerce products keep their SKU in WooCommerce, not in the
		// standalone item meta, so both stores have to be consulted or a
		// second run trips WooCommerce's duplicate-SKU check.
		if ( function_exists( 'wc_get_product_id_by_sku' ) && wc_get_product_id_by_sku( $sku ) ) {
			return true;
		}

		$posts = get_posts(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'any',
				'meta_key'    => '_sidrena_sku', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off seeding lookup.
				'meta_value'  => $sku, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-off seeding lookup.
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);

		return ! empty( $posts );
	}

	/**
	 * Create a standalone seed item and return its catalog item.
	 *
	 * @param string       $scenario Scenario key (used for the SKU).
	 * @param string       $name     Product name.
	 * @param array<mixed> $meta     Meta values (price, sku, barcode, unit, ...).
	 * @return \SidrenaCijena\Catalog\CatalogItem|null
	 */
	protected static function create_item( $scenario, $name, array $meta = array() ) {
		$sku = 'SEED-' . strtoupper( str_replace( '-', '_', $scenario ) );

		if ( self::exists( $sku ) ) {
			return null;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return null;
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, '_sidrena_sku', isset( $meta['sku'] ) ? $meta['sku'] : $sku );
		update_post_meta( $post_id, '_sidrena_regular_price', isset( $meta['price'] ) ? (string) $meta['price'] : '9.99' );

		if ( isset( $meta['sale'] ) ) {
			update_post_meta( $post_id, '_sidrena_sale_price', (string) $meta['sale'] );
		}

		if ( isset( $meta['barcode'] ) ) {
			update_post_meta( $post_id, '_sidrena_barcode', $meta['barcode'] );
		}

		if ( isset( $meta['availability'] ) ) {
			update_post_meta( $post_id, '_sidrena_availability', $meta['availability'] );
		}

		if ( isset( $meta['unit'] ) ) {
			update_post_meta( $post_id, '_sidrena_unit', $meta['unit'] );
			update_post_meta( $post_id, '_sidrena_unit_price', isset( $meta['unit_price'] ) ? (string) $meta['unit_price'] : '' );
		}

		update_post_meta( $post_id, '_sidrena_first_seen', isset( $meta['first_seen'] ) ? $meta['first_seen'] : '2025-01-15' );
		wp_set_object_terms( $post_id, 'product', StandalonePostType::TAX_TYPE );

		if ( isset( $meta['category'] ) ) {
			wp_set_object_terms( $post_id, $meta['category'], 'category', true );
		}

		return ( new StandaloneCatalogProvider() )->get_item( $post_id );
	}

	/**
	 * Record an anchor for a seed item.
	 *
	 * @param \SidrenaCijena\Catalog\CatalogItem|null $item  Item.
	 * @param float                                   $price Anchor price.
	 * @param string                                  $date  Reference date ('' for the applicable default).
	 * @return void
	 */
	protected static function anchor( $item, $price, $date = '' ) {
		if ( ! $item ) {
			return;
		}

		$args = array( 'source' => 'cli' );

		if ( '' !== $date ) {
			$args['reference_date'] = $date;
		}

		Plugin::instance()->anchors()->record( $item, $price, $args );
	}

	/**
	 * Record a classified sale for a seed item.
	 *
	 * @param \SidrenaCijena\Catalog\CatalogItem|null $item  Item.
	 * @param string                                  $type  Sale type.
	 * @param string                                  $start Start date.
	 * @param string                                  $end   End date.
	 * @param float                                   $promo Promotional price.
	 * @return void
	 */
	protected static function sale( $item, $type, $start, $end, $promo ) {
		if ( ! $item ) {
			return;
		}

		Plugin::instance()->special_sale_service()->create(
			$item,
			array(
				'sale_type'   => $type,
				'sale_name'   => $type,
				'start_date'  => $start,
				'end_date'    => $end,
				'promo_price' => $promo,
			)
		);
	}

	/**
	 * Seed one scenario. Each returns true when it created its item.
	 *
	 * @return bool
	 */
	protected static function seed_normal() {
		$item = self::create_item( 'normal', 'Seed normal product', array( 'price' => '19.99' ) );
		self::anchor( $item, 19.99 );

		return (bool) $item;
	}

	/**
	 * Seed the sale scenario.
	 *
	 * @return bool
	 */
	protected static function seed_sale() {
		$item = self::create_item(
			'sale',
			'Seed sale product',
			array(
				'price' => '19.99',
				'sale'  => '14.99',
			)
		);
		self::anchor( $item, 19.99 );
		self::sale( $item, 'akcija', Dates::today(), gmdate( 'Y-m-d', strtotime( '+7 days' ) ), 14.99 );

		return (bool) $item;
	}

	/**
	 * Seed the no-SKU scenario.
	 *
	 * @return bool
	 */
	protected static function seed_no_sku() {
		$sku  = 'SEED-NO_SKU';
		$item = self::exists( $sku ) ? null : self::create_item( 'no-sku', 'Seed product without SKU', array( 'price' => '9.99' ) );

		if ( $item ) {
			delete_post_meta( $item->id, '_sidrena_sku' );
			self::anchor( ( new StandaloneCatalogProvider() )->get_item( $item->id ), 9.99 );

			return true;
		}

		return false;
	}

	/**
	 * Seed the barcode scenario.
	 *
	 * @return bool
	 */
	protected static function seed_barcode() {
		$item = self::create_item(
			'barcode',
			'Seed product with barcode',
			array(
				'price'   => '12.50',
				'barcode' => '3851234567001',
			)
		);
		self::anchor( $item, 12.50 );

		return (bool) $item;
	}

	/**
	 * Seed the no-barcode scenario.
	 *
	 * @return bool
	 */
	protected static function seed_no_barcode() {
		$item = self::create_item( 'no-barcode', 'Seed product without barcode', array( 'price' => '12.50' ) );
		self::anchor( $item, 12.50 );

		return (bool) $item;
	}

	/**
	 * Seed the variable-product scenario (WooCommerce only).
	 *
	 * @return bool
	 */
	protected static function seed_variable() {
		if ( ! WooCommerceDetector::is_active() || ! class_exists( 'WC_Product_Variable' ) ) {
			Cli::out( 'Scenario variable skipped (WooCommerce is not active).', 'warning' );

			return false;
		}

		if ( self::exists( 'SEED-VARIABLE' ) ) {
			Cli::out( 'Scenario variable skipped (already seeded).', 'warning' );

			return false;
		}

		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Seed variable product' );
		$parent->set_sku( 'SEED-VARIABLE' );
		$parent->set_status( 'publish' );
		$parent->save();
		$parent_id = $parent->get_id();

		$provider = new \SidrenaCijena\WooCommerce\WooCommerceCatalogProvider();
		$prices   = array( 29.99, 34.99 );

		foreach ( $prices as $price ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent_id );
			$variation->set_regular_price( (string) $price );
			$variation->set_status( 'publish' );
			$variation->save();

			$item = $provider->get_variation( $parent_id, $variation->get_id() );

			if ( $item ) {
				Plugin::instance()->anchors()->record( $item, $price + 5, array( 'source' => 'cli' ) );
			}
		}

		return true;
	}

	/**
	 * Seed the legacy-category scenario.
	 *
	 * @return bool
	 */
	protected static function seed_legacy_category() {
		$item = self::create_item(
			'legacy-category',
			'Seed legacy food product',
			array(
				'price'    => '6.99',
				'category' => 'hrana',
			)
		);
		self::anchor( $item, 7.49, RuleConfig::DEFAULT_LEGACY_DATE );

		return (bool) $item;
	}

	/**
	 * Seed the introduced-after-reference-date scenario.
	 *
	 * @return bool
	 */
	protected static function seed_introduced_after() {
		$item = self::create_item(
			'introduced-after',
			'Seed product introduced after the reference date',
			array(
				'price'      => '11.99',
				'first_seen' => '2026-11-01',
			)
		);
		self::anchor( $item, 11.99 );

		return (bool) $item;
	}

	/**
	 * Seed the frequently-re-priced scenario.
	 *
	 * @return bool
	 */
	protected static function seed_frequently_repriced() {
		$item = self::create_item( 'frequently-repriced', 'Seed frequently re-priced product', array( 'price' => '10.99' ) );

		if ( ! $item ) {
			return false;
		}

		$history = Plugin::instance()->history();
		$steps   = array(
			array( '12.99', '2026-07-01' ),
			array( '11.99', '2026-08-01' ),
			array( '10.99', '2026-09-01' ),
			array( '10.49', '2026-09-15' ),
			array( '10.99', '2026-10-01' ),
		);

		foreach ( $steps as $step ) {
			$history->insert(
				array(
					'product_id' => $item->id,
					'sku'        => $item->sku,
					'price_type' => 'regular',
					'price'      => $step[0],
					'currency'   => 'EUR',
					'valid_from' => $step[1] . ' 00:00:00',
					'source'     => 'cli',
				)
			);
		}

		self::anchor( $item, 12.99 );

		return true;
	}

	/**
	 * Seed the consecutive-promotions scenario.
	 *
	 * @return bool
	 */
	protected static function seed_consecutive_promotions() {
		$item = self::create_item( 'consecutive-promotions', 'Seed consecutive promotions product', array( 'price' => '20.00' ) );

		if ( ! $item ) {
			return false;
		}

		$history = Plugin::instance()->history();
		$history->insert(
			array(
				'product_id' => $item->id,
				'sku'        => $item->sku,
				'price_type' => 'sale',
				'price'      => 17.50,
				'currency'   => 'EUR',
				'valid_from' => '2026-09-05 00:00:00',
				'valid_to'   => '2026-09-12 23:59:59',
				'source'     => 'cli',
			)
		);

		self::anchor( $item, 20.00 );
		self::sale( $item, 'snizenje', '2026-09-13', '2026-09-20', 16.99 );

		return true;
	}

	/**
	 * Seed the out-of-stock scenario.
	 *
	 * @return bool
	 */
	protected static function seed_out_of_stock() {
		$item = self::create_item(
			'out-of-stock',
			'Seed out-of-stock product',
			array(
				'price'        => '8.99',
				'availability' => 'unavailable',
			)
		);
		self::anchor( $item, 8.99 );

		return (bool) $item;
	}

	/**
	 * Seed the unit-pricing scenario.
	 *
	 * @return bool
	 */
	protected static function seed_unit_pricing() {
		$item = self::create_item(
			'unit-pricing',
			'Seed unit-priced product',
			array(
				'price'      => '6.99',
				'unit'       => 'kg',
				'unit_price' => '27.96',
			)
		);
		self::anchor( $item, 6.99 );

		return (bool) $item;
	}

	/**
	 * Seed the missing-anchor scenario.
	 *
	 * @return bool
	 */
	protected static function seed_missing_anchor() {
		return (bool) self::create_item( 'missing-anchor', 'Seed product without anchor', array( 'price' => '5.49' ) );
	}

	/**
	 * Seed the price-above-anchor scenario.
	 *
	 * @return bool
	 */
	protected static function seed_price_above() {
		$item = self::create_item( 'price-above', 'Seed product priced above anchor', array( 'price' => '22.00' ) );
		self::anchor( $item, 19.99 );

		return (bool) $item;
	}

	/**
	 * Seed the price-equal-to-anchor scenario.
	 *
	 * @return bool
	 */
	protected static function seed_price_equal() {
		$item = self::create_item( 'price-equal', 'Seed product priced at anchor', array( 'price' => '19.99' ) );
		self::anchor( $item, 19.99 );

		return (bool) $item;
	}

	/**
	 * Seed the price-below-anchor scenario.
	 *
	 * @return bool
	 */
	protected static function seed_price_below() {
		$item = self::create_item( 'price-below', 'Seed product priced below anchor', array( 'price' => '15.99' ) );
		self::anchor( $item, 19.99 );

		return (bool) $item;
	}

	/**
	 * Seed the unclassified-sale scenario (sale price, no classified special sale).
	 *
	 * @return bool
	 */
	protected static function seed_untyped_sale() {
		$item = self::create_item(
			'untyped-sale',
			'Seed product with unclassified sale',
			array(
				'price' => '19.99',
				'sale'  => '13.99',
			)
		);
		self::anchor( $item, 19.99 );

		return (bool) $item;
	}
}
