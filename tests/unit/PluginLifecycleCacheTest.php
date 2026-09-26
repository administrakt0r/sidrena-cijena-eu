<?php
/**
 * Unit tests for catalog lifecycle cache invalidation.
 *
 * @package SidrenaCijena\Tests\Unit
 */

namespace SidrenaCijena\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Api\RestCache;
use SidrenaCijena\Plugin;
use SidrenaCijena\Pricing\AnchorCache;
use SidrenaCijena\Tests\Support\PluginSwap;

require_once __DIR__ . '/PluginSwap.php';

/**
 * Post double carrying the fields the lifecycle handlers read.
 */
class LifecycleTestPost {

	/**
	 * Post type.
	 *
	 * @var string
	 */
	public $post_type = 'sidrena_item';

	/**
	 * Post status.
	 *
	 * @var string
	 */
	public $post_status = 'publish';
}

/**
 * REST cache double counting flushes.
 */
class CountingRestCache extends RestCache {

	/**
	 * Flush calls.
	 *
	 * @var int
	 */
	public $flushes = 0;

	/**
	 * Record a flush.
	 *
	 * @return void
	 */
	public function flush() {
		++$this->flushes;
	}
}

/**
 * Anchor cache double counting flushes.
 */
class CountingAnchorCache extends AnchorCache {

	/**
	 * Flush calls.
	 *
	 * @var int
	 */
	public $flushes = 0;

	/**
	 * Record a flush.
	 *
	 * @return void
	 */
	public function flush() {
		++$this->flushes;
	}
}

/**
 * @covers \SidrenaCijena\Plugin::catalog_post_types
 * @covers \SidrenaCijena\Plugin::flush_on_catalog_status_change
 * @covers \SidrenaCijena\Plugin::flush_on_catalog_item_deleted
 */
class PluginLifecycleCacheTest extends TestCase {

	/**
	 * REST cache double.
	 *
	 * @var CountingRestCache
	 */
	protected $rest_cache;

	/**
	 * Anchor cache double.
	 *
	 * @var CountingAnchorCache
	 */
	protected $anchor_cache;

	/**
	 * Swap in counting cache doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->rest_cache   = new CountingRestCache();
		$this->anchor_cache = new CountingAnchorCache();

		PluginSwap::swap( 'rest_cache', $this->rest_cache );
		PluginSwap::swap( 'anchor_cache', $this->anchor_cache );
	}

	/**
	 * Restore the container.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PluginSwap::swap( 'rest_cache', null );
		PluginSwap::swap( 'anchor_cache', null );

		parent::tearDown();
	}

	/**
	 * Build a post double.
	 *
	 * @param string $post_type   Post type.
	 * @param string $post_status Post status.
	 * @return LifecycleTestPost
	 */
	protected function post( $post_type = 'sidrena_item', $post_status = 'publish' ) {
		$post              = new LifecycleTestPost();
		$post->post_type   = $post_type;
		$post->post_status = $post_status;

		return $post;
	}

	/**
	 * Both caches flush exactly once.
	 *
	 * @return void
	 */
	protected function assert_flushed_once() {
		$this->assertSame( 1, $this->rest_cache->flushes );
		$this->assertSame( 1, $this->anchor_cache->flushes );
	}

	/**
	 * The catalog post types cover the standalone catalog and WooCommerce.
	 *
	 * @return void
	 */
	public function test_catalog_post_types_cover_both_catalogs() {
		$types = Plugin::catalog_post_types();

		$this->assertContains( 'sidrena_item', $types );
		$this->assertContains( 'product', $types );
		$this->assertContains( 'product_variation', $types );
	}

	/**
	 * Trashing a published catalog item flushes the caches.
	 *
	 * @return void
	 */
	public function test_trashing_a_catalog_item_flushes_caches() {
		Plugin::instance()->flush_on_catalog_status_change( 'trash', 'publish', $this->post() );

		$this->assert_flushed_once();
	}

	/**
	 * Publishing a draft catalog item flushes the caches.
	 *
	 * @return void
	 */
	public function test_publishing_a_catalog_item_flushes_caches() {
		Plugin::instance()->flush_on_catalog_status_change( 'publish', 'draft', $this->post( 'product', 'draft' ) );

		$this->assert_flushed_once();
	}

	/**
	 * Unpublishing a variation flushes the caches.
	 *
	 * @return void
	 */
	public function test_unpublishing_a_variation_flushes_caches() {
		Plugin::instance()->flush_on_catalog_status_change( 'draft', 'publish', $this->post( 'product_variation' ) );

		$this->assert_flushed_once();
	}

	/**
	 * Saving a published item without changing status keeps the cache warm.
	 *
	 * @return void
	 */
	public function test_content_edits_of_published_items_do_not_flush() {
		Plugin::instance()->flush_on_catalog_status_change( 'publish', 'publish', $this->post() );

		$this->assertSame( 0, $this->rest_cache->flushes );
		$this->assertSame( 0, $this->anchor_cache->flushes );
	}

	/**
	 * Non-catalog post types never flush the caches.
	 *
	 * @return void
	 */
	public function test_non_catalog_status_changes_do_not_flush() {
		Plugin::instance()->flush_on_catalog_status_change( 'trash', 'publish', $this->post( 'post' ) );
		Plugin::instance()->flush_on_catalog_status_change( 'publish', 'draft', $this->post( 'page' ) );

		$this->assertSame( 0, $this->rest_cache->flushes );
		$this->assertSame( 0, $this->anchor_cache->flushes );
	}

	/**
	 * Deleting a catalog item flushes the caches.
	 *
	 * @return void
	 */
	public function test_deleting_a_catalog_item_flushes_caches() {
		Plugin::instance()->flush_on_catalog_item_deleted( 55, $this->post( 'product' ) );

		$this->assert_flushed_once();
	}

	/**
	 * Deleting a non-catalog item keeps the cache warm.
	 *
	 * @return void
	 */
	public function test_deleting_a_non_catalog_item_does_not_flush() {
		Plugin::instance()->flush_on_catalog_item_deleted( 55, $this->post( 'post' ) );

		$this->assertSame( 0, $this->rest_cache->flushes );
		$this->assertSame( 0, $this->anchor_cache->flushes );
	}
}
