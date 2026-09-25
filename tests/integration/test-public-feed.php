<?php
/**
 * Public price-list feed integration tests.
 *
 * @package SidrenaCijena\Tests\Integration
 */

use SidrenaCijena\Api\PublicFeed;
use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Plugin;

/**
 * Covers the `/sidrena-cijena.xml` and `/sidrena-cijena.csv` feed.
 */
class Test_Public_Feed extends WP_UnitTestCase {

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
	 * Feed under test.
	 *
	 * @var PublicFeed
	 */
	protected $feed;

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

		$this->feed = new PublicFeed();
	}

	/**
	 * Remove the feed rules so other tests keep a clean rule set.
	 *
	 * @return void
	 */
	public function tear_down() {
		$rules = get_option( 'rewrite_rules' );

		if ( is_array( $rules ) ) {
			foreach ( array_keys( $rules ) as $rule ) {
				if ( 0 === strpos( $rule, '^' . PublicFeed::STEM ) ) {
					unset( $rules[ $rule ] );
				}
			}

			update_option( 'rewrite_rules', $rules );
		}

		parent::tear_down();
	}

	/**
	 * The feed query var has to be public.
	 *
	 * @return void
	 */
	public function test_query_var_is_registered() {
		$this->assertContains( PublicFeed::QUERY_VAR, $this->feed->query_vars( array( 'p' ) ) );
	}

	/**
	 * Both formats are reachable on the public stem.
	 *
	 * @return void
	 */
	public function test_rewrite_rule_covers_both_formats() {
		global $wp_rewrite;

		$this->feed->add_rewrites();

		$rules = $wp_rewrite->extra_rules_top;
		$key   = '^' . PublicFeed::STEM . '\.(xml|csv)$';

		$this->assertArrayHasKey( $key, $rules, 'No rewrite rule was registered for the public feed.' );
		$this->assertSame( 'index.php?' . PublicFeed::QUERY_VAR . '=$matches[1]', $rules[ $key ] );
	}

	/**
	 * The webshop location is the one published at the public URL.
	 *
	 * @return void
	 */
	public function test_webshop_location_is_public() {
		$this->assertSame( $this->webshop_id, (int) PublicFeed::public_location()['id'] );
	}

	/**
	 * A published file is served from the publication row.
	 *
	 * @return void
	 */
	public function test_published_file_is_available_for_the_public_format() {
		$this->create_item( 'Kava mljevena 250 g', 'KAV-250', 6.99 );

		$generated = Plugin::instance()->generation()->generate( $this->webshop_id, 'xml', array( 'force' => true ) );

		$this->assertIsArray( $generated );
		$this->assertSame( 'published', $generated['publication']['status'] );

		$row = $this->invoke( 'current_file', array( $this->webshop_id, 'xml' ) );

		$this->assertIsArray( $row );
		$this->assertFileExists( $row['path'] );
	}

	/**
	 * A location without a published file is reported as missing so the feed can answer 503.
	 *
	 * @return void
	 */
	public function test_location_without_publication_has_no_public_file() {
		$this->create_item( 'Kava mljevena 250 g', 'KAV-250', 6.99 );

		Plugin::instance()->generation()->generate( $this->webshop_id, 'xml', array( 'force' => true ) );

		$this->assertNull( $this->invoke( 'current_file', array( $this->webshop_id, 'csv' ) ) );
	}

	/**
	 * Invoke a protected feed method.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	protected function invoke( $method, array $args = array() ) {
		$reflection = new ReflectionMethod( PublicFeed::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $this->feed, $args );
	}

	/**
	 * Create a catalog item with an anchor, so publication is not blocked (FR-007).
	 *
	 * @param string $name  Product name.
	 * @param string $sku   SKU.
	 * @param float  $price Regular price and anchor price.
	 * @return void
	 */
	protected function create_item( $name, $sku, $price ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $name,
			)
		);

		update_post_meta( $post_id, '_sidrena_sku', $sku );
		update_post_meta( $post_id, '_sidrena_regular_price', $price );
		wp_set_object_terms( $post_id, 'product', StandalonePostType::TAX_TYPE );

		Plugin::instance()->anchors()->record(
			Plugin::instance()->catalog()->get_item( $post_id ),
			$price,
			array( 'source' => 'test' )
		);
	}
}
