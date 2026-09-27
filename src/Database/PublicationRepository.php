<?php
/**
 * Price-list publication archive.
 *
 * Archive rows are never deleted by cleanup; only files are removed after the
 * retention period (FR-046 to FR-049).
 *
 * @package SidrenaCijena\Database
 */

namespace SidrenaCijena\Database;

use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for `{prefix}sidrena_publications`.
 */
class PublicationRepository {

	/**
	 * Allowed statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'pending', 'generated', 'validated', 'published', 'failed', 'superseded', 'expired' );

	/**
	 * Statuses considered publicly retained.
	 *
	 * @var string[]
	 */
	const RETAINED_STATUSES = array( 'published', 'superseded' );

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

		$this->table = $wpdb->prefix . 'sidrena_publications';
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
	 * Insert a publication row.
	 *
	 * @param array<mixed> $data Fields.
	 * @return int Inserted ID.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$row = $this->normalize( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $this->table, $row, $this->formats() );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a publication row.
	 *
	 * @param int          $id   Row ID.
	 * @param array<mixed> $data Fields.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$existing = $this->get( $id );
		$row      = $this->normalize( array_merge( $existing ? $existing : array(), $data ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( $this->table, $row, array( 'id' => (int) $id ), $this->formats(), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Set the status of a publication.
	 *
	 * @param int    $id     Row ID.
	 * @param string $status New status.
	 * @param array  $extra  Additional fields (for example `published_at`, `validation_summary`).
	 * @return bool
	 */
	public function set_status( $id, $status, array $extra = array() ) {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}

		return $this->update( $id, array_merge( array( 'status' => $status ), $extra ) );
	}

	/**
	 * Fetch a row.
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

		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Latest publication for a location and format.
	 *
	 * @param int      $location_id Location ID.
	 * @param string   $format      Format (`csv` or `xml`).
	 * @param string[] $statuses    Accepted statuses.
	 * @return array|null
	 */
	public function latest( $location_id, $format, array $statuses = self::RETAINED_STATUSES ) {
		global $wpdb;

		$statuses = array_values( array_intersect( $statuses, self::STATUSES ) );

		if ( empty( $statuses ) ) {
			return null;
		}

		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$params = array_merge( array( $this->table, (int) $location_id, (string) $format ), $statuses );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Status placeholders are generated safely and bound dynamically.
				"SELECT * FROM %i WHERE location_id = %d AND format = %s AND status IN ({$placeholders}) ORDER BY id DESC LIMIT 1",
				$params
			),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Latest publication for a location/format regardless of status.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $format      Format.
	 * @return array|null
	 */
	public function latest_any( $location_id, $format ) {
		return $this->latest( $location_id, $format, self::STATUSES );
	}

	/**
	 * Mark all other retained publications for a location/format as superseded.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $format   Format.
	 * @param int    $except_id  Row to keep.
	 * @return void
	 */
	public function supersede( $location_id, $format, $except_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'superseded' WHERE location_id = %d AND format = %s AND id != %d AND status = 'published'",
				$this->table,
				(int) $location_id,
				(string) $format,
				(int) $except_id
			)
		);
	}

