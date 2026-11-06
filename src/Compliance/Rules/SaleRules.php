<?php
/**
 * Special form of sale validation rules.
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance\Rules;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Compliance\Finding;
use SidrenaCijena\Compliance\Validator;
use SidrenaCijena\Pricing\LowestPrice30DayCalculator;
use SidrenaCijena\Pricing\SpecialSaleOfferMatcher;
use SidrenaCijena\Support\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Registers findings for unclassified promotions and missing 30-day prices.
 */
class SaleRules {

	/**
	 * Validator instance.
	 *
	 * @var Validator
	 */
	protected $validator;

	/**
	 * Constructor.
	 *
	 * @param Validator $validator Validator instance.
	 */
	public function __construct( Validator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * Register the rule callbacks.
	 *
	 * @return void
	 */
	public function register() {
		$this->validator->register( 'missing_sale_type', array( $this, 'missing_sale_type' ) );
		$this->validator->register( 'missing_30_day_price', array( $this, 'missing_30_day_price' ) );
		$this->validator->register( 'sale_price_not_below_reference', array( $this, 'sale_price_not_below_reference' ) );
	}

	/**
	 * Promotional price without a classified special form of sale.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function missing_sale_type( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem || ! $this->is_promotional( $item ) ) {
				continue;
			}

			if ( $this->sale_for( $item, $context ) ) {
				continue;
			}

			$findings[] = Finding::error(
				'missing_sale_type',
				__( 'The current promotional price has no matching classified special form of sale. Classify this offer before publishing.', 'titanworks-bazna-cijena' ),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-023',
					'context'        => array(
						'sku'          => $item->sku,
						'variation_id' => $item->variation_id,
						'sale_price'   => $item->sale_price,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * A classified sale without a calculated 30-day lowest price.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function missing_30_day_price( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem || ! $this->is_promotional( $item ) ) {
				continue;
			}

			$sale = $this->sale_for( $item, $context );

			if ( ! $sale ) {
				continue;
			}

			$lowest = Currency::to_storage( isset( $sale['lowest_30_day_price'] ) ? $sale['lowest_30_day_price'] : null );

			$window         = ! empty( $sale['start_date'] ) ? ( new LowestPrice30DayCalculator() )->window( (string) $sale['start_date'] ) : null;
			$window_matches = $window
				&& isset( $sale['window_start'], $sale['window_end'] )
				&& $window['window_start'] === (string) $sale['window_start']
				&& substr( (string) $sale['window_end'], 0, 10 ) === $window['window_end'];

			if ( null !== $lowest && $lowest > 0 && $window_matches ) {
				continue;
			}

			$findings[] = Finding::error(
				'missing_30_day_price',
				__( 'The special form of sale has no usable lowest price for the 30-day period before its start. Recalculate the sale and review any applicable legal exception before publishing.', 'titanworks-bazna-cijena' ),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-019',
					'context'        => array(
						'sku'          => $item->sku,
						'variation_id' => $item->variation_id,
						'sale_type'    => isset( $sale['sale_type'] ) ? (string) $sale['sale_type'] : '',
						'sale_id'      => isset( $sale['id'] ) ? (int) $sale['id'] : 0,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Whether the item carries a promotional price.
	 *
	 * @param CatalogItem $item Item.
	 * @return bool
	 */
	protected function is_promotional( CatalogItem $item ) {
		$current = Currency::to_storage( $item->effective_price() );
		$sale    = Currency::to_storage( $item->sale_price );
		$regular = Currency::to_storage( $item->regular_price );

		if ( null === $current || $current <= 0 ) {
			return false;
		}

		if ( null !== $regular && $regular > 0 ) {
			return $current < $regular;
		}

		return 'woocommerce' === $item->source
			&& null !== $item->current_price
			&& null !== $sale
			&& $sale > 0
			&& $current === $sale;
	}

	/**
	 * Active special sale recorded for this exact item.
	 *
	 * @param CatalogItem  $item    Item.
	 * @param array<mixed> $context Validation context.
	 * @return array<mixed>|null
	 */
	protected function sale_for( CatalogItem $item, array $context ) {
		$sale = isset( $context['sales'][ $item->key() ] ) ? $context['sales'][ $item->key() ] : null;

		if ( ! $sale ) {
			return null;
		}

		$period_key = isset( $sale['product_id'] ) ? (int) $sale['product_id'] . ':' . (int) ( isset( $sale['variation_id'] ) ? $sale['variation_id'] : 0 ) : $item->key();
		$periods    = isset( $context['sale_periods'] ) && is_array( $context['sale_periods'] ) ? $context['sale_periods'] : array();
		$period     = isset( $periods[ $period_key ] ) ? $periods[ $period_key ] : null;

		return SpecialSaleOfferMatcher::matches( $item, $sale, $period ) ? $sale : null;
	}

	/**
	 * The active price must be below the reference price for a qualifying offer.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function sale_price_not_below_reference( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem || ! $this->is_promotional( $item ) ) {
				continue;
			}

			$sale = $this->sale_for( $item, $context );

			if ( ! $sale ) {
				continue;
			}

			$lowest  = Currency::to_storage( isset( $sale['lowest_30_day_price'] ) ? $sale['lowest_30_day_price'] : null );
			$current = Currency::to_storage( $item->effective_price() );

			if ( null === $lowest || $lowest <= 0 || null === $current || $current < $lowest ) {
				continue;
			}

			$findings[] = Finding::error(
				'sale_price_not_below_reference',
				__( 'The current promotional price is not below the applicable lowest price from the preceding 30 days. Review the offer and its reference price before publishing.', 'titanworks-bazna-cijena' ),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-019',
					'context'        => array(
						'sku'                 => $item->sku,
						'variation_id'        => $item->variation_id,
						'current_price'       => $current,
						'lowest_30_day_price' => $lowest,
						'sale_id'             => isset( $sale['id'] ) ? (int) $sale['id'] : 0,
					),
				)
			);
		}

		return $findings;
	}
}
