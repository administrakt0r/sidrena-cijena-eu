<?php
/**
 * Administrator explanation for 30-day reference price calculations.
 *
 * @package SidrenaCijena\Pricing
 */

namespace SidrenaCijena\Pricing;

use SidrenaCijena\Admin\AdminMenu;
use SidrenaCijena\Admin\PriceHistoryPage;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * Builds structured, audit-ready explanations of 30-day lowest price calculations.
 */
class LowestPriceExplainer {

	/**
	 * Price history repository.
	 *
	 * @var PriceHistoryRepository|null
	 */
	protected $history;

	/**
	 * 30-day calculator.
	 *
	 * @var LowestPrice30DayCalculator
	 */
	protected $calculator;

	/**
	 * Constructor.
	 *
	 * @param PriceHistoryRepository|null     $history    History repository.
	 * @param LowestPrice30DayCalculator|null $calculator 30-day calculator.
	 */
	public function __construct( $history = null, $calculator = null ) {
		$this->history    = $history instanceof PriceHistoryRepository ? $history : null;
		$this->calculator = $calculator instanceof LowestPrice30DayCalculator ? $calculator : new LowestPrice30DayCalculator();
	}

	/**
	 * Build an audit explanation for an item and special sale date/record.
	 *
	 * @param CatalogItem                $item         Catalog item.
	 * @param array<string,mixed>|string $sale_or_date Sale record or start date (`Y-m-d`).
	 * @param array<mixed>|null          $history      Optional explicit history rows.
	 * @return array<string,mixed>
	 */
	public function explain( CatalogItem $item, $sale_or_date, $history = null ) {
		if ( is_array( $sale_or_date ) ) {
			$sale_start = isset( $sale_or_date['start_date'] ) ? (string) $sale_or_date['start_date'] : '';
		} else {
			$sale_start = (string) $sale_or_date;
		}

		if ( '' === $sale_start || '0000-00-00' === $sale_start ) {
			$sale_start = Dates::today();
		} else {
			$sale_start = substr( $sale_start, 0, 10 );
		}

		if ( ! Dates::is_valid_date( $sale_start ) ) {
			$sale_start = Dates::today();
		}

		$window = $this->calculator->window( $sale_start );

		if ( null === $history ) {
			if ( $this->history ) {
				$window_start = $window ? $window['window_start'] : '';
				$history      = $this->history->timeline( (int) $item->id, (int) $item->variation_id, $sale_start, $window_start );
			} else {
				$history = array();
			}
		}

		$audit              = $this->calculator->audit_window( $history, $sale_start );
		$lowest_price       = $audit['price'];
		$source_records     = $audit['source_records'];
		$determining_record = $audit['determining_record'];
		$rows               = $audit['rows'];
		$has_history        = ( $rows > 0 );
		$is_short           = false;
		$coverage_days      = 0;
		$coverage_start     = null;
		$conditions         = array();
		$warnings           = array();

		if ( ! $has_history ) {
			$conditions[] = 'no_history';
			$window_desc  = $window ? sprintf( '%1$s to %2$s', $window['window_start'], $window['window_end'] ) : '';
			$warnings[]   = sprintf(
				/* translators: 1: calculation window description */
				__( 'No price history exists in or before the 30-day calculation window (%1$s). A reference price cannot be calculated without historical evidence.', 'titanworks-sidrena-bazna-cijena' ),
				$window_desc
			);
		} else {
			if ( $window ) {
				$earliest_from = null;

				foreach ( $source_records as $rec ) {
					if ( null === $earliest_from || $rec['effective_from'] < $earliest_from ) {
						$earliest_from = $rec['effective_from'];
					}
				}

				if ( null !== $earliest_from && $earliest_from > $window['window_start'] ) {
					$is_short       = true;
					$coverage_start = $earliest_from;
					$diff           = Dates::diff_days( $earliest_from, $window['window_end'] );
					$coverage_days  = null !== $diff ? $diff + 1 : 0;
					$conditions[]   = 'short_history';
					$warnings[]     = sprintf(
						/* translators: 1: number of coverage days, 2: earliest date in history */
						__( 'Price history is shorter than 30 days (covers %1$d days starting from %2$s). The reference price is calculated from the available history.', 'titanworks-sidrena-bazna-cijena' ),
						$coverage_days,
						$earliest_from
					);
				} else {
					$coverage_days  = 30;
					$coverage_start = $window['window_start'];
				}

				$ended_sales = array();
				foreach ( $source_records as $r ) {
					if ( 'sale' === $r['price_type'] && ! empty( $r['effective_to'] ) && $r['effective_to'] <= $window['window_end'] ) {
						$ended_sales[] = $r;
					}
				}

				if ( ! empty( $ended_sales ) ) {
					$conditions[] = 'recent_sale_ended';
					$last_ended   = end( $ended_sales );
					$warnings[]   = sprintf(
						/* translators: 1: end date, 2: sale price */
						__( 'A prior promotional sale ended recently within the 30-day window (ended %1$s, price %2$s) and was included in determining the 30-day lowest price.', 'titanworks-sidrena-bazna-cijena' ),
						$last_ended['effective_to'],
						Currency::format( (float) $last_ended['price'], $item->currency )
					);
				}
			}

			if ( $rows > 1 ) {
				$sale_records = array();
				$prices       = array();

				foreach ( $source_records as $r ) {
					if ( 'sale' === $r['price_type'] ) {
						$sale_records[] = $r;
					}

					$p = (string) $r['price'];
					if ( ! in_array( $p, $prices, true ) ) {
						$prices[] = $p;
					}
				}

				if ( count( $sale_records ) >= 2 || count( $prices ) >= 2 ) {
					$conditions[] = 'successive_reductions';
					$warnings[]   = __( 'Multiple successive reductions detected. All applicable prices in the 30-day window were evaluated.', 'titanworks-sidrena-bazna-cijena' );
				}
			}

			if ( $determining_record ) {
				$src = (string) $determining_record['source'];

				if ( in_array( $src, array( 'import', 'seed' ), true ) ) {
					$conditions[] = 'imported_baseline';
					$warnings[]   = sprintf(
						/* translators: 1: record ID, 2: source label */
						__( 'The reference price is determined by an imported or seeded baseline record (#%1$d, source: %2$s).', 'titanworks-sidrena-bazna-cijena' ),
						(int) $determining_record['id'],
						Labels::source( $src )
					);
				} elseif ( 'manual' === $src && in_array( $determining_record['price_type'], array( 'anchor', 'historical' ), true ) ) {
					$conditions[] = 'manual_baseline';
					$warnings[]   = sprintf(
						/* translators: 1: record ID */
						__( 'The reference price is determined by a manual baseline record (#%1$d).', 'titanworks-sidrena-bazna-cijena' ),
						(int) $determining_record['id']
					);
				}
			}
		}

		if ( empty( $conditions ) ) {
			$conditions[] = 'sufficient_history';
		}

		$summary = $has_history && null !== $lowest_price
			? sprintf(
				/* translators: 1: lowest price, 2: window start date, 3: window end date */
				__( 'The lowest applicable price in the 30-day window (%2$s to %3$s) is %1$s.', 'titanworks-sidrena-bazna-cijena' ),
				Currency::format( (float) $lowest_price, $item->currency, array( 'display' => 'symbol' ) ),
				$window ? $window['window_start'] : '',
				$window ? $window['window_end'] : ''
			)
			: __( 'No applicable lowest price could be calculated for this 30-day window.', 'titanworks-sidrena-bazna-cijena' );

		$stored_lowest_price = is_array( $sale_or_date ) && isset( $sale_or_date['lowest_30_day_price'] ) && '' !== $sale_or_date['lowest_30_day_price']
			? (float) $sale_or_date['lowest_30_day_price']
			: null;

		return array(
			'product_id'          => (int) $item->id,
			'variation_id'        => (int) $item->variation_id,
			'parent_id'           => (int) $item->parent_id,
			'sku'                 => (string) $item->sku,
			'item_name'           => (string) $item->name,
			'is_variation'        => (bool) $item->is_variation(),
			'identity_label'      => $this->format_identity( $item ),
			'sale_start'          => $sale_start,
			'window_start'        => $window ? $window['window_start'] : null,
			'window_end'          => $window ? $window['window_end'] : null,
			'lowest_price'        => $lowest_price,
			'stored_lowest_price' => $stored_lowest_price,
			'currency'            => $item->currency,
			'has_history'         => $has_history,
			'is_short'            => $is_short,
			'coverage_days'       => $coverage_days,
			'coverage_start'      => $coverage_start,
			'conditions'          => $conditions,
			'warnings'            => $warnings,
			'rows'                => $rows,
			'source_records'      => $source_records,
			'determining_record'  => $determining_record,
			'summary'             => $summary,
		);
	}

