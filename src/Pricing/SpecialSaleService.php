<?php
/**
 * Special sale service.
 *
 * @package Titanworks\SidrenaCijena\Pricing
 */

namespace Titanworks\SidrenaCijena\Pricing;

use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;
use Titanworks\SidrenaCijena\Database\SpecialSaleRepository;
use Titanworks\SidrenaCijena\Support\Currency;
use Titanworks\SidrenaCijena\Support\Dates;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates special sale records with their 30-day lowest price snapshot.
 *
 * A special sale always requires an explicit type; an unclassified promotion
 * is never treated as a special form of sale (FR-023). Recalculations append
 * new history rows and never rewrite earlier records.
 */
class SpecialSaleService {

	/**
	 * Special sale repository.
	 *
	 * @var SpecialSaleRepository
	 */
	protected $sales;

	/**
	 * Price history repository.
	 *
	 * @var PriceHistoryRepository
	 */
	protected $history;

	/**
	 * 30-day calculator.
	 *
	 * @var LowestPrice30DayCalculator
	 */
	protected $calculator;

	/**
	 * Constructor.
	 *
	 * @param SpecialSaleRepository      $sales      Special sale repository.
	 * @param PriceHistoryRepository     $history    Price history repository.
	 * @param LowestPrice30DayCalculator $calculator 30-day calculator.
	 */
	public function __construct( SpecialSaleRepository $sales, PriceHistoryRepository $history, LowestPrice30DayCalculator $calculator ) {
		$this->sales      = $sales;
		$this->history    = $history;
		$this->calculator = $calculator;
	}

	/**
	 * Classify a new special sale for an item.
	 *
	 * @param CatalogItem  $item Catalog item.
	 * @param array<mixed> $args Sale fields (`sale_type`, `sale_name`, `start_date`, `end_date`, `promo_price`).
	 * @return int|WP_Error Sale ID.
	 */
	public function create( CatalogItem $item, array $args ) {
		$prepared = $this->prepare( $item, $args );

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$sale_id = $this->sales->insert( $prepared['sale'] );

		if ( ! $sale_id ) {
			return new WP_Error(
				'special_sale_not_stored',
				__( 'The special sale could not be stored.', 'sidrena-cijena-eu' ),
				array( 'status' => 500 )
			);
		}

		$this->append_history( $item, $sale_id, $prepared );

		/**
		 * Fires after a special sale was classified.
		 *
		 * @param int $sale_id      Sale ID.
		 * @param int $product_id   Product ID.
		 * @param int $variation_id Variation ID.
		 */
		do_action( 'twbce_cijena_special_sale_changed', $sale_id, (int) $item->id, (int) $item->variation_id );

		return $sale_id;
	}

	/**
	 * Update an existing sale and recompute its 30-day price.
	 *
	 * @param CatalogItem  $item    Catalog item.
	 * @param int          $sale_id Sale ID.
	 * @param array<mixed> $args    Sale fields.
	 * @return bool|WP_Error
	 */
	public function update( CatalogItem $item, $sale_id, array $args ) {
		$existing = $this->sales->get( $sale_id );

		if ( ! $existing ) {
			return new WP_Error(
				'special_sale_not_found',
				__( 'The special sale could not be found.', 'sidrena-cijena-eu' ),
				array( 'status' => 404 )
			);
		}

		$prepared = $this->prepare( $item, $args );

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$prepared['sale']['status'] = isset( $args['status'] ) && in_array( $args['status'], SpecialSaleRepository::STATUSES, true )
			? (string) $args['status']
			: (string) $existing['status'];

		if ( ! $this->sales->update( $sale_id, $prepared['sale'] ) ) {
			return new WP_Error(
				'special_sale_not_updated',
				__( 'The special sale could not be updated.', 'sidrena-cijena-eu' ),
				array( 'status' => 500 )
			);
		}

		if ( $this->lowest_changed( $existing, $prepared ) ) {
			$this->append_history( $item, $sale_id, $prepared );
		}

		do_action( 'twbce_cijena_special_sale_changed', (int) $sale_id, (int) $item->id, (int) $item->variation_id );

		return true;
	}

	/**
	 * Recompute the stored 30-day price when underlying history changed.
	 *
	 * @param CatalogItem $item    Catalog item.
	 * @param int         $sale_id Sale ID.
	 * @return float|null|WP_Error Recalculated lowest price.
	 */
	public function recompute( CatalogItem $item, $sale_id ) {
		$existing = $this->sales->get( $sale_id );

		if ( ! $existing ) {
			return new WP_Error(
				'special_sale_not_found',
				__( 'The special sale could not be found.', 'sidrena-cijena-eu' ),
				array( 'status' => 404 )
			);
		}

		$prepared = $this->prepare(
			$item,
			array(
				'sale_type'   => $existing['sale_type'],
				'sale_name'   => $existing['sale_name'],
				'start_date'  => $existing['start_date'],
				'end_date'    => $existing['end_date'],
				'promo_price' => $existing['promo_price'],
				'status'      => $existing['status'],
			)
		);

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$this->sales->update( $sale_id, $prepared['sale'] );

		if ( $this->lowest_changed( $existing, $prepared ) ) {
			$this->append_history( $item, $sale_id, $prepared );

			do_action( 'twbce_cijena_special_sale_changed', (int) $sale_id, (int) $item->id, (int) $item->variation_id );
		}

		return $prepared['lowest'];
	}

	/**
	 * Cancel a sale.
	 *
	 * @param int $sale_id Sale ID.
	 * @return bool
	 */
	public function cancel( $sale_id ) {
		$cancelled = $this->sales->set_status( (int) $sale_id, 'cancelled' );

		if ( $cancelled ) {
			do_action( 'twbce_cijena_special_sale_changed', (int) $sale_id, 0, 0 );
		}

		return $cancelled;
	}

