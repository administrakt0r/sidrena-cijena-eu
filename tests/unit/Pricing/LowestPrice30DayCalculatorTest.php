<?php
/**
 * 30-day lowest price calculator tests.
 *
 * @package SidrenaCijena\Tests\Unit\Pricing
 */

namespace SidrenaCijena\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Pricing\LowestPrice30DayCalculator;

/**
 * Covers the exact 30-day window, its boundaries and price applicability.
 */
class LowestPrice30DayCalculatorTest extends TestCase {

	/**
	 * Calculator under test.
	 *
	 * @var LowestPrice30DayCalculator
	 */
	private $calculator;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->calculator = new LowestPrice30DayCalculator();
	}

	/**
	 * Build a history row.
	 *
	 * @param float       $price Price.
	 * @param string|null $from  Valid from.
	 * @param string|null $to    Valid to.
	 * @param string      $type  Price type.
	 * @param array       $extra Extra fields.
	 * @return array
	 */
	private function row( $price, $from, $to = null, $type = 'regular', array $extra = array() ) {
		return array_merge(
			array(
				'price'      => $price,
				'valid_from' => $from,
				'valid_to'   => $to,
				'price_type' => $type,
				'created_at' => '2026-08-01 00:00:00',
			),
			$extra
		);
	}

	/**
	 * The window is the 30 calendar days before the start date.
	 *
	 * @return void
	 */
	public function test_window_is_exactly_thirty_calendar_days() {
		$window = $this->calculator->window( '2026-10-01' );

		$this->assertSame( '2026-09-01', $window['window_start'] );
		$this->assertSame( '2026-09-30', $window['window_end'] );
	}

	/**
	 * The sale start date itself is excluded from the window.
	 *
	 * @return void
	 */
	public function test_sale_start_date_is_excluded() {
		$history = array(
			$this->row( 9.99, '2026-10-01' ),
			$this->row( 12.00, '2026-09-15' ),
		);

		$this->assertSame( 12.0, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * The day exactly 30 days before the start date is included.
	 *
	 * @return void
	 */
	public function test_day_exactly_thirty_days_before_is_included() {
		$history = array(
			$this->row( 5.00, '2026-09-01' ),
			$this->row( 4.00, '2026-08-01', '2026-08-31' ),
		);

		$this->assertSame( 5.0, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * A row that ended before the window does not apply.
	 *
	 * @return void
	 */
	public function test_row_ending_before_window_is_excluded() {
		$history = array(
			$this->row( 4.00, '2026-08-01', '2026-08-31' ),
			$this->row( 6.00, '2026-09-10' ),
		);

		$this->assertSame( 6.0, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * Open-ended rows apply to the whole window.
	 *
	 * @return void
	 */
	public function test_open_ended_row_is_included() {
		$history = array( $this->row( 7.25, '2026-01-01', null ) );

		$this->assertSame( 7.25, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * A null-price closure observation prevents an ended sale from leaking into later windows.
	 *
	 * @return void
	 */
	public function test_closed_sale_is_not_carried_into_a_later_window() {
		$history = array(
			$this->row( 5.00, '2026-08-10', null, 'sale' ),
			$this->row( null, '2026-08-20', null, 'sale' ),
			$this->row( 10.00, '2026-08-10', null, 'regular' ),
		);

		$this->assertSame( 10.0, $this->calculator->calculate( $history, '2026-09-20' ) );
	}

	/**
	 * Every price observed on a calendar day remains eligible, including successive reductions.
	 *
	 * @return void
	 */
	public function test_same_day_sale_reductions_are_all_considered() {
		$history = array(
			$this->row( 12.00, '2026-09-10', null, 'regular' ),
			$this->row( 9.00, '2026-09-10 09:00:00', null, 'sale' ),
			$this->row( 7.00, '2026-09-10 13:00:00', null, 'sale' ),
			$this->row( 5.00, '2026-09-10 17:00:00', null, 'sale' ),
		);

		$this->assertSame( 5.0, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * An earlier same-day price remains eligible when the window begins on the transition day.
	 *
	 * @return void
	 */
	public function test_price_applied_before_an_intraday_increase_counts_on_that_day() {
		$history = array(
			$this->row( 5.00, '2026-09-01 08:00:00', null, 'sale' ),
			$this->row( 9.00, '2026-09-01 13:00:00', null, 'sale' ),
		);

		$this->assertSame( 5.0, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * Windows crossing a daylight-saving transition keep 30 calendar days.
	 *
	 * @return void
	 */
	public function test_window_spans_dst_change_deterministically() {
		$window = $this->calculator->window( '2026-10-30' );

		$this->assertSame( '2026-09-30', $window['window_start'] );
		$this->assertSame( '2026-10-29', $window['window_end'] );

		$history = array( $this->row( 7.50, '2026-10-20' ) );

		$this->assertSame( 7.5, $this->calculator->calculate( $history, '2026-10-30' ) );
	}

	/**
	 * Consecutive and overlapping promotions do not reset the window.
	 *
	 * @return void
	 */
	public function test_consecutive_and_overlapping_sales() {
		$history = array(
			$this->row( 9.00, '2026-08-01' ),
			$this->row( 8.00, '2026-09-05', '2026-09-10', 'sale' ),
			$this->row( 7.00, '2026-09-08', '2026-09-12', 'sale' ),
		);

		$this->assertSame( 7.0, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * Computed 30-day snapshots never reference themselves.
	 *
	 * @return void
	 */
	public function test_lowest_30_day_rows_are_ignored() {
		$history = array(
			$this->row( 1.00, '2026-09-10', null, 'lowest_30_day' ),
			$this->row( 10.00, '2026-09-10' ),
		);

		$this->assertSame( 10.0, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * Empty history yields no price.
	 *
	 * @return void
	 */
	public function test_empty_history_returns_null() {
		$result = $this->calculator->calculate_window( array(), '2026-10-01' );

		$this->assertNull( $result['price'] );
		$this->assertSame( 0, $result['rows'] );
	}

	/**
	 * An invalid start date yields no window and no price.
	 *
	 * @return void
	 */
	public function test_invalid_start_date_returns_null() {
		$result = $this->calculator->calculate_window( array( $this->row( 5.00, '2026-09-10' ) ), 'not-a-date' );

		$this->assertNull( $result['price'] );
		$this->assertNull( $result['window_start'] );
		$this->assertNull( $result['window_end'] );
	}

	/**
	 * Availability does not change the calculated price.
	 *
	 * @return void
	 */
	public function test_availability_does_not_affect_calculation() {
		$history = array(
			$this->row( 6.00, '2026-09-10', null, 'regular', array( 'availability' => 'unavailable' ) ),
			$this->row( 8.00, '2026-09-12' ),
		);

		$this->assertSame( 6.0, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * Rows are evaluated independently of their variation ID.
	 *
	 * @return void
	 */
	public function test_variation_rows_are_evaluated() {
		$history = array(
			$this->row( 12.00, '2026-09-10', null, 'regular', array( 'variation_id' => 55 ) ),
			$this->row( 11.50, '2026-09-11', null, 'sale', array( 'variation_id' => 55 ) ),
		);

		$this->assertSame( 11.5, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * Rows without a validity start fall back to their creation date.
	 *
	 * @return void
	 */
	public function test_created_at_used_when_valid_from_missing() {
		$history = array(
			array(
				'price'      => 3.33,
				'valid_from' => null,
				'valid_to'   => null,
				'price_type' => 'historical',
				'created_at' => '2026-09-20 08:00:00',
			),
		);

		$this->assertSame( 3.33, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * Rows created outside the window are ignored.
	 *
	 * @return void
	 */
	public function test_created_at_outside_window_is_ignored() {
		$history = array(
			array(
				'price'      => 1.00,
				'valid_from' => null,
				'valid_to'   => null,
				'price_type' => 'historical',
				'created_at' => '2026-06-01 08:00:00',
			),
		);

		$this->assertNull( $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * An open-ended observation ends when the next observation of the same
	 * price type starts; a long superseded cheap price must not understate
	 * the lowest price of a later window.
	 *
	 * @return void
	 */
	public function test_open_row_is_bounded_by_next_row_of_same_type() {
		$history = array(
			$this->row( 5.00, '2026-02-01' ),
			$this->row( 12.00, '2026-09-01' ),
		);

		$this->assertSame( 12.0, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * A superseded observation that ended before the window is excluded even
	 * when it was the cheapest price ever recorded.
	 *
	 * @return void
	 */
	public function test_superseded_row_ending_before_window_is_excluded() {
		$history = array(
			$this->row( 4.00, '2026-06-01' ),
			$this->row( 9.00, '2026-08-15' ),
		);

		$this->assertSame( 9.0, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * The latest observation of a type stays open-ended.
	 *
	 * @return void
	 */
	public function test_latest_row_of_type_stays_open_ended() {
		$history = array(
			$this->row( 7.25, '2026-01-01' ),
		);

		$this->assertSame( 7.25, $this->calculator->calculate( $history, '2026-10-01' ) );
	}

	/**
	 * A UTC creation timestamp is converted to the site-local day before it is
	 * compared with the window.
	 *
	 * @return void
	 */
	public function test_created_at_is_converted_from_utc_to_site_time() {
		$inside = array(
			array(
				'price'      => 8.00,
				'valid_from' => null,
				'valid_to'   => null,
				'price_type' => 'historical',
				'created_at' => '2026-08-31 22:30:00',
			),
		);

		$this->assertSame( 8.0, $this->calculator->calculate( $inside, '2026-10-01' ) );

		$outside = array(
			array(
				'price'      => 8.00,
				'valid_from' => null,
				'valid_to'   => null,
				'price_type' => 'historical',
				'created_at' => '2026-09-30 23:30:00',
			),
		);

		$this->assertNull( $this->calculator->calculate( $outside, '2026-10-01' ) );
	}
}