	/**
	 * Human-readable product and variation identity label.
	 *
	 * @param CatalogItem $item Catalog item.
	 * @return string
	 */
	public function format_identity( CatalogItem $item ) {
		$sku = '' !== trim( (string) $item->sku ) ? (string) $item->sku : '—';

		if ( $item->is_variation() ) {
			return sprintf(
				/* translators: 1: variation ID, 2: parent product ID, 3: SKU, 4: variation name/attributes */
				__( 'Variation #%1$d of Product #%2$d (SKU: %3$s) — %4$s', 'titanworks-sidrena-bazna-cijena' ),
				(int) $item->variation_id,
				(int) $item->id,
				$sku,
				(string) $item->name
			);
		}

		if ( 'woocommerce' === $item->source ) {
			return sprintf(
				/* translators: 1: product ID, 2: SKU, 3: product name */
				__( 'Product #%1$d (SKU: %2$s) — %3$s', 'titanworks-sidrena-bazna-cijena' ),
				(int) $item->id,
				$sku,
				(string) $item->name
			);
		}

		return sprintf(
			/* translators: 1: item ID, 2: SKU, 3: item name */
			__( 'Standalone item #%1$d (SKU: %2$s) — %3$s', 'titanworks-sidrena-bazna-cijena' ),
			(int) $item->id,
			$sku,
			(string) $item->name
		);
	}

