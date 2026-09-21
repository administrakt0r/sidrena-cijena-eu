<?php
/**
 * Anchor record integration tests.
 *
 * @package SidrenaCijena\Tests
 */

use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Pricing\AnchorPriceService;

/**
 * Exercises anchor recording against the real database.
 */
class Test_Anchor_Records extends WP_UnitTestCase {

	/**
	 * History repository.
	 *
	 * @var PriceHistoryRepository
	 */
	private $history;

	/**
	 * Prepare the standalone catalog.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		StandalonePostType::register();
		Plugin::instance()->set_catalog( new StandaloneCatalogProvider() );

		$this->history = new PriceHistoryRepository();
	}

	/**
	 * Create a standalone catalog item.
	 *
	 * @param array $meta Meta values.
	 * @return int
	 */
	private function create_item( array $meta = array() ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_title'  => 'Kava mljevena 250 g',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, '_sidrena_sku', 'KAV-250' );
		update_post_meta( $post_id, '_sidrena_regular_price', '6.99' );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		wp_set_object_terms( $post_id, 'product', StandalonePostType::TAX_TYPE );

		return $post_id;
	}

	/**
	 * Anchors are recorded and corrections preserve the original row.
	 *
	 * @return void
	 */
	public function test_record_and_correct_anchor() {
		$post_id = $this->create_item();
		$item    = Plugin::instance()->catalog()->get_item( $post_id );

		$this->assertNotNull( $item );
		$this->assertSame( 'KAV-250', $item->sku );

		$service = new AnchorPriceService( $this->history );

		$record_id = $service->record( $item, '7.49', array( 'source' => 'manual' ) );

		$this->assertIsInt( $record_id );

		$record = $this->history->get( $record_id );

		$this->assertSame( 'anchor', $record['price_type'] );
		$this->assertSame( '7.490000', number_format( (float) $record['price'], 6, '.', '' ) );
		$this->assertSame( '2026-09-10', $record['reference_date'] );

		$error = $service->record( $item, '8.49' );

		$this->assertWPError( $error );
		$this->assertSame( 'correction_reason_required', $error->get_error_code() );

		$correction_id = $service->record( $item, '8.49', array( 'correction_reason' => 'Ispravak prema računu' ) );

		$this->assertIsInt( $correction_id );
		$this->assertSame( 2, $this->history->count( array( 'product_id' => $post_id ) ) );

		$original = $this->history->get( $record_id );

		$this->assertSame( '7.490000', number_format( (float) $original['price'], 6, '.', '' ) );

		$correction = $this->history->get( $correction_id );

		$this->assertSame( $record_id, (int) $correction['correction_of'] );
		$this->assertSame( 'Ispravak prema računu', $correction['correction_reason'] );
	}

	/**
	 * History survives item deletion.
	 *
	 * @return void
	 */
	public function test_history_survives_item_deletion() {
		$post_id = $this->create_item();
		$item    = Plugin::instance()->catalog()->get_item( $post_id );

		$service = new AnchorPriceService( $this->history );

		$service->record( $item, '7.49' );

		wp_delete_post( $post_id, true );

		$this->assertSame( 1, $this->history->count( array( 'product_id' => $post_id ) ) );
	}
}
