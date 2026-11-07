<?php
/**
 * Structured log data access.
 *
 * @package Titanworks\SidrenaCijena\Database
 */

namespace Titanworks\SidrenaCijena\Database;

use Titanworks\SidrenaCijena\Support\Dates;

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
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

		delete_transient( 'twbce_log_cnt_all' );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Query log entries.
	 *
	 * @param array<mixed> $args `level`, `event`, `date_from`, `date_to`, `page`, `per_page`.
	 * @return array{items:array[],total:int}
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : 50;
		$offset   = ( $page - 1 ) * $per_page;

		$level  = ! empty( $args['level'] ) && in_array( $args['level'], self::LEVELS, true ) ? $args['level'] : '';
		$params = array( $this->table, $level, $level );
		foreach ( array( 'event', 'date_from', 'date_to' ) as $key ) {
			$value    = ! empty( $args[ $key ] ) ? (string) $args[ $key ] : '';
			$params[] = $value;
			$params[] = $value;
		}
		$list_params = array_merge( $params, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Repository needs current records after writes in the same request.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- wpdb accepts the complete, ordered argument array.
			$wpdb->prepare(
				"SELECT * FROM %i WHERE (%s = '' OR level = %s) AND (%s = '' OR event = %s)
				AND (%s = '' OR created_at >= NULLIF(%s, '')) AND (%s = '' OR created_at <= NULLIF(%s, ''))
				ORDER BY id DESC LIMIT %d OFFSET %d",
				$list_params
			),
			ARRAY_A
		);

		$rows_array = is_array( $rows ) ? $rows : array();

		if ( 1 === $page && count( $rows_array ) < $per_page ) {
			$total = count( $rows_array );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Repository needs current records after writes in the same request.
			$total = (int) $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- wpdb accepts the complete, ordered argument array.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE (%s = '' OR level = %s) AND (%s = '' OR event = %s)
					AND (%s = '' OR created_at >= NULLIF(%s, '')) AND (%s = '' OR created_at <= NULLIF(%s, ''))",
					$params
				)
			);
		}

		$items = array();

		foreach ( $rows_array as $row ) {
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

		delete_transient( 'twbce_log_cnt_all' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $this->table, $cutoff )
		);
	}

	/**
	 * Number of entries.
	 *
	 * @return int
	 */
	public function count_all() {
		$cached = get_transient( 'twbce_log_cnt_all' );
		if ( false !== $cached && is_numeric( $cached ) ) {
			return (int) $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table ) );
		set_transient( 'twbce_log_cnt_all', $total, 30 );

		return $total;
	}
}
