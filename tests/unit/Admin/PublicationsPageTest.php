<?php
/**
 * Publications screen tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\PublicationsPage;
use SidrenaCijena\Database\PublicationRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * Publication repository double.
 */
class PublicationsPageTestRepository extends PublicationRepository {

	/**
	 * Items.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public $items = array();

	/**
	 * Index.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public function index( array $args = array() ) {
		return array(
			'items' => $this->items,
			'total' => count( $this->items ),
		);
	}
}

/**
 * @covers \SidrenaCijena\Admin\PublicationsPage
 */
class PublicationsPageTest extends TestCase {

	/**
	 * Repository double.
	 *
	 * @var PublicationsPageTestRepository
	 */
	protected $repo;

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$_GET                               = array();
		$GLOBALS['sidrena_test_options']    = array();
		$GLOBALS['sidrena_test_transients'] = array();

		$this->repo = new PublicationsPageTestRepository();
		PluginSwap::swap( 'publications', $this->repo );
	}

	/**
	 * Empty state includes busy form markup.
	 *
	 * @return void
	 */
	public function test_render_empty_state_includes_busy_form(): void {
		ob_start();
		PublicationsPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'sidrena-busy-form', $html );
		$this->assertStringContainsString( 'sidrena-btn-busy', $html );
		$this->assertStringContainsString( 'No price lists have been published yet', $html );
	}

	/**
	 * Publications table renders published rows and formatted datetimes.
	 *
	 * @return void
	 */
	public function test_render_lists_published_price_lists(): void {
		$this->repo->items = array(
			array(
				'id'           => 1,
				'filename'     => 'PRODAVAONICA_ILICA-1_P1_1_28092026_0800.csv',
				'format'       => 'csv',
				'location_id'  => 1,
				'published_at' => '2026-09-28 08:00:00',
				'expires_at'   => '2026-10-28 08:00:00',
				'item_count'   => 25,
				'checksum'     => 'abcdef1234567890',
				'status'       => 'published',
			),
		);

		ob_start();
		PublicationsPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'PRODAVAONICA_ILICA-1_P1_1_28092026_0800.csv', $html );
		$this->assertStringContainsString( 'Published', $html );
		$this->assertStringContainsString( '28.09.2026. 10:00', $html );
	}
}
