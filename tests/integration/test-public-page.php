<?php
/**
 * Public HTML price list and archive integration tests.
 *
 * @package SidrenaCijena\Tests\Integration
 */

use SidrenaCijena\Api\PublicArchiveSitemapProvider;
use SidrenaCijena\Api\PublicPage;
use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Plugin;

/**
 * Covers `/sidrena-cjenik` and `/arhiva-sidrene-cijene` and their shortcodes.
 */
class Test_Public_Page extends WP_UnitTestCase {

	/**
	 * Location repository.
	 *
	 * @var LocationRepository
	 */
	protected $locations;

	/**
	 * Webshop location ID.
	 *
	 * @var int
	 */
	protected $webshop_id;

	/**
	 * Page under test.
	 *
	 * @var PublicPage
	 */
	protected $page;

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		StandalonePostType::register();
		Plugin::instance()->set_catalog( new StandaloneCatalogProvider() );

		$this->locations = new LocationRepository();

		foreach ( $this->locations->all() as $location ) {
			$this->locations->delete( (int) $location['id'] );
		}

		// The store comes first so the webshop is not picked only because it is the lowest ID.
		$this->locations->insert(
			array(
				'name'       => 'Prodavaonica Ilica',
				'type'       => 'prodavaonica',
				'address'    => 'Ilica 150',
				'city'       => 'Zagreb',
				'identifier' => 'P2',
				'active'     => true,
			)
		);

		$this->webshop_id = (int) $this->locations->insert(
			array(
				'name'       => 'Webshop',
				'type'       => 'webshop',
				'address'    => 'Vlaška 68',
				'city'       => 'Zagreb',
				'identifier' => 'P1',
				'active'     => true,
			)
		);