	/**
	 * The sale that currently applies to an item.
	 *
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $at           Site-local datetime.
	 * @return array|null
	 */
	public function active( $product_id, $variation_id = 0, $at = '' ) {
		return $this->sales->active_for_item( (int) $product_id, (int) $variation_id, $at );
	}

	/**
	 * Validate sale fields and calculate the 30-day window and price.
	 *
	 * @param CatalogItem  $item Catalog item.
	 * @param array<mixed> $args Sale fields.
	 * @return array{sale:array<mixed>,lowest:float|null,window:array{window_start:string,window_end:string}|null}|WP_Error
	 */
	protected function prepare( CatalogItem $item, array $args ) {
		$sale_type = isset( $args['sale_type'] ) ? sanitize_key( (string) $args['sale_type'] ) : '';

		if ( '' === $sale_type ) {
			return new WP_Error(
				'missing_sale_type',
				__( 'A special form of sale must be classified with a sale type (FR-023).', 'sidrena-cijena-eu' ),
				array( 'status' => 400 )
			);
		}

		$start = Dates::date_only( isset( $args['start_date'] ) ? (string) $args['start_date'] : '' );

		if ( ! Dates::is_valid_date( $start ) ) {
			return new WP_Error(
				'invalid_sale_start_date',
				__( 'The special sale needs a valid start date.', 'sidrena-cijena-eu' ),
				array( 'status' => 400 )
			);
		}

		$end = isset( $args['end_date'] ) && '' !== $args['end_date'] ? Dates::date_only( (string) $args['end_date'] ) : null;

		if ( null !== $end && ( ! Dates::is_valid_date( $end ) || $end < $start ) ) {
			return new WP_Error(
				'invalid_sale_end_date',
				__( 'The end date must be a valid date on or after the start date.', 'sidrena-cijena-eu' ),
				array( 'status' => 400 )
			);
		}

		// The end date is inclusive: store it as the last second of that day so
		// the sale stays active for the whole end day instead of ending at
		// midnight (a one-day sale must be able to be active).
		$end_stored = null !== $end ? $end . ' 23:59:59' : null;

		$promo = Currency::to_storage( isset( $args['promo_price'] ) && '' !== $args['promo_price'] ? $args['promo_price'] : null );

		$window = $this->calculator->window( $start );
		$lowest = $this->calculator->calculate(
			$this->history->timeline(
				(int) $item->id,
				(int) $item->variation_id,
				$start,
				$window ? $window['window_start'] : ''
			),
			$start
		);

		return array(
			'sale'   => array(
				'product_id'          => (int) $item->id,
				'variation_id'        => (int) $item->variation_id,
				'sale_type'           => $sale_type,
				'sale_name'           => isset( $args['sale_name'] ) ? (string) $args['sale_name'] : '',
				'start_date'          => $start,
				'end_date'            => $end_stored,
				'promo_price'         => $promo,
				'lowest_30_day_price' => $lowest,
				'window_start'        => $window ? $window['window_start'] : null,
				'window_end'          => $window ? $window['window_end'] : null,
				'status'              => isset( $args['status'] ) && in_array( $args['status'], SpecialSaleRepository::STATUSES, true ) ? (string) $args['status'] : 'scheduled',
				'created_by'          => get_current_user_id(),
			),
			'lowest' => $lowest,
			'window' => $window,
		);
	}

	/**
	 * Whether the recalculated snapshot differs from the stored one.
	 *
	 * @param array<mixed> $existing Stored sale row.
	 * @param array<mixed> $prepared Prepared data.
	 * @return bool
	 */
	protected function lowest_changed( array $existing, array $prepared ) {
		$old = null === $existing['lowest_30_day_price'] ? null : (float) $existing['lowest_30_day_price'];
		$new = null === $prepared['lowest'] ? null : (float) $prepared['lowest'];

		if ( $old !== $new ) {
			return true;
		}

		$old_window_start = (string) $existing['window_start'];
		$old_window_end   = (string) $existing['window_end'];
		$new_window_start = $prepared['window'] ? (string) $prepared['window']['window_start'] : '';
		$new_window_end   = $prepared['window'] ? (string) $prepared['window']['window_end'] : '';

		return $old_window_start !== $new_window_start || $old_window_end !== $new_window_end;
	}

	/**
	 * Append the computed 30-day price to the immutable history.
	 *
	 * @param CatalogItem  $item     Catalog item.
	 * @param int          $sale_id  Sale ID.
	 * @param array<mixed> $prepared Prepared data.
	 * @return int History row ID (0 when there is nothing to store).
	 */
	protected function append_history( CatalogItem $item, $sale_id, array $prepared ) {
		if ( null === $prepared['lowest'] ) {
			return 0;
		}

		return $this->history->insert(
			array(
				'product_id'     => (int) $item->id,
				'variation_id'   => (int) $item->variation_id,
				'item_type'      => $item->is_service() ? 'service' : 'product',
				'sku'            => $item->sku,
				'price_type'     => 'lowest_30_day',
				'price'          => (float) $prepared['lowest'],
				'currency'       => $item->currency,
				'valid_from'     => $prepared['sale']['start_date'],
				'valid_to'       => $prepared['sale']['end_date'],
				'reference_date' => null,
				'source'         => 'manual',
				'created_by'     => get_current_user_id(),
				'metadata'       => array(
					'sale_id'      => (int) $sale_id,
					'window_start' => $prepared['window'] ? $prepared['window']['window_start'] : null,
					'window_end'   => $prepared['window'] ? $prepared['window']['window_end'] : null,
				),
			)
		);
	}
}
