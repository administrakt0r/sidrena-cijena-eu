<?php
/**
 * Special sale data access.
 *
 * @package Titanworks\SidrenaCijena\Database
 */

namespace Titanworks\SidrenaCijena\Database;

use Titanworks\SidrenaCijena\Support\Currency;
use Titanworks\SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for `{prefix}sidrena_special_sales`.
 */
class SpecialSaleRepository {

	/**
	 * Allowed statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'scheduled', 'active', 'ended', 'cancelled' );

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Request-local active-sale snapshot.
	 *
	 * @var array<string,array<mixed>>|null
	 */
	protected static $active_map_cache = null;

	/**
	 * Clear the active sale snapshot cache.
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$active_map_cache = null;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->table = $wpdb->prefix . 'sidrena_special_sales';
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public function table() {
		return $this->table;
	}

	/**
	 * Insert a sale.
	 *
	 * @param array<mixed> $data Fields.
	 * @return int Inserted ID.
	 * @throws \InvalidArgumentException When required fields are missing.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$row                    = $this->normalize( $data );
		self::$active_map_cache = null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $this->table, $row, $this->formats() );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a sale.
	 *
	 * @param int          $id   Sale ID.
	 * @param array<mixed> $data Fields.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$existing = $this->get( $id );

		if ( ! $existing ) {
			return false;
		}

		$row                    = $this->normalize( array_merge( $existing, $data ) );
		self::$active_map_cache = null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( $this->table, $row, array( 'id' => (int) $id ), $this->formats(), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Set the status of a sale.
	 *
	 * @param int    $id     Sale ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public function set_status( $id, $status ) {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}

		return $this->update( $id, array( 'status' => $status ) );
	}

	/**
	 * Fetch a sale.
	 *
	 * @param int $id Sale ID.
	 * @return array|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, (int) $id ),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * All sales for an item/variation, newest first.
	 *
	 * @param int          $product_id   Item ID.
	 * @param int          $variation_id Variation ID (0 for none).
	 * @param array<mixed> $statuses     Optional status filter.
	 * @return array<mixed>[]
	 */
	public function for_item( $product_id, $variation_id = 0, array $statuses = array() ) {
		global $wpdb;

		$filter_status = ! empty( $statuses );
		$statuses      = array_values( array_unique( array_intersect( $statuses, self::STATUSES ) ) );
		if ( $filter_status && empty( $statuses ) ) {
			return array();
		}
		$variation_id = (int) $variation_id;
		$params       = array_merge(
			array( $this->table, (int) $product_id, $variation_id, $variation_id, $variation_id, $filter_status ? 1 : 0 ),
			array_pad( $statuses, 4, '' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Repository needs current records after writes in the same request.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- wpdb accepts the complete, ordered argument array.
			$wpdb->prepare(
				'SELECT * FROM %i WHERE product_id = %d
				AND ((%d > 0 AND variation_id = %d) OR (%d <= 0 AND variation_id IS NULL))
				AND (%d = 0 OR status IN (%s, %s, %s, %s)) ORDER BY start_date DESC',
				$params
			),
			ARRAY_A
		);

		return array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * The sale that is active (or scheduled to start) for an item at a moment.
	 *
	 * @param int    $product_id   Item ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $at           Site-local datetime (`Y-m-d H:i:s`); defaults to now.
	 * @return array|null
	 */
	public function active_for_item( $product_id, $variation_id = 0, $at = '' ) {
		if ( '' === (string) $at ) {
			$map = $this->active_map();
			$key = (int) $product_id . ':' . (int) $variation_id;

			return isset( $map[ $key ] ) ? $map[ $key ] : null;
		}

		$at = $at ? $at : Dates::now_site()->format( 'Y-m-d H:i:s' );

		foreach ( $this->for_item( $product_id, $variation_id, array( 'active', 'scheduled' ) ) as $sale ) {
			$started = $sale['start_date'] <= $at;
			$open    = empty( $sale['end_date'] ) || $sale['end_date'] >= $at;

			if ( $started && $open ) {
				return $sale;
			}
		}

		return null;
	}

	/**
	 * Active/scheduled sales for many items, keyed by `product:variation`.
	 *
	 * @param string $at Site-local datetime.
	 * @return array<string,array<mixed>>
	 */
	public function active_map( $at = '' ) {
		global $wpdb;

		$cache_result = ! $at;
		if ( $cache_result && null !== self::$active_map_cache ) {
			return self::$active_map_cache;
		}

		$at = $at ? $at : Dates::now_site()->format( 'Y-m-d H:i:s' );

		$prepared = $wpdb->prepare(
			"SELECT * FROM %i
			WHERE status IN ('scheduled','active')
			AND start_date <= %s
			AND ( end_date IS NULL OR end_date >= %s )
			ORDER BY start_date DESC",
			$this->table,
			$at,
			$at
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

		$map = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$row = $this->hydrate( $row );
			$key = (int) $row['product_id'] . ':' . (int) $row['variation_id'];
			if ( ! isset( $map[ $key ] ) ) {
				$map[ $key ] = $row;
			}
		}

		if ( $cache_result ) {
			self::$active_map_cache = $map;
		}

		return $map;
	}

	/**
	 * Transition sales whose dates have passed (`scheduled → active → ended`).
	 *
	 * @param string $now Site-local datetime.
	 * @return int Number of rows updated.
	 */
	public function refresh_statuses( $now = '' ) {
		global $wpdb;
		self::$active_map_cache = null;

		$now = $now ? $now : Dates::now_site()->format( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$activated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'active', updated_at = %s
				WHERE status = 'scheduled' AND start_date <= %s AND ( end_date IS NULL OR end_date >= %s )",
				$this->table,
				Dates::now_utc(),
				$now,
				$now
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ended = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'ended', updated_at = %s
				WHERE status IN ('scheduled','active') AND end_date IS NOT NULL AND end_date < %s",
				$this->table,
				Dates::now_utc(),
				$now
			)
		);

		$total = (int) $activated + (int) $ended;

		if ( $total > 0 ) {
			do_action( 'twbce_cijena_special_sale_changed', 0, 0, 0 );
		}

		return $total;
	}

	/**
	 * Count sales by status.
	 *
	 * @return array<string,int>
	 */
	public function count_by_status() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM %i GROUP BY status', $this->table ), ARRAY_A );

		$counts = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$counts[ $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Normalize a row for persistence.
	 *
	 * @param array<mixed> $data Input.
	 * @return array<mixed>
	 * @throws \InvalidArgumentException When required fields are missing.
	 */
	protected function normalize( array $data ) {
		$product_id = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;

		if ( $product_id <= 0 ) {
			throw new \InvalidArgumentException( 'A special sale requires product_id.' );
		}

		$sale_type = isset( $data['sale_type'] ) ? sanitize_key( (string) $data['sale_type'] ) : '';

		if ( '' === $sale_type ) {
			throw new \InvalidArgumentException( 'A special sale requires a sale_type (FR-023).' );
		}

		$start_date = isset( $data['start_date'] ) ? (string) $data['start_date'] : '';

		if ( ! $start_date || ! Dates::parse_site( $start_date ) ) {
			throw new \InvalidArgumentException( 'A special sale requires a valid start_date.' );
		}

		$end_date = ! empty( $data['end_date'] ) ? (string) $data['end_date'] : null;

		if ( $end_date && Dates::is_valid_date( $end_date ) ) {
			// The end date is inclusive, so a date-only value has to cover the
			// whole day; otherwise the sale would end at midnight and a one-day
			// sale could never be active.
			$end_date .= ' 23:59:59';
		}

		if ( $end_date && strtotime( $end_date ) < strtotime( $start_date ) ) {
			throw new \InvalidArgumentException( 'end_date must be later than start_date.' );
		}

		$status = isset( $data['status'] ) && in_array( $data['status'], self::STATUSES, true ) ? $data['status'] : 'scheduled';

		return array(
			'product_id'          => $product_id,
			'variation_id'        => ! empty( $data['variation_id'] ) ? (int) $data['variation_id'] : null,
			'sale_type'           => $sale_type,
			'sale_name'           => isset( $data['sale_name'] ) ? sanitize_text_field( (string) $data['sale_name'] ) : null,
			'start_date'          => $start_date,
			'end_date'            => $end_date,
			'promo_price'         => Currency::to_storage( isset( $data['promo_price'] ) && '' !== $data['promo_price'] ? $data['promo_price'] : null ),
			'lowest_30_day_price' => isset( $data['lowest_30_day_price'] ) && '' !== $data['lowest_30_day_price'] ? (float) $data['lowest_30_day_price'] : null,
			'window_start'        => ! empty( $data['window_start'] ) ? (string) $data['window_start'] : null,
			'window_end'          => ! empty( $data['window_end'] ) ? (string) $data['window_end'] : null,
			'status'              => $status,
			'created_at'          => isset( $data['created_at'] ) ? (string) $data['created_at'] : Dates::now_utc(),
			'updated_at'          => Dates::now_utc(),
			'created_by'          => ! empty( $data['created_by'] ) ? (int) $data['created_by'] : null,
			'metadata'            => ! empty( $data['metadata'] ) ? ( is_array( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : (string) $data['metadata'] ) : null,
		);
	}

	/**
	 * Formats for $wpdb.
	 *
	 * @return string[]
	 */
	protected function formats() {
		return array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );
	}

	/**
	 * Cast types.
	 *
	 * @param array<mixed> $row Row.
	 * @return array<mixed>
	 */
	protected function hydrate( array $row ) {
		$row['id']                  = (int) $row['id'];
		$row['product_id']          = (int) $row['product_id'];
		$row['variation_id']        = (int) $row['variation_id'];
		$row['promo_price']         = isset( $row['promo_price'] ) ? (float) $row['promo_price'] : null;
		$row['lowest_30_day_price'] = isset( $row['lowest_30_day_price'] ) ? (float) $row['lowest_30_day_price'] : null;

		return $row;
	}
}
