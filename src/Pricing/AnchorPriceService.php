<?php
/**
 * Anchor price resolution and recording.
 *
 * Anchor records are historical, append-only data. Recording a new anchor for
 * an item that already has one requires a correction reason and creates a new
 * record that references the original (FR-003 to FR-010).
 *
 * @package SidrenaCijena\Pricing
 */

namespace SidrenaCijena\Pricing;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Compliance\ReferenceDateResolver;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Anchor price service.
 */
class AnchorPriceService {

	/**
	 * Price history repository.
	 *
	 * @var PriceHistoryRepository
	 */
	protected $history;

	/**
	 * Anchor cache.
	 *
	 * @var AnchorCache|null
	 */
	protected $cache;

	/**
	 * Constructor.
	 *
	 * @param PriceHistoryRepository $history History repository.
	 * @param AnchorCache|null       $cache   Anchor cache.
	 */
	public function __construct( PriceHistoryRepository $history, ?AnchorCache $cache = null ) {
		$this->history = $history;
		$this->cache   = $cache;
	}

	/**
	 * Latest anchor record for an item.
	 *
	 * @param CatalogItem $item Catalog item.
	 * @return array|null
	 */
	public function get_anchor( CatalogItem $item ) {
		if ( $this->cache ) {
			$cached = $this->cache->get_anchor( $item->id, $item->variation_id );

			if ( null !== $cached || $this->cache->is_hit() ) {
				return $cached;
			}
		}

		$anchor = $this->history->latest( $item->id, $item->variation_id, 'anchor' );

		if ( $this->cache ) {
			$this->cache->set_anchor( $item->id, $item->variation_id, $anchor );
		}

		return $anchor;
	}

	/**
	 * Latest anchor price value.
	 *
	 * @param CatalogItem $item Catalog item.
	 * @return float|null
	 */
	public function get_anchor_price( CatalogItem $item ) {
		$anchor = $this->get_anchor( $item );

		if ( ! $anchor || null === $anchor['price'] ) {
			return null;
		}

		return (float) $anchor['price'];
	}

	/**
	 * Record an anchor price.
	 *
	 * @param CatalogItem  $item Catalog item.
	 * @param string|float $price Anchor price.
	 * @param array        $args  `reference_date`, `source`, `correction_reason`, `correction_of`, `tax_inclusive`, `created_by`, `metadata`.
	 * @return int|WP_Error Record ID or error.
	 */
	public function record( CatalogItem $item, $price, array $args = array() ) {
		$price = Currency::to_storage( $price );

		if ( null === $price || $price < 0 ) {
			return new WP_Error(
				'invalid_anchor_price',
				__( 'The anchor price must be a non-negative number.', 'sidrena-cijena' )
			);
		}

		$resolved = ReferenceDateResolver::resolve( $item );

		$reference_date = isset( $args['reference_date'] ) && '' !== $args['reference_date']
			? (string) $args['reference_date']
			: $resolved['date'];

		if ( ! Dates::is_valid_date( $reference_date ) ) {
			return new WP_Error(
				'invalid_reference_date',
				__( 'The reference date is not a valid date (YYYY-MM-DD).', 'sidrena-cijena' )
			);
		}

		$existing = $this->history->latest( $item->id, $item->variation_id, 'anchor' );

		$correction_of = isset( $args['correction_of'] ) ? (int) $args['correction_of'] : 0;

		if ( ! $correction_of && $existing ) {
			$correction_of = (int) $existing['id'];
		}

		$reason = isset( $args['correction_reason'] ) ? trim( (string) $args['correction_reason'] ) : '';

		if ( $correction_of && '' === $reason ) {
			return new WP_Error(
				'correction_reason_required',
				__( 'Changing an existing anchor price requires a correction reason.', 'sidrena-cijena' )
			);
		}

		$record = array(
			'product_id'     => (int) $item->id,
			'variation_id'   => (int) $item->variation_id,
			'item_type'      => $item->type,
			'sku'            => $item->sku,
			'price_type'     => 'anchor',
			'price'          => $price,
			'currency'       => $item->currency ? $item->currency : Currency::DEFAULT_CURRENCY,
			'tax_inclusive'  => isset( $args['tax_inclusive'] ) ? (bool) $args['tax_inclusive'] : $item->tax_inclusive,
			'valid_from'     => isset( $args['valid_from'] ) ? $args['valid_from'] : null,
			'valid_to'       => null,
			'reference_date' => $reference_date,
			'source'         => isset( $args['source'] ) && '' !== $args['source'] ? (string) $args['source'] : 'manual',
			'created_by'     => isset( $args['created_by'] ) ? (int) $args['created_by'] : get_current_user_id(),
			'metadata'       => isset( $args['metadata'] ) ? $args['metadata'] : null,
		);

		if ( $correction_of ) {
			$record['correction_of']     = $correction_of;
			$record['correction_reason'] = $reason;
			$id                          = $this->history->insert_correction( $record );
		} else {
			$id = $this->history->insert( $record );
		}

		if ( ! $id ) {
			return new WP_Error(
				'anchor_record_failed',
				__( 'The anchor price could not be recorded.', 'sidrena-cijena' )
			);
		}

		if ( $this->cache ) {
			$this->cache->invalidate( $item->id, $item->variation_id );
		}

		/**
		 * Fires after an anchor record has been created.
		 *
		 * @param int    $id             Record ID.
		 * @param int    $product_id     Product ID.
		 * @param int    $variation_id   Variation ID.
		 * @param string $reference_date Reference date.
		 */
		do_action( 'sidrena_cijena_anchor_recorded', $id, $item->id, $item->variation_id, $reference_date );

		return $id;
	}

	/**
	 * Anchor status for an item.
	 *
	 * @param CatalogItem $item Catalog item.
	 * @return array{state:string,anchor:array|null,reference_date:string,rule:string,introduced_after:bool}
	 */
	public function status( CatalogItem $item ) {
		$resolved = ReferenceDateResolver::resolve( $item );
		$anchor   = $this->get_anchor( $item );

		$introduced_after = $item->first_seen && $item->first_seen > $resolved['date'];

		if ( ! $anchor ) {
			return array(
				'state'            => 'missing',
				'anchor'           => null,
				'reference_date'   => $resolved['date'],
				'rule'             => $resolved['rule'],
				'introduced_after' => (bool) $introduced_after,
			);
		}

		$state = (string) $anchor['reference_date'] === $resolved['date'] ? 'valid' : 'needs_verification';

		return array(
			'state'            => $state,
			'anchor'           => $anchor,
			'reference_date'   => $resolved['date'],
			'rule'             => $resolved['rule'],
			'introduced_after' => (bool) $introduced_after,
		);
	}

	/**
	 * Whether an item's anchor needs administrator verification.
	 *
	 * @param CatalogItem $item Catalog item.
	 * @return bool
	 */
	public function needs_verification( CatalogItem $item ) {
		$status = $this->status( $item );

		return 'needs_verification' === $status['state'] || ( 'missing' === $status['state'] && $status['introduced_after'] );
	}
}
