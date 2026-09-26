<?php
/**
 * Price-list generation, publication and retention integration tests.
 *
 * @package SidrenaCijena\Tests\Integration
 */

use SidrenaCijena\Catalog\StandaloneCatalogProvider;
use SidrenaCijena\Catalog\StandalonePostType;
use SidrenaCijena\Admin\AnchorMetaBox;
use SidrenaCijena\Admin\SpecialSaleMetaBox;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\WooCommerce\WooCommerceDetector;

/**
 * US2 integration coverage.
 */
class Test_Publication extends WP_UnitTestCase {

	/**
	 * Location repository.
	 *
	 * @var LocationRepository
	 */
	protected $locations;

	/**
	 * Created location ID.
	 *
	 * @var int
	 */
	protected $location_id = 0;

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

		$existing = $this->locations->all();

		foreach ( $existing as $location ) {
			$this->locations->delete( (int) $location['id'] );
		}

		$this->location_id = $this->locations->insert(
			array(
				'name'       => 'Prodavaonica Vlaška',
				'type'       => 'prodavaonica',
				'address'    => 'Vlaška 68',
				'city'       => 'Zagreb',
				'identifier' => 'P1',
				'active'     => true,
			)
		);
	}

	/**
	 * Create a standalone item.
	 *
	 * @param string $name  Name.
	 * @param string $sku   SKU.
	 * @param float  $price Regular price.
	 * @return int
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
		update_post_meta( $post_id, '_sidrena_availability', 'available' );
		wp_set_object_terms( $post_id, 'product', StandalonePostType::TAX_TYPE );

		// FR-007: publication is blocked until the anchor price is recorded.
		$item = Plugin::instance()->catalog()->get_item( $post_id );
		Plugin::instance()->anchors()->record( $item, $price, array( 'source' => 'test' ) );

		return (int) $post_id;
	}

	/**
	 * Product editor controls must not emit nested forms or hijack Update.
	 *
	 * @return void
	 */
	public function test_product_evidence_actions_do_not_nest_or_override_editor_form() {
		$id   = $this->create_item( 'Editor form regression', 'EDITOR-1', 3.99 );
		$post = get_post( $id );

		ob_start();
		AnchorMetaBox::render( $post );
		$anchor_html = (string) ob_get_clean();

		ob_start();
		SpecialSaleMetaBox::render( $post );
		$sale_html = (string) ob_get_clean();

		foreach ( array( $anchor_html, $sale_html ) as $html ) {
			$this->assertStringNotContainsString( '<form', $html, 'Meta-box controls must not create invalid nested product-editor forms.' );
			$this->assertStringContainsString( 'formaction="' . esc_url( admin_url( 'admin-post.php' ) ) . '"', $html );
			$this->assertStringNotContainsString( 'type="hidden" name="action"', $html, 'Normal Update must retain the editor action.' );
		}
		$this->assertStringContainsString( 'name="sidrena_cijena_anchor_nonce"', $anchor_html );
		$this->assertStringNotContainsString( 'name="_wpnonce"', $anchor_html, 'The meta-box nonce must not overwrite the product editor nonce.' );

		if ( WooCommerceDetector::is_active() ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
			$tabs = AnchorMetaBox::product_data_tab( array() );
			$this->assertArrayHasKey( 'sidrena_cijena', $tabs );
			$this->assertSame( 'sidrena_cijena_product_data', $tabs['sidrena_cijena']['target'] );
		}
	}

	/**
	 * Generation publishes a legally named CSV file and archives it.
	 *
	 * @return void
	 */
	public function test_generation_publishes_csv() {
		$this->create_item( 'Kava mljevena 250 g', 'KAV-250', 6.99 );

		$result = Plugin::instance()->generation()->generate(
			$this->location_id,
			'csv',
			array(
				'source' => 'manual',
				'force'  => true,
			)
		);

		$this->assertIsArray( $result );
		$this->assertMatchesRegularExpression( '/^PRODAVAONICA_VLASKA68_ZAGREB_P1_[0-9]{2}_[0-9]{8}_[0-9]{4}\.CSV$/', $result['filename'] );
		$this->assertSame( 1, $result['item_count'] );

		$publication = $result['publication'];

		$this->assertSame( 'published', $publication['status'] );
		$this->assertSame( 64, strlen( (string) $publication['checksum'] ) );
		$this->assertFileExists( (string) $publication['path'] );

		$csv = (string) file_get_contents( $publication['path'] );

		$this->assertStringContainsString( 'vrsta;naziv;sifra;', $csv );
		$this->assertStringContainsString( 'proizvod;Kava mljevena 250 g;KAV-250;', $csv );

		wp_delete_file( (string) $publication['path'] );
	}

	/**
	 * Unchanged content is not republished; the current file stays available.
	 *
	 * @return void
	 */
	public function test_unchanged_content_is_skipped() {
		$this->create_item( 'Sok naranča 1 L', 'SOK-1L', 1.79 );

		$first  = Plugin::instance()->generation()->generate( $this->location_id, 'csv', array( 'force' => true ) );
		$second = Plugin::instance()->generation()->generate( $this->location_id, 'csv' );

		$this->assertIsArray( $first );
		$this->assertIsArray( $second );
		$this->assertTrue( $second['skipped'] );
		$this->assertSame( $first['filename'], $second['filename'] );

		$latest = Plugin::instance()->publications()->latest( $this->location_id, 'csv' );

		$this->assertSame( 'published', $latest['status'] );
		$this->assertFileExists( (string) $latest['path'] );

		wp_delete_file( (string) $latest['path'] );
	}

	/**
	 * A forced regeneration supersedes the previous version but keeps the row.
	 *
	 * @return void
	 */
	public function test_forced_regeneration_supersedes_previous() {
		$item = $this->create_item( 'Kruh', 'KRUH-1', 1.29 );

		$first = Plugin::instance()->generation()->generate( $this->location_id, 'csv', array( 'force' => true ) );

		update_post_meta( $item, '_sidrena_regular_price', 1.49 );

		$second = Plugin::instance()->generation()->generate( $this->location_id, 'csv', array( 'force' => true ) );

		$this->assertIsArray( $first );
		$this->assertIsArray( $second );

		$first_row  = Plugin::instance()->publications()->get( (int) $first['publication']['id'] );
		$second_row = Plugin::instance()->publications()->get( (int) $second['publication']['id'] );

		$this->assertSame( 'superseded', $first_row['status'] );
		$this->assertSame( 'published', $second_row['status'] );
		$this->assertSame( (int) $first_row['id'] + 1, (int) $second_row['id'] );

		wp_delete_file( (string) $first_row['path'] );
		wp_delete_file( (string) $second_row['path'] );
	}

	/**
	 * A missing facility identifier blocks generation and is archived.
	 *
	 * @return void
	 */
	public function test_missing_identifier_blocks_generation() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $this->locations->table(), array( 'identifier' => '' ), array( 'id' => $this->location_id ) );

		$result = Plugin::instance()->generation()->generate( $this->location_id, 'csv' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_store_identifier', $result->get_error_code() );

		$rows = Plugin::instance()->publications()->for_location( $this->location_id );

		$this->assertNotEmpty( $rows );
		$this->assertSame( 'failed', $rows[0]['status'] );
	}

	/**
	 * Expired files are removed after retention while rows are kept.
	 *
	 * @return void
	 */
	public function test_cleanup_removes_files_after_retention() {
		$this->create_item( 'Jabuka', 'JAB-1', 1.99 );

		$result = Plugin::instance()->generation()->generate( $this->location_id, 'csv', array( 'force' => true ) );

		$this->assertIsArray( $result );

		$id  = (int) $result['publication']['id'];
		$row = Plugin::instance()->publications()->get( $id );

		Plugin::instance()->publications()->update(
			$id,
			array(
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
			)
		);

		\SidrenaCijena\Cron\CleanupJob::run();

		$this->assertFileDoesNotExist( (string) $row['path'] );

		$expired = Plugin::instance()->publications()->get( $id );

		$this->assertSame( 'expired', $expired['status'] );
		$this->assertNotEmpty( $expired['expired_at'] );
		$this->assertSame( (string) $row['checksum'], (string) $expired['checksum'], 'Archive rows are never destroyed.' );
	}
}
