<?php
/**
 * Log viewer screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Database\LogRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * Shows structured plugin logs for administrators.
 */
class LogsPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const SLUG = 'sidrena-cijena-logs';

	/**
	 * Rows per page.
	 *
	 * @var int
	 */
	const PER_PAGE = 50;

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'titanworks-bazna-cijena' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only log filters.
		$level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only log filters.
		$event = isset( $_GET['event'] ) ? sanitize_key( wp_unslash( $_GET['event'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		$args = array(
			'page'     => $page,
			'per_page' => self::PER_PAGE,
		);

		if ( in_array( $level, LogRepository::LEVELS, true ) ) {
			$args['level'] = $level;
		}

		if ( in_array( $event, LogRepository::EVENTS, true ) ) {
			$args['event'] = $event;
		}

		$result = Plugin::instance()->logs()->query( $args );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Logs', 'titanworks-bazna-cijena' ) . '</h1>';
		echo '<p>' . esc_html__( 'Operational events only. Secrets and customer personal data are never written to these logs.', 'titanworks-bazna-cijena' ) . '</p>';

		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';

		echo '<label>' . esc_html__( 'Level', 'titanworks-bazna-cijena' ) . ' <select name="level">';
		echo '<option value="">' . esc_html__( 'All levels', 'titanworks-bazna-cijena' ) . '</option>';

		foreach ( LogRepository::LEVELS as $candidate ) {
			echo '<option value="' . esc_attr( $candidate ) . '"' . selected( $level, $candidate, false ) . '>' . esc_html( Labels::log_level( $candidate ) ) . '</option>';
		}

		echo '</select></label> ';

		echo '<label>' . esc_html__( 'Event', 'titanworks-bazna-cijena' ) . ' <select name="event">';
		echo '<option value="">' . esc_html__( 'All events', 'titanworks-bazna-cijena' ) . '</option>';

		foreach ( LogRepository::EVENTS as $candidate ) {
			echo '<option value="' . esc_attr( $candidate ) . '"' . selected( $event, $candidate, false ) . '>' . esc_html( Labels::log_event( $candidate ) ) . '</option>';
		}

		echo '</select></label> ';
		submit_button( __( 'Filter', 'titanworks-bazna-cijena' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Log entries', 'titanworks-bazna-cijena' ) . '</caption>';
		echo '<thead><tr>';
		foreach ( array(
			__( 'Time', 'titanworks-bazna-cijena' ),
			__( 'Level', 'titanworks-bazna-cijena' ),
			__( 'Event', 'titanworks-bazna-cijena' ),
			__( 'Message', 'titanworks-bazna-cijena' ),
			__( 'User', 'titanworks-bazna-cijena' ),
		) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No log entries match these filters.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		}

		foreach ( $result['items'] as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( self::site_time( $row['created_at'] ) ) . '</td>';
			echo '<td><span class="sidrena-cijena-status sidrena-cijena-status--' . esc_attr( (string) $row['level'] ) . '">' . esc_html( Labels::log_level( (string) $row['level'] ) ) . '</span></td>';
			echo '<td>' . esc_html( Labels::log_event( (string) $row['event'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['message'] ) . '</td>';
			echo '<td>' . esc_html( self::user_label( $row ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		Pagination::render( $page, (int) $result['total'], self::PER_PAGE );

		echo '</div>';
	}

	/**
	 * Human-readable user label for a log row.
	 *
	 * @param array<mixed> $row Log row.
	 * @return string
	 */
	protected static function user_label( array $row ) {
		$context = isset( $row['context'] ) && is_array( $row['context'] ) ? $row['context'] : array();
		$user_id = isset( $row['user_id'] )
			? (int) $row['user_id']
			: ( isset( $context['user_id'] ) ? (int) $context['user_id'] : 0 );

		if ( $user_id <= 0 ) {
			return __( 'system', 'titanworks-bazna-cijena' );
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return '#' . $user_id;
		}

		return ! empty( $user->display_name ) ? $user->display_name : ( ! empty( $user->user_login ) ? $user->user_login : '#' . $user_id );
	}

	/**
	 * Convert a stored UTC datetime to the site time zone.
	 *
	 * @param mixed $datetime UTC datetime.
	 * @return string
	 */
	protected static function site_time( $datetime ) {
		if ( empty( $datetime ) ) {
			return '—';
		}

		$formatted = Dates::format_datetime_display( (string) $datetime );

		return '' !== $formatted ? $formatted : '—';
	}
}
