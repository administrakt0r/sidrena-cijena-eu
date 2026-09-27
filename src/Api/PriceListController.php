<?php
/**
 * Public price-list REST API.
 *
 * @package SidrenaCijena\Api
 */

namespace SidrenaCijena\Api;

use SidrenaCijena\Admin\Capabilities;
use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Cron\Scheduler;
use SidrenaCijena\Export\CsvWriter;
use SidrenaCijena\Export\FilenameGenerator;
use SidrenaCijena\Export\PriceListBuilder;
use SidrenaCijena\Export\XmlWriter;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the public read routes and the management status route.
 */
class PriceListController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'sidrena-cijena/v1';

	/**
	 * Public requests allowed per IP and minute.
	 *
	 * @var int
	 */
	const THROTTLE_LIMIT = 60;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/prices',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_prices' ),
				'permission_callback' => '__return_true',
				'args'                => self::prices_args(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/prices.csv',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'download_csv' ),
				'permission_callback' => '__return_true',
				'args'                => self::download_args(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/prices.xml',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'download_xml' ),
				'permission_callback' => '__return_true',
				'args'                => self::download_args(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/publications',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_publications' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'location' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'format'   => array(
						'type'              => 'string',
						'default'           => '',
						'enum'              => array( '', 'csv', 'xml' ),
						'sanitize_callback' => 'sanitize_key',
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_status' ),
				'permission_callback' => array( Capabilities::class, 'current_user_can' ),
			)
		);

		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_download' ), 10, 4 );
	}

	/**
	 * Paginated current price data.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_prices( $request ) {
		$throttled = self::throttle();

		if ( is_wp_error( $throttled ) ) {
			return $throttled;
		}

		$args = self::request_args( $request );

		$items = self::items( $args );

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$total    = count( $items );
		$per_page = (int) $args['per_page'];
		$page     = (int) $args['page'];
		$offset   = ( $page - 1 ) * $per_page;

		$page_items = array_slice( $items, $offset, $per_page );

		$payload = array(
			'data' => array_map( array( __CLASS__, 'present' ), $page_items ),
			'meta' => array(
				'page'         => $page,
				'per_page'     => $per_page,
				'total'        => $total,
				'total_pages'  => (int) ceil( $total / max( 1, $per_page ) ),
				'generated_at' => Dates::now_site()->format( 'c' ),
				'rule_version' => (string) RuleConfig::get( 'rule_version', '' ),
			),
		);

		return self::cache_headers( new \WP_REST_Response( $payload, 200 ), $payload, $request );
	}

	/**
	 * CSV download.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function download_csv( $request ) {
		return self::download( $request, 'csv' );
	}

	/**
	 * XML download.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function download_xml( $request ) {
		return self::download( $request, 'xml' );
	}

	/**
	 * Retained publication index.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_publications( $request ) {
		$throttled = self::throttle();

		if ( is_wp_error( $throttled ) ) {
			return $throttled;
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 200, (int) $request->get_param( 'per_page' ) ) );
		$location = (int) $request->get_param( 'location' );
		$format   = (string) $request->get_param( 'format' );

		$cache     = Plugin::instance()->rest_cache();
		$cache_key = $cache->key(
			array(
				'endpoint' => 'publications',
				'page'     => $page,
				'per_page' => $per_page,
				'location' => $location,
				'format'   => $format,
			)
		);

		$cached = $cache->get( $cache_key );
		if ( is_array( $cached ) ) {
			return new \WP_REST_Response( $cached, 200 );
		}

		$result = Plugin::instance()->publications()->index(
			array(
				'page'     => $page,
				'per_page' => $per_page,
				'location' => $location,
				'format'   => $format,
			)
		);

		$data = array();

		foreach ( $result['items'] as $row ) {
			$data[] = array(
				'id'           => (int) $row['id'],
				'location_id'  => (int) $row['location_id'],
				'format'       => (string) $row['format'],
				'filename'     => (string) $row['filename'],
				'url'          => (string) $row['url'],
				'checksum'     => (string) $row['checksum'],
				'rule_version' => (string) $row['rule_version'],
				'status'       => (string) $row['status'],
				'item_count'   => (int) $row['item_count'],
				'published_at' => self::site_time( $row['published_at'] ),
				'expires_at'   => self::site_time( $row['expires_at'] ),
			);
		}

		$payload = array(
			'data' => $data,
			'meta' => array(
				'page'     => $page,
				'per_page' => $per_page,
				'total'    => (int) $result['total'],
			),
		);

		$cache->set( $cache_key, $payload, 60 );

		return new \WP_REST_Response( $payload, 200 );
	}

	/**
	 * Management status summary.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_status( $request ) {
		unset( $request );

		$cache     = Plugin::instance()->rest_cache();
		$cache_key = $cache->key(
			array(
				'endpoint' => 'status',
			)
		);

		$cached = $cache->get( $cache_key );
		if ( is_array( $cached ) ) {
			return new \WP_REST_Response( $cached, 200 );
		}

		$locations = array();

		foreach ( Plugin::instance()->locations()->all( true ) as $location ) {
			$last = Plugin::instance()->publications()->last_successful( (int) $location['id'] );

			$locations[] = array(
				'id'             => (int) $location['id'],
				'name'           => (string) $location['name'],
				'identifier'     => (string) $location['identifier'],
				'active'         => (bool) $location['active'],
				'last_published' => $last && $last['published_at'] ? self::site_time( $last['published_at'] ) : null,
				'last_filename'  => $last ? (string) $last['filename'] : null,
			);
		}

		$payload = array(
			'schema_version' => \SidrenaCijena\Database\Schema::DB_VERSION,
			'rule'           => RuleConfig::all(),
			'locations'      => $locations,
			'cron'           => array(
				'healthy'  => Scheduler::is_healthy(),
				'next_run' => Scheduler::next_run() ? self::site_time( gmdate( 'Y-m-d H:i:s', (int) Scheduler::next_run() ) ) : null,
			),
		);

		$cache->set( $cache_key, $payload, 60 );

		return new \WP_REST_Response( $payload, 200 );
	}

	/**
	 * Serve CSV/XML responses raw instead of JSON.
	 *
	 * @param bool             $served Whether the request has been served.
	 * @param mixed            $result  Response.
	 * @param \WP_REST_Request $request Request.
	 * @param \WP_REST_Server  $server  Server.
	 * @return bool
	 */
	public static function serve_download( $served, $result, $request, $server ) {
		unset( $request, $server );

		if ( $served || ! $result instanceof \WP_REST_Response ) {
			return $served;
		}

		$data = $result->get_data();

		if ( ! is_array( $data ) || empty( $data['sidrena_download'] ) || ! is_array( $data['sidrena_download'] ) ) {
			return $served;
		}

		$download = $data['sidrena_download'];

		header( 'Content-Type: ' . (string) $download['content_type'] );
		header( 'Content-Disposition: attachment; filename="' . (string) $download['filename'] . '"' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Deterministic CSV/XML content escaped per the format contracts.
		echo (string) $download['content'];

		return true;
	}

	/**
	 * Build one download response.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $format  Format.
	 * @return \WP_REST_Response|\WP_Error
	 */
	protected static function download( $request, $format ) {
		$throttled = self::throttle();

		if ( is_wp_error( $throttled ) ) {
			return $throttled;
		}

		$location = self::resolve_location( (int) $request->get_param( 'location' ) );

		if ( is_wp_error( $location ) ) {
			return $location;
		}

		$args = array(
			'type' => (string) $request->get_param( 'type' ),
		);

		$build = self::cached_build( $location, $args );
		$items = $build['items'];
		$meta  = $build['meta'];

		if ( 'xml' === $format ) {
			$content      = ( new XmlWriter() )->to_string( $items, $location, $meta );
			$content_type = 'application/xml; charset=utf-8';
		} else {
			$content      = ( new CsvWriter() )->to_string( $items );
			$content_type = 'text/csv; charset=utf-8';
		}

		$filename = self::filename( $location, $format );

		return new \WP_REST_Response(
			array(
				'sidrena_download' => array(
					'content'      => $content,
					'content_type' => $content_type,
					'filename'     => $filename,
				),
			),
			200
		);
	}

	/**
	 * Canonical rows for a location, cached across requests.
	 *
	 * Builds walk the whole catalog, so the result is kept in the shared
	 * public-response cache instead of being rebuilt on every request. The
	 * cache is invalidated on price, anchor, sale, publication, location and
	 * settings changes; request filters (`sale_only`, `updated_since`) are
	 * applied afterwards and are not part of the key.
	 *
	 * @param array<mixed> $location Location row (empty for all locations).
	 * @param array<mixed> $args     Builder arguments (`type`).
	 * @return array{items:array<int,array<mixed>>,meta:array<mixed>}
	 */
	protected static function cached_build( array $location, array $args = array() ) {
		$type = isset( $args['type'] ) ? (string) $args['type'] : 'all';

		$cache = Plugin::instance()->rest_cache();
		$key   = $cache->key(
			array(
				'build'    => 'prices',
				'location' => isset( $location['id'] ) ? (int) $location['id'] : 0,
				'type'     => $type,
			)
		);

		$cached = $cache->get( $key );

		if ( is_array( $cached ) && isset( $cached['items'], $cached['meta'] ) ) {
			return $cached;
		}

		$build = self::builder()->build( $location, array( 'type' => $type ) );

		$cache->set( $key, $build );

		return $build;
	}

	/**
	 * Current price rows for the request arguments.
	 *
	 * @param array<mixed> $args Normalized arguments.
	 * @return array<mixed>[]|\WP_Error
	 */
	protected static function items( array $args ) {
		$location = null;

		if ( ! empty( $args['location'] ) ) {
			$location = Plugin::instance()->locations()->get( (int) $args['location'] );

			if ( ! $location ) {
				return new \WP_Error( 'sidrena_cijena_location_not_found', __( 'The sales location could not be found.', 'sidrena-cijena-eu' ), array( 'status' => 404 ) );
			}
		}

		$build = self::cached_build(
			$location ? $location : array(),
			array( 'type' => (string) $args['type'] )
		);

		$items = $build['items'];

		if ( ! empty( $args['sale_only'] ) ) {
			$items = array_values(
				array_filter(
					$items,
					static function ( $item ) {
						return ! empty( $item['poseban_oblik_prodaje'] );
					}
				)
			);
		}

		if ( ! empty( $args['updated_since'] ) ) {
			$since = strtotime( (string) $args['updated_since'] );

			if ( $since ) {
				$items = array_values(
					array_filter(
						$items,
						static function ( $item ) use ( $since ) {
							return strtotime( (string) $item['updated_at'] ) > $since;
						}
					)
				);
			}
		}

		return $items;
	}

	/**
	 * Present a canonical row as the documented REST object.
	 *
	 * @param array<mixed> $item Canonical row.
	 * @return array<mixed>
	 */
	public static function present( array $item ) {
		$sale = isset( $item['special_sale'] ) && is_array( $item['special_sale'] ) ? $item['special_sale'] : null;

		return array(
			'id'                    => (int) $item['product_id'],
			'variation_id'          => (int) $item['variation_id'],
			'type'                  => 'usluga' === $item['vrsta'] ? 'service' : 'product',
			'name'                  => (string) $item['naziv'],
			'sku'                   => (string) $item['sifra'],
			'brand'                 => (string) $item['marka'],
			'barcode'               => (string) $item['barkod'],
			'unit'                  => (string) $item['jedinica_mjere'],
			'unit_price'            => null === $item['cijena_za_jedinicu_mjere'] ? null : (float) $item['cijena_za_jedinicu_mjere'],
			'currency'              => isset( $item['currency'] ) && '' !== $item['currency'] ? (string) $item['currency'] : Currency::DEFAULT_CURRENCY,
			'current_price'         => (float) $item['maloprodajna_cijena'],
			'anchor_price'          => null === $item['sidrena_cijena'] ? null : (float) $item['sidrena_cijena'],
			'anchor_reference_date' => $item['anchor_reference_date'],
			'availability'          => 'dostupno' === $item['dostupnost'] ? 'available' : 'unavailable',
			'special_sale'          => null === $sale ? null : array(
				'active'              => true,
				'type'                => isset( $sale['sale_type'] ) ? (string) $sale['sale_type'] : null,
				'name'                => isset( $sale['sale_name'] ) && '' !== $sale['sale_name'] ? (string) $sale['sale_name'] : ( isset( $sale['sale_type'] ) ? (string) $sale['sale_type'] : null ),
				'start_date'          => self::site_time( isset( $sale['start_date'] ) ? $sale['start_date'] : null ),
				'end_date'            => self::site_time( isset( $sale['end_date'] ) ? $sale['end_date'] : null ),
				'lowest_30_day_price' => isset( $sale['lowest_30_day_price'] ) ? (float) $sale['lowest_30_day_price'] : null,
			),
			'updated_at'            => self::site_time( $item['updated_at'] ),
		);
	}

	/**
	 * Row builder.
	 *
	 * @return PriceListBuilder
	 */
	protected static function builder() {
		return new PriceListBuilder(
			Plugin::instance()->catalog(),
			Plugin::instance()->history(),
			Plugin::instance()->special_sales()
		);
	}

	/**
	 * Normalize request arguments.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<mixed>
	 */
	protected static function request_args( $request ) {
		$type = (string) $request->get_param( 'type' );

		if ( ! in_array( $type, array( 'product', 'service' ), true ) ) {
			$type = 'all';
		}

		return array(
			'page'          => max( 1, (int) $request->get_param( 'page' ) ),
			'per_page'      => max( 1, min( 500, (int) $request->get_param( 'per_page' ) ) ),
			'type'          => $type,
			'location'      => (int) $request->get_param( 'location' ),
			'updated_since' => sanitize_text_field( (string) $request->get_param( 'updated_since' ) ),
			'sale_only'     => (bool) $request->get_param( 'sale_only' ),
		);
	}

	/**
	 * Resolve a location for downloads.
	 *
	 * @param int $requested Requested location ID.
	 * @return array<mixed>|\WP_Error
	 */
	protected static function resolve_location( $requested ) {
		$locations = Plugin::instance()->locations();

		if ( $requested ) {
			$location = $locations->get( $requested );

			if ( ! $location ) {
				return new \WP_Error( 'sidrena_cijena_location_not_found', __( 'The sales location could not be found.', 'sidrena-cijena-eu' ), array( 'status' => 404 ) );
			}

			return $location;
		}

		$active = $locations->all( true );

		if ( 1 === count( $active ) ) {
			return $active[0];
		}

		if ( count( $active ) > 1 ) {
			return new \WP_Error( 'sidrena_cijena_location_required', __( 'A location parameter is required when several sales locations are active.', 'sidrena-cijena-eu' ), array( 'status' => 400 ) );
		}

		return new \WP_Error( 'sidrena_cijena_location_not_found', __( 'No active sales location exists.', 'sidrena-cijena-eu' ), array( 'status' => 404 ) );
	}

	/**
	 * Download filename: the latest published name, or a fresh name.
	 *
	 * @param array<mixed> $location Location row.
	 * @param string       $format   Format.
	 * @return string
	 */
	protected static function filename( array $location, $format ) {
		$latest = Plugin::instance()->publications()->latest( (int) $location['id'], (string) $format );

		if ( $latest && ! empty( $latest['filename'] ) ) {
			return (string) $latest['filename'];
		}

		$filename = ( new FilenameGenerator() )->generate( $location, $format, 1 );

		return is_wp_error( $filename ) ? 'cjenik.' . ( 'xml' === $format ? 'xml' : 'csv' ) : $filename;
	}

	/**
	 * Add caching headers and handle conditional requests.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @param array<mixed>      $payload  Response payload.
	 * @param \WP_REST_Request  $request  Request.
	 * @return \WP_REST_Response
	 */
	protected static function cache_headers( $response, array $payload, $request ) {
		$etag = '"' . md5( (string) wp_json_encode( $payload ) ) . '"';

		$response->header( 'ETag', $etag );
		$response->header( 'Cache-Control', 'public, max-age=' . RestCache::TTL );
		$response->header( 'X-Robots-Tag', 'noindex' );

		$generated = isset( $payload['meta']['generated_at'] ) ? (string) $payload['meta']['generated_at'] : '';
		$time      = $generated ? strtotime( $generated ) : false;
		$modified  = false !== $time ? $time : time();

		$response->header( 'Last-Modified', gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT' );

		$if_none_match = (string) $request->get_header( 'if_none_match' );

		if ( '' !== $if_none_match && $if_none_match === $etag ) {
			$response->set_status( 304 );
			$response->set_data( null );

			return $response;
		}

		$if_modified = (string) $request->get_header( 'if_modified_since' );

		if ( '' !== $if_modified && strtotime( $if_modified ) >= $modified ) {
			$response->set_status( 304 );
			$response->set_data( null );
		}

		return $response;
	}

	/**
	 * Basic per-IP throttling.
	 *
	 * Uses wp_cache on persistent object cache hosts to avoid options-table round
	 * trips, falling back to transients.
	 *
	 * @return true|\WP_Error
	 */
	protected static function throttle() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( '' === $ip ) {
			return true;
		}

		$key = 'sidrena_cijena_throttle_' . md5( $ip );

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			$cached = wp_cache_get( $key, 'sidrena_cijena_throttle' );
			$count  = false !== $cached ? (int) $cached : 0;

			if ( $count >= self::THROTTLE_LIMIT ) {
				return new \WP_Error(
					'sidrena_cijena_throttled',
					__( 'Too many requests. Please retry shortly.', 'sidrena-cijena-eu' ),
					array(
						'status'  => 429,
						'headers' => array( 'Retry-After' => 60 ),
					)
				);
			}

			wp_cache_set( $key, $count + 1, 'sidrena_cijena_throttle', MINUTE_IN_SECONDS );

			return true;
		}

		$count = (int) get_transient( $key );

		if ( $count >= self::THROTTLE_LIMIT ) {
			return new \WP_Error(
				'sidrena_cijena_throttled',
				__( 'Too many requests. Please retry shortly.', 'sidrena-cijena-eu' ),
				array(
					'status'  => 429,
					'headers' => array( 'Retry-After' => 60 ),
				)
			);
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Convert a stored UTC datetime to the site time zone (ISO 8601).
	 *
	 * @param mixed $datetime UTC datetime.
	 * @return string|null
	 */
	protected static function site_time( $datetime ) {
		if ( empty( $datetime ) ) {
			return null;
		}

		return Dates::from_utc( (string) $datetime )->format( 'c' );
	}

	/**
	 * `/prices` argument schema.
	 *
	 * @return array<mixed>
	 */
	protected static function prices_args() {
		return array(
			'page'          => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'      => array(
				'type'              => 'integer',
				'default'           => 100,
				'sanitize_callback' => 'absint',
			),
			'type'          => array(
				'type'              => 'string',
				'default'           => 'all',
				'enum'              => array( 'all', 'product', 'service' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'location'      => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'updated_since' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'sale_only'     => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Download argument schema.
	 *
	 * @return array<mixed>
	 */
	protected static function download_args() {
		return array(
			'location' => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'type'     => array(
				'type'              => 'string',
				'default'           => 'all',
				'enum'              => array( 'all', 'product', 'service' ),
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}
}
