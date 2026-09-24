<?php
/**
 * Anchor price validation rules.
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance\Rules;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Compliance\Finding;
use SidrenaCijena\Compliance\Validator;
use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Registers anchor-price findings (missing anchor, invalid reference date,
 * stored anchor recorded for a different reference date).
 */
class AnchorRules {

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
		$this->validator->register( 'missing_anchor_price', array( $this, 'missing_anchor_price' ) );
		$this->validator->register( 'invalid_anchor_date', array( $this, 'invalid_anchor_date' ) );
		$this->validator->register( 'anchor_needs_verification', array( $this, 'anchor_needs_verification' ) );
	}

	/**
	 * Anchor price is missing for the item.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function missing_anchor_price( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem || isset( $context['anchors'][ $item->key() ] ) ) {
				continue;
			}

			$reference        = isset( $context['reference_dates'][ $item->key() ] ) ? $context['reference_dates'][ $item->key() ] : array();
			$reference_date   = isset( $reference['date'] ) ? (string) $reference['date'] : '';
			$introduced_after = $this->introduced_after( $item, $reference_date );

			if ( $introduced_after ) {
				$message = sprintf(
					/* translators: %s: reference date. */
					__( 'No anchor price is recorded. For an item first offered after %s, record the price applied when it first entered sale and the date it was first applied.', 'sidrena-cijena' ),
					$reference_date
				);
			} else {
				$message = sprintf(
					/* translators: %s: reference date. */
					__( 'No anchor price is recorded for this item (reference date %s).', 'sidrena-cijena' ),
					$reference_date
				);
			}

			$findings[] = Finding::error(
				'missing_anchor_price',
				$message,
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-007',
					'context'        => array(
						'sku'              => $item->sku,
						'variation_id'     => $item->variation_id,
						'reference_date'   => $reference_date,
						'introduced_after' => $introduced_after,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Stored anchor rows with a missing or invalid reference date.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function invalid_anchor_date( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem ) {
				continue;
			}

			$anchor = isset( $context['anchors'][ $item->key() ] ) ? $context['anchors'][ $item->key() ] : null;

			if ( ! $anchor ) {
				continue;
			}

			$date = isset( $anchor['reference_date'] ) ? (string) $anchor['reference_date'] : '';

			if ( '' !== $date && Dates::is_valid_date( $date ) ) {
				continue;
			}

			$findings[] = Finding::error(
				'invalid_anchor_date',
				__( 'The recorded anchor price has a missing or invalid reference date.', 'sidrena-cijena' ),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-005',
					'context'        => array(
						'sku'            => $item->sku,
						'record_id'      => isset( $anchor['id'] ) ? (int) $anchor['id'] : 0,
						'reference_date' => $date,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Stored anchor recorded for a reference date other than the applicable one.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function anchor_needs_verification( array $context ) {
		$findings = array();

		foreach ( $context['items'] as $item ) {
			if ( ! $item instanceof CatalogItem ) {
				continue;
			}

			$anchor = isset( $context['anchors'][ $item->key() ] ) ? $context['anchors'][ $item->key() ] : null;
			$date   = $anchor && isset( $anchor['reference_date'] ) ? (string) $anchor['reference_date'] : '';
			$wanted = isset( $context['reference_dates'][ $item->key() ]['date'] ) ? (string) $context['reference_dates'][ $item->key() ]['date'] : '';

			if ( ! $anchor || '' === $date || ! Dates::is_valid_date( $date ) || '' === $wanted ) {
				continue;
			}

			$is_first_offer_anchor = $this->introduced_after( $item, $wanted )
				&& $date >= $wanted
				&& $date <= Dates::today();

			if ( $date === $wanted || $is_first_offer_anchor ) {
				continue;
			}

			$findings[] = Finding::warning(
				'anchor_needs_verification',
				sprintf(
					/* translators: 1: recorded reference date, 2: applicable reference date. */
					__( 'The anchor price was recorded for reference date %1$s but the applicable reference date is %2$s. Review whether the record is correct.', 'sidrena-cijena' ),
					$date,
					$wanted
				),
				array(
					'entity_type'    => 'catalog_item',
					'entity_id'      => $item->id,
					'rule_reference' => 'FR-090',
					'context'        => array(
						'sku'                     => $item->sku,
						'recorded_reference_date' => $date,
						'expected_reference_date' => $wanted,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Whether the item first appeared after the reference date.
	 *
	 * @param CatalogItem $item           Item.
	 * @param string      $reference_date Reference date.
	 * @return bool
	 */
	protected function introduced_after( CatalogItem $item, $reference_date ) {
		if ( '' === $reference_date || '' === (string) $item->first_seen ) {
			return false;
		}

		return substr( (string) $item->first_seen, 0, 10 ) > $reference_date;
	}
}
