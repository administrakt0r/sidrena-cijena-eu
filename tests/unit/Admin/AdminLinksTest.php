<?php
/**
 * Admin entity links tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Admin
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\AdminLinks;
use Titanworks\SidrenaCijena\Admin\LocationsPage;
use Titanworks\SidrenaCijena\Admin\PublicationsPage;
use Titanworks\SidrenaCijena\Admin\SettingsPage;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\AdminLinks
 */
class AdminLinksTest extends TestCase {

	/**
	 * Test edit_url resolution for supported entity types.
	 *
	 * @return void
	 */
	public function test_edit_url_resolves_supported_entities() {
		$catalog_item_url = AdminLinks::edit_url( 'catalog_item', 77 );
		$this->assertNotNull( $catalog_item_url );
		$this->assertStringContainsString( 'post=77', $catalog_item_url );
		$this->assertStringContainsString( 'action=edit', $catalog_item_url );

		$product_url = AdminLinks::edit_url( 'product', 42 );
		$this->assertNotNull( $product_url );
		$this->assertStringContainsString( 'post=42', $product_url );
		$this->assertStringContainsString( 'action=edit', $product_url );

		$item_url = AdminLinks::edit_url( 'item', 99 );
		$this->assertNotNull( $item_url );
		$this->assertStringContainsString( 'post=99', $item_url );

		$variation_url = AdminLinks::edit_url( 'variation', 101 );
		$this->assertNotNull( $variation_url );
		$this->assertStringContainsString( 'post=101', $variation_url );

		$location_url = AdminLinks::edit_url( 'location', 5 );
		$this->assertNotNull( $location_url );
		$this->assertStringContainsString( 'page=' . LocationsPage::SLUG, $location_url );
		$this->assertStringContainsString( 'location=5', $location_url );

		$publication_url = AdminLinks::edit_url( 'publication', 12 );
		$this->assertNotNull( $publication_url );
		$this->assertStringContainsString( 'page=' . PublicationsPage::SLUG, $publication_url );

		$rule_url = AdminLinks::edit_url( 'rule', 0 );
		$this->assertNotNull( $rule_url );
		$this->assertStringContainsString( 'page=' . SettingsPage::SLUG, $rule_url );
		$this->assertStringContainsString( 'tab=legal', $rule_url );
	}

	/**
	 * Test edit_url returns null for invalid entities or IDs.
	 *
	 * @return void
	 */
	public function test_edit_url_returns_null_for_invalid_input() {
		$this->assertNull( AdminLinks::edit_url( 'product', 0 ) );
		$this->assertNull( AdminLinks::edit_url( 'product', -5 ) );
		$this->assertNull( AdminLinks::edit_url( 'unknown_type', 12 ) );
		$this->assertNull( AdminLinks::edit_url( 'location', 0 ) );
	}

	/**
	 * Test render_entity_link renders HTML anchor or plain text fallback.
	 *
	 * @return void
	 */
	public function test_render_entity_link() {
		$html = AdminLinks::render_entity_link( 'product', 42 );
		$this->assertStringContainsString( '<a href=', $html );
		$this->assertStringContainsString( 'post=42', $html );
		$this->assertStringContainsString( '#42', $html );

		$custom_html = AdminLinks::render_entity_link( 'product', 42, 'Special Item <&>' );
		$this->assertStringContainsString( 'Special Item &lt;&amp;&gt;', $custom_html );

		$fallback = AdminLinks::render_entity_link( 'unknown_type', 0, 'Plain Text' );
		$this->assertSame( 'Plain Text', $fallback );
		$this->assertStringNotContainsString( '<a', $fallback );
	}

	/**
	 * Test render_item_link renders product edit link.
	 *
	 * @return void
	 */
	public function test_render_item_link() {
		$html = AdminLinks::render_item_link( 55, 'Premium Olive Oil' );
		$this->assertStringContainsString( '<a href=', $html );
		$this->assertStringContainsString( 'post=55', $html );
		$this->assertStringContainsString( 'Premium Olive Oil', $html );

		$fallback = AdminLinks::render_item_link( 0, 'Unlinked Item' );
		$this->assertSame( 'Unlinked Item', $fallback );
		$this->assertStringNotContainsString( '<a', $fallback );
	}
}
