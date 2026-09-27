<?php
/**
 * Public HTML price list and archive pages.
 *
 * @package SidrenaCijena\Api
 */

namespace SidrenaCijena\Api;

use SidrenaCijena\Export\PriceListBuilder;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Serves a human-readable price list at `/sidrena-cjenik` and the publication
 * archive at `/arhiva-sidrene-cijene`, and provides the same tables through the
 * `[sidrena-cjenik]` and `[sidrena-arhiva]` shortcodes.
 *
 * The decree expects a price list a person can open, not only a machine
 * endpoint; the rows are the same canonical rows the CSV/XML exports use, so a
 * printed page cannot disagree with the published file.
 */
class PublicPage {

	/**
	 * Query var carrying the requested page.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'sidrena_cijena_page';

	/**
	 * Value of the price-list page.
	 *
	 * @var string
	 */
	const PAGE_LIST = 'cjenik';

	/**
	 * Value of the archive page.
	 *
	 * @var string
	 */
	const PAGE_ARCHIVE = 'arhiva';

	/**
	 * URL stem of the price-list page.
	 *
	 * @var string
	 */
	const STEM_LIST = 'sidrena-cjenik';

	/**
	 * URL stem of the archive page.
	 *
	 * @var string
	 */
	const STEM_ARCHIVE = 'arhiva-sidrene-cijene';

	/**
	 * Maximum publications shown in the archive.
	 *
	 * @var int
	 */
	const ARCHIVE_LIMIT = 50;

	/**
	 * Maximum items per page on the public price list.
	 *
	 * @var int
	 */
	const LIST_PER_PAGE = 50;

