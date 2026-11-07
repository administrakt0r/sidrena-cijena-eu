<?php
/**
 * Append-only price history ledger.
 *
 * Rows are never updated or deleted through this repository; corrections are
 * new rows referencing the original (FR-005, FR-028).
 *
 * @package Titanworks\SidrenaCijena\Database
 */

namespace Titanworks\SidrenaCijena\Database;

use Titanworks\SidrenaCijena\Support\Currency;
use Titanworks\SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for `{prefix}sidrena_price_history`.
 */
class PriceHistoryRepository {

	/**
	 * Allowed price types.
	 *
	 * @var string[]
	 */
	const TYPES = array( 'regular', 'sale', 'anchor', 'lowest_30_day', 'historical' );

	/**
	 * Allowed sources.
	 *
	 * @var string[]
	 */
	const SOURCES = array( 'woocommerce', 'standalone', 'manual', 'bulk', 'import', 'cli', 'migration', 'system', 'observed_first_offer' );

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Request-level memoized active sale period map.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	protected static $active_sale_period_cache = array();

	/**
	 * Product IDs whose active sale periods have been queried during this request.
	 *
	 * @var array<int,bool>
	 */
	protected static $queried_sale_period_ids = array();

	/**
	 * In-memory count cache per query args.
	 *
	 * @var array<string,int>
	 */
	protected static $count_cache = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->table = $wpdb->prefix . 'sidrena_price_history';
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
	 * Clear the request-level query cache.
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$active_sale_period_cache = array();
		self::$queried_sale_period_ids  = array();
		self::$count_cache              = array();
	}

	/**
	 * Append a price history record.
	 *
	 * @param array<mixed> $data Record fields.
	 * @return int Inserted row ID.
	 * @throws \InvalidArgumentException When required fields are invalid.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$this->reset_cache();

		$row = $this->normalize( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $this->table, $row, $this->formats( $row ) );

		if ( false === $inserted || (int) $wpdb->insert_id <= 0 ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Append a correction record referencing an existing row.
	 *
	 * @param array<mixed> $data Record fields; `correction_of` and `correction_reason` are required.
	 * @return int Inserted row ID.
	 * @throws \InvalidArgumentException When the correction is invalid.
	 */
	public function insert_correction( array $data ) {
		if ( empty( $data['correction_of'] ) ) {
			throw new \InvalidArgumentException( 'A correction requires correction_of.' );
		}

		if ( empty( $data['correction_reason'] ) ) {
			throw new \InvalidArgumentException( 'A correction requires a non-empty correction_reason.' );
		}

		$original = $this->get( (int) $data['correction_of'] );

		if ( ! $original ) {
			throw new \InvalidArgumentException( 'correction_of must reference an existing price history record.' );
		}

		$data['product_id']   = isset( $data['product_id'] ) ? (int) $data['product_id'] : (int) $original['product_id'];
		$data['variation_id'] = isset( $data['variation_id'] ) ? (int) $data['variation_id'] : (int) $original['variation_id'];
		$data['price_type']   = isset( $data['price_type'] ) ? $data['price_type'] : $original['price_type'];

		if ( (int) $original['product_id'] !== (int) $data['product_id']
			|| (int) $original['variation_id'] !== (int) $data['variation_id']
			|| $original['price_type'] !== $data['price_type'] ) {
			throw new \InvalidArgumentException( 'A correction must reference a record of the same item, variation and price type.' );
		}

		return $this->insert( $data );
	}

	/**
	 * Fetch a row by ID.
	 *
	 * @param int $id Row ID.
	 * @return array|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, (int) $id ),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Latest record of a type for an item/variation.
	 *
	 * @param int    $product_id   Item ID.
	 * @param int    $variation_id Variation ID (0 for none).
	 * @param string $price_type   Price type.
	 * @return array|null
	 */
	public function latest( $product_id, $variation_id, $price_type ) {
		$rows = $this->query(
			array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'price_type'   => $price_type,
				'orderby'      => 'id',
				'order'        => 'DESC',
				'limit'        => 1,
			)
		);

		return isset( $rows[0] ) ? $rows[0] : null;
	}

	/**
	 * Latest anchor record for an item/variation.
	 *
	 * @param int $product_id   Item ID.
	 * @param int $variation_id Variation ID (0 for none).
	 * @return array|null
	 */
	public function latest_anchor( $product_id, $variation_id = 0 ) {
		return $this->latest( $product_id, $variation_id, 'anchor' );
	}

	/**
	 * Latest records for several items at once, keyed by `product:variation`.
	 *
	 * @param int[]  $product_ids Item IDs.
	 * @param string $price_type  Price type.
	 * @return array<string,array<mixed>>
	 */
	public function latest_map( array $product_ids, $price_type ) {
		global $wpdb;

		$product_ids = array_values( array_unique( array_filter( array_map( 'intval', $product_ids ) ) ) );

		if ( empty( $product_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
		$params       = array_merge( array( $this->table, $this->table, $price_type ), $product_ids );

		// Select only each item's greatest ledger ID. Corrections are appended,
		// so this preserves the existing latest-by-ID rule without loading history.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT history.* FROM %i AS history
			INNER JOIN (
				SELECT product_id, variation_id, MAX(id) AS latest_id
				FROM %i
				WHERE price_type = %s AND product_id IN ({$placeholders})
				GROUP BY product_id, variation_id
			) AS latest ON latest.latest_id = history.id
			ORDER BY history.id ASC",
			$params
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$map = array();

		foreach ( $rows as $row ) {
			$map[ (int) $row['product_id'] . ':' . (int) $row['variation_id'] ] = $row;
		}

		return $map;
	}

	/**
	 * First sale-price row in each currently open WooCommerce sale interval.
	 *
	 * A null sale row closes an interval. Progressive reductions remain in the
	 * same interval; a later sale after a closure starts a new offer.
	 *
	 * @param int[] $product_ids Product IDs.
	 * @return array<string,array<string,mixed>> Map keyed by `product:variation`.
	 */
	public function active_sale_period_map( array $product_ids ) {
		global $wpdb;

		$product_ids = array_values( array_unique( array_filter( array_map( 'intval', $product_ids ) ) ) );

		if ( empty( $product_ids ) ) {
			return array();
		}

		$missing_ids = array();
		foreach ( $product_ids as $pid ) {
			if ( ! isset( self::$queried_sale_period_ids[ $pid ] ) ) {
				$missing_ids[] = $pid;
			}
		}

		if ( ! empty( $missing_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $missing_ids ), '%d' ) );
			$params       = array_merge( array( $this->table, $this->table, $this->table ), $missing_ids, $missing_ids, array( $this->table ) );

			// The first non-null price after the latest closure identifies this offer;
			// later reductions keep the same start row and reference window.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$sql = $wpdb->prepare(
				"SELECT history.product_id, COALESCE(history.variation_id, 0) AS variation_id, history.price, history.valid_from, closure_event.valid_from AS prior_closure_at
				FROM %i AS history
				INNER JOIN (
					SELECT sale.product_id, sale.variation_id, MIN(sale.id) AS start_id, MAX(closure.closure_id) AS closure_id
					FROM %i AS sale
					LEFT JOIN (
						SELECT product_id, variation_id, MAX(id) AS closure_id
						FROM %i
						WHERE price_type = 'sale' AND price IS NULL AND product_id IN ({$placeholders})
						GROUP BY product_id, variation_id
					) AS closure ON closure.product_id = sale.product_id AND closure.variation_id <=> sale.variation_id
					WHERE sale.price_type = 'sale' AND sale.price IS NOT NULL AND sale.product_id IN ({$placeholders})
					AND sale.id > COALESCE(closure.closure_id, 0)
					GROUP BY sale.product_id, sale.variation_id
				) AS period ON period.start_id = history.id
				LEFT JOIN %i AS closure_event ON closure_event.id = period.closure_id
				ORDER BY history.id ASC",
				$params
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $sql, ARRAY_A );

			foreach ( (array) $rows as $row ) {
				$key                                    = (int) $row['product_id'] . ':' . (int) $row['variation_id'];
				self::$active_sale_period_cache[ $key ] = $row;
			}

			foreach ( $missing_ids as $pid ) {
				self::$queried_sale_period_ids[ $pid ] = true;
			}
		}

		$map = array();
		foreach ( self::$active_sale_period_cache as $key => $row ) {
			$pid = (int) $row['product_id'];
			if ( in_array( $pid, $product_ids, true ) ) {
				$map[ $key ] = $row;
			}
		}

		return $map;
	}

	/**
	 * Which of several items already have a record of a type.
	 *
	 * One query for the whole batch, keyed by `product:variation`, to avoid an
	 * existence check per catalog item on large shops.
	 *
	 * @param int[]  $product_ids Item IDs.
	 * @param string $price_type  Price type.
	 * @return array<string,bool> Map of `product:variation` => true.
	 */
	public function exists_map( array $product_ids, $price_type ) {
		global $wpdb;

		$product_ids = array_values( array_unique( array_filter( array_map( 'intval', $product_ids ) ) ) );

		if ( empty( $product_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
		$params       = array_merge( array( $this->table, $price_type ), $product_ids );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT DISTINCT product_id, variation_id FROM %i WHERE price_type = %s AND product_id IN ({$placeholders})",
			$params
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$map = array();

		foreach ( (array) $rows as $row ) {
			$map[ (int) $row['product_id'] . ':' . (int) $row['variation_id'] ] = true;
		}

		return $map;
	}

	/**
	 * Query records.
	 *
	 * Supported args: `product_id`, `variation_id`, `price_type`, `source`,
	 * `date_from`, `date_to`, `reference_date`, `limit`, `offset`, `orderby`, `order`.
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return array<mixed>[]
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		$orderby   = $this->sanitize_orderby( isset( $args['orderby'] ) ? $args['orderby'] : 'created_at' );
		$ascending = isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] );
		$limit     = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 100;
		$offset    = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$params    = array_merge( array( $this->table ), $this->filter_parameters( $args ), array( $orderby, $limit, $offset ) );

		if ( $ascending ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Repository needs current records after writes in the same request.
			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- wpdb accepts the complete, ordered argument array.
				$wpdb->prepare(
					"SELECT * FROM %i WHERE (%d = 0 OR product_id = %d)
			AND (%d = 0 OR (%d > 0 AND variation_id = %d) OR (%d <= 0 AND variation_id IS NULL))
			AND (%s = '' OR price_type = %s) AND (%s = '' OR source = %s)
			AND (%s = '' OR item_type = %s) AND (%s = '' OR sku = %s)
			AND (%s = '' OR reference_date = %s) AND (%s = '' OR created_at >= %s)
			AND (%s = '' OR created_at <= %s) AND (%d = 0 OR correction_of = %d) ORDER BY %i ASC LIMIT %d OFFSET %d",
					$params
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Repository needs current records after writes in the same request.
			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- wpdb accepts the complete, ordered argument array.
				$wpdb->prepare(
					"SELECT * FROM %i WHERE (%d = 0 OR product_id = %d)
			AND (%d = 0 OR (%d > 0 AND variation_id = %d) OR (%d <= 0 AND variation_id IS NULL))
			AND (%s = '' OR price_type = %s) AND (%s = '' OR source = %s)
			AND (%s = '' OR item_type = %s) AND (%s = '' OR sku = %s)
			AND (%s = '' OR reference_date = %s) AND (%s = '' OR created_at >= %s)
			AND (%s = '' OR created_at <= %s) AND (%d = 0 OR correction_of = %d) ORDER BY %i DESC LIMIT %d OFFSET %d",
					$params
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count records matching a query.
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return int
	 */
	public function count( array $args = array() ) {
		$cache_key = md5( wp_json_encode( $args ) );
		if ( isset( self::$count_cache[ $cache_key ] ) ) {
			return self::$count_cache[ $cache_key ];
		}

		global $wpdb;

		$params = array_merge( array( $this->table ), $this->filter_parameters( $args ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Repository needs current records after writes in the same request.
		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- wpdb accepts the complete, ordered argument array.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE (%d = 0 OR product_id = %d)
			AND (%d = 0 OR (%d > 0 AND variation_id = %d) OR (%d <= 0 AND variation_id IS NULL))
			AND (%s = '' OR price_type = %s) AND (%s = '' OR source = %s)
			AND (%s = '' OR item_type = %s) AND (%s = '' OR sku = %s)
			AND (%s = '' OR reference_date = %s) AND (%s = '' OR created_at >= %s)
			AND (%s = '' OR created_at <= %s) AND (%d = 0 OR correction_of = %d)",
				$params
			)
		);

		self::$count_cache[ $cache_key ] = $total;

		return $total;
	}

	/**
	 * Timeline used by the 30-day calculator: records that can cover a date
	 * range, ordered chronologically.
	 *
	 * A row counts when it overlaps `[since_date, until_date]`, so a sale that
	 * ended before the promotion still contributes its price to the window.
	 *
	 * @param int    $product_id   Item ID.
	 * @param int    $variation_id Variation ID (0 for none).
	 * @param string $until_date   Inclusive upper bound (`Y-m-d`).
	 * @param string $since_date   Inclusive lower bound (`Y-m-d`), empty for no lower bound.
	 * @return array<mixed>[]
	 */
	public function timeline( $product_id, $variation_id, $until_date, $since_date = '' ) {
		global $wpdb;

		$variation_id = (int) $variation_id;

		$lower_bound = '' !== (string) $since_date ? (string) $since_date : (string) $until_date;

		$params = array( $this->table, (int) $product_id, $variation_id, $variation_id, $variation_id, $until_date . ' 23:59:59', $lower_bound . ' 00:00:00' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Repository needs current records after writes in the same request.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- wpdb accepts the complete, ordered argument array.
			$wpdb->prepare(
				"SELECT * FROM %i
			WHERE product_id = %d
			AND ((%d > 0 AND variation_id = %d) OR (%d <= 0 AND variation_id IS NULL))
			AND price_type IN ('regular','sale','historical','lowest_30_day')
			AND ( valid_from IS NULL OR valid_from <= %s )
			AND ( valid_to IS NULL OR valid_to >= %s )
			ORDER BY COALESCE(valid_from, created_at) ASC, id ASC",
				$params
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Validate and normalize a record for insertion.
	 *
	 * @param array<mixed> $data Input fields.
	 * @return array<mixed>
	 * @throws \InvalidArgumentException When a required field is invalid.
	 */
	protected function normalize( array $data ) {
		$price_type = isset( $data['price_type'] ) ? (string) $data['price_type'] : '';

		if ( ! in_array( $price_type, self::TYPES, true ) ) {
			throw new \InvalidArgumentException( 'Invalid price_type.' );
		}

		$source = isset( $data['source'] ) ? (string) $data['source'] : 'manual';

		if ( ! in_array( $source, self::SOURCES, true ) ) {
			$source = 'manual';
		}

		$product_id = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;

		if ( $product_id <= 0 ) {
			throw new \InvalidArgumentException( 'product_id is required.' );
		}

		$variation_id = isset( $data['variation_id'] ) ? (int) $data['variation_id'] : 0;
		$price        = Currency::to_storage( isset( $data['price'] ) ? $data['price'] : null );

		if ( null !== $price && $price < 0 ) {
			throw new \InvalidArgumentException( 'price must be null or non-negative.' );
		}

		$currency = isset( $data['currency'] ) ? strtoupper( (string) $data['currency'] ) : Currency::DEFAULT_CURRENCY;

		if ( ! Currency::is_valid_code( $currency ) ) {
			throw new \InvalidArgumentException( 'currency must be a three-letter ISO 4217 code.' );
		}

		$reference_date = isset( $data['reference_date'] ) && '' !== $data['reference_date'] ? (string) $data['reference_date'] : null;

		if ( 'anchor' === $price_type ) {
			if ( null === $reference_date || ! Dates::is_valid_date( $reference_date ) ) {
				throw new \InvalidArgumentException( 'Anchor records require a valid reference_date.' );
			}
		} elseif ( null !== $reference_date && ! Dates::is_valid_date( $reference_date ) ) {
			throw new \InvalidArgumentException( 'reference_date must be a valid date.' );
		}

		$valid_from = ! empty( $data['valid_from'] ) ? (string) $data['valid_from'] : null;
		$valid_to   = ! empty( $data['valid_to'] ) ? (string) $data['valid_to'] : null;

		if ( $valid_from && $valid_to && strtotime( $valid_to ) < strtotime( $valid_from ) ) {
			throw new \InvalidArgumentException( 'valid_to must be later than valid_from.' );
		}

		$item_type = isset( $data['item_type'] ) && 'service' === $data['item_type'] ? 'service' : 'product';

		return array(
			'product_id'        => $product_id,
			'variation_id'      => $variation_id > 0 ? $variation_id : null,
			'item_type'         => $item_type,
			'sku'               => isset( $data['sku'] ) && '' !== $data['sku'] ? (string) $data['sku'] : null,
			'price_type'        => $price_type,
			'price'             => $price,
			'currency'          => $currency,
			'tax_inclusive'     => empty( $data['tax_inclusive'] ) ? 0 : 1,
			'valid_from'        => $valid_from,
			'valid_to'          => $valid_to,
			'reference_date'    => $reference_date,
			'source'            => $source,
			'special_sale_type' => isset( $data['special_sale_type'] ) && '' !== $data['special_sale_type'] ? (string) $data['special_sale_type'] : null,
			'correction_of'     => ! empty( $data['correction_of'] ) ? (int) $data['correction_of'] : null,
			'correction_reason' => ! empty( $data['correction_reason'] ) ? (string) $data['correction_reason'] : null,
			'created_at'        => ! empty( $data['created_at'] ) ? (string) $data['created_at'] : Dates::now_utc(),
			'updated_at'        => null,
			'created_by'        => ! empty( $data['created_by'] ) ? (int) $data['created_by'] : null,
			'metadata'          => ! empty( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
		);
	}

	/**
	 * Column formats for $wpdb.
	 *
	 * @param array<mixed> $row Row.
	 * @return string[]
	 */
	protected function formats( array $row ) {
		$formats = array();

		foreach ( $row as $key => $value ) {
			if ( in_array( $key, array( 'product_id', 'variation_id', 'tax_inclusive', 'correction_of', 'created_by' ), true ) ) {
				$formats[] = '%d';
			} elseif ( 'price' === $key ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}

		return $formats;
	}

	/**
	 * Bind optional filters to the fixed query predicates.
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return array<int,int|string>
	 */
	protected function filter_parameters( array $args ) {
		$product   = ! empty( $args['product_id'] ) ? (int) $args['product_id'] : 0;
		$variation = isset( $args['variation_id'] ) ? (int) $args['variation_id'] : 0;
		$params    = array( $product, $product, isset( $args['variation_id'] ) ? 1 : 0, $variation, $variation, $variation );
		foreach ( array( 'price_type', 'source', 'item_type', 'sku', 'reference_date', 'date_from', 'date_to' ) as $key ) {
			$value    = ! empty( $args[ $key ] ) ? (string) $args[ $key ] : '';
			$params[] = $value;
			$params[] = $value;
		}
		$correction = ! empty( $args['correction_of'] ) ? (int) $args['correction_of'] : 0;
		$params[]   = $correction;
		$params[]   = $correction;
		return $params;
	}

	/**
	 * Whitelist ORDER BY columns.
	 *
	 * @param string $orderby Requested column.
	 * @return string
	 */
	protected function sanitize_orderby( $orderby ) {
		$allowed = array( 'id', 'created_at', 'valid_from', 'reference_date', 'price' );

		return in_array( $orderby, $allowed, true ) ? $orderby : 'created_at';
	}
}
