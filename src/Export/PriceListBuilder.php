<?php
/**
 * Canonical price-list row builder.
 *
 * @package SidrenaCijena\Export
 */

namespace SidrenaCijena\Export;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\SpecialSaleRepository;
use SidrenaCijena\Pricing\SpecialSaleOfferMatcher;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Turns catalog items into the canonical rows shared by the CSV/XML exports and
 * the public REST endpoint (`docs/price-list-format.md`).
 */
class PriceListBuilder {

	/**
	 * Catalog provider.
	 *
	 * @var CatalogProvider
	 */
	protected $catalog;

	/**
	 * Price history repository.
	 *
	 * @var PriceHistoryRepository
	 */
	protected $history;

	/**
	 * Special sale repository.
	 *
	 * @var SpecialSaleRepository
	 */
	protected $sales;

	/**
	 * Constructor.
	 *
	 * @param CatalogProvider        $catalog Catalog provider.
	 * @param PriceHistoryRepository $history Price history repository.
	 * @param SpecialSaleRepository  $sales   Special sale repository.
	 */
	public function __construct( CatalogProvider $catalog, PriceHistoryRepository $history, SpecialSaleRepository $sales ) {
		$this->catalog = $catalog;
		$this->history = $history;
		$this->sales   = $sales;
	}

	/**
	 * Build the canonical rows for a location.
	 *
	 * @param array<mixed> $location Location row.
	 * @param array<mixed> $args     Arguments (`type`).
	 * @return array{items:array<int,array<mixed>>,meta:array<mixed>}
	 */
	public function build( array $location, array $args = array() ) {
		$type = isset( $args['type'] ) ? (string) $args['type'] : 'all';

		$items = array();

		foreach ( $this->catalog->iterate(
			array(
				'type'               => $type,
				'include_variations' => true,
			)
		) as $item ) {
			if ( ! $this->applies( $item, $location ) ) {
				continue;
			}

			$items[] = $item;
		}

		$ids     = array();
		$woo_ids = array();

		foreach ( $items as $item ) {
			$ids[] = (int) $item->id;

			if ( 'woocommerce' === $item->source ) {
				$woo_ids[] = (int) $item->id;
			}
		}

		$anchors      = empty( $ids ) ? array() : $this->history->latest_map( $ids, 'anchor' );
		$sales        = empty( $items ) ? array() : $this->sales->active_map();
		$sale_periods = empty( $woo_ids ) ? array() : $this->history->active_sale_period_map( $woo_ids );

		$rows = array();

		foreach ( $items as $item ) {
			$rows[] = $this->row( $item, $anchors, $sales, $sale_periods );
		}

		usort( $rows, array( $this, 'compare' ) );

		$counts = array(
			'product' => 0,
			'service' => 0,
		);

		foreach ( $items as $item ) {
			++$counts[ $item->is_service() ? 'service' : 'product' ];
		}

		return array(
			'items' => $rows,
			'meta'  => array(
				'location'     => $location,
				'rule_version' => (string) RuleConfig::get( 'rule_version', '' ),
				'generated_at' => Dates::now_site()->format( 'c' ),
				'count'        => count( $rows ),
				'counts'       => $counts,
			),
		);
	}

	/**
	 * Build one canonical row.
	 *
	 * @param CatalogItem         $item    Item.
	 * @param array<string,array> $anchors Anchor rows keyed by `product:variation`.
	 * @param array<string,array> $sales   Active sale rows keyed by `product:variation`.
	 * @param array<string,array> $periods Active WooCommerce sale periods keyed by `product:variation`.
	 * @return array<mixed>
	 */
	protected function row( CatalogItem $item, array $anchors, array $sales, array $periods = array() ) {
		$key    = $item->key();
		$sale   = $this->sale_for( $item, $sales, $periods );
		$anchor = isset( $anchors[ $key ] ) ? $anchors[ $key ] : null;

		$retail = $item->effective_price();

		if ( null === $retail ) {
			$retail = 0.0;
		}

		$updated_at = $anchor && ! empty( $anchor['created_at'] ) ? (string) $anchor['created_at'] : Dates::now_site()->format( 'Y-m-d H:i:s' );

		return array(
			'vrsta'                         => $item->is_service() ? 'usluga' : 'proizvod',
			'naziv'                         => (string) $item->name,
			'sifra'                         => (string) $item->sku,
			'marka'                         => (string) $item->brand,
			'jedinica_mjere'                => (string) $item->unit,
			'cijena_za_jedinicu_mjere'      => null === $item->unit_price ? null : (float) $item->unit_price,
			'maloprodajna_cijena'           => (float) $retail,
			'poseban_oblik_prodaje'         => null !== $sale,
			'naziv_posebnog_oblika_prodaje' => null !== $sale ? $this->sale_name( $sale ) : '',
			'sidrena_cijena'                => $anchor ? (float) $anchor['price'] : null,
			'currency'                      => (string) $item->currency,
			'barkod'                        => (string) $item->barcode,
			'dostupnost'                    => 'available' === $item->availability ? 'dostupno' : 'nedostupno',
			'product_id'                    => (int) $item->id,
			'variation_id'                  => (int) $item->variation_id,
			'anchor_reference_date'         => $anchor && ! empty( $anchor['reference_date'] ) ? (string) $anchor['reference_date'] : null,
			'current_price'                 => null === $item->current_price ? null : (float) $item->current_price,
			'updated_at'                    => $updated_at,
			'special_sale'                  => $sale,
		);
	}

