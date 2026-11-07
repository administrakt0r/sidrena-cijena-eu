<?php
/**
 * Tests for restricted asset loading across admin and frontend.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Support
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\AdminMenu;
use Titanworks\SidrenaCijena\Api\PublicPage;
use Titanworks\SidrenaCijena\Support\Settings;
use Titanworks\SidrenaCijena\WooCommerce\Display\PriceDisplay;
use Titanworks\SidrenaCijena\Pricing\AnchorPriceService;
use Titanworks\SidrenaCijena\Pricing\AnchorCache;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;
use Titanworks\SidrenaCijena\Catalog\StandaloneCatalogProvider;
use Titanworks\SidrenaCijena\Database\SpecialSaleRepository;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\AdminMenu::enqueue_assets
 * @covers \Titanworks\SidrenaCijena\WooCommerce\Display\PriceDisplay::enqueue_assets
 * @covers \Titanworks\SidrenaCijena\Api\PublicPage::enqueue_assets
 */
class AssetLoadingTest extends TestCase {

	/**
	 * Reset global state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_options']            = array();
		$GLOBALS['twbce_test_enqueued_styles']    = array();
		$GLOBALS['twbce_test_enqueued_scripts']   = array();
		$GLOBALS['twbce_test_registered_scripts'] = array();
		$GLOBALS['twbce_test_is_product']         = false;
		$GLOBALS['twbce_test_query_vars']         = array();
		$_SERVER['REQUEST_URI']                   = '/';
		Settings::reset_cache();
	}

	/**
	 * AdminMenu skips unrelated screens.
	 *
	 * @return void
	 */
	public function test_admin_menu_skips_unrelated_screens() {
		AdminMenu::enqueue_assets( 'edit.php' );

		$this->assertArrayNotHasKey( 'sidrena-cijena-admin', $GLOBALS['twbce_test_enqueued_styles'] );
		$this->assertArrayNotHasKey( 'sidrena-cijena-admin', $GLOBALS['twbce_test_enqueued_scripts'] );
	}

	/**
	 * AdminMenu enqueues on plugin screens without wp-i18n dependency.
	 *
	 * @return void
	 */
	public function test_admin_menu_enqueues_on_plugin_screens_without_wp_i18n() {
		AdminMenu::enqueue_assets( 'toplevel_page_sidrena-cijena' );

		$this->assertArrayHasKey( 'sidrena-cijena-admin', $GLOBALS['twbce_test_enqueued_styles'] );
		$this->assertArrayHasKey( 'sidrena-cijena-admin', $GLOBALS['twbce_test_enqueued_scripts'] );

		$script = $GLOBALS['twbce_test_enqueued_scripts']['sidrena-cijena-admin'];
		$this->assertNotContains( 'wp-i18n', $script['deps'] );
	}

	/**
	 * PriceDisplay skips non-product non-archive pages.
	 *
	 * @return void
	 */
	public function test_price_display_skips_non_targeted_pages() {
		$cache   = new AnchorCache();
		$history = new PriceHistoryRepository();
		$anchors = new AnchorPriceService( $history, $cache );
		$display = new PriceDisplay( $anchors );

		$display->enqueue_assets();

		$this->assertArrayNotHasKey( 'sidrena-cijena-frontend', $GLOBALS['twbce_test_enqueued_styles'] );
		$this->assertArrayNotHasKey( 'sidrena-cijena-frontend', $GLOBALS['twbce_test_enqueued_scripts'] );
	}

	/**
	 * PriceDisplay enqueues when forced via builder/theme filter.
	 *
	 * @return void
	 */
	public function test_price_display_enqueues_when_filter_permits() {
		$cache   = new AnchorCache();
		$history = new PriceHistoryRepository();
		$anchors = new AnchorPriceService( $history, $cache );
		$display = new PriceDisplay( $anchors );

		add_filter( 'twbce_cijena_enqueue_frontend_assets', '__return_true' );
		$display->enqueue_assets();
		remove_filter( 'twbce_cijena_enqueue_frontend_assets', '__return_true' );

		$this->assertArrayHasKey( 'sidrena-cijena-frontend', $GLOBALS['twbce_test_enqueued_styles'] );
		$this->assertArrayHasKey( 'sidrena-cijena-frontend', $GLOBALS['twbce_test_enqueued_scripts'] );
	}

	/**
	 * PublicPage skips non-cjenik non-archive requests.
	 *
	 * @return void
	 */
	public function test_public_page_skips_unrelated_requests() {
		$catalog = new StandaloneCatalogProvider();
		$history = new PriceHistoryRepository();
		$sales   = new SpecialSaleRepository();
		$page    = new PublicPage( $catalog, $history, $sales );

		$page->enqueue_assets();

		$this->assertArrayNotHasKey( 'sidrena-cijena-frontend', $GLOBALS['twbce_test_enqueued_styles'] );
	}

	/**
	 * PublicPage enqueues on public price list requests.
	 *
	 * @return void
	 */
	public function test_public_page_enqueues_on_price_list() {
		$catalog = new StandaloneCatalogProvider();
		$history = new PriceHistoryRepository();
		$sales   = new SpecialSaleRepository();
		$page    = new PublicPage( $catalog, $history, $sales );

		$GLOBALS['twbce_test_query_vars'][ PublicPage::QUERY_VAR ] = PublicPage::PAGE_LIST;

		$page->enqueue_assets();

		$this->assertArrayHasKey( 'sidrena-cijena-frontend', $GLOBALS['twbce_test_enqueued_styles'] );
	}
}
