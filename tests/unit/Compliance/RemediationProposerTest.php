<?php
/**
 * RemediationProposer unit tests.
 *
 * @package SidrenaCijena\Tests\Unit\Compliance
 */

namespace SidrenaCijena\Tests\Unit\Compliance;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Compliance\Finding;
use SidrenaCijena\Compliance\RemediationProposer;

/**
 * @covers \SidrenaCijena\Compliance\RemediationProposer
 */
class RemediationProposerTest extends TestCase {

	/**
	 * Missing anchor price proposes entering anchor with designated reference date.
	 *
	 * @return void
	 */
	public function test_propose_missing_anchor_price() {
		$proposal = RemediationProposer::propose( 'missing_anchor_price', array( 'reference_date' => '2026-09-10' ) );
		$this->assertStringContainsString( '2026-09-10', $proposal );
		$this->assertStringContainsString( 'Sidrena cijena', $proposal );
	}

	/**
	 * Promotional item missing an anchor price proposes fulfilling triple-price cross-compliance.
	 *
	 * @return void
	 */
	public function test_propose_missing_anchor_price_promotional() {
		$proposal = RemediationProposer::propose(
			'missing_anchor_price',
			array(
				'reference_date' => '2026-09-10',
				'is_promotional' => true,
			)
		);
		$this->assertStringContainsString( '2026-09-10', $proposal );
		$this->assertStringContainsString( 'three distinct prices', $proposal );
	}

	/**
	 * Invalid anchor date proposes updating to statutory reference date.
	 *
	 * @return void
	 */
	public function test_propose_invalid_anchor_date() {
		$proposal = RemediationProposer::propose( 'invalid_anchor_date' );
		$this->assertStringContainsString( '2026-09-10', $proposal );
		$this->assertStringContainsString( '2025-05-02', $proposal );
	}

	/**
	 * Missing sale type proposes selecting a statutory sale type.
	 *
	 * @return void
	 */
	public function test_propose_missing_sale_type() {
		$proposal = RemediationProposer::propose( 'missing_sale_type' );
		$this->assertStringContainsString( 'akcija', $proposal );
		$this->assertStringContainsString( 'sniženje', $proposal );
	}

	/**
	 * Missing 30-day price proposes calculating prior 30-day price.
	 *
	 * @return void
	 */
	public function test_propose_missing_30_day_price() {
		$proposal = RemediationProposer::propose( 'missing_30_day_price' );
		$this->assertStringContainsString( '30', $proposal );
	}

	/**
	 * Sale price not below reference proposes lowering sale price.
	 *
	 * @return void
	 */
	public function test_propose_sale_price_not_below_reference() {
		$proposal = RemediationProposer::propose( 'sale_price_not_below_reference' );
		$this->assertStringContainsString( 'lower', strtolower( $proposal ) );
	}

	/**
	 * Missing store identifier proposes setting identifier in settings.
	 *
	 * @return void
	 */
	public function test_propose_missing_store_identifier() {
		$proposal = RemediationProposer::propose( 'missing_store_identifier' );
		$this->assertStringContainsString( 'P1', $proposal );
	}

	/**
	 * Missing location address proposes entering address and city.
	 *
	 * @return void
	 */
	public function test_propose_missing_location_address() {
		$proposal = RemediationProposer::propose( 'missing_location_address' );
		$this->assertStringContainsString( 'street address', strtolower( $proposal ) );
	}

	/**
	 * Catalog identifiers propose assigning code, barcode, or name.
	 *
	 * @return void
	 */
	public function test_propose_catalog_identifiers() {
		$this->assertStringContainsString( 'SKU', RemediationProposer::propose( 'missing_sku' ) );
		$this->assertStringContainsString( 'barcode', strtolower( RemediationProposer::propose( 'missing_barcode' ) ) );
		$this->assertStringContainsString( 'title', strtolower( RemediationProposer::propose( 'missing_name' ) ) );
	}

	/**
	 * Unknown code returns empty string without error.
	 *
	 * @return void
	 */
	public function test_unknown_code_returns_empty() {
		$this->assertSame( '', RemediationProposer::propose( 'completely_unknown_code' ) );
	}

	/**
	 * Finding instances automatically carry proposals for known codes.
	 *
	 * @return void
	 */
	public function test_finding_carries_proposal() {
		$finding = Finding::error( 'missing_anchor_price', 'Missing anchor', array( 'context' => array( 'reference_date' => '2026-09-10' ) ) );
		$this->assertNotEmpty( $finding->proposal );
		$this->assertStringContainsString( '2026-09-10', $finding->proposal );

		$array = $finding->to_array();
		$this->assertArrayHasKey( 'proposal', $array );
		$this->assertSame( $finding->proposal, $array['proposal'] );
	}
}
