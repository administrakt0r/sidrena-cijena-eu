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
				__( 'A promotional price is set without a classified special form of sale. Classify the promotion (for example akcija or sniženje).', 'sidrena-cijena' ),
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
			if ( ! $item instanceof CatalogItem ) {
				continue;
			}

			$sale = $this->sale_for( $item, $context );

			if ( ! $sale ) {
				continue;
			}

			$lowest = isset( $sale['lowest_30_day_price'] ) ? (string) $sale['lowest_30_day_price'] : '';

			if ( '' !== $lowest ) {
				continue;
			}

			$findings[] = Finding::error(
				'missing_30_day_price',
				__( 'The special form of sale has no calculated lowest price of the last 30 days. Recalculate the sale before publishing.', 'sidrena-cijena' ),
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
		$sale    = $item->sale_price;
		$regular = $item->regular_price;

		if ( null === $sale || $sale <= 0 ) {
			return false;
		}

		return null === $regular || $sale < $regular;
	}

	/**
	 * Active special sale for an item, falling back to its parent.
	 *
	 * @param CatalogItem  $item    Item.
	 * @param array<mixed> $context Validation context.
	 * @return array<mixed>|null
	 */
	protected function sale_for( CatalogItem $item, array $context ) {
		if ( isset( $context['sales'][ $item->key() ] ) ) {
			return $context['sales'][ $item->key() ];
		}

		if ( $item->is_variation() && isset( $context['sales'][ $item->id . ':0' ] ) ) {
			return $context['sales'][ $item->id . ':0' ];
		}

		return null;
	}
}
