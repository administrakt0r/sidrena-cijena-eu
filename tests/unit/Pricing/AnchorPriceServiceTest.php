<?php
/**
 * Anchor price service tests.
 *
 * @package Titanworks\SidrenaCijena\Tests
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Compliance\RuleConfig;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;
use Titanworks\SidrenaCijena\Pricing\AnchorPriceService;

/**
 * In-memory history repository for unit tests.
 */
class FakeHistory extends PriceHistoryRepository {

	/**
	 * Stored rows keyed by ID.
	 *
	 * @var array<int,array>
	 */
	public $rows = array();

	/**
	 * Append a record.
	 *
	 * @param array $data Record fields.
	 * @return int
	 */
	public function insert( array $data ) {
		$id                 = count( $this->rows ) + 1;
		$data['id']         = $id;
		$data['created_at'] = isset( $data['created_at'] ) ? $data['created_at'] : '2026-09-21 08:00:00';

		$this->rows[ $id ] = $data;

		return $id;
	}

	/**
	 * Append a correction record.
	 *
	 * @param array $data Record fields.
	 * @return int
	 * @throws \InvalidArgumentException When required correction fields are missing.
	 */
	public function insert_correction( array $data ) {
		if ( empty( $data['correction_of'] ) ) {
			throw new \InvalidArgumentException( 'A correction requires correction_of.' );
		}

		if ( empty( $data['correction_reason'] ) ) {
			throw new \InvalidArgumentException( 'A correction requires a correction_reason.' );
		}

		return $this->insert( $data );
	}

	/**
	 * Latest record of a type.
	 *
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $price_type   Price type.
	 * @return array|null
	 */
	public function latest( $product_id, $variation_id, $price_type ) {
		$match = null;

		foreach ( $this->rows as $row ) {
			if ( (int) $row['product_id'] !== (int) $product_id ) {
				continue;
			}

			if ( (int) ( isset( $row['variation_id'] ) ? $row['variation_id'] : 0 ) !== (int) $variation_id ) {
				continue;
			}

			if ( $row['price_type'] !== $price_type ) {
				continue;
			}

			$match = $row;
		}

		return $match;
	}
}

/**
 * @covers \Titanworks\SidrenaCijena\Pricing\AnchorPriceService
 */
class AnchorPriceServiceTest extends TestCase {

	/**
	 * History double.
	 *
	 * @var FakeHistory
	 */
	private $history;

	/**
	 * Service under test.
	 *
	 * @var AnchorPriceService
	 */
	private $service;

	/**
	 * Reset options and rebuild the service.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_options'] = array();
		update_option( RuleConfig::OPTION, RuleConfig::defaults(), false );

		$this->history = new FakeHistory();
		$this->service = new AnchorPriceService( $this->history );
	}

	/**
	 * Build a catalog item.
	 *
	 * @param array $overrides Field overrides.
	 * @return CatalogItem
	 */
	private function item( array $overrides = array() ) {
		return new CatalogItem(
			array_merge(
				array(
					'id'         => 10,
					'name'       => 'Kava mljevena 250 g',
					'sku'        => 'KAV-250',
					'currency'   => 'EUR',
					'first_seen' => '2025-01-01',
				),
				$overrides
			)
		);
	}

	/**
	 * Missing anchors yield null and a missing status.
	 *
	 * @return void
	 */
	public function test_missing_anchor() {
		$item = $this->item();

		$this->assertNull( $this->service->get_anchor_price( $item ) );

		$status = $this->service->status( $item );

		$this->assertSame( 'missing', $status['state'] );
		$this->assertSame( RuleConfig::DEFAULT_GENERAL_DATE, $status['reference_date'] );
	}

	/**
	 * A post-reference item uses its recorded first-offer date rather than the general fixed date.
	 *
	 * @return void
	 */
	public function test_first_offer_anchor_date_is_valid_for_new_items() {
		$item = $this->item( array( 'first_seen' => '2026-09-11' ) );
		$this->service->record( $item, '12.50', array( 'reference_date' => '2026-09-11' ) );

		$status = $this->service->status( $item );

		$this->assertSame( 'valid', $status['state'] );
		$this->assertTrue( $status['introduced_after'] );
		$this->assertSame( '2026-09-11', $status['reference_date'] );
	}

