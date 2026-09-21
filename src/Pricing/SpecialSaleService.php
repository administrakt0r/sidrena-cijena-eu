<?php
/**
 * Special sale service.
 *
 * @package SidrenaCijena\Pricing
 */

namespace SidrenaCijena\Pricing;

use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\SpecialSaleRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates special sale records with their 30-day lowest price snapshot.
 */
class SpecialSaleService {

	/**
	 * Constructor.
	 *
	 * @param SpecialSaleRepository      $sales      Special sale repository.
	 * @param PriceHistoryRepository     $history    Price history repository.
	 * @param LowestPrice30DayCalculator $calculator 30-day calculator.
	 */
	public function __construct( SpecialSaleRepository $sales, PriceHistoryRepository $history, LowestPrice30DayCalculator $calculator ) {
		unset( $sales, $history, $calculator );
	}
}
