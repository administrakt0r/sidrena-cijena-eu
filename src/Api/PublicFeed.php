<?php
/**
 * Public price-list feed on a stable, human-readable URL.
 *
 * @package SidrenaCijena\Api
 */

namespace SidrenaCijena\Api;

use SidrenaCijena\Cron\Scheduler;
use SidrenaCijena\Plugin;
use SidrenaCijena\Logging\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Serves the current price list at `/sidrena-cijena.xml` and `/sidrena-cijena.csv`.
 *
 * The decree expects a price list that can be retrieved at a known address, not
 * only through a JSON endpoint. When the file is missing the response is a 503
 * and exactly one background rebuild is queued, so the visitor is told the list
 * is unavailable instead of receiving a stale or empty document.
 */
class PublicFeed {

	/**
	 * URL stem of the public feed.
	 *
	 * @var string
	 */
	const STEM = 'sidrena-cijena';

	/**
	 * Query var that carries the requested format.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'sidrena_cijena_feed';

	/**
	 * Option storing the last queued rebuild timestamp.
	 *
	 * @var string
	 */
	const OPTION_LAST_QUEUE = 'sidrena_cijena_public_feed_last_queue';

	/**
	 * Minimum seconds between two queued rebuilds.
	 *
	 * @var int
	 */
	const REBUILD_COOLDOWN = 900;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		// Called from `init` itself: a hook added to the running `init` action would never fire.
		$this->add_rewrites();

		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve' ), 0 );
	}

	/**
	 * Add the public feed rules.
	 *
	 * @return void
	 */
	public function add_rewrites() {
		add_rewrite_rule(
			'^' . self::STEM . '\.(xml|csv)$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Allow the feed query var through WordPress.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Serve the feed for a public feed request.
	 *
	 * @return void
	 */
	public function serve() {
		$format = (string) get_query_var( self::QUERY_VAR );

		if ( ! in_array( $format, array( 'xml', 'csv' ), true ) ) {
			return;
		}

		$location = self::public_location();
		$row      = $location ? $this->current_file( (int) $location['id'], $format ) : null;

		if ( $row && is_readable( $row['path'] ) ) {
			$this->send_file( $row, $format );

			return;
		}

		$this->serve_missing();
	}

	/**
	 * The location whose price list is published at the public URLs.
	 *
	 * The webshop location represents the online store; a shop without one
	 * falls back to its first active location. Shared by the public feed and
	 * the public HTML pages so they can never point at different shops.
	 *
	 * @return array<mixed>|null
	 */
	public static function public_location() {
		$locations = Plugin::instance()->locations()->all( true );

		foreach ( $locations as $location ) {
			if ( 'webshop' === $location['type'] ) {
				return $location;
			}
		}

		return $locations ? $locations[0] : null;
	}

	/**
	 * The currently published file for a location and format.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $format      `xml` or `csv`.
	 * @return array<mixed>|null
	 */
	protected function current_file( $location_id, $format ) {
		$row = Plugin::instance()->publications()->latest( $location_id, $format, array( 'published' ) );

		if ( ! $row || empty( $row['path'] ) ) {
			return null;
		}

		return $row;
	}

	/**
	 * Stream the published file.
	 *
	 * @param array<mixed> $row    Publication row.
	 * @param string       $format `xml` or `csv`.
	 * @return void
	 */
	protected function send_file( array $row, $format ) {
		nocache_headers();
		header( 'Content-Type: ' . ( 'xml' === $format ? 'application/xml' : 'text/csv' ) . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $row['filename'] . '"' );
		header( 'Content-Length: ' . (int) filesize( $row['path'] ) );
		header( 'X-Sidrena-Cijena-Checksum: ' . $row['checksum'] );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming the generated price list.
		readfile( $row['path'] );
		exit;
	}

	/**
	 * Report a missing price list and queue one rebuild.
	 *
	 * @return void
	 */
	protected function serve_missing() {
		$this->queue_rebuild();

		status_header( 503 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Retry-After: ' . self::REBUILD_COOLDOWN );

		echo esc_html__( 'The price list is being regenerated. Please try again shortly.', 'sidrena-cijena' ) . "\n";
		exit;
	}

	/**
	 * Queue a rebuild, at most once per cooldown window.
	 *
	 * @return void
	 */
	protected function queue_rebuild() {
		$last = (int) get_option( self::OPTION_LAST_QUEUE, 0 );

		if ( $last && ( time() - $last ) < self::REBUILD_COOLDOWN ) {
			return;
		}

		update_option( self::OPTION_LAST_QUEUE, time(), false );
		Scheduler::queue_service_regeneration( array(), 60 );

		Logger::info(
			'public_feed_rebuild_queued',
			'A public price-list request found no published file; a rebuild was queued.',
			array( 'cooldown' => self::REBUILD_COOLDOWN )
		);
	}
}