	/**
	 * Whether the table styles were already printed on this request.
	 *
	 * @var bool
	 */
	protected static $styled = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		// Called from `init` itself: a hook added to the running `init` action would never fire.
		$this->add_rewrites();

		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'parse_request', array( $this, 'parse_request_fallback' ) );
		add_action( 'template_redirect', array( $this, 'serve' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'footer_link' ) );
		add_action( 'wp_sitemaps_init', array( $this, 'register_sitemap_provider' ) );

		add_shortcode( 'sidrena-cjenik', array( $this, 'shortcode_list' ) );
		add_shortcode( 'sidrena_cjenik', array( $this, 'shortcode_list' ) );
		add_shortcode( 'sidrena-arhiva', array( $this, 'shortcode_archive' ) );
		add_shortcode( 'sidrena_arhiva', array( $this, 'shortcode_archive' ) );
	}

	/**
	 * Add the public page rules.
	 *
	 * @return void
	 */
	public function add_rewrites() {
		add_rewrite_rule(
			'^' . self::STEM_LIST . '/?$',
			'index.php?' . self::QUERY_VAR . '=' . self::PAGE_LIST,
			'top'
		);

		add_rewrite_rule(
			'^' . self::STEM_ARCHIVE . '/?$',
			'index.php?' . self::QUERY_VAR . '=' . self::PAGE_ARCHIVE,
			'top'
		);

		// Self-heal: ensure rules are flushed if missing from the active rules.
		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) && ! empty( $rules ) && ! isset( $rules[ '^' . self::STEM_ARCHIVE . '/?$' ] ) ) {
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Extract normalized path from a URL.
	 *
	 * @param string $url URL or request URI.
	 * @return string
	 */
	protected static function parse_path( $url ) {
		$path = wp_parse_url( (string) $url, PHP_URL_PATH );

		return trim( (string) $path, '/' );
	}

	/**
	 * Fallback route matching when rewrite rules have not been flushed yet.
	 *
	 * @param \WP $wp WordPress environment.
	 * @return void
	 */
	public function parse_request_fallback( $wp ) {
		$request = trim( (string) $wp->request, '/' );
		if ( '' === $request && isset( $_SERVER['REQUEST_URI'] ) ) {
			$raw_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$request = self::parse_path( $raw_uri );
			$home    = self::parse_path( home_url() );
			if ( '' !== $home && 0 === strpos( $request, $home ) ) {
				$request = trim( (string) substr( $request, strlen( $home ) ), '/' );
			}
		}

		if ( self::STEM_ARCHIVE === $request ) {
			$wp->query_vars[ self::QUERY_VAR ] = self::PAGE_ARCHIVE;
			$wp->matched_rule                  = 'sidrena_cijena_archive';
			unset( $wp->query_vars['error'] );
		} elseif ( self::STEM_LIST === $request ) {
			$wp->query_vars[ self::QUERY_VAR ] = self::PAGE_LIST;
			$wp->matched_rule                  = 'sidrena_cijena_cjenik';
			unset( $wp->query_vars['error'] );
		}
	}

	/**
	 * Allow the page query var through WordPress.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Serve a public page request.
	 *
	 * @return void
	 */
	public function serve() {
		$page = (string) get_query_var( self::QUERY_VAR );

		if ( ! in_array( $page, array( self::PAGE_LIST, self::PAGE_ARCHIVE ), true ) ) {
			$raw_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$request = '' !== $raw_uri ? self::parse_path( $raw_uri ) : '';
			$home    = self::parse_path( home_url() );
			if ( '' !== $home && 0 === strpos( $request, $home ) ) {
				$request = trim( (string) substr( $request, strlen( $home ) ), '/' );
			}

			if ( self::STEM_ARCHIVE === $request ) {
				$page = self::PAGE_ARCHIVE;
			} elseif ( self::STEM_LIST === $request ) {
				$page = self::PAGE_LIST;
			} else {
				return;
			}
		}

		global $wp_query;

		if ( self::PAGE_ARCHIVE === $page && ! Settings::get( 'public_archive_enabled', true ) ) {
			if ( is_object( $wp_query ) ) {
				$wp_query->set_404();
			}
			status_header( 404 );
			return;
		}

		if ( is_object( $wp_query ) ) {
			$wp_query->is_404 = false;
		}

		status_header( 200 );
		nocache_headers();

		get_header();

		// The templates escape every value they print.
		echo $this->render( $page, $this->request_args() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		get_footer();

		exit;
	}

	/**
	 * Show a link to the archive in the active theme footer.
	 *
	 * @return void
	 */
	public function footer_link() {
		if ( ! Settings::get( 'public_archive_enabled', true ) || ! Settings::get( 'archive_footer_link', true ) ) {
			return;
		}

		$text = (string) apply_filters( 'sidrena_cijena_footer_link_text', __( 'Cjenici', 'sidrena-cijena-eu' ) );

		printf(
			'<p class="sidrena-cijena-archive-link"><a href="%1$s">%2$s</a></p>',
			esc_url( home_url( '/' . self::STEM_ARCHIVE . '/' ) ),
			esc_html( $text )
		);
	}

	/**
	 * Enqueue stylesheet on the frontend.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$page    = (string) get_query_var( self::QUERY_VAR );
		$is_page = in_array( $page, array( self::PAGE_LIST, self::PAGE_ARCHIVE ), true );

		if ( ! $is_page ) {
			$raw_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$request = '' !== $raw_uri ? self::parse_path( $raw_uri ) : '';
			$home    = self::parse_path( home_url() );
			if ( '' !== $home && 0 === strpos( $request, $home ) ) {
				$request = trim( (string) substr( $request, strlen( $home ) ), '/' );
			}

			if ( self::STEM_ARCHIVE === $request || self::STEM_LIST === $request ) {
				$is_page = true;
			}
		}

		if ( ! $is_page && function_exists( 'is_singular' ) && is_singular() ) {
			global $post;
			if ( is_object( $post ) && isset( $post->post_content ) && ( false !== strpos( (string) $post->post_content, 'sidrena-cjenik' ) || false !== strpos( (string) $post->post_content, 'sidrena-arhiva' ) ) ) {
				$is_page = true;
			}
		}

		if ( ! $is_page ) {
			return;
		}

		wp_enqueue_style(
			'sidrena-cijena-frontend',
			SIDRENA_CIJENA_URL . 'assets/css/frontend.css',
			array(),
			SIDRENA_CIJENA_VERSION
		);
	}

	/**
	 * Register the archive URL in the WordPress sitemap.
	 *
	 * @param \WP_Sitemaps $sitemaps Sitemap registry.
	 * @return void
	 */
	public function register_sitemap_provider( $sitemaps ) {
		if ( ! Settings::get( 'public_archive_enabled', true ) || ! Settings::get( 'archive_sitemap_link', true ) || ! class_exists( '\WP_Sitemaps_Provider' ) ) {
			return;
		}

		$sitemaps->registry->add_provider( 'sidrena-cijena', new PublicArchiveSitemapProvider() );
	}

	/**
	 * Render a public page as HTML.
	 *
	 * @param string       $page Page identifier.
	 * @param array<mixed> $args Arguments.
	 * @return string
	 */
	public function render( $page, array $args = array() ) {
		if ( self::PAGE_ARCHIVE === $page ) {
			return $this->render_archive( $args );
		}

		return $this->render_list( $args );
	}

	/**
	 * Render the price list table.
	 *
	 * @param array<mixed> $args `q`, `type`, `paged`.
	 * @return string
	 */
	public function render_list( array $args = array() ) {
		$query = isset( $args['q'] ) ? trim( (string) $args['q'] ) : '';
		$type  = isset( $args['type'] ) ? (string) $args['type'] : '';
		$paged = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;

		if ( ! in_array( $type, array( 'proizvod', 'usluga' ), true ) ) {
			$type = '';
		}

		$location = PublicFeed::public_location();

		// Building the rows walks the whole catalog on every request, so the
		// rendered table is kept in the shared public-response cache and is
		// invalidated whenever price data, settings or publications change.
		$cache  = Plugin::instance()->rest_cache();
		$key    = $cache->key(
			array(
				'page'     => 'cjenik',
				'location' => $location ? (int) $location['id'] : 0,
				'q'        => $query,
				'type'     => $type,
				'paged'    => $paged,
			)
		);
		$cached = $cache->get( $key );

		if ( null !== $cached ) {
			return $this->style() . (string) $cached;
		}

		$rows = array();
		$meta = array();

		if ( $location ) {
			$builder = new PriceListBuilder(
				Plugin::instance()->catalog(),
				Plugin::instance()->history(),
				Plugin::instance()->special_sales()
			);

			$build = $builder->build( $location );
			$rows  = $build['items'];
			$meta  = $build['meta'];
		}

		$filtered = array();

		foreach ( $rows as $row ) {
			if ( '' !== $type && $type !== $row['vrsta'] ) {
				continue;
			}

			if ( '' !== $query && ! $this->matches( $row, $query ) ) {
				continue;
			}

			$filtered[] = $this->display_row( $row );
		}

		$total_items = count( $filtered );
		$per_page    = self::LIST_PER_PAGE;
		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
		$paged       = min( $paged, $total_pages );
		$offset      = ( $paged - 1 ) * $per_page;
		$page_rows   = array_slice( $filtered, $offset, $per_page );

		$html = $this->partial(
			'public-price-list.php',
			array(
				'rows'        => $page_rows,
				'meta'        => $meta,
				'location'    => $location,
				'q'           => $query,
				'type'        => $type,
				'paged'       => $paged,
				'per_page'    => $per_page,
				'total_items' => $total_items,
				'total_pages' => $total_pages,
				'feed_xml'    => home_url( '/' . PublicFeed::STEM . '.xml' ),
				'feed_csv'    => home_url( '/' . PublicFeed::STEM . '.csv' ),
				'archive_url' => home_url( '/' . self::STEM_ARCHIVE . '/' ),
			)
		);

		$cache->set( $key, $html );

		return $this->style() . $html;
	}

	/**
	 * Render the publication archive table.
	 *
	 * @param array<mixed> $args Optional. Arguments including `paged`.
	 * @return string
	 */
	public function render_archive( array $args = array() ) {
		$paged = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		if ( 1 === $paged && isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public pagination.
			$paged = max( 1, absint( wp_unslash( $_GET['paged'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public pagination.
		}

		$location = PublicFeed::public_location();
		$items    = array();
		$total    = 0;
		$per_page = self::ARCHIVE_LIMIT;

		if ( $location ) {
			$index = Plugin::instance()->publications()->index(
				array(
					'location' => (int) $location['id'],
					'page'     => $paged,
					'per_page' => $per_page,
				)
			);

			$items = (array) $index['items'];
			$total = (int) $index['total'];
		}

		$rows = array();

		foreach ( $items as $item ) {
			$rows[] = $this->display_publication( $item );
		}

		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;

		return $this->style() . $this->partial(
			'public-archive.php',
			array(
				'rows'        => $rows,
				'location'    => $location,
				'paged'       => $paged,
				'per_page'    => $per_page,
				'total_items' => $total,
				'total_pages' => $total_pages,
				'list_url'    => home_url( '/' . self::STEM_LIST . '/' ),
				'feed_xml'    => home_url( '/' . PublicFeed::STEM . '.xml' ),
				'feed_csv'    => home_url( '/' . PublicFeed::STEM . '.csv' ),
			)
		);
	}

	/**
	 * Shortcode: the price list table.
	 *
	 * @param array<mixed> $atts Attributes.
	 * @return string
	 */
	public function shortcode_list( $atts ) {
		$atts = shortcode_atts(
			array(
				'q'     => '',
				'type'  => '',
				'paged' => 0,
			),
			$atts,
			'sidrena-cjenik'
		);

		$paged = (int) $atts['paged'];
		if ( $paged <= 0 && isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filter.
			$paged = max( 1, absint( wp_unslash( $_GET['paged'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filter.
		}
		if ( $paged <= 0 ) {
			$paged = 1;
		}

		return $this->render_list(
			array(
				'q'     => (string) $atts['q'],
				'type'  => (string) $atts['type'],
				'paged' => $paged,
			)
		);
	}

	/**
	 * Shortcode: the publication archive table.
	 *
	 * @param array<mixed> $atts Attributes.
	 * @return string
	 */
	public function shortcode_archive( $atts ) {
		$atts = shortcode_atts(
			array(
				'paged' => 0,
			),
			$atts,
			'sidrena-arhiva'
		);

		$paged = (int) $atts['paged'];
		if ( $paged <= 0 && isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filter.
			$paged = max( 1, absint( wp_unslash( $_GET['paged'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filter.
		}
		if ( $paged <= 0 ) {
			$paged = 1;
		}

		return $this->render_archive( array( 'paged' => $paged ) );
	}

	/**
	 * Request arguments of a public page request.
	 *
	 * @return array<mixed>
	 */
	protected function request_args() {
		$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filter.
		$type  = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filter.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filter.

		return array(
			'q'     => $query,
			'type'  => $type,
			'paged' => $paged,
		);
	}

	/**
	 * Whether a row matches a search term.
	 *
	 * @param array<mixed> $row Canonical row.
	 * @param string       $q   Search term.
	 * @return bool
	 */
	protected function matches( array $row, $q ) {
		foreach ( array( 'naziv', 'sifra', 'marka', 'barkod' ) as $field ) {
			$value = isset( $row[ $field ] ) ? (string) $row[ $field ] : '';

			if ( '' === $value ) {
				continue;
			}

			$found = function_exists( 'mb_stripos' ) ? mb_stripos( $value, $q ) : stripos( $value, $q );

			if ( false !== $found ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add display values to a canonical row.
	 *
	 * @param array<mixed> $row Canonical row.
	 * @return array<mixed>
	 */
	protected function display_row( array $row ) {
		$row['unit_price_display']    = null === $row['cijena_za_jedinicu_mjere'] ? '' : Currency::format( (float) $row['cijena_za_jedinicu_mjere'] );
		$row['retail_display']        = Currency::format( (float) $row['maloprodajna_cijena'] );
		$lowest_30_day_price          = isset( $row['special_sale']['lowest_30_day_price'] ) ? $row['special_sale']['lowest_30_day_price'] : null;
		$row['lowest_30_day_display'] = null === $lowest_30_day_price ? '' : Currency::format( (float) $lowest_30_day_price );
		$row['anchor_display']        = null === $row['sidrena_cijena'] ? '' : Currency::format( (float) $row['sidrena_cijena'] );
		$row['reference_display']     = Dates::format_date_display( isset( $row['anchor_reference_date'] ) ? $row['anchor_reference_date'] : '' );
		$row['type_display']          = 'usluga' === $row['vrsta'] ? __( 'Service', 'sidrena-cijena-eu' ) : __( 'Product', 'sidrena-cijena-eu' );
		$row['sale_display']          = empty( $row['poseban_oblik_prodaje'] ) ? '' : (string) $row['naziv_posebnog_oblika_prodaje'];
		$row['availability_display']  = 'dostupno' === $row['dostupnost'] ? __( 'Available', 'sidrena-cijena-eu' ) : __( 'Unavailable', 'sidrena-cijena-eu' );

		return $row;
	}

	/**
	 * Add display values to a publication row.
	 *
	 * @param array<mixed> $item Publication row.
	 * @return array<mixed>
	 */
	protected function display_publication( array $item ) {
		$published = ! empty( $item['published_at'] ) ? (string) $item['published_at'] : (string) $item['created_at'];

		$item['published_display'] = wp_date( 'd.m.Y. H:i', (int) strtotime( $published ) );
		$item['format_display']    = strtoupper( (string) $item['format'] );
		$item['size_display']      = size_format( (int) $item['file_size'] );
		$item['checksum_short']    = substr( (string) $item['checksum'], 0, 12 );
		$item['download_url']      = (string) $item['url'];

		return $item;
	}

	/**
	 * Load a template partial, allowing a theme override.
	 *
	 * @param string       $name Partial file name.
	 * @param array<mixed> $args Template arguments.
	 * @return string
	 */
	protected function partial( $name, array $args ) {
		$template = locate_template( array( 'sidrena-cijena/' . $name ) );
		$path     = $template ? $template : SIDRENA_CIJENA_DIR . 'templates/' . $name;

		if ( ! is_readable( $path ) ) {
			return '';
		}

		ob_start();

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template arguments.
		extract( $args, EXTR_SKIP );

		include $path;

		return (string) ob_get_clean();
	}

	/**
	 * Minimal table styles, printed once per request.
	 *
	 * The public pages must survive any theme, so the tables carry their own
	 * few rules instead of depending on a stylesheet the theme may not load.
	 *
	 * @return string
	 */
	protected function style() {
		if ( self::$styled ) {
			return '';
		}

		self::$styled = true;

		return '<style>.sidrena-cijena-table{width:100%;border-collapse:collapse;margin:1rem 0}.sidrena-cijena-table caption{text-align:left;font-weight:600;margin-bottom:.5rem}.sidrena-cijena-table th,.sidrena-cijena-table td{padding:.4rem .5rem;border-bottom:1px solid #dcdcde;text-align:left;vertical-align:top}.sidrena-cijena-cjenik__meta,.sidrena-cijena-cjenik__links{margin:.5rem 0}.sidrena-cijena-cjenik__search{display:flex;gap:.5rem;align-items:center;margin:.5rem 0}</style>';
	}
}
