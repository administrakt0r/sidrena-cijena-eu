<?php
/**
 * Manual bulk anchor entry screen.
 *
 * @package Titanworks\SidrenaCijena\Admin
 */

namespace Titanworks\SidrenaCijena\Admin;

use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Support\Currency;
use Titanworks\SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Records anchors for many items at once without guessing silently.
 */
class BulkAnchorEntryPage {

	const SLUG = 'sidrena-cijena-anchors';

	const ACTION = 'twbce_cijena_bulk_anchors';

	const ACTION_ALL_CURRENT = 'twbce_cijena_set_all_anchors_current';

	const PER_PAGE = 50;

	/**
	 * Number of catalog items checked per batched existence query.
	 */
	const CHECK_CHUNK = 200;

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena-eu' ) );
		}

		$paged   = self::current_page();
		$missing = self::missing_items( $paged );
		$rows    = $missing['rows'];
		$total   = $missing['total'];

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Anchor entry', 'sidrena-cijena-eu' ) . '</h1>';

		self::render_notice();

		echo '<div class="card sidrena-bulk-all-card">';
		echo '<h2>' . esc_html__( 'Set anchor prices for all products at once (from current price)', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p>' . esc_html__( 'This option automatically sets the anchor price for products and variations in your shop based on their current active price and prescribed legal reference date (2 May 2025 for legacy categories, 10 September 2026 for all others).', 'sidrena-cijena-eu' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sidrena-busy-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_ALL_CURRENT ) . '" />';
		wp_nonce_field( self::ACTION_ALL_CURRENT, 'twbce_cijena_all_current_nonce' );
		echo '<p>';
		echo '<label class="sidrena-label-inline-wide"><input type="radio" name="scope" value="missing" checked="checked" /> ' . esc_html__( 'Only for products missing an anchor price (recommended)', 'sidrena-cijena-eu' ) . '</label>';
		echo '<label><input type="radio" name="scope" value="all" /> ' . esc_html__( 'For all products in catalog (including overwriting existing)', 'sidrena-cijena-eu' ) . '</label>';
		echo '</p>';
		echo '<p><button type="submit" class="button button-primary sidrena-btn-busy" data-sidrena-confirm="' . esc_attr__( 'Are you sure you want to set anchor prices for all products in your shop based on their current prices?', 'sidrena-cijena-eu' ) . '">' . esc_html__( 'Set anchor prices for all products in shop', 'sidrena-cijena-eu' ) . '</button></p>';
		echo '</form>';
		echo '</div>';

		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Suggested prices are taken from the current price and are NOT verified historical anchor prices. Review every row before saving; unchecked rows are never stored.', 'sidrena-cijena-eu' ) . '</p></div>';

		if ( ! $rows ) {
			if ( $total > 0 ) {
				echo '<p>' . esc_html__( 'No items without an anchor price on this page.', 'sidrena-cijena-eu' ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'Every catalog item has an anchor price recorded.', 'sidrena-cijena-eu' ) . '</p>';
			}

			Pagination::render( $paged, $total, self::PER_PAGE );

			echo '</div>';
			return;
		}

		echo '<p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of items without an anchor. */
				__( '%d item(s) have no anchor price yet.', 'sidrena-cijena-eu' ),
				$total
			)
		);
		echo '</p>';

		echo '<p class="sidrena-bulk-selection-actions">';
		echo '<button type="button" class="button button-secondary" id="sidrena-btn-select-all">' . esc_html__( 'Select all on this page', 'sidrena-cijena-eu' ) . '</button> ';
		echo '<button type="button" class="button button-secondary" id="sidrena-btn-deselect-all">' . esc_html__( 'Deselect all', 'sidrena-cijena-eu' ) . '</button>';
		echo '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sidrena-busy-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		wp_nonce_field( self::ACTION, 'twbce_cijena_bulk_nonce' );

		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Items without an anchor price', 'sidrena-cijena-eu' ) . '</caption>';
		echo '<thead><tr>';
		echo '<th class="check-column" scope="col"><input type="checkbox" id="sidrena-select-all" title="' . esc_attr__( 'Select all on page', 'sidrena-cijena-eu' ) . '" aria-label="' . esc_attr__( 'Select all on page', 'sidrena-cijena-eu' ) . '" /></th>';
		echo '<th scope="col">' . esc_html__( 'Item', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'SKU', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Current price', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Suggested anchor (unverified)', 'sidrena-cijena-eu' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Reference date', 'sidrena-cijena-eu' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$item    = $row['item'];
			$suggest = $item->effective_price();
			$field   = 'rows[' . (int) $item->id . '][' . (int) $item->variation_id . ']';

			$item_title = $item->name . ( $item->is_variation() ? ' (' . __( 'variation', 'sidrena-cijena-eu' ) . ' #' . (int) $item->variation_id . ')' : '' );

			echo '<tr>';
			echo '<td class="check-column"><input type="checkbox" class="sidrena-row-cb" name="' . esc_attr( $field ) . '[record]" value="1" aria-label="' . esc_attr( sprintf( /* translators: %s: catalog item name. */ __( 'Select %s', 'sidrena-cijena-eu' ), $item->name ) ) . '" /></td>';
			echo '<td>' . wp_kses_post( AdminLinks::render_item_link( (int) $item->id, $item_title ) ) . '</td>';
			echo '<td>' . esc_html( (string) $item->sku ) . '</td>';
			echo '<td>' . esc_html( null === $suggest ? '—' : Currency::format( $suggest, $item->currency ) ) . '</td>';
			echo '<td><input type="text" name="' . esc_attr( $field ) . '[price]" value="' . esc_attr( null === $suggest ? '' : Currency::format_decimal( $suggest ) ) . '" class="small-text" inputmode="decimal" placeholder="' . esc_attr__( 'e.g. 19.99', 'sidrena-cijena-eu' ) . '" aria-label="' . esc_attr__( 'Suggested anchor (unverified)', 'sidrena-cijena-eu' ) . '" /></td>';
			$ref_formatted = Dates::format_date_display( $row['reference_date'] );
			echo '<td><input type="text" class="small-text sidrena-datepicker" name="' . esc_attr( $field ) . '[reference_date]" value="' . esc_attr( $ref_formatted ) . '" placeholder="d.m.Y." autocomplete="off" inputmode="numeric" aria-label="' . esc_attr__( 'Reference date', 'sidrena-cijena-eu' ) . '" /></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		submit_button( __( 'Record checked anchors', 'sidrena-cijena-eu' ), 'primary sidrena-btn-busy' );
		echo '</form>';

		Pagination::render( $paged, $total, self::PER_PAGE );

		echo '</div>';
	}

	/**
	 * Handle setting anchor prices for all products at once from current prices.
	 *
	 * @return void
	 */
	public static function handle_set_all_current() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to record anchor prices.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::ACTION_ALL_CURRENT, 'twbce_cijena_all_current_nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$scope = isset( $_POST['scope'] ) && 'all' === $_POST['scope'] ? 'all' : 'missing';

		$plugin  = Plugin::instance();
		$catalog = $plugin->catalog();
		$history = $plugin->history();
		$anchors = $plugin->anchors();

		$recorded = 0;
		$skipped  = 0;
		$failed   = 0;

		$items = $catalog->iterate(
			array(
				'per_page'           => self::CHECK_CHUNK,
				'include_variations' => true,
			)
		);

		$pending = array();

		foreach ( $items as $item ) {
			$price = $item->effective_price();

			if ( null === $price || (float) $price <= 0.0 ) {
				++$failed;
				continue;
			}

			$pending[] = array(
				'item'  => $item,
				'price' => (float) $price,
			);

			if ( count( $pending ) >= self::CHECK_CHUNK ) {
				self::record_pending( $pending, $scope, $history, $anchors, $recorded, $skipped, $failed );
				$pending = array();
			}
		}

		self::record_pending( $pending, $scope, $history, $anchors, $recorded, $skipped, $failed );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                     => self::SLUG,
					'twbce_cijena_notice'      => $recorded > 0 ? 'success' : ( $failed > 0 ? 'error' : 'info' ),
					'twbce_cijena_notice_text' => sprintf(
						/* translators: 1: count recorded, 2: count skipped, 3: count failed */
						__( 'Successfully set %1$d anchor price(s) from current prices. %2$d item(s) already had an anchor. %3$d item(s) had no valid price.', 'sidrena-cijena-eu' ),
						$recorded,
						$skipped,
						$failed
					),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Record anchors for a prepared batch, resolving existing anchors in one query.
	 *
	 * @param array<int,array{item:\Titanworks\SidrenaCijena\Catalog\CatalogItem,price:float}> $pending  Candidates.
	 * @param string                                                                           $scope    `missing` or `all`.
	 * @param \Titanworks\SidrenaCijena\Database\PriceHistoryRepository                        $history  History repository.
	 * @param \Titanworks\SidrenaCijena\Pricing\AnchorPriceService                             $anchors  Anchor service.
	 * @param int                                                                              $recorded Recorded counter (by reference).
	 * @param int                                                                              $skipped  Skipped counter (by reference).
	 * @param int                                                                              $failed   Failed counter (by reference).
	 * @return void
	 */
	protected static function record_pending( array $pending, $scope, $history, $anchors, &$recorded, &$skipped, &$failed ) {
		if ( empty( $pending ) ) {
			return;
		}

		$items    = array();
		$anchored = array();

		foreach ( $pending as $row ) {
			$items[] = $row['item'];
		}

		if ( 'missing' === $scope ) {
			$anchored = $history->exists_map( self::item_ids( $items ), 'anchor' );
		}

		foreach ( $pending as $row ) {
			$item = $row['item'];

			if ( 'missing' === $scope && isset( $anchored[ $item->key() ] ) ) {
				++$skipped;
				continue;
			}

			$resolved = \Titanworks\SidrenaCijena\Compliance\ReferenceDateResolver::resolve( $item );

			$result = $anchors->record(
				$item,
				$row['price'],
				array(
					'source'            => 'bulk_current',
					'reference_date'    => $resolved['date'],
					'correction_reason' => __( 'Bulk setup of anchor price from current catalog price', 'sidrena-cijena-eu' ),
				)
			);

			if ( is_wp_error( $result ) ) {
				++$failed;
				continue;
			}

			++$recorded;
		}
	}

	/**
	 * Handle the bulk save.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to record anchor prices.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::ACTION, 'twbce_cijena_bulk_nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified above; each field is sanitized below.
		$rows = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : array();

		$plugin  = Plugin::instance();
		$catalog = $plugin->catalog();
		$anchors = $plugin->anchors();

		$recorded = 0;
		$failed   = 0;

		foreach ( $rows as $product_id => $variations ) {
			$product_id = absint( $product_id );

			if ( $product_id <= 0 || ! is_array( $variations ) ) {
				continue;
			}

			foreach ( $variations as $variation_id => $fields ) {
				$variation_id = absint( $variation_id );

				if ( ! is_array( $fields ) || empty( $fields['record'] ) ) {
					continue;
				}

				$price = isset( $fields['price'] ) ? sanitize_text_field( (string) $fields['price'] ) : '';
				$date  = isset( $fields['reference_date'] ) ? Dates::normalize_date( sanitize_text_field( (string) $fields['reference_date'] ) ) : '';

				if ( '' === $price ) {
					++$failed;
					continue;
				}

				$item = $variation_id > 0 ? $catalog->get_variation( $product_id, $variation_id ) : $catalog->get_item( $product_id );

				if ( ! $item ) {
					++$failed;
					continue;
				}

				$result = $anchors->record(
					$item,
					$price,
					array(
						'source'         => 'bulk',
						'reference_date' => $date,
					)
				);

				if ( is_wp_error( $result ) ) {
					++$failed;
					continue;
				}

				++$recorded;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                     => self::SLUG,
					'twbce_cijena_notice'      => $failed > 0 ? 'error' : 'success',
					'twbce_cijena_notice_text' => sprintf(
						/* translators: 1: recorded anchors, 2: failed rows. */
						__( 'Recorded %1$d anchor(s); %2$d row(s) failed.', 'sidrena-cijena-eu' ),
						$recorded,
						$failed
					),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Items without an anchor for one page of the missing set.
	 *
	 * The catalog is streamed once; both the total and the requested page are
	 * derived from the same filtered set, so a page can never claim the catalog
	 * is complete while unanchored items exist on another page.
	 *
	 * @param int $paged Page number.
	 * @return array{rows:array<int,array<string,mixed>>,total:int}
	 */
	protected static function missing_items( $paged ) {
		$plugin  = Plugin::instance();
		$catalog = $plugin->catalog();
		$history = $plugin->history();

		$offset = ( max( 1, (int) $paged ) - 1 ) * self::PER_PAGE;
		$total  = 0;
		$rows   = array();
		$chunk  = array();

		foreach ( $catalog->iterate( array( 'per_page' => self::CHECK_CHUNK ) ) as $item ) {
			$chunk[] = $item;

			if ( count( $chunk ) >= self::CHECK_CHUNK ) {
				self::collect_missing( $history, $chunk, $offset, $total, $rows );
				$chunk = array();
			}
		}

		self::collect_missing( $history, $chunk, $offset, $total, $rows );

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Add a batch of unanchored items to the page and total accumulators.
	 *
	 * @param \Titanworks\SidrenaCijena\Database\PriceHistoryRepository $history History repository.
	 * @param array<int,\Titanworks\SidrenaCijena\Catalog\CatalogItem>  $items   Items.
	 * @param int                                                       $offset  First wanted index.
	 * @param int                                                       $total   Running total (by reference).
	 * @param array<int,array<string,mixed>>                            $rows    Collected page rows (by reference).
	 * @return void
	 */
	protected static function collect_missing( $history, array $items, $offset, &$total, array &$rows ) {
		if ( empty( $items ) ) {
			return;
		}

		$anchored = $history->exists_map( self::item_ids( $items ), 'anchor' );

		foreach ( $items as $item ) {
			if ( isset( $anchored[ $item->key() ] ) ) {
				continue;
			}

			if ( $total >= $offset && count( $rows ) < self::PER_PAGE ) {
				$rows[] = array(
					'item'           => $item,
					'reference_date' => \Titanworks\SidrenaCijena\Compliance\ReferenceDateResolver::resolve( $item )['date'],
				);
			}

			++$total;
		}
	}

	/**
	 * Item IDs of a batch, for a single existence query.
	 *
	 * @param array<int,\Titanworks\SidrenaCijena\Catalog\CatalogItem> $items Items.
	 * @return int[]
	 */
	protected static function item_ids( array $items ) {
		$ids = array();

		foreach ( $items as $item ) {
			$ids[] = (int) $item->id;
		}

		return $ids;
	}

	/**
	 * Count items in a batch with no anchor record.
	 *
	 * @param \Titanworks\SidrenaCijena\Database\PriceHistoryRepository $history History repository.
	 * @param array<int,\Titanworks\SidrenaCijena\Catalog\CatalogItem>  $items   Items.
	 * @return int
	 */
	protected static function missing_in( $history, array $items ) {
		if ( empty( $items ) ) {
			return 0;
		}

		$anchored = $history->exists_map( self::item_ids( $items ), 'anchor' );
		$missing  = 0;

		foreach ( $items as $item ) {
			if ( ! isset( $anchored[ $item->key() ] ) ) {
				++$missing;
			}
		}

		return $missing;
	}

	/**
	 * Current page number.
	 *
	 * @return int
	 */
	protected static function current_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		return isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
	}

	/**
	 * Render the notice.
	 *
	 * @return void
	 */
	protected static function render_notice() {
		Notice::from_request();
	}
}
