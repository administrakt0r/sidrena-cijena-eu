<?php
/**
 * Price-list generation service.
 *
 * @package SidrenaCijena\Export
 */

namespace SidrenaCijena\Export;

use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\PublicationRepository;
use SidrenaCijena\Database\SpecialSaleRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and publishes the daily price lists per sales location and format.
 */
class GenerationService {

	/**
	 * Constructor.
	 *
	 * @param CatalogProvider        $catalog      Catalog provider.
	 * @param LocationRepository     $locations    Location repository.
	 * @param PublicationRepository  $publications Publication repository.
	 * @param PriceHistoryRepository $history      Price history repository.
	 * @param SpecialSaleRepository  $sales        Special sale repository.
	 */
	public function __construct( CatalogProvider $catalog, LocationRepository $locations, PublicationRepository $publications, PriceHistoryRepository $history, SpecialSaleRepository $sales ) {
		unset( $catalog, $locations, $publications, $history, $sales );
	}
}
