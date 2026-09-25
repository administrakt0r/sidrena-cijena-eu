<?php
/**
 * Append-only price history ledger.
 *
 * Rows are never updated or deleted through this repository; corrections are
 * new rows referencing the original (FR-005, FR-028).
 *
 * @package SidrenaCijena\Database
 */

namespace SidrenaCijena\Database;

use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;

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
	 * Append a price history record.
	 *
	 * @param array<mixed> $data Record fields.
	 * @return int Inserted row ID.
	 * @throws \InvalidArgumentException When required fields are invalid.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$row = $this->normalize( $data );

		$wpdb->insert( $this->table, $row, $this->formats( $row ) );

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

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
		$params       = array_merge( array( $price_type ), $product_ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE price_type = %s AND product_id IN ({$placeholders}) ORDER BY id ASC",
			$params
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$map = array();

		foreach ( $rows as $row ) {
			$map[ (int) $row['product_id'] . ':' . (int) $row['variation_id'] ] = $row;
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

		list( $where, $params ) = $this->build_where( $args );

		$orderby = $this->sanitize_orderby( isset( $args['orderby'] ) ? $args['orderby'] : 'created_at' );
		$order   = isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$limit  = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 100;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$sql      = "SELECT * FROM {$this->table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$prepared = $wpdb->prepare( $sql, $params );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count records matching a query.
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return int
	 */
	public function count( array $args = array() ) {
		global $wpdb;

		list( $where, $params ) = $this->build_where( $args );

		$sql = "SELECT COUNT(*) FROM {$this->table} {$where}";

		if ( empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			return (int) $wpdb->get_var( $sql );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$prepared = $wpdb->prepare( $sql, $params );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $prepared );
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

		$variation_sql = (int) $variation_id > 0
			? $wpdb->prepare( 'variation_id = %d', (int) $variation_id )
			: 'variation_id IS NULL';

		$lower_bound = '' !== (string) $since_date ? (string) $since_date : (string) $until_date;

		$sql = "SELECT * FROM {$this->table}
			WHERE product_id = %d
			AND {$variation_sql}
			AND price_type IN ('regular','sale','historical','lowest_30_day')
			AND ( valid_from IS NULL OR valid_from <= %s )
			AND ( valid_to IS NULL OR valid_to >= %s )
			ORDER BY COALESCE(valid_from, created_at) ASC, id ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$prepared = $wpdb->prepare( $sql, (int) $product_id, $until_date . ' 23:59:59', $lower_bound . ' 00:00:00' );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

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
	 * Build a WHERE clause with prepared values.
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return array{0:string,1:array}
	 */
	protected function build_where( array $args ) {
		global $wpdb;

		$conditions = array();
		$params     = array();

		if ( ! empty( $args['product_id'] ) ) {
			$conditions[] = 'product_id = %d';
			$params[]     = (int) $args['product_id'];
		}

		if ( array_key_exists( 'variation_id', $args ) && null !== $args['variation_id'] ) {
			if ( (int) $args['variation_id'] > 0 ) {
				$conditions[] = 'variation_id = %d';
				$params[]     = (int) $args['variation_id'];
			} else {
				$conditions[] = 'variation_id IS NULL';
			}
		}

		if ( ! empty( $args['price_type'] ) ) {
			$conditions[] = 'price_type = %s';
			$params[]     = (string) $args['price_type'];
		}

		if ( ! empty( $args['source'] ) ) {
			$conditions[] = 'source = %s';
			$params[]     = (string) $args['source'];
		}

		if ( ! empty( $args['item_type'] ) ) {
			$conditions[] = 'item_type = %s';
			$params[]     = (string) $args['item_type'];
		}

		if ( ! empty( $args['sku'] ) ) {
			$conditions[] = 'sku = %s';
			$params[]     = (string) $args['sku'];
		}

		if ( ! empty( $args['reference_date'] ) ) {
			$conditions[] = 'reference_date = %s';
			$params[]     = (string) $args['reference_date'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$conditions[] = 'created_at >= %s';
			$params[]     = (string) $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$conditions[] = 'created_at <= %s';
			$params[]     = (string) $args['date_to'];
		}

		if ( ! empty( $args['correction_of'] ) ) {
			$conditions[] = 'correction_of = %d';
			$params[]     = (int) $args['correction_of'];
		}

		$where = empty( $conditions ) ? '' : 'WHERE ' . implode( ' AND ', $conditions );

		unset( $wpdb );

		return array( $where, $params );
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