	/**
	 * Active special sale recorded for this exact item.
	 *
	 * @param CatalogItem         $item  Item.
	 * @param array<string,array> $sales Sale rows keyed by `product:variation`.
	 * @param array<string,array> $periods Active WooCommerce sale periods.
	 * @return array|null
	 */
	protected function sale_for( CatalogItem $item, array $sales, array $periods = array() ) {
		$key  = $item->key();
		$sale = isset( $sales[ $key ] ) ? $sales[ $key ] : null;

		if ( ! $sale ) {
			return null;
		}

		$current = Currency::to_storage( $item->effective_price() );

		$regular     = Currency::to_storage( $item->regular_price );
		$sale_price  = Currency::to_storage( $item->sale_price );
		$promotional = null !== $current && null !== $regular && $regular > 0 && $current < $regular;

		if ( ! $promotional && 'woocommerce' === $item->source && null !== $item->current_price && null !== $current && null !== $sale_price ) {
			$promotional = $current === $sale_price;
		}

		if ( ! $promotional ) {
			return null;
		}

		$period_key = isset( $sale['product_id'] ) ? (int) $sale['product_id'] . ':' . (int) ( isset( $sale['variation_id'] ) ? $sale['variation_id'] : 0 ) : $item->key();
		$period     = isset( $periods[ $period_key ] ) ? $periods[ $period_key ] : null;

		return SpecialSaleOfferMatcher::matches( $item, $sale, $period ) ? $sale : null;
	}

	/**
	 * Display name of a special sale.
	 *
	 * @param array<mixed> $sale Sale row.
	 * @return string
	 */
	protected function sale_name( array $sale ) {
		if ( ! empty( $sale['sale_name'] ) ) {
			return (string) $sale['sale_name'];
		}

		return isset( $sale['sale_type'] ) ? (string) $sale['sale_type'] : '';
	}

	/**
	 * Whether a location's applicability rules include an item.
	 *
	 * @param CatalogItem  $item     Item.
	 * @param array<mixed> $location Location row.
	 * @return bool
	 */
	protected function applies( CatalogItem $item, array $location ) {
		$config = isset( $location['applies_to'] ) && is_array( $location['applies_to'] ) ? $location['applies_to'] : array();

		if ( empty( $config['mode'] ) || 'all' === $config['mode'] ) {
			return true;
		}

		$categories = isset( $config['categories'] ) ? (array) $config['categories'] : array();

		if ( empty( $categories ) ) {
			return true;
		}

		$match = (bool) array_intersect( $item->categories, array_map( 'strval', $categories ) );

		return 'exclude' === $config['mode'] ? ! $match : $match;
	}

	/**
	 * Deterministic row comparison: products first, then `sifra`, `naziv`, ID.
	 *
	 * @param array<mixed> $a Row.
	 * @param array<mixed> $b Row.
	 * @return int
	 */
	protected function compare( array $a, array $b ) {
		$type = strcmp( (string) $a['vrsta'], (string) $b['vrsta'] );

		if ( 0 !== $type ) {
			return $type;
		}

		$sku = strcmp( (string) $a['sifra'], (string) $b['sifra'] );

		if ( 0 !== $sku ) {
			return $sku;
		}

		$name = strcmp( (string) $a['naziv'], (string) $b['naziv'] );

		if ( 0 !== $name ) {
			return $name;
		}

		if ( $a['product_id'] === $b['product_id'] ) {
			return $a['variation_id'] <=> $b['variation_id'];
		}

		return $a['product_id'] <=> $b['product_id'];
	}

	/**
	 * Content fingerprint of the deterministic row data.
	 *
	 * @param array<mixed> $items        Canonical rows.
	 * @param array<mixed> $location     Location row.
	 * @param string       $format       Format.
	 * @param string       $rule_version Rule version.
	 * @return string
	 */
	public function fingerprint( array $items, array $location, $format, $rule_version ) {
		$payload = array(
			'location'     => isset( $location['id'] ) ? (int) $location['id'] : 0,
			'format'       => (string) $format,
			'rule_version' => (string) $rule_version,
			'items'        => array(),
		);

		foreach ( $items as $item ) {
			$payload['items'][] = array(
				$item['vrsta'],
				$item['naziv'],
				$item['sifra'],
				$item['marka'],
				$item['jedinica_mjere'],
				$item['cijena_za_jedinicu_mjere'],
				$item['maloprodajna_cijena'],
				$item['poseban_oblik_prodaje'],
				$item['naziv_posebnog_oblika_prodaje'],
				$item['sidrena_cijena'],
				$item['anchor_reference_date'],
				$item['currency'],
				$item['barkod'],
				$item['dostupnost'],
			);
		}

		return hash( 'sha256', (string) wp_json_encode( $payload ) );
	}
}