	/**
	 * Paginated index of retained publications.
	 *
	 * @param array<mixed> $args Query arguments: `location`, `format`, `page`, `per_page`, `include_expired`.
	 * @return array{items:array[],total:int}
	 */
	public function index( array $args = array() ) {
		global $wpdb;

		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 500, (int) $args['per_page'] ) ) : 50;
		$offset   = ( $page - 1 ) * $per_page;

		$conditions = array();
		$params     = array();

		if ( empty( $args['include_expired'] ) ) {
			$conditions[] = 'status IN (%s, %s)';
			$params[]     = 'published';
			$params[]     = 'superseded';
			$conditions[] = 'expired_at IS NULL';
		}

		if ( ! empty( $args['location'] ) ) {
			$conditions[] = 'location_id = %d';
			$params[]     = (int) $args['location'];
		}

		if ( ! empty( $args['format'] ) ) {
			$conditions[] = 'format = %s';
			$params[]     = (string) $args['format'];
		}

		$where = empty( $conditions ) ? '' : 'WHERE ' . implode( ' AND ', $conditions );

		$query_params = array_merge( array( $this->table ), $params );
		$list_params  = array_merge( $query_params, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where only contains hardcoded condition templates and values are bound via $wpdb->prepare().
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i {$where}", $query_params ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where only contains hardcoded condition templates and values are bound via $wpdb->prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i {$where} ORDER BY published_at DESC, id DESC LIMIT %d OFFSET %d", $list_params ), ARRAY_A );

		$items = array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Publications whose retention has elapsed and whose file is still present.
	 *
	 * @param string $now_utc Current UTC datetime.
	 * @return array<mixed>[]
	 */
	public function expired_candidates( $now_utc ) {
		global $wpdb;

		$prepared = $wpdb->prepare(
			"SELECT * FROM %i WHERE expires_at IS NOT NULL AND expires_at <= %s AND expired_at IS NULL AND status IN ('published','superseded') ORDER BY expires_at ASC LIMIT 500",
			$this->table,
			(string) $now_utc
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

		return array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Mark a publication as expired (archive row retained).
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public function mark_expired( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array(
				'status'     => 'expired',
				'expired_at' => Dates::now_utc(),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Last successful publication.
	 *
	 * @param int $location_id Optional location filter.
	 * @return array|null
	 */
	public function last_successful( $location_id = 0 ) {
		global $wpdb;

		if ( $location_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE published_at IS NOT NULL AND location_id = %d ORDER BY published_at DESC LIMIT 1', $this->table, (int) $location_id ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE published_at IS NOT NULL ORDER BY published_at DESC LIMIT 1', $this->table ), ARRAY_A );
		}

		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Check if a location has a published price list on a specific site date.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $date        Site-local date (Y-m-d). Defaults to today.
	 * @return bool
	 */
	public function has_published_on_date( $location_id, $date = '' ) {
		if ( empty( $date ) ) {
			$date = Dates::today();
		}

		$last = $this->last_successful( (int) $location_id );

		if ( ! $last || empty( $last['published_at'] ) ) {
			return false;
		}

		$site_time = Dates::from_utc( (string) $last['published_at'] );

		return (bool) ( $site_time && $site_time->format( 'Y-m-d' ) === $date );
	}

	/**
	 * Count publications by status.
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
	 * Recent publications for a location.
	 *
	 * @param int $location_id Location ID.
	 * @param int $limit       Maximum rows.
	 * @return array<mixed>[]
	 */
	public function for_location( $location_id, $limit = 20 ) {
		global $wpdb;

		$prepared = $wpdb->prepare(
			'SELECT * FROM %i WHERE location_id = %d ORDER BY id DESC LIMIT %d',
			$this->table,
			(int) $location_id,
			max( 1, (int) $limit )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

		return array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Normalize a row for persistence.
	 *
	 * @param array<mixed> $data Input.
	 * @return array<mixed>
	 */
	protected function normalize( array $data ) {
		return array(
			'location_id'         => isset( $data['location_id'] ) ? (int) $data['location_id'] : 0,
			'format'              => isset( $data['format'] ) && 'xml' === $data['format'] ? 'xml' : 'csv',
			'filename'            => isset( $data['filename'] ) ? (string) $data['filename'] : '',
			'path'                => isset( $data['path'] ) ? (string) $data['path'] : '',
			'url'                 => isset( $data['url'] ) ? (string) $data['url'] : '',
			'checksum'            => isset( $data['checksum'] ) ? (string) $data['checksum'] : '',
			'content_fingerprint' => isset( $data['content_fingerprint'] ) ? (string) $data['content_fingerprint'] : '',
			'file_size'           => isset( $data['file_size'] ) ? (int) $data['file_size'] : 0,
			'rule_version'        => isset( $data['rule_version'] ) ? (string) $data['rule_version'] : '',
			'status'              => isset( $data['status'] ) && in_array( $data['status'], self::STATUSES, true ) ? $data['status'] : 'pending',
			'sequence_no'         => isset( $data['sequence_no'] ) ? (int) $data['sequence_no'] : 0,
			'item_count'          => isset( $data['item_count'] ) ? (int) $data['item_count'] : 0,
			'validation_summary'  => isset( $data['validation_summary'] ) ? ( is_array( $data['validation_summary'] ) ? wp_json_encode( $data['validation_summary'] ) : (string) $data['validation_summary'] ) : null,
			'created_at'          => isset( $data['created_at'] ) ? (string) $data['created_at'] : Dates::now_utc(),
			'published_at'        => ! empty( $data['published_at'] ) ? (string) $data['published_at'] : null,
			'expires_at'          => ! empty( $data['expires_at'] ) ? (string) $data['expires_at'] : null,
			'expired_at'          => ! empty( $data['expired_at'] ) ? (string) $data['expired_at'] : null,
			'created_by'          => ! empty( $data['created_by'] ) ? (int) $data['created_by'] : null,
		);
	}

	/**
	 * Formats for $wpdb.
	 *
	 * @return string[]
	 */
	protected function formats() {
		return array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' );
	}

	/**
	 * Cast types and decode JSON.
	 *
	 * @param array<mixed> $row Row.
	 * @return array<mixed>
	 */
	protected function hydrate( array $row ) {
		$row['id']          = (int) $row['id'];
		$row['location_id'] = (int) $row['location_id'];
		$row['file_size']   = (int) $row['file_size'];
		$row['sequence_no'] = (int) $row['sequence_no'];
		$row['item_count']  = (int) $row['item_count'];

		if ( ! empty( $row['validation_summary'] ) ) {
			$decoded                   = json_decode( (string) $row['validation_summary'], true );
			$row['validation_summary'] = is_array( $decoded ) ? $decoded : array();
		} else {
			$row['validation_summary'] = array();
		}

		return $row;
	}
}
