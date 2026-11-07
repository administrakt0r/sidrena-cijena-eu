<?php
/**
 * First-offer evidence integration tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Integration
 */

use Titanworks\SidrenaCijena\Catalog\StandalonePostType;
use Titanworks\SidrenaCijena\Compliance\RuleConfig;
use Titanworks\SidrenaCijena\Plugin;
use Titanworks\SidrenaCijena\Pricing\FirstOfferRecorder;
use Titanworks\SidrenaCijena\Support\Dates;

/**
 * Exercises real lifecycle hooks and persistence, without guessing prior prices.
 */
class Test_First_Offer extends WP_UnitTestCase {

	/**
	 * Register standalone fixtures and a baseline before today.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		StandalonePostType::register();
		RuleConfig::update( array( 'general_reference_date' => '2020-01-01' ) );
	}

	/**
	 * Create a draft with actual retail data.
	 *
	 * @param string $status Initial status.
	 * @return int
	 */
	private function item( $status = 'draft' ) {
		return wp_insert_post(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_title'  => 'First offer test',
				'post_status' => $status,
				'meta_input'  => array( '_twbce_regular_price' => '12.50' ),
			)
		);
	}

	/**
	 * Publish records the observed price/date once; changes cannot rewrite it.
	 *
	 * @return void
	 */
	public function test_draft_then_publish_captures_once() {
		$id = $this->item();
		$this->assertNull( Plugin::instance()->history()->latest( $id, 0, 'anchor' ) );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$first = Plugin::instance()->history()->latest( $id, 0, 'anchor' );
		$this->assertSame( '12.500000', $first['price'] );
		$this->assertSame( Dates::today(), $first['reference_date'] );
		$this->assertSame( 'observed_first_offer', $first['source'] );
		$this->assertSame( $id, json_decode( $first['metadata'], true )['product_id'] );
		update_post_meta( $id, '_twbce_regular_price', '30.00' );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( $first['id'], Plugin::instance()->history()->latest( $id, 0, 'anchor' )['id'] );
	}

	/**
	 * An existing entry without a witnessed insertion remains missing.
	 *
	 * @return void
	 */
	public function test_existing_product_is_not_backfilled() {
		$id = $this->item();
		delete_post_meta( $id, FirstOfferRecorder::ELIGIBLE );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$this->assertNull( Plugin::instance()->history()->latest( $id, 0, 'anchor' ) );
	}

	/**
	 * Copied metadata cannot attach evidence to another item.
	 *
	 * @return void
	 */
	public function test_copied_eligibility_does_not_qualify_another_product() {
		$original = $this->item();
		$copy     = $this->item();
		update_post_meta( $copy, FirstOfferRecorder::ELIGIBLE, $original );
		wp_update_post(
			array(
				'ID'          => $copy,
				'post_status' => 'publish',
			)
		);
		$this->assertNull( Plugin::instance()->history()->latest( $copy, 0, 'anchor' ) );
	}

	/**
	 * Pre-baseline publication cannot prove a future reference-date price.
	 *
	 * @return void
	 */
	public function test_pre_baseline_offer_is_not_later_backfilled() {
		RuleConfig::update( array( 'general_reference_date' => '2099-01-01' ) );
		$id = $this->item( 'publish' );
		$this->assertNotEmpty( get_post_meta( $id, FirstOfferRecorder::OBSERVATION, true ) );
		RuleConfig::update( array( 'general_reference_date' => '2020-01-01' ) );
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Later edit',
			)
		);
		$this->assertNull( Plugin::instance()->history()->latest( $id, 0, 'anchor' ) );
	}

	/**
	 * An existing manually evidenced anchor takes precedence.
	 *
	 * @return void
	 */
	public function test_existing_anchor_is_never_overwritten() {
		$id = $this->item();
		Plugin::instance()->history()->insert(
			array(
				'product_id'     => $id,
				'price_type'     => 'anchor',
				'price'          => 8,
				'reference_date' => '2020-01-01',
			)
		);
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( '8.000000', Plugin::instance()->history()->latest( $id, 0, 'anchor' )['price'] );
	}

	/**
	 * Service taxonomies and a zero price are supported.
	 *
	 * @return void
	 */
	public function test_service_with_zero_price() {
		$id = $this->item();
		wp_set_object_terms( $id, 'service', StandalonePostType::TAX_TYPE );
		update_post_meta( $id, '_twbce_regular_price', '0' );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$anchor = Plugin::instance()->history()->latest( $id, 0, 'anchor' );
		$this->assertSame( 'service', $anchor['item_type'] );
		$this->assertSame( '0.000000', $anchor['price'] );
	}
}
