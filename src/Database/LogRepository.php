<?php
/**
 * Structured log data access.
 *
 * @package SidrenaCijena\Database
 */

namespace SidrenaCijena\Database;

use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for `{prefix}sidrena_logs`.
 */
class LogRepository {

	/**
	 * Allowed levels.
	 *
	 * @var string[]
	 */
	const LEVELS = array( 'info', 'warning', 'error' );

	/**
	 * Known event codes.
	 *
	 * @var string[]
	 */
	const EVENTS = array( 'export_scheduled', 'export_manual', 'export_failed', 'import', 'validation_failure', 'history_correction', 'migration', 'settings_change', 'cleanup' );

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

		$this->table = $wpdb->prefix . 'sidrena_logs';
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
	 * Insert a log entry.
	 *
	 * @param string   $level    Level.
	 * @param string   $event    Event code.
	 * @param string   $message  Message.
	 * @param array    $context  Structured context.
	 * @param int|null $user_id  User ID.
	 * @return int Inserted ID.
	 */
	public function insert( $level, $event, $message, array $context = array(), $user_id = null ) {
		global $wpdb;

		$level = in_array( $level, self::LEVELS, true ) ? $level : 'info';

		$wpdb->insert(
			$this->table,
			array(
				'level'      => $level,
				'event'      => substr( sanitize_key( $event ), 0, 50 ),
				'message'    => wp_strip_all_tags( (string) $message ),
				'context'    => empty( $context ) ? null : wp_json_encode( $context ),
				'user_id'    => null === $user_id ? null : (int) $user_id,
				'created_at' => Dates::now_utc(),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Query log entries.
	 *
	 * @param array $args `level`, `event`, `date_from`, `date_to`, `page`, `per_page`.
	 * @return array{items:array[],total:int}
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : 50;
		$offset   = ( $page - 1 ) * $per_page;

		$conditions = array();
		$params     = array();

		if ( ! empty( $args['level'] ) && in_array( $args['level'], self::LEVELS, true ) ) {
			$conditions[] = 'level = %s';
			$params[]     = $args['level'];
		}

		if ( ! empty( $args['event'] ) ) {
			$conditions[] = 'event = %s';
			$params[]     = (string) $args['event'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$conditions[] = 'created_at >= %s';
			$params[]     = (string) $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$conditions[] = 'created_at <= %s';
			$params[]     = (string) $args['date_to'];
		}

		$where = empty( $conditions ) ? '' : 'WHERE ' . implode( ' AND ', $conditions );

		$count_sql = "SELECT COUNT(*) FROM {$this->table} {$where}";
		$list_sql  = "SELECT * FROM {$this->table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d";

		if ( empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			$total = (int) $wpdb->get_var( $count_sql );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $per_page, $offset ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
			$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
		}

		$items = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$row['id']      = (int) $row['id'];
			$row['user_id'] = null === $row['user_id'] ? null : (int) $row['user_id'];
			$row['context'] = ! empty( $row['context'] ) ? json_decode( (string) $row['context'], true ) : array();
			$items[]        = $row;
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Delete log entries older than the retention period.
	 *
	 * @param int $days Retention in days.
	 * @return int Number of rows deleted.
	 */
	public function prune( $days ) {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, (int) $days ) * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$this->table} WHERE created_at < %s", $cutoff )
		);
	}

	/**
	 * Number of entries.
	 *
	 * @return int
	 */
	public function count_all() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}
}
