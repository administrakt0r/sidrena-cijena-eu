<?php
/**
 * Publication gate regressions.
 *
 * @package SidrenaCijena\Tests\Unit\Export
 */

namespace SidrenaCijena\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Export\GenerationService;

/**
 * @covers \SidrenaCijena\Export\GenerationService::publication_blockers
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
			array( 'code' => 'missing_sale_type' ),
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
}
