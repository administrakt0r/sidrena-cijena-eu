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
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only log filters.
		$level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only log filters.
		$event = isset( $_GET['event'] ) ? sanitize_key( wp_unslash( $_GET['event'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		$args = array(
			'page'     => $page,
			'per_page' => 50,
		);

		if ( in_array( $level, LogRepository::LEVELS, true ) ) {
			$args['level'] = $level;
		}

		if ( in_array( $event, LogRepository::EVENTS, true ) ) {
			$args['event'] = $event;
		}

		$result = Plugin::instance()->logs()->query( $args );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Logs', 'sidrena-cijena' ) . '</h1>';
		echo '<p>' . esc_html__( 'Operational events only. Secrets and customer personal data are never written to these logs.', 'sidrena-cijena' ) . '</p>';

		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';

		echo '<label>' . esc_html__( 'Level', 'sidrena-cijena' ) . ' <select name="level">';
		echo '<option value="">' . esc_html__( 'All levels', 'sidrena-cijena' ) . '</option>';

		foreach ( LogRepository::LEVELS as $candidate ) {
			echo '<option value="' . esc_attr( $candidate ) . '"' . selected( $level, $candidate, false ) . '>' . esc_html( ucfirst( $candidate ) ) . '</option>';
		}

		echo '</select></label> ';

		echo '<label>' . esc_html__( 'Event', 'sidrena-cijena' ) . ' <select name="event">';
		echo '<option value="">' . esc_html__( 'All events', 'sidrena-cijena' ) . '</option>';

		foreach ( LogRepository::EVENTS as $candidate ) {
			echo '<option value="' . esc_attr( $candidate ) . '"' . selected( $event, $candidate, false ) . '>' . esc_html( $candidate ) . '</option>';
		}

		echo '</select></label> ';
		submit_button( __( 'Filter', 'sidrena-cijena' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		foreach ( array(
			__( 'Time', 'sidrena-cijena' ),
			__( 'Level', 'sidrena-cijena' ),
			__( 'Event', 'sidrena-cijena' ),
			__( 'Message', 'sidrena-cijena' ),
			__( 'User', 'sidrena-cijena' ),
		) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No log entries match these filters.', 'sidrena-cijena' ) . '</td></tr>';
		}

		foreach ( $result['items'] as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( self::site_time( $row['created_at'] ) ) . '</td>';
			echo '<td><span class="sidrena-cijena-status sidrena-cijena-status--' . esc_attr( (string) $row['level'] ) . '">' . esc_html( (string) $row['level'] ) . '</span></td>';
			echo '<td><code>' . esc_html( (string) $row['event'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $row['message'] ) . '</td>';
			echo '<td>' . esc_html( self::user_label( $row ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$total = (int) $result['total'];
		$pages = (int) ceil( $total / 50 );

		if ( $pages > 1 ) {
			echo '<p>';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $page,
						'total'   => $pages,
					)
				)
			);
			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Human-readable user label for a log row.
	 *
	 * @param array<mixed> $row Log row.
	 * @return string
	 */
	protected static function user_label( array $row ) {
		$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;

		if ( $user_id <= 0 ) {
			return __( 'system', 'sidrena-cijena' );
		}

		$user = get_userdata( $user_id );

		return $user ? $user->user_login : '#' . $user_id;
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

		return Dates::from_utc( (string) $datetime )->format( 'Y-m-d H:i' );
	}
}
