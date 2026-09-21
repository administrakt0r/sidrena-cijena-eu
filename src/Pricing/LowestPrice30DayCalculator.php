<?php
/**
 * 30-day lowest price calculator.
 *
 * @package SidrenaCijena\Pricing
 */

namespace SidrenaCijena\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Pure calculation of the lowest price in the 30 calendar days immediately
 * before a special sale start date (start date excluded).
 */
class LowestPrice30DayCalculator {

	/**
	 * Calculate the lowest price from a set of history rows.
	 *
	 * @param array<mixed> $history History rows ordered by validity.
	 * @param string       $sale_start Special sale start date (Y-m-d).
	 * @return float|null
	 */
	public function calculate( array $history, $sale_start ) {
		unset( $history, $sale_start );

		return null;
	}
}
