<?php
/**
 * Compliance dashboard screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Compliance\Finding;
use SidrenaCijena\Compliance\ReferenceDateResolver;
use SidrenaCijena\Compliance\Validator;
use SidrenaCijena\Cron\DailyGeneration;
use SidrenaCijena\Cron\Scheduler;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * Overview of anchor prices, compliance findings and export status.
 */
class DashboardPage {

	/**
	 * Maximum number of catalog items scanned for dashboard aggregates.
	 */
	const SCAN_LIMIT = 500;

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'titanworks-sidrena-bazna-cijena' ) );
		}

		$data = self::aggregate();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena', 'titanworks-sidrena-bazna-cijena' ) . '</h1>';
		echo '<p class="sidrena-dashboard-guide-action"><a class="button button-secondary" href="' . esc_url( AdminMenu::url( DocumentationPage::SLUG ) ) . '"><span class="dashicons dashicons-book sidrena-icon-inline"></span>' . esc_html__( 'Open documentation & guide', 'titanworks-sidrena-bazna-cijena' ) . '</a></p>';

		if ( $data['total'] > $data['scanned'] ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: scanned items, 2: total items. */
						__( 'Showing aggregates for the first %1$d of %2$d catalog items. Run the compliance check for a full report.', 'titanworks-sidrena-bazna-cijena' ),
						$data['scanned'],
						$data['total']
					)
				)
			);
		}

		self::render_anchor_cards( $data );
		self::render_export_section( $data );
		self::render_findings( $data );

		echo '</div>';
	}

	/**
	 * Compute dashboard aggregates.
	 *
	 * @param int  $limit Maximum items to scan.
	 * @param bool $force Whether to bypass the aggregate cache.
	 * @return array<mixed>
	 */
	public static function aggregate( $limit = self::SCAN_LIMIT, $force = false ) {
		$plugin    = Plugin::instance();
		$catalog   = $plugin->catalog();
		$history   = $plugin->history();
		$sales     = $plugin->special_sales();
		$validator = $plugin->validator();
		$limit     = max( 1, (int) $limit );

		$cache     = $plugin->rest_cache();
		$cache_key = $cache->key(
			array(
				'aggregate' => 'dashboard',
				'limit'     => $limit,
			)
		);

		if ( ! $force ) {
			$cached = $cache->get( $cache_key );
			if ( is_array( $cached ) && isset( $cached['total'], $cached['findings'] ) ) {
				return $cached;
			}
		}

		$total = (int) $catalog->count_items();

		$items = array();

		foreach ( $catalog->iterate( array( 'per_page' => 200 ) ) as $item ) {
			$items[] = $item;

			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		$ids = array();

		foreach ( $items as $item ) {
			$ids[] = (int) $item->id;
		}

		$anchors   = $ids ? $history->latest_map( $ids, 'anchor' ) : array();
		$regular   = $ids ? $history->latest_map( $ids, 'regular' ) : array();
		$sales_map = $items ? $sales->active_map() : array();

		$anchor_states = array(
			'valid'              => 0,
			'missing'            => 0,
			'needs_verification' => 0,
		);

		$comparison = array(
			'below'   => 0,
			'equal'   => 0,
			'above'   => 0,
			'unknown' => 0,
		);

		$missing_history         = 0;
		$thirty_day_warnings     = 0;
		$unclassified_promotions = 0;

		foreach ( $items as $item ) {
			$key    = $item->key();
			$anchor = isset( $anchors[ $key ] ) ? $anchors[ $key ] : null;

			if ( ! $anchor ) {
				++$anchor_states['missing'];
				++$comparison['unknown'];
			} else {
				$resolved = ReferenceDateResolver::resolve( $item );
				$recorded = isset( $anchor['reference_date'] ) ? (string) $anchor['reference_date'] : '';

				if ( '' !== $recorded && $recorded === $resolved['date'] ) {
					++$anchor_states['valid'];
				} else {
					++$anchor_states['needs_verification'];
				}

				$anchor_price = (float) $anchor['price'];
				$current      = $item->effective_price();

				if ( null === $current ) {
					++$comparison['unknown'];
				} elseif ( $current < $anchor_price ) {
					++$comparison['below'];
				} elseif ( $current > $anchor_price ) {
					++$comparison['above'];
				} else {
					++$comparison['equal'];
				}
			}

			$sale = isset( $sales_map[ $key ] ) ? $sales_map[ $key ] : null;

			if ( ! isset( $regular[ $key ] ) && ! $sale ) {
				++$missing_history;
			}

			if ( $sale && empty( $sale['lowest_30_day_price'] ) ) {
				++$thirty_day_warnings;
			}

			if ( ! $sale && self::is_promotional( $item ) ) {
				++$unclassified_promotions;
			}
		}

		$findings = array_merge(
			$validator->validate_items( $items ),
			$validator->validate_global()
		);

		$publications = $plugin->publications();
		$index        = $publications->index( array( 'per_page' => 5 ) );
		$failed       = $publications->count_by_status();
		$next_run     = Scheduler::next_run();

		$counts = Validator::counts( $findings );

		$result = array(
			'total'                   => $total,
			'scanned'                 => count( $items ),
			'anchor_states'           => $anchor_states,
			'comparison'              => $comparison,
			'missing_history'         => $missing_history,
			'thirty_day_warnings'     => $thirty_day_warnings,
			'unclassified_promotions' => $unclassified_promotions,
			'findings'                => $findings,
			'counts'                  => $counts,
			'last_successful'         => $publications->last_successful(),
			'next_run'                => $next_run,
			'publications'            => $index['items'],
			'failed_publications'     => isset( $failed['failed'] ) ? (int) $failed['failed'] : 0,
		);

		// Never cache away a compliance ERROR: only cache when free of compliance errors.
		if ( empty( $counts[ Finding::ERROR ] ) ) {
			$cache->set( $cache_key, $result, 60 );
		}

		return $result;
	}

	/**
	 * Whether the item carries a promotional price.
	 *
	 * @param CatalogItem $item Item.
	 * @return bool
	 */
	protected static function is_promotional( CatalogItem $item ) {
		if ( null === $item->sale_price || $item->sale_price <= 0 ) {
			return false;
		}

		return null === $item->regular_price || $item->sale_price < $item->regular_price;
	}

	/**
	 * Render the anchor and comparison cards.
	 *
	 * @param array<mixed> $data Aggregates.
	 * @return void
	 */
	protected static function render_anchor_cards( array $data ) {
		$cards = array(
			__( 'Anchors valid', 'titanworks-sidrena-bazna-cijena' )     => $data['anchor_states']['valid'],
			__( 'Anchors missing', 'titanworks-sidrena-bazna-cijena' )   => $data['anchor_states']['missing'],
			__( 'Anchors needing verification', 'titanworks-sidrena-bazna-cijena' ) => $data['anchor_states']['needs_verification'],
			__( 'Items with no price history', 'titanworks-sidrena-bazna-cijena' ) => $data['missing_history'],
			__( 'Current price below anchor', 'titanworks-sidrena-bazna-cijena' ) => $data['comparison']['below'],
			__( 'Current price equal to anchor', 'titanworks-sidrena-bazna-cijena' ) => $data['comparison']['equal'],
			__( 'Current price above anchor', 'titanworks-sidrena-bazna-cijena' ) => $data['comparison']['above'],
			__( '30-day price warnings', 'titanworks-sidrena-bazna-cijena' ) => $data['thirty_day_warnings'],
			__( 'Unclassified promotions', 'titanworks-sidrena-bazna-cijena' ) => $data['unclassified_promotions'],
			__( 'Validation errors', 'titanworks-sidrena-bazna-cijena' ) => $data['counts'][ Finding::ERROR ],
			__( 'Validation warnings', 'titanworks-sidrena-bazna-cijena' ) => $data['counts'][ Finding::WARNING ],
		);

		echo '<div class="sidrena-cijena-cards">';

		foreach ( $cards as $label => $value ) {
			echo '<div class="sidrena-cijena-card">';
			echo '<span class="sidrena-cijena-card__value">' . esc_html( (string) $value ) . '</span>';
			echo '<span class="sidrena-cijena-card__label">' . esc_html( $label ) . '</span>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render the export status section.
	 *
	 * @param array<mixed> $data Aggregates.
	 * @return void
	 */
	protected static function render_export_section( array $data ) {
		echo '<h2>' . esc_html__( 'Export status', 'titanworks-sidrena-bazna-cijena' ) . '</h2>';
		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Export status', 'titanworks-sidrena-bazna-cijena' ) . '</caption>';
		echo '<tbody>';

		echo '<tr><td>' . esc_html__( 'Last successful publication', 'titanworks-sidrena-bazna-cijena' ) . '</td><td>';
		if ( $data['last_successful'] ) {
			$row = $data['last_successful'];
			echo esc_html( (string) $row['filename'] ) . ' — ' . esc_html( self::site_time( isset( $row['published_at'] ) ? (string) $row['published_at'] : '' ) );
		} else {
			esc_html_e( 'No price list has been published yet.', 'titanworks-sidrena-bazna-cijena' );
		}
		echo '</td></tr>';

		echo '<tr><td>' . esc_html__( 'Next scheduled generation', 'titanworks-sidrena-bazna-cijena' ) . '</td><td>';
		if ( $data['next_run'] ) {
			echo esc_html( self::timestamp_time( (int) $data['next_run'] ) );
		} else {
			esc_html_e( 'Not scheduled.', 'titanworks-sidrena-bazna-cijena' );
		}
		echo '</td></tr>';

		echo '<tr><td>' . esc_html__( 'Failed publications', 'titanworks-sidrena-bazna-cijena' ) . '</td><td>';
		printf(
			'%1$d <a href="%2$s">%3$s</a>',
			(int) $data['failed_publications'],
			esc_url( AdminMenu::url( 'sidrena-cijena-publications' ) ),
			esc_html__( 'View price lists', 'titanworks-sidrena-bazna-cijena' )
		);
		echo '</td></tr>';

		$last_gen = get_option( DailyGeneration::RESULT_OPTION, null );
		if ( $last_gen && is_array( $last_gen ) ) {
			echo '<tr><td>' . esc_html__( 'Last generation status', 'titanworks-sidrena-bazna-cijena' ) . '</td><td>';
			if ( ! empty( $last_gen['summary']['failed'] ) ) {
				echo '<span class="sidrena-cijena-status sidrena-cijena-status--error">' . esc_html__( 'Failed', 'titanworks-sidrena-bazna-cijena' ) . '</span> ';
				echo esc_html( ! empty( $last_gen['errors'] ) ? implode( '; ', (array) $last_gen['errors'] ) : '' );
			} else {
				echo '<span class="sidrena-cijena-status sidrena-cijena-status--valid">' . esc_html__( 'Successful', 'titanworks-sidrena-bazna-cijena' ) . '</span>';
			}
			echo '</td></tr>';
		}

		echo '</tbody></table>';

		if ( ! empty( $data['publications'] ) ) {
			echo '<h3>' . esc_html__( 'Recent publications', 'titanworks-sidrena-bazna-cijena' ) . '</h3>';
			echo '<table class="widefat striped">';
			echo '<caption class="screen-reader-text">' . esc_html__( 'Recent publications', 'titanworks-sidrena-bazna-cijena' ) . '</caption>';
			echo '<thead><tr>';
			echo '<th scope="col">' . esc_html__( 'File', 'titanworks-sidrena-bazna-cijena' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Format', 'titanworks-sidrena-bazna-cijena' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Published', 'titanworks-sidrena-bazna-cijena' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Status', 'titanworks-sidrena-bazna-cijena' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $data['publications'] as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( (string) $row['filename'] ) . '</td>';
				echo '<td>' . esc_html( strtoupper( (string) $row['format'] ) ) . '</td>';
				echo '<td>' . esc_html( self::site_time( isset( $row['published_at'] ) ? (string) $row['published_at'] : '' ) ) . '</td>';
				echo '<td><span class="sidrena-cijena-status sidrena-cijena-status--' . esc_attr( (string) $row['status'] ) . '">' . esc_html( Labels::publication_status( (string) $row['status'] ) ) . '</span></td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}
	}

	/**
	 * Render the findings summary.
	 *
	 * @param array<mixed> $data Aggregates.
	 * @return void
	 */
	protected static function render_findings( array $data ) {
		echo '<h2>' . esc_html__( 'Validation findings', 'titanworks-sidrena-bazna-cijena' ) . '</h2>';

		printf(
			'<p><a class="button" href="%s">%s</a></p>',
			esc_url( AdminMenu::url( 'sidrena-cijena-compliance' ) ),
			esc_html__( 'Run compliance check', 'titanworks-sidrena-bazna-cijena' )
		);

		if ( empty( $data['findings'] ) ) {
			echo '<p>' . esc_html__( 'No findings for the scanned items.', 'titanworks-sidrena-bazna-cijena' ) . '</p>';

			return;
		}

		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Validation findings', 'titanworks-sidrena-bazna-cijena' ) . '</caption>';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Severity', 'titanworks-sidrena-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Code', 'titanworks-sidrena-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Item', 'titanworks-sidrena-bazna-cijena' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Explanation', 'titanworks-sidrena-bazna-cijena' ) . '</th>';
		echo '</tr></thead><tbody>';

		$shown = 0;

		foreach ( $data['findings'] as $finding ) {
			if ( $shown >= 10 ) {
				break;
			}

			++$shown;

			echo '<tr>';
			echo '<td><span class="sidrena-cijena-status sidrena-cijena-status--' . esc_attr( strtolower( $finding->severity ) ) . '">' . esc_html( Labels::severity( $finding->severity ) ) . '</span></td>';
			echo '<td><code>' . esc_html( $finding->code ) . '</code></td>';
			echo '<td>' . wp_kses_post( AdminLinks::render_entity_link( (string) $finding->entity_type, (int) $finding->entity_id ) ) . '</td>';
			echo '<td>' . esc_html( $finding->message ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		if ( count( $data['findings'] ) > $shown ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: number of findings not shown. */
						__( '%d further findings are not shown; run the compliance check for the full report.', 'titanworks-sidrena-bazna-cijena' ),
						count( $data['findings'] ) - $shown
					)
				)
			);
		}
	}

	/**
	 * Format a UTC datetime for display in site time.
	 *
	 * @param string $datetime UTC datetime.
	 * @return string
	 */
	protected static function site_time( $datetime ) {
		if ( '' === (string) $datetime ) {
			return '—';
		}

		$formatted = Dates::format_datetime_display( (string) $datetime );

		return '' !== $formatted ? $formatted : '—';
	}

	/**
	 * Format a WP timestamp for display in site time.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	protected static function timestamp_time( $timestamp ) {
		$formatted = Dates::format_datetime_display( (int) $timestamp );

		return '' !== $formatted ? $formatted : '—';
	}
}
