<?php
/**
 * Published price-list archive screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Cron\Scheduler;
use SidrenaCijena\Export\FilenameGenerator;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Lists generated price-list publications per location and format.
 */
class PublicationsPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const SLUG = 'sidrena-cijena-publications';

	/**
	 * Generate-now action.
	 *
	 * @var string
	 */
	const ACTION = 'sidrena_cijena_generate_now';

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena' ) );
		}

		$page     = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$per_page = 20;

		$publications = Plugin::instance()->publications();
		$result       = $publications->index(
			array(
				'page'     => $page,
				'per_page' => $per_page,
			)
		);

		$locations = array();

		foreach ( Plugin::instance()->locations()->all() as $location ) {
			$locations[ (int) $location['id'] ] = $location;
		}

		$last = $publications->last_successful();
		$next = Scheduler::next_run();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Price lists', 'sidrena-cijena' ) . '</h1>';

		self::notice();

		echo '<p>';
		echo esc_html__( 'Filenames follow the legally structured pattern:', 'sidrena-cijena' ) . ' ';
		echo '<code>' . esc_html( FilenameGenerator::pattern() ) . '</code>';
		echo '</p>';

		echo '<p>';
		printf(
			/* translators: 1: last publication time, 2: next scheduled run. */
			esc_html__( 'Last published: %1$s. Next scheduled generation: %2$s.', 'sidrena-cijena' ),
			esc_html( $last && $last['published_at'] ? self::site_time( $last['published_at'] ) : __( 'never', 'sidrena-cijena' ) ),
			esc_html( $next ? self::site_time( gmdate( 'Y-m-d H:i:s', (int) $next ) ) : __( 'not scheduled', 'sidrena-cijena' ) )
		);
		echo '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		submit_button( __( 'Generate now', 'sidrena-cijena' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		foreach ( array(
			__( 'Filename', 'sidrena-cijena' ),
			__( 'Format', 'sidrena-cijena' ),
			__( 'Location', 'sidrena-cijena' ),
			__( 'Published', 'sidrena-cijena' ),
			__( 'Expires', 'sidrena-cijena' ),
			__( 'Items', 'sidrena-cijena' ),
			__( 'Checksum', 'sidrena-cijena' ),
			__( 'Status', 'sidrena-cijena' ),
		) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No price lists have been published yet.', 'sidrena-cijena' ) . '</td></tr>';
		}

		foreach ( $result['items'] as $row ) {
			$location_name = isset( $locations[ (int) $row['location_id'] ] ) ? (string) $locations[ (int) $row['location_id'] ]['name'] : '#' . (int) $row['location_id'];

			echo '<tr>';
			echo '<td>';
			if ( ! empty( $row['url'] ) ) {
				echo '<a href="' . esc_url( (string) $row['url'] ) . '">' . esc_html( (string) $row['filename'] ) . '</a>';
			} else {
				echo esc_html( (string) $row['filename'] );
			}
			echo '</td>';
			echo '<td>' . esc_html( strtoupper( (string) $row['format'] ) ) . '</td>';
			echo '<td>' . esc_html( $location_name ) . '</td>';
			echo '<td>' . esc_html( self::site_time( $row['published_at'] ) ) . '</td>';
			echo '<td>' . esc_html( self::site_time( $row['expires_at'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) (int) $row['item_count'] ) . '</td>';
			echo '<td><code>' . esc_html( substr( (string) $row['checksum'], 0, 12 ) ) . '</code></td>';
			echo '<td><span class="sidrena-cijena-status sidrena-cijena-status--' . esc_attr( (string) $row['status'] ) . '">' . esc_html( (string) $row['status'] ) . '</span></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$total = (int) $result['total'];
		$pages = (int) ceil( $total / $per_page );

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
	 * Handle the "Generate now" action.
	 *
	 * @return void
	 */
	public static function handle_generate() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sidrena-cijena' ) );
		}

		check_admin_referer( self::ACTION );

		$results = Plugin::instance()->generation()->generate_all(
			array(
				'source' => 'manual',
				'force'  => true,
			)
		);

		$published = 0;
		$failed    = 0;

		foreach ( $results as $location ) {
			if ( is_wp_error( $location ) ) {
				++$failed;
				continue;
			}

			foreach ( (array) $location as $result ) {
				if ( is_wp_error( $result ) ) {
					++$failed;
				} else {
					++$published;
				}
			}
		}

		$url = add_query_arg(
			array(
				'page'                  => self::SLUG,
				'sidrena_cijena_notice' => $failed ? 'error' : 'success',
				'generated'             => $published,
				'failed'                => $failed,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render the generation notice.
	 *
	 * @return void
	 */
	protected static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice flags.
		$notice = isset( $_GET['sidrena_cijena_notice'] ) ? sanitize_key( wp_unslash( $_GET['sidrena_cijena_notice'] ) ) : '';

		if ( '' === $notice ) {
			return;
		}

		$generated = isset( $_GET['generated'] ) ? absint( wp_unslash( $_GET['generated'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$failed    = isset( $_GET['failed'] ) ? absint( wp_unslash( $_GET['failed'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$class = 'error' === $notice ? 'notice notice-error' : 'notice notice-success';

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html(
				sprintf(
					/* translators: 1: number of generated price lists, 2: number of failures. */
					__( 'Generation finished: %1$d price lists generated, %2$d failed.', 'sidrena-cijena' ),
					(int) $generated,
					(int) $failed
				)
			)
		);
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
