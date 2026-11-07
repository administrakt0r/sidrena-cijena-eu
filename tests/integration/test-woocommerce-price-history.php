<?php
/**
 * Integration tests for WooCommerce CRUD price-history capture.
 *
 * @package Titanworks\SidrenaCijena\Tests\Integration
 */

use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;

/**
 * Exercises actual WooCommerce save and scheduled-sale hooks.
 */
class Test_WooCommerce_Price_History extends WP_UnitTestCase {

	/**
	 * Product created by the current test.
	 *
	 * @var int
	 */
	protected $product_id = 0;

	/**
	 * Remove only the fixture created by this test.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wpdb;

		if ( $this->product_id ) {
			$product = wc_get_product( $this->product_id );

			if ( $product ) {
				$product->delete( true );
			}

			// Remove only this test fixture's append-only rows after the fixture product is deleted.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$wpdb->prefix . 'sidrena_price_history',
				array( 'product_id' => $this->product_id ),
				array( '%d' )
			);
		}

		parent::tear_down();
	}

	/**
	 * Product CRUD saves record ordinary edits, sale start and sale closure.
	 *
	 * @return void
	 */
	public function test_crud_and_scheduled_sale_transitions_are_recorded() {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active in this test environment.' );
		}
		if ( ! function_exists( 'wc_handle_product_start_scheduled_sale' ) || ! function_exists( 'wc_handle_product_end_scheduled_sale' ) ) {
			$this->markTestSkipped( 'This WooCommerce version does not expose per-product scheduled-sale callbacks.' );
		}

		$repository = new PriceHistoryRepository();
		$product    = new WC_Product_Simple();
		$product->set_name( 'History recorder fixture' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '42.00' );
		$this->product_id = $product->save();

		$rows = $repository->query(
			array(
				'product_id' => $this->product_id,
				'price_type' => 'regular',
				'order'      => 'ASC',
			)
		);
		$this->assertCount( 1, $rows );
		$this->assertSame( '42.000000', $rows[0]['price'] );

		$product = wc_get_product( $this->product_id );
		$product->set_regular_price( '39.00' );
		$product->save();
		$rows = $repository->query(
			array(
				'product_id' => $this->product_id,
				'price_type' => 'regular',
				'order'      => 'ASC',
			)
		);
		$this->assertCount( 2, $rows );
		$this->assertSame( '39.000000', $rows[1]['price'] );

		$product = wc_get_product( $this->product_id );
		$product->set_sale_price( '30.00' );
		$product->set_date_on_sale_from( $this->site_datetime( time() + 60 ) );
		$product->save();
		$this->assertNull( $repository->latest( $this->product_id, 0, 'sale' ) );

		$start_timestamp = time() - 60;
		update_post_meta( $this->product_id, '_sale_price_dates_from', $start_timestamp );
		do_action( 'wc_product_start_scheduled_sale', $this->product_id );
		$sale = $repository->latest( $this->product_id, 0, 'sale' );
		$this->assertNotNull( $sale );
		$this->assertSame( '30.000000', $sale['price'] );
		$this->assertSame( $this->site_datetime( $start_timestamp ), $sale['valid_from'] );
		$periods = $repository->active_sale_period_map( array( $this->product_id ) );
		$this->assertSame( $sale['valid_from'], $periods[ $this->product_id . ':0' ]['valid_from'] );
		$this->assertSame( '30.000000', $periods[ $this->product_id . ':0' ]['price'] );

		$product = wc_get_product( $this->product_id );
		$product->set_date_on_sale_to( $this->site_datetime( time() + 3600 ) );
		$product->save();
		$end_timestamp = time() - 60;
		update_post_meta( $this->product_id, '_sale_price_dates_to', $end_timestamp );
		do_action( 'wc_product_end_scheduled_sale', $this->product_id );
		$sale = $repository->latest( $this->product_id, 0, 'sale' );
		$this->assertNull( $sale['price'] );
		$this->assertSame( 'price_interval_closed', json_decode( $sale['metadata'], true )['event'] );
		$this->assertSame( $this->site_datetime( $end_timestamp + 1 ), $sale['valid_from'] );
		$this->assertSame( array(), $repository->active_sale_period_map( array( $this->product_id ) ) );
	}

	/**
	 * Format a Unix timestamp as a WooCommerce site-local datetime.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	protected function site_datetime( $timestamp ) {
		return ( new DateTimeImmutable( '@' . (int) $timestamp ) )
			->setTimezone( wp_timezone() )
			->format( 'Y-m-d H:i:s' );
	}
}