	/**
	 * Recording stores an immutable anchor record with resolved defaults.
	 *
	 * @return void
	 */
	public function test_record_stores_anchor_record() {
		$id = $this->service->record( $this->item(), '12,50', array( 'source' => 'manual' ) );

		$this->assertIsInt( $id );
		$this->assertArrayHasKey( $id, $this->history->rows );

		$row = $this->history->rows[ $id ];

		$this->assertSame( 'anchor', $row['price_type'] );
		$this->assertSame( 12.5, (float) $row['price'] );
		$this->assertSame( RuleConfig::DEFAULT_GENERAL_DATE, $row['reference_date'] );
		$this->assertSame( 'manual', $row['source'] );
		$this->assertSame( 'EUR', $row['currency'] );
		$this->assertSame( 'KAV-250', $row['sku'] );
		$this->assertSame( '2025-01-01' !== $row['reference_date'], true );

		$this->assertSame( 12.5, $this->service->get_anchor_price( $this->item() ) );
	}

	/**
	 * Invalid prices are rejected.
	 *
	 * @return void
	 */
	public function test_record_rejects_invalid_price() {
		$error = $this->service->record( $this->item(), 'nije cijena' );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'invalid_anchor_price', $error->get_error_code() );
		$this->assertCount( 0, $this->history->rows );
	}

	/**
	 * Negative prices are rejected.
	 *
	 * @return void
	 */
	public function test_record_rejects_negative_price() {
		$error = $this->service->record( $this->item(), '-5' );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'invalid_anchor_price', $error->get_error_code() );
	}

	/**
	 * An invalid reference date is rejected.
	 *
	 * @return void
	 */
	public function test_record_rejects_invalid_reference_date() {
		$error = $this->service->record( $this->item(), '10', array( 'reference_date' => '10.09.2026.' ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'invalid_reference_date', $error->get_error_code() );
	}

	/**
	 * Changing an anchor requires a correction reason.
	 *
	 * @return void
	 */
	public function test_correction_requires_reason() {
		$this->service->record( $this->item(), '10.00' );

		$error = $this->service->record( $this->item(), '12.00' );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'correction_reason_required', $error->get_error_code() );
		$this->assertCount( 1, $this->history->rows );
	}

	/**
	 * Corrections preserve the original record.
	 *
	 * @return void
	 */
	public function test_correction_preserves_original() {
		$original_id = $this->service->record( $this->item(), '10.00' );

		$correction_id = $this->service->record(
			$this->item(),
			'12.00',
			array( 'correction_reason' => 'Ispravak prema računu dobavljača' )
		);

		$this->assertIsInt( $correction_id );
		$this->assertNotSame( $original_id, $correction_id );
		$this->assertCount( 2, $this->history->rows );

		$this->assertSame( 10.0, (float) $this->history->rows[ $original_id ]['price'] );
		$this->assertSame( $original_id, $this->history->rows[ $correction_id ]['correction_of'] );
		$this->assertSame( 'Ispravak prema računu dobavljača', $this->history->rows[ $correction_id ]['correction_reason'] );
		$this->assertSame( 12.0, $this->service->get_anchor_price( $this->item() ) );
	}

	/**
	 * Items introduced after the reference date are flagged.
	 *
	 * @return void
	 */
	public function test_item_introduced_after_reference_date() {
		$item   = $this->item( array( 'first_seen' => '2026-10-01' ) );
		$status = $this->service->status( $item );

		$this->assertSame( 'missing', $status['state'] );
		$this->assertTrue( $status['introduced_after'] );
		$this->assertTrue( $this->service->needs_verification( $item ) );
	}

	/**
	 * Stored anchors with a different reference date need verification.
	 *
	 * @return void
	 */
	public function test_stored_anchor_with_other_reference_date_needs_verification() {
		$this->service->record( $this->item(), '10.00', array( 'reference_date' => '2025-05-02' ) );

		$status = $this->service->status( $this->item() );

		$this->assertSame( 'needs_verification', $status['state'] );
		$this->assertTrue( $this->service->needs_verification( $this->item() ) );
	}

	/**
	 * Valid anchors report a valid status.
	 *
	 * @return void
	 */
	public function test_valid_anchor_status() {
		$this->service->record( $this->item(), '10.00' );

		$status = $this->service->status( $this->item() );

		$this->assertSame( 'valid', $status['state'] );
		$this->assertFalse( $this->service->needs_verification( $this->item() ) );
	}

	/**
	 * Parent and variation anchors are isolated.
	 *
	 * @return void
	 */
	public function test_parent_and_variation_anchors_are_isolated() {
		$this->service->record( $this->item(), '10.00' );
		$this->service->record( $this->item( array( 'variation_id' => 55 ) ), '12.00' );

		$this->assertSame( 10.0, $this->service->get_anchor_price( $this->item() ) );
		$this->assertSame( 12.0, $this->service->get_anchor_price( $this->item( array( 'variation_id' => 55 ) ) ) );
	}
}
