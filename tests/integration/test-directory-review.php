<?php
/**
 * Directory review regressions against real WordPress escaping and database APIs.
 *
 * @package Titanworks\SidrenaCijena\Tests
 */

use Titanworks\SidrenaCijena\Admin\Capabilities;
use Titanworks\SidrenaCijena\Admin\FormState;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;
use Titanworks\SidrenaCijena\Database\LogRepository;
use Titanworks\SidrenaCijena\Database\PublicationRepository;
use Titanworks\SidrenaCijena\Support\IdentifierMigration;
use Titanworks\SidrenaCijena\Support\Settings;

/**
 * Verify upgrades retain evidence and database filters resist SQL injection.
 */
class DirectoryReviewTest extends WP_UnitTestCase {

	/**
	 * @return void
	 */
	public function test_upgrade_preserves_catalog_settings_and_ledger(): void {
		global $wpdb;

		delete_option( IdentifierMigration::OPTION );
		delete_option( Settings::OPTION );
		update_option( 'sidrena_cijena_settings', array( 'merchant_name' => 'Existing merchant' ) );
		$post_id = self::factory()->post->create( array( 'post_type' => 'sidrena_item' ) );
		update_post_meta( $post_id, '_sidrena_regular_price', '12.50' );
		$user_id = self::factory()->user->create();
		$user    = new WP_User( $user_id );
		$user->add_cap( 'manage_sidrena_cijena', false );

		$history = new PriceHistoryRepository();
		$id      = $history->insert(
			array(
				'product_id'     => $post_id,
				'price_type'     => 'anchor',
				'price'          => '12.50',
				'reference_date' => '2026-09-10',
				'source'         => 'manual',
			)
		);
		$before  = $history->get( $id );

		IdentifierMigration::run();

		$this->assertSame( 'Existing merchant', get_option( Settings::OPTION )['merchant_name'] );
		$this->assertSame( 'twbce_item', get_post_type( $post_id ) );
		$this->assertSame( '12.50', get_post_meta( $post_id, '_twbce_regular_price', true ) );
		$this->assertSame( $before, $history->get( $id ) );
		$user = new WP_User( $user_id );
		$this->assertFalse( $user->has_cap( Capabilities::CAPABILITY ) );

		update_option( Settings::OPTION, array( 'merchant_name' => 'Updated merchant' ) );
		IdentifierMigration::run();
		$this->assertSame( 'Updated merchant', get_option( Settings::OPTION )['merchant_name'] );
	}

	/**
	 * @return void
	 */
	public function test_anchor_markup_keeps_keyboard_controls_and_removes_event_handlers(): void {
		$html = '<div class="anchor" style="--sidrena-size:0.4" onmouseover="alert(1)"><span role="button" tabindex="0" aria-label="Help" aria-expanded="false" data-sidrena-cijena-tooltip-trigger onclick="alert(2)">?</span></div>';
		$out  = \Titanworks\SidrenaCijena\WooCommerce\Display\PriceDisplay::sanitize_markup( $html );

		$this->assertStringContainsString( 'tabindex="0"', $out );
		$this->assertStringContainsString( 'role="button"', $out );
		$this->assertStringContainsString( 'data-sidrena-cijena-tooltip-trigger', $out );
		$this->assertStringContainsString( '--sidrena-size:0.4', $out );
		$this->assertStringNotContainsString( 'onmouseover', $out );
		$this->assertStringNotContainsString( 'onclick', $out );
	}

