<?php
/**
 * Publication gate regressions.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Export
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Export\GenerationService;

/**
 * @covers \Titanworks\SidrenaCijena\Export\GenerationService::publication_blockers
 */
class PublicationGateTest extends TestCase {
	/**
	 * Optional catalog metadata must not prevent a priced, anchored list.
	 *
	 * @return void
	 */
	public function test_optional_findings_do_not_block_publication() {
		$findings = array(
			array( 'code' => 'missing_sku' ),
			array( 'code' => 'missing_barcode' ),
		);

		$this->assertSame( array(), GenerationService::publication_blockers( $findings ) );
	}

	/**
	 * Retail and dated anchor evidence remain mandatory.
	 *
	 * @return void
	 */
	public function test_missing_prices_and_invalid_anchor_date_still_block() {
		$findings = array(
			array( 'code' => 'missing_sku' ),
			array( 'code' => 'missing_anchor_price' ),
			array( 'code' => 'invalid_retail_price' ),
			array( 'code' => 'invalid_anchor_date' ),
		);

		$this->assertSame( array_slice( $findings, 1 ), GenerationService::publication_blockers( $findings ) );
	}

	/**
	 * An active offer without a usable reference cannot be published.
	 *
	 * @return void
	 */
	public function test_discounted_offer_reference_findings_block_publication() {
		$findings = array(
			array( 'code' => 'missing_sale_type' ),
			array( 'code' => 'missing_30_day_price' ),
			array( 'code' => 'sale_price_not_below_reference' ),
			array( 'code' => 'missing_sku' ),
		);

		$this->assertSame( array_slice( $findings, 0, 3 ), GenerationService::publication_blockers( $findings ) );
	}
}