		$this->page = new PublicPage();
	}

	/**
	 * Remove the page rules so other tests keep a clean rule set.
	 *
	 * @return void
	 */
	public function tear_down() {
		$rules = get_option( 'rewrite_rules' );

		if ( is_array( $rules ) ) {
			foreach ( array_keys( $rules ) as $rule ) {
				if ( 0 === strpos( $rule, '^' . PublicPage::STEM_LIST ) || 0 === strpos( $rule, '^' . PublicPage::STEM_ARCHIVE ) ) {
					unset( $rules[ $rule ] );
				}
			}

			update_option( 'rewrite_rules', $rules );
		}

		parent::tear_down();
	}

	/**
	 * The page query var has to be public.
	 *
	 * @return void
	 */
	public function test_query_var_is_registered() {
		$this->assertContains( PublicPage::QUERY_VAR, $this->page->query_vars( array( 'p' ) ) );
	}

	/**
	 * Both pages have their own rewrite rule.
	 *
	 * @return void
	 */
	public function test_rewrite_rules_cover_both_pages() {
		global $wp_rewrite;

		$this->page->add_rewrites();

		$rules = $wp_rewrite->extra_rules_top;

		$list_key    = '^' . PublicPage::STEM_LIST . '/?$';
		$archive_key = '^' . PublicPage::STEM_ARCHIVE . '/?$';

		$this->assertArrayHasKey( $list_key, $rules, 'No rewrite rule was registered for the price list page.' );
		$this->assertSame( 'index.php?' . PublicPage::QUERY_VAR . '=' . PublicPage::PAGE_LIST, $rules[ $list_key ] );

		$this->assertArrayHasKey( $archive_key, $rules, 'No rewrite rule was registered for the archive page.' );
		$this->assertSame( 'index.php?' . PublicPage::QUERY_VAR . '=' . PublicPage::PAGE_ARCHIVE, $rules[ $archive_key ] );
	}

	/**
	 * Both shortcodes are available.
	 *
	 * @return void
	 */
	public function test_shortcodes_are_registered() {
		$this->page->register();

		$this->assertTrue( shortcode_exists( 'sidrena-cjenik' ) );
		$this->assertTrue( shortcode_exists( 'sidrena-arhiva' ) );
	}

	/**
	 * The archive is included in WordPress's sitemap index by default.
	 *
	 * @return void
	 */
	public function test_archive_is_registered_in_sitemap() {
		$server   = wp_sitemaps_get_server();
		$provider = $server->registry->get_provider( 'sidrena-cijena' );

		$this->assertInstanceOf( PublicArchiveSitemapProvider::class, $provider );
		$this->assertSame(
			array( array( 'loc' => home_url( '/' . PublicPage::STEM_ARCHIVE . '/' ) ) ),
			$provider->get_url_list( 1 )
		);
	}

	/**
	 * The price list table carries the canonical rows.
	 *
	 * @return void
	 */
	public function test_list_renders_canonical_rows() {
		$this->create_item( 'Kava mljevena 250 g', 'KAV-250', 6.99 );

		$html = $this->page->render_list();

		$this->assertStringContainsString( 'KAV-250', $html );
		$this->assertStringContainsString( 'Kava mljevena 250 g', $html );
		$this->assertStringContainsString( '6,99', $html );
		$this->assertStringContainsString( 'sidrena-cijena-table', $html );
	}

	/**
	 * The search filter narrows the table.
	 *
	 * @return void
	 */
	public function test_list_search_filters_rows() {
		$this->create_item( 'Kava mljevena 250 g', 'KAV-250', 6.99 );
		$this->create_item( 'Sok od jabuke 1 L', 'SOK-1L', 2.49 );

		$html = $this->page->render_list( array( 'q' => 'KAV' ) );

		$this->assertStringContainsString( 'KAV-250', $html );
		$this->assertStringNotContainsString( 'SOK-1L', $html );
	}

	/**
	 * The type filter narrows the table.
	 *
	 * @return void
	 */
	public function test_list_type_filter_narrows_rows() {
		$this->create_item( 'Kava mljevena 250 g', 'KAV-250', 6.99 );
		$this->create_item( 'Dostava', 'DOST-1', 5.00, 'service' );

		$html = $this->page->render_list( array( 'type' => 'usluga' ) );

		$this->assertStringContainsString( 'DOST-1', $html );
		$this->assertStringNotContainsString( 'KAV-250', $html );
	}

	/**
	 * The archive lists the published files of the public location.
	 *
	 * @return void
	 */
	public function test_archive_lists_published_files() {
		$this->create_item( 'Kava mljevena 250 g', 'KAV-250', 6.99 );

		$generated = Plugin::instance()->generation()->generate( $this->webshop_id, 'xml', array( 'force' => true ) );

		$this->assertIsArray( $generated );

		$html = $this->page->render_archive();

		$this->assertStringContainsString( $generated['filename'], $html );
		$this->assertStringContainsString( __( 'Download', 'titanworks-bazna-cijena' ), $html );
	}

	/**
	 * An empty archive says so instead of rendering a broken table.
	 *
	 * @return void
	 */
	public function test_empty_archive_is_stated() {
		$html = $this->page->render_archive();

		$this->assertStringContainsString( __( 'No price lists have been published yet.', 'titanworks-bazna-cijena' ), $html );
	}

	/**
	 * Create a catalog item with an anchor, so publication is not blocked (FR-007).
	 *
	 * @param string $name  Product name.
	 * @param string $sku   SKU.
	 * @param float  $price Regular price and anchor price.
	 * @param string $type  `product` or `service`.
	 * @return void
	 */
	protected function create_item( $name, $sku, $price, $type = 'product' ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $name,
			)
		);

		update_post_meta( $post_id, '_sidrena_sku', $sku );
		update_post_meta( $post_id, '_sidrena_regular_price', $price );
		wp_set_object_terms( $post_id, $type, StandalonePostType::TAX_TYPE );

		Plugin::instance()->anchors()->record(
			Plugin::instance()->catalog()->get_item( $post_id ),
			$price,
			array( 'source' => 'test' )
		);
	}
}