	/**
	 * @return void
	 */
	public function test_filter_and_sort_inputs_cannot_change_sql_structure(): void {
		$history = new PriceHistoryRepository();
		$product = self::factory()->post->create();
		$id      = $history->insert(
			array(
				'product_id'     => $product,
				'price_type'     => 'anchor',
				'price'          => '9.99',
				'reference_date' => '2026-09-10',
				'source'         => 'manual',
			)
		);
		$filter  = array(
			'product_id'     => $product,
			'variation_id'   => 0,
			'price_type'     => 'anchor',
			'reference_date' => '2026-09-10',
			'order'          => 'ASC',
		);
		$this->assertCount( 1, $history->query( $filter ) );
		$this->assertSame( 1, $history->count( $filter ) );
		$filter['price_type'] = "anchor' OR 1=1 --";
		$this->assertSame( array(), $history->query( $filter ) );
		$this->assertSame( 0, $history->count( $filter ) );
		$filter['price_type'] = 'anchor';
		$filter['orderby']    = 'id; DROP TABLE wp_posts';
		$filter['order']      = 'DESC; DROP TABLE wp_posts';
		$this->assertSame( (int) $id, (int) $history->query( $filter )[0]['id'] );
	}

	/**
	 * @return void
	 */
	public function test_validation_attributes_escape_malicious_ids(): void {
		ob_start();
		FormState::render_error_attributes( array( 'name' => 'Invalid' ), 'name', 'name" onfocus="alert(1)' );
		$output = ob_get_clean();
		$this->assertStringNotContainsString( ' onfocus="', $output );
		$this->assertStringContainsString( 'aria-invalid="true"', $output );
	}
	/**
	 * @return void
	 */
	public function test_public_html_preserves_search_controls_and_removes_event_handlers(): void {
		$html = '<form method="get"><input type="search" name="q" value="tea" onfocus="alert(1)"><select name="type"><option value="usluga" selected>Service</option></select><button type="submit">Filter</button></form>';
		$safe = wp_kses( $html, \Titanworks\SidrenaCijena\Api\PublicPage::allowed_html() );
		$this->assertStringContainsString( '<form method="get">', $safe );
		$this->assertStringContainsString( 'name="q"', $safe );
		$this->assertStringContainsString( '<select name="type">', $safe );
		$this->assertStringNotContainsString( 'onfocus', $safe );
	}
	/**
	 * @return void
	 */
	public function test_log_and_publication_filters_bind_values(): void {
		$logs = new LogRepository();
		$logs->insert( 'info', 'import', 'Directory regression' );
		$this->assertNotEmpty( $logs->query( array( 'event' => 'import' ) )['items'] );
		$filter = array(
			'event' => "import' OR 1=1 --",
			'page'  => 2,
		);
		$this->assertSame( 0, $logs->query( $filter )['total'] );

		$publications = new PublicationRepository();
		$publications->insert( array( 'status' => 'published' ) );
		$publications->insert( array( 'status' => 'expired' ) );
		$this->assertSame( 1, $publications->index()['total'] );
		$this->assertSame( 2, $publications->index( array( 'include_expired' => true ) )['total'] );
		$this->assertSame( 0, $publications->index( array( 'format' => "csv' OR 1=1 --" ) )['total'] );
	}

	/**
	 * Role capability denials must survive upgrades when another role grants the legacy capability.
	 *
	 * @return void
	 */
	public function test_upgrade_preserves_explicit_role_denials(): void {
		delete_option( IdentifierMigration::OPTION );
		update_option( 'sidrena_cijena_settings', array() );
		add_role( 'twbce_review_granted', 'Review grant', array( 'manage_sidrena_cijena' => true ) );
		add_role( 'twbce_review_denied', 'Review denial', array( 'manage_sidrena_cijena' => false ) );
		$user_id = self::factory()->user->create( array( 'role' => 'twbce_review_granted' ) );
		$user    = new WP_User( $user_id );
		$user->add_role( 'twbce_review_denied' );
		$this->assertFalse( $user->has_cap( 'manage_sidrena_cijena' ) );

		IdentifierMigration::run();

		$this->assertTrue( get_role( 'twbce_review_granted' )->capabilities[ Capabilities::CAPABILITY ] );
		$this->assertFalse( get_role( 'twbce_review_denied' )->capabilities[ Capabilities::CAPABILITY ] );
		$this->assertFalse( ( new WP_User( $user_id ) )->has_cap( Capabilities::CAPABILITY ) );
		remove_role( 'twbce_review_granted' );
		remove_role( 'twbce_review_denied' );
	}
}
