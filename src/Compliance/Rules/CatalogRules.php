<?php
/**
 * Catalog data validation rules.
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance\Rules;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Compliance\Finding;
use SidrenaCijena\Compliance\Validator;
use SidrenaCijena\Support\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Registers catalog-data findings (SKU, name, barcode, prices, availability,
 * currency and duplicate records).
 */
class CatalogRules {

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
		$this->validator->register( 'missing_sku', array( $this, 'missing_sku' ) );
		$this->validator->register( 'missing_name', array( $this, 'missing_name' ) );
		$this->validator->register( 'missing_barcode', array( $this, 'missing_barcode' ) );
		$this->validator->register( 'invalid_retail_price', array( $this, 'invalid_retail_price' ) );
		$this->validator->register( 'invalid_unit_price', array( $this, 'invalid_unit_price' ) );
		$this->validator->register( 'invalid_availability', array( $this, 'invalid_availability' ) );
		$this->validator->register( 'invalid_currency', array( $this, 'invalid_currency' ) );
		$this->validator->register( 'duplicate_product_records', array( $this, 'duplicate_product_records' ) );
	}

	/**
	 * Items without a SKU.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function missing_sku( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem || '' !== trim( (string) $item->sku ) ) {
				continue;
			}

			$findings[] = Finding::error(
				'missing_sku',
				__( 'The item has no SKU (product code), which the price list requires.', 'sidrena-cijena-eu' ),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-033',
					'context'        => array( 'variation_id' => $item->variation_id ),
				)
			);
		}

		return $findings;
	}

	/**
	 * Items without a name.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function missing_name( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem || '' !== trim( (string) $item->name ) ) {
				continue;
			}

			$findings[] = Finding::error(
				'missing_name',
				__( 'The item has no name, which the price list requires.', 'sidrena-cijena-eu' ),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-033',
					'context'        => array(
						'sku'          => $item->sku,
						'variation_id' => $item->variation_id,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Items without a barcode.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function missing_barcode( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem || '' !== trim( (string) $item->barcode ) ) {
				continue;
			}

			$findings[] = Finding::warning(
				'missing_barcode',
				__( 'No barcode is recorded for this item; add one if a barcode is required for it.', 'sidrena-cijena-eu' ),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-058',
					'context'        => array(
						'sku'          => $item->sku,
						'variation_id' => $item->variation_id,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Items without a positive retail price.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function invalid_retail_price( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem ) {
				continue;
			}

			$price = $item->effective_price();

			if ( null !== $price && $price > 0 ) {
				continue;
			}

			$findings[] = Finding::error(
				'invalid_retail_price',
				__( 'The retail price is missing or not a positive amount.', 'sidrena-cijena-eu' ),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-033',
					'context'        => array(
						'sku'          => $item->sku,
						'variation_id' => $item->variation_id,
						'price'        => $price,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Items with an invalid unit price.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function invalid_unit_price( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem || null === $item->unit_price ) {
				continue;
			}

			if ( $item->unit_price >= 0 ) {
				continue;
			}

			$findings[] = Finding::error(
				'invalid_unit_price',
				__( 'The price per unit of measure is not a valid non-negative amount.', 'sidrena-cijena-eu' ),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-033',
					'context'        => array(
						'sku'          => $item->sku,
						'variation_id' => $item->variation_id,
						'unit_price'   => $item->unit_price,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Items with an unknown availability value.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function invalid_availability( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem || in_array( $item->availability, array( 'available', 'unavailable' ), true ) ) {
				continue;
			}

			$findings[] = Finding::error(
				'invalid_availability',
				__( 'The availability value is not one of the supported values (available or unavailable).', 'sidrena-cijena-eu' ),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-033',
					'context'        => array(
						'sku'          => $item->sku,
						'variation_id' => $item->variation_id,
						'availability' => $item->availability,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Items whose currency is invalid or unexpected.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function invalid_currency( array $context ) {
		$findings = array();
		$expected = isset( $context['expected_currency'] ) ? (string) $context['expected_currency'] : Currency::DEFAULT_CURRENCY;

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem ) {
				continue;
			}

			$currency = (string) $item->currency;

			if ( Currency::is_valid_code( $currency ) && $currency === $expected ) {
				continue;
			}

			$findings[] = Finding::error(
				'invalid_currency',
				sprintf(
					/* translators: 1: item currency, 2: expected currency. */
					__( 'The item currency %1$s is not the expected currency %2$s.', 'sidrena-cijena-eu' ),
					'' === $currency ? '—' : $currency,
					$expected
				),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-033',
					'context'        => array(
						'sku'          => $item->sku,
						'variation_id' => $item->variation_id,
						'currency'     => $currency,
						'expected'     => $expected,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Duplicate records inside a validation batch.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function duplicate_product_records( array $context ) {
		$by_sku = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem ) {
				continue;
			}

			$sku = trim( (string) $item->sku );

			if ( '' === $sku ) {
				continue;
			}

			$key = $sku . ':' . (int) $item->variation_id;

			if ( ! isset( $by_sku[ $key ] ) ) {
				$by_sku[ $key ] = array();
			}

			$by_sku[ $key ][] = $item;
		}

		$findings = array();

		foreach ( $by_sku as $key => $items ) {
			if ( count( $items ) < 2 ) {
				continue;
			}

			foreach ( $items as $item ) {
				$findings[] = Finding::warning(
					'duplicate_product_records',
					sprintf(
						/* translators: %s: SKU. */
						__( 'More than one catalog record uses the SKU %s. Price history may be ambiguous.', 'sidrena-cijena-eu' ),
						$item->sku
					),
					array(
						'entity_type'    => 'catalog_item',
						'entity_id'      => $item->id,
						'rule_reference' => 'FR-058',
						'context'        => array(
							'sku'          => $item->sku,
							'variation_id' => $item->variation_id,
							'duplicates'   => count( $items ),
						),
					)
				);
			}
		}

		return $findings;
	}
}
