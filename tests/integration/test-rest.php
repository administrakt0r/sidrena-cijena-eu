<?php
/**
 * Public REST API integration tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Integration
 */

use Titanworks\SidrenaCijena\Catalog\StandaloneCatalogProvider;
use Titanworks\SidrenaCijena\Catalog\StandalonePostType;
use Titanworks\SidrenaCijena\Database\LocationRepository;
use Titanworks\SidrenaCijena\Plugin;

/**
 * US2 REST coverage.
 */
class Test_Rest extends WP_UnitTestCase {

	/**
	 * Location repository.
	 *
	 * @var LocationRepository
	 */
	protected $locations;

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

		$this->locations->insert(
			array(
				'name'       => 'Webshop',
				'type'       => 'webshop',
				'address'    => 'Vlaška 68',
				'city'       => 'Zagreb',
				'identifier' => 'P1',
				'active'     => true,
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => StandalonePostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Kava mljevena 250 g',
			)
		);

		update_post_meta( $post_id, '_twbce_sku', 'KAV-250' );
		update_post_meta( $post_id, '_twbce_regular_price', 6.99 );
		wp_set_object_terms( $post_id, 'product', StandalonePostType::TAX_TYPE );
	}

	/**
	 * Dispatch a REST request.
	 *
	 * @param string               $route  Route.
	 * @param array<string,string> $params Query parameters.
	 * @return WP_REST_Response
	 */
	protected function dispatch( $route, array $params = array() ) {
		$request = new WP_REST_Request( 'GET', $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * `/prices` returns the documented JSON shape.
	 *
	 * @return void
	 */
	public function test_prices_route() {
		$response = $this->dispatch( '/sidrena-cijena/v1/prices' );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertNotEmpty( $data['data'] );

		$item = $data['data'][0];

		$this->assertSame( 'KAV-250', $item['sku'] );
		$this->assertSame( 'product', $item['type'] );
		$this->assertSame( 6.99, $item['current_price'] );
		$this->assertNull( $item['anchor_price'] );
		$this->assertSame( 'EUR', $item['currency'] );

		$this->assertSame( 'noindex', $response->get_headers()['X-Robots-Tag'] );
		$this->assertStringContainsString( 'max-age=300', $response->get_headers()['Cache-Control'] );
	}

	/**
	 * `/prices` supports pagination and type filters.
	 *
	 * @return void
	 */
	public function test_prices_pagination_and_filtering() {
		$response = $this->dispatch(
			'/sidrena-cijena/v1/prices',
			array(
				'per_page' => 1,
				'type'     => 'service',
			)
		);

		$data = $response->get_data();

		$this->assertSame( 1, $data['meta']['per_page'] );
		$this->assertSame( 0, $data['meta']['total'] );
		$this->assertSame( array(), $data['data'] );
	}

	/**
	 * `/prices.csv` and `/prices.xml` return the documented payloads.
	 *
	 * @return void
	 */
	public function test_download_routes() {
		$csv = $this->dispatch( '/sidrena-cijena/v1/prices.csv' );
		$xml = $this->dispatch( '/sidrena-cijena/v1/prices.xml' );

		$this->assertSame( 200, $csv->get_status() );
		$this->assertSame( 200, $xml->get_status() );

		$csv_data = $csv->get_data();
		$xml_data = $xml->get_data();

		$this->assertArrayHasKey( 'twbce_download', $csv_data );
		$this->assertStringContainsString( 'vrsta;naziv;sifra;', $csv_data['twbce_download']['content'] );
		$this->assertSame( 'text/csv; charset=utf-8', $csv_data['twbce_download']['content_type'] );
		$this->assertMatchesRegularExpression( '/\.CSV$/', $csv_data['twbce_download']['filename'] );

		$this->assertStringContainsString( '<cjenik', $xml_data['twbce_download']['content'] );
		$this->assertSame( 'application/xml; charset=utf-8', $xml_data['twbce_download']['content_type'] );
	}

	/**
	 * `/publications` lists retained versions.
	 *
	 * @return void
	 */
	public function test_publications_route() {
		$response = $this->dispatch( '/sidrena-cijena/v1/publications' );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'meta', $data );
	}

	/**
	 * `/status` requires the management capability.
	 *
	 * @return void
	 */
	public function test_status_route_requires_capability() {
		wp_set_current_user( 0 );

		$response = $this->dispatch( '/sidrena-cijena/v1/status' );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );

		$response = $this->dispatch( '/sidrena-cijena/v1/status' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'schema_version', $response->get_data() );
	}

	/**
	 * Unknown locations return 404.
	 *
	 * @return void
	 */
	public function test_unknown_location_returns_404() {
		$response = $this->dispatch(
			'/sidrena-cijena/v1/prices',
			array(
				'location' => 999999,
			)
		);

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * `per_page` is clamped to the documented maximum.
	 *
	 * @return void
	 */
	public function test_per_page_is_clamped() {
		$response = $this->dispatch(
			'/sidrena-cijena/v1/prices',
			array(
				'per_page' => 5000,
			)
		);

		$data = $response->get_data();

		$this->assertSame( 500, $data['meta']['per_page'] );
	}
}
