<?php
/**
 * Audit trail screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Database\LogRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * Shows rule-configuration changes and operational log entries.
 */
class AuditPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const SLUG = 'sidrena-cijena-audit';

	/**
	 * Rows per page.
	 *
	 * @var int
	 */
	const PER_PAGE = 50;

	/**
	 * Rule-change rows per page.
	 *
	 * @var int
	 */
	const HISTORY_PER_PAGE = 20;

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'titanworks-bazna-cijena' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$event = isset( $_GET['event'] ) ? sanitize_key( wp_unslash( $_GET['event'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$history_page = isset( $_GET['history_paged'] ) ? max( 1, absint( wp_unslash( $_GET['history_paged'] ) ) ) : 1;

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Audit trail', 'titanworks-bazna-cijena' ) . '</h1>';

		echo '<p>' . esc_html__( 'Every legal-rule change and every recorded correction is kept here with the user and the timestamp.', 'titanworks-bazna-cijena' ) . '</p>';

		self::render_rule_changes( $history_page );
		self::render_logs( $event, $page );

		echo '</div>';
	}

	/**
	 * Render the legal-rule change history.
	 *
	 * @param int $page Current history page.
	 * @return void
	 */
	protected static function render_rule_changes( $page ) {
		$history = array_reverse( RuleConfig::history() );
		$total   = count( $history );
		$offset  = ( max( 1, (int) $page ) - 1 ) * self::HISTORY_PER_PAGE;

		echo '<h2>' . esc_html__( 'Legal rule changes', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Legal rule change history', 'titanworks-bazna-cijena' ) . '</caption>';
		echo '<thead><tr>';
		foreach ( array(
			__( 'When', 'titanworks-bazna-cijena' ),
			__( 'Setting', 'titanworks-bazna-cijena' ),
			__( 'Old value', 'titanworks-bazna-cijena' ),
			__( 'New value', 'titanworks-bazna-cijena' ),
			__( 'User', 'titanworks-bazna-cijena' ),
			__( 'Reason', 'titanworks-bazna-cijena' ),
		) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $history ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No legal-rule changes have been recorded.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		}

		foreach ( array_slice( $history, $offset, self::HISTORY_PER_PAGE ) as $entry ) {
			$timestamp = isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '';

			echo '<tr>';
			echo '<td>' . esc_html( '' !== $timestamp ? self::site_time( $timestamp ) : '—' ) . '</td>';
			echo '<td>' . esc_html( Labels::rule_setting( isset( $entry['key'] ) ? (string) $entry['key'] : '' ) ) . '</td>';
			echo '<td>' . esc_html( self::format_value( isset( $entry['old'] ) ? $entry['old'] : '' ) ) . '</td>';
			echo '<td>' . esc_html( self::format_value( isset( $entry['new'] ) ? $entry['new'] : '' ) ) . '</td>';
			echo '<td>' . esc_html( self::user_label( isset( $entry['user_id'] ) ? (int) $entry['user_id'] : 0 ) ) . '</td>';
			echo '<td>' . esc_html( isset( $entry['reason'] ) ? (string) $entry['reason'] : '' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		Pagination::render( $page, $total, self::HISTORY_PER_PAGE, array( 'query_arg' => 'history_paged' ) );
	}

	/**
	 * Render the operational log.
	 *
	 * @param string $event Event filter.
	 * @param int    $page  Current page.
	 * @return void
	 */
	protected static function render_logs( $event, $page ) {
		$logs = new LogRepository();
		$args = array(
			'page'     => $page,
			'per_page' => self::PER_PAGE,
		);

		if ( '' !== $event ) {
			$args['event'] = $event;
		}

		$result = $logs->query( $args );
		$rows   = $result['items'];

		echo '<h2>' . esc_html__( 'Operational log', 'titanworks-bazna-cijena' ) . '</h2>';

		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		echo '<p><label>' . esc_html__( 'Event', 'titanworks-bazna-cijena' ) . ' <select name="event">';
		echo '<option value="">' . esc_html__( 'All events', 'titanworks-bazna-cijena' ) . '</option>';

		foreach ( LogRepository::EVENTS as $option ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( $event, $option, false ) . '>' . esc_html( Labels::log_event( $option ) ) . '</option>';
		}

		echo '</select></label> ';
		submit_button( __( 'Filter', 'titanworks-bazna-cijena' ), 'secondary', 'submit', false );
		echo '</p></form>';

		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Log entries', 'titanworks-bazna-cijena' ) . '</caption>';
		echo '<thead><tr>';
		foreach ( array(
			__( 'When', 'titanworks-bazna-cijena' ),
			__( 'Level', 'titanworks-bazna-cijena' ),
			__( 'Event', 'titanworks-bazna-cijena' ),
			__( 'Message', 'titanworks-bazna-cijena' ),
			__( 'User', 'titanworks-bazna-cijena' ),
		) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No log entries match the filter.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			$context = isset( $row['context'] ) && is_array( $row['context'] ) ? $row['context'] : array();
			$user_id = isset( $row['user_id'] )
				? (int) $row['user_id']
				: ( isset( $context['user_id'] ) ? (int) $context['user_id'] : 0 );

			echo '<tr>';
			echo '<td>' . esc_html( Dates::format_datetime_display( (string) $row['created_at'] ) ) . '</td>';
			echo '<td><span class="sidrena-cijena-status sidrena-cijena-status--' . esc_attr( (string) $row['level'] ) . '">' . esc_html( Labels::log_level( (string) $row['level'] ) ) . '</span></td>';
			echo '<td>' . esc_html( Labels::log_event( (string) $row['event'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['message'] ) . '</td>';
			echo '<td>' . esc_html( self::user_label( $user_id ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		Pagination::render( $page, (int) $result['total'], self::PER_PAGE );
	}

	/**
	 * Convert an ISO timestamp to the site time zone.
	 *
	 * @param string $timestamp Timestamp.
	 * @return string
	 */
	protected static function site_time( $timestamp ) {
		$formatted = Dates::format_datetime_display( $timestamp );

		return '' !== $formatted ? $formatted : (string) $timestamp;
	}

	/**
	 * Format a stored value for display.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	protected static function format_value( $value ) {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}

		if ( is_bool( $value ) || 'true' === $value || 'false' === $value ) {
			$bool = is_bool( $value ) ? $value : ( 'true' === $value );
			return $bool ? __( 'Yes', 'titanworks-bazna-cijena' ) : __( 'No', 'titanworks-bazna-cijena' );
		}

		return (string) $value;
	}

	/**
	 * Display name for a user ID.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	protected static function user_label( $user_id ) {
		if ( $user_id <= 0 ) {
			return __( 'system', 'titanworks-bazna-cijena' );
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return '#' . $user_id;
		}

		return ! empty( $user->display_name ) ? $user->display_name : ( ! empty( $user->user_login ) ? $user->user_login : '#' . $user_id );
	}
}