	/**
	 * Render HTML explanation markup for administrators.
	 *
	 * @param array<string,mixed> $explanation Structured explanation array.
	 * @return string
	 */
	public function render_html( array $explanation ) {
		ob_start();
		?>
		<div class="sidrena-cijena-explanation">
			<p class="sidrena-cijena-identity">
				<strong><?php echo esc_html__( 'Item identity:', 'titanworks-sidrena-bazna-cijena' ); ?></strong>
				<code><?php echo esc_html( (string) $explanation['identity_label'] ); ?></code>
			</p>

			<div class="sidrena-cijena-summary-box">
				<p>
					<strong><?php echo esc_html__( 'Calculation window (30 days):', 'titanworks-sidrena-bazna-cijena' ); ?></strong>
					<?php echo esc_html( sprintf( '%1$s to %2$s', (string) $explanation['window_start'], (string) $explanation['window_end'] ) ); ?>
					<?php /* translators: %s: sale start date */ ?>
					<span class="description">(<?php echo esc_html( sprintf( __( 'for sale starting %s', 'titanworks-sidrena-bazna-cijena' ), (string) $explanation['sale_start'] ) ); ?>)</span>
				</p>
				<p>
					<strong><?php echo esc_html__( 'Selected 30-day lowest price:', 'titanworks-sidrena-bazna-cijena' ); ?></strong>
					<?php if ( null === $explanation['lowest_price'] ) : ?>
						<em class="sidrena-cijena-not-calculated"><?php echo esc_html__( 'Not calculated (no applicable price in window)', 'titanworks-sidrena-bazna-cijena' ); ?></em>
					<?php else : ?>
						<span class="sidrena-cijena-lowest-val">
							<?php echo esc_html( Currency::format( (float) $explanation['lowest_price'], (string) $explanation['currency'], array( 'display' => 'symbol' ) ) ); ?>
						</span>
						<?php if ( ! empty( $explanation['determining_record'] ) ) : ?>
							<span class="description">
								<?php
								$det = $explanation['determining_record'];
								printf(
									'(%s)',
									esc_html(
										sprintf(
											/* translators: 1: record ID, 2: price type, 3: effective date */
											__( 'determined by record #%1$d, %2$s from %3$s', 'titanworks-sidrena-bazna-cijena' ),
											(int) $det['id'],
											Labels::price_type( (string) $det['price_type'] ),
											(string) $det['effective_from']
										)
									)
								);
								?>
							</span>
						<?php endif; ?>
					<?php endif; ?>
				</p>
			</div>

			<?php foreach ( (array) $explanation['warnings'] as $warning ) : ?>
				<div class="notice notice-warning inline">
					<p><?php echo esc_html( (string) $warning ); ?></p>
				</div>
			<?php endforeach; ?>

			<?php if ( in_array( 'sufficient_history', (array) $explanation['conditions'], true ) ) : ?>
				<p class="description sidrena-cijena-sufficient-history">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php echo esc_html__( 'Full 30-day price history is available.', 'titanworks-sidrena-bazna-cijena' ); ?>
				</p>
			<?php endif; ?>

			<details class="sidrena-cijena-source-records">
				<summary>
					<?php
					$count = is_array( $explanation['source_records'] ) ? count( $explanation['source_records'] ) : 0;
					/* translators: %d: count of records */
					echo esc_html( sprintf( __( 'View source records (%d found in window)', 'titanworks-sidrena-bazna-cijena' ), $count ) );
					?>
				</summary>
				<div class="sidrena-cijena-records-wrap">
					<?php if ( empty( $explanation['source_records'] ) ) : ?>
						<p class="description"><?php echo esc_html__( 'No price history records overlapped this calculation window.', 'titanworks-sidrena-bazna-cijena' ); ?></p>
					<?php else : ?>
						<table class="widefat striped sidrena-source-records-table">
							<caption class="screen-reader-text"><?php echo esc_html__( 'Price history records', 'titanworks-sidrena-bazna-cijena' ); ?></caption>
							<thead>
								<tr>
									<th scope="col"><?php echo esc_html__( 'ID', 'titanworks-sidrena-bazna-cijena' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Price', 'titanworks-sidrena-bazna-cijena' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Type', 'titanworks-sidrena-bazna-cijena' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Source', 'titanworks-sidrena-bazna-cijena' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Effective period', 'titanworks-sidrena-bazna-cijena' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Role', 'titanworks-sidrena-bazna-cijena' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( (array) $explanation['source_records'] as $record ) : ?>
									<tr<?php echo ! empty( $record['is_determining'] ) ? ' class="sidrena-determining-record"' : ''; ?>>
										<td>#<?php echo esc_html( (string) $record['id'] ); ?></td>
										<td><?php echo esc_html( Currency::format( (float) $record['price'], (string) $explanation['currency'] ) ); ?></td>
										<td><?php echo esc_html( Labels::price_type( (string) $record['price_type'] ) ); ?></td>
										<td><?php echo esc_html( Labels::source( (string) $record['source'] ) ); ?></td>
										<td>
											<?php
											$eff = (string) $record['effective_from'];
											if ( ! empty( $record['effective_to'] ) ) {
												$eff .= ' to ' . (string) $record['effective_to'];
											} else {
												$eff .= ' (' . __( 'open', 'titanworks-sidrena-bazna-cijena' ) . ')';
											}
											echo esc_html( $eff );
											?>
										</td>
										<td>
											<?php if ( ! empty( $record['is_determining'] ) ) : ?>
												<span class="badge sidrena-explainer-badge">
													<?php echo esc_html__( 'Determining lowest price', 'titanworks-sidrena-bazna-cijena' ); ?>
												</span>
											<?php else : ?>
												<span class="description"><?php echo esc_html__( 'Informed calculation', 'titanworks-sidrena-bazna-cijena' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
					<p class="sidrena-cijena-history-link-wrap">
						<?php
						$history_link = AdminMenu::url(
							PriceHistoryPage::SLUG,
							array(
								'product_id'   => (int) $explanation['product_id'],
								'variation_id' => (int) $explanation['variation_id'],
							)
						);
						?>
						<a href="<?php echo esc_url( $history_link ); ?>">
							<?php echo esc_html__( 'Open full price history for this item &rarr;', 'titanworks-sidrena-bazna-cijena' ); ?>
						</a>
					</p>
				</div>
			</details>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
