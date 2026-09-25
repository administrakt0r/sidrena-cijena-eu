<?php
/**
 * Manual bulk anchor entry screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Records anchors for many items at once without guessing silently.
 */
class BulkAnchorEntryPage {

	const SLUG = 'sidrena-cijena-anchors';

	const ACTION = 'sidrena_cijena_bulk_anchors';

	const ACTION_ALL_CURRENT = 'sidrena_cijena_set_all_anchors_current';

	const PER_PAGE = 50;

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena' ) );
		}

		$paged = self::current_page();
		$rows  = self::rows( $paged );
		$total = self::missing_count();
		$pages = (int) ceil( $total / self::PER_PAGE );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Anchor entry', 'sidrena-cijena' ) . '</h1>';

		self::render_notice();

		echo '<div class="card sidrena-bulk-all-card" style="max-width:100%;margin-top:15px;margin-bottom:20px;padding:16px 20px;">';
		echo '<h2>' . esc_html__( 'Set anchor prices for all products at once (from current price)', 'sidrena-cijena' ) . '</h2>';
		echo '<p>' . esc_html__( 'This option automatically sets the anchor price for products and variations in your shop based on their current active price and prescribed legal reference date (2 May 2025 for legacy categories, 10 September 2026 for all others).', 'sidrena-cijena' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Are you sure you want to set anchor prices for all products in your shop based on their current prices?', 'sidrena-cijena' ) ) . '\');">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_ALL_CURRENT ) . '" />';
		wp_nonce_field( self::ACTION_ALL_CURRENT, 'sidrena_cijena_all_current_nonce' );
		echo '<p>';
		echo '<label style="margin-right:20px;"><input type="radio" name="scope" value="missing" checked="checked" /> ' . esc_html__( 'Only for products missing an anchor price (recommended)', 'sidrena-cijena' ) . '</label>';
		echo '<label><input type="radio" name="scope" value="all" /> ' . esc_html__( 'For all products in catalog (including overwriting existing)', 'sidrena-cijena' ) . '</label>';
		echo '</p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Set anchor prices for all products in shop', 'sidrena-cijena' ) . '</button></p>';
		echo '</form>';
		echo '</div>';

		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Suggested prices are taken from the current price and are NOT verified historical anchor prices. Review every row before saving; unchecked rows are never stored.', 'sidrena-cijena' ) . '</p></div>';

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Every catalog item has an anchor price recorded.', 'sidrena-cijena' ) . '</p></div>';
			return;
		}

		echo '<p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of items without an anchor. */
				__( '%d item(s) have no anchor price yet.', 'sidrena-cijena' ),
				$total
			)
		);
		echo '</p>';

		echo '<p class="sidrena-bulk-selection-actions" style="margin-bottom:12px;">';
		echo '<button type="button" class="button button-secondary" id="sidrena-btn-select-all">' . esc_html__( 'Select all on this page', 'sidrena-cijena' ) . '</button> ';
		echo '<button type="button" class="button button-secondary" id="sidrena-btn-deselect-all">' . esc_html__( 'Deselect all', 'sidrena-cijena' ) . '</button>';
		echo '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		wp_nonce_field( self::ACTION, 'sidrena_cijena_bulk_nonce' );

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th class="check-column"><input type="checkbox" id="sidrena-select-all" title="' . esc_attr__( 'Select all on page', 'sidrena-cijena' ) . '" /></th>';
		echo '<th>' . esc_html__( 'Item', 'sidrena-cijena' ) . '</th>';
		echo '<th>SKU</th>';
		echo '<th>' . esc_html__( 'Current price', 'sidrena-cijena' ) . '</th>';
		echo '<th>' . esc_html__( 'Suggested anchor (unverified)', 'sidrena-cijena' ) . '</th>';
		echo '<th>' . esc_html__( 'Reference date', 'sidrena-cijena' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$item    = $row['item'];
			$suggest = $item->effective_price();
			$field   = 'rows[' . (int) $item->id . '][' . (int) $item->variation_id . ']';

			echo '<tr>';
			echo '<td class="check-column"><input type="checkbox" class="sidrena-row-cb" name="' . esc_attr( $field ) . '[record]" value="1" /></td>';
			echo '<td>' . esc_html( $item->name ) . ( $item->is_variation() ? ' (' . esc_html__( 'variation', 'sidrena-cijena' ) . ' #' . (int) $item->variation_id . ')' : '' ) . '</td>';
			echo '<td>' . esc_html( (string) $item->sku ) . '</td>';
			echo '<td>' . esc_html( null === $suggest ? '—' : Currency::format( $suggest, $item->currency ) ) . '</td>';
			echo '<td><input type="text" name="' . esc_attr( $field ) . '[price]" value="' . esc_attr( null === $suggest ? '' : Currency::format_decimal( $suggest ) ) . '" class="small-text" /></td>';
			echo '<td><input type="date" name="' . esc_attr( $field ) . '[reference_date]" value="' . esc_attr( $row['reference_date'] ) . '" /></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		submit_button( __( 'Record checked anchors', 'sidrena-cijena' ) );
		echo '</form>';

		echo '<script>
		document.addEventListener("DOMContentLoaded", function() {
			var master = document.getElementById("sidrena-select-all");
			var cbs = document.querySelectorAll(".sidrena-row-cb");
			if (master) {
				master.addEventListener("change", function() {
					for (var i = 0; i < cbs.length; i++) {
						cbs[i].checked = master.checked;
					}
				});
			}
			var btnAll = document.getElementById("sidrena-btn-select-all");
			if (btnAll) {
				btnAll.addEventListener("click", function(e) {
					e.preventDefault();
					for (var i = 0; i < cbs.length; i++) {
						cbs[i].checked = true;
					}
					if (master) master.checked = true;
				});
			}
			var btnNone = document.getElementById("sidrena-btn-deselect-all");
			if (btnNone) {
				btnNone.addEventListener("click", function(e) {
					e.preventDefault();
					for (var i = 0; i < cbs.length; i++) {
						cbs[i].checked = false;
					}
					if (master) master.checked = false;
				});
			}
		});
		</script>';

		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				(string) paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			);
			echo '</div></div>';
		}

		echo '</div>';
	}

	/**
	 * Handle setting anchor prices for all products at once from current prices.
	 *
	 * @return void
	 */
	public static function handle_set_all_current() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to record anchor prices.', 'sidrena-cijena' ) );
		}

		check_admin_referer( self::ACTION_ALL_CURRENT, 'sidrena_cijena_all_current_nonce' );

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
				'per_page'           => 100,
				'include_variations' => true,
			)
		);

		foreach ( $items as $item ) {
			if ( 'missing' === $scope && $history->latest( $item->id, $item->variation_id, 'anchor' ) ) {
				++$skipped;
				continue;
			}

			$price = $item->effective_price();
			if ( null === $price || (float) $price <= 0.0 ) {
				++$failed;
				continue;
			}

			$resolved = \SidrenaCijena\Compliance\ReferenceDateResolver::resolve( $item );
			$date     = $resolved['date'];

			$result = $anchors->record(
				$item,
				$price,
				array(
					'source'            => 'bulk_current',
					'reference_date'    => $date,
					'correction_reason' => __( 'Bulk setup of anchor price from current catalog price', 'sidrena-cijena' ),
				)
			);

			if ( is_wp_error( $result ) ) {
				++$failed;
				continue;
			}

			++$recorded;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                       => self::SLUG,
					'sidrena_cijena_notice'      => $recorded > 0 ? 'success' : ( $failed > 0 ? 'error' : 'info' ),
					'sidrena_cijena_notice_text' => sprintf(
						/* translators: 1: count recorded, 2: count skipped, 3: count failed */
						__( 'Successfully set %1$d anchor price(s) from current prices. %2$d item(s) already had an anchor. %3$d item(s) had no valid price.', 'sidrena-cijena' ),
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
	 * Handle the bulk save.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to record anchor prices.', 'sidrena-cijena' ) );
		}

		check_admin_referer( self::ACTION, 'sidrena_cijena_bulk_nonce' );

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
				$date  = isset( $fields['reference_date'] ) ? sanitize_text_field( (string) $fields['reference_date'] ) : '';

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
					'page'                       => self::SLUG,
					'sidrena_cijena_notice'      => $failed > 0 ? 'error' : 'success',
					'sidrena_cijena_notice_text' => sprintf(
						/* translators: 1: recorded anchors, 2: failed rows. */
						__( 'Recorded %1$d anchor(s); %2$d row(s) failed.', 'sidrena-cijena' ),
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
	 * Items without an anchor for the current page.
	 *
	 * @param int $paged Page number.
	 * @return array<int,array<string,mixed>>
	 */
	protected static function rows( $paged ) {
		$plugin  = Plugin::instance();
		$catalog = $plugin->catalog();
		$history = $plugin->history();

		$items = $catalog->get_items(
			array(
				'page'     => max( 1, $paged ),
				'per_page' => self::PER_PAGE,
			)
		);

		$rows = array();

		foreach ( $items as $item ) {
			if ( $history->latest( $item->id, $item->variation_id, 'anchor' ) ) {
				continue;
			}

			$rows[] = array(
				'item'           => $item,
				'reference_date' => \SidrenaCijena\Compliance\ReferenceDateResolver::resolve( $item )['date'],
			);
		}

		return $rows;
	}

	/**
	 * Number of catalog items without an anchor (best effort).
	 *
	 * @return int
	 */
	protected static function missing_count() {
		$plugin  = Plugin::instance();
		$catalog = $plugin->catalog();
		$history = $plugin->history();
		$count   = 0;

		foreach ( $catalog->iterate( array( 'per_page' => 200 ) ) as $item ) {
			if ( ! $history->latest( $item->id, $item->variation_id, 'anchor' ) ) {
				++$count;
			}
		}

		return $count;
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice display.
		$type = isset( $_GET['sidrena_cijena_notice'] ) ? sanitize_key( wp_unslash( $_GET['sidrena_cijena_notice'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice display.
		$text = isset( $_GET['sidrena_cijena_notice_text'] ) ? sanitize_text_field( wp_unslash( $_GET['sidrena_cijena_notice_text'] ) ) : '';

		if ( '' === $type || '' === $text ) {
			return;
		}

		$class = 'success' === $type ? 'notice-success sidrena-cijena-notice' : 'notice-error sidrena-cijena-notice';

		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}
}
