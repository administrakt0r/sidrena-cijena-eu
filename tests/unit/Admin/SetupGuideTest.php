<?php
/**
 * Setup Guide dynamic status tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\SetupGuidePage;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Database\PublicationRepository;
use SidrenaCijena\Plugin;
use SidrenaCijena\Tests\Support\PluginSwap;

require_once dirname( __DIR__ ) . '/PluginSwap.php';

/**
 * Catalog provider double with a fixed item count.
 */
class GuideFakeCatalog implements CatalogProvider {

	/**
	 * Reported item count.
	 *
	 * @var int
	 */
	public $count = 0;

	/**
	 * Source identifier.
	 *
	 * @return string
	 */
	public function get_source() {
		return 'fake';
	}

	/**
	 * Availability.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Single item.
	 *
	 * @param int $id Item ID.
	 * @return null
	 */
	public function get_item( $id ) {
		return null;
	}

	/**
	 * Variation.
	 *
	 * @param int $parent_id    Parent ID.
	 * @param int $variation_id Variation ID.
	 * @return null
	 */
	public function get_variation( $parent_id, $variation_id ) {
		return null;
	}

	/**
	 * Items.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function get_items( array $args = array() ) {
		return array();
	}

	/**
	 * Item count.
	 *
	 * @param array $args Query args.
	 * @return int
	 */
	public function count_items( array $args = array() ) {
		return (int) $this->count;
	}

	/**
	 * Iterate.
	 *
	 * @param array $args Query args.
	 * @return \Generator
	 */
	public function iterate( array $args = array() ) {
		yield from array();
	}

	/**
	 * Categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array();
	}

	/**
	 * Item categories.
	 *
	 * @param int $id Item ID.
	 * @return array
	 */
	public function get_item_categories( $id ) {
		return array();
	}
}

/**
 * History repository double with a fixed anchor count.
 */
class GuideFakeHistory extends PriceHistoryRepository {

	/**
	 * Reported anchor count.
	 *
	 * @var int
	 */
	public $anchor_count = 0;

	/**
	 * Count records.
	 *
	 * @param array $args Query args.
	 * @return int
	 */
	public function count( array $args = array() ) {
		return (int) $this->anchor_count;
	}
}

/**
 * Location repository double with in-memory rows.
 */
class GuideFakeLocations extends LocationRepository {

	/**
	 * Rows.
	 *
	 * @var array<int,array>
	 */
	public $rows = array();

	/**
	 * All locations.
	 *
	 * @param bool $active_only Active only flag.
	 * @return array<int,array>
	 */
	public function all( $active_only = false ) {
		return $this->rows;
	}
}

/**
 * Publication repository double with an optional last publication.
 */
class GuideFakePublications extends PublicationRepository {

	/**
	 * Last successful publication.
	 *
	 * @var array|null
	 */
	public $last = null;

	/**
	 * Last successful publication.
	 *
	 * @param int $location_id Optional location filter.
	 * @return array|null
	 */
	public function last_successful( $location_id = 0 ) {
		return $this->last;
	}
}

/**
 * @covers \SidrenaCijena\Admin\SetupGuidePage
 */
class SetupGuideTest extends TestCase {

	/**
	 * Catalog double.
	 *
	 * @var GuideFakeCatalog
	 */
	protected $catalog;

	/**
	 * History double.
	 *
	 * @var GuideFakeHistory
	 */
	protected $history;

	/**
	 * Locations double.
	 *
	 * @var GuideFakeLocations
	 */
	protected $locations;

	/**
	 * Publications double.
	 *
	 * @var GuideFakePublications
	 */
	protected $publications;

	/**
	 * Wire fresh doubles before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__ ) . '/wc-stubs.php';

		$GLOBALS['sidrena_test_options']    = array();
		$GLOBALS['sidrena_test_transients'] = array();

		$this->catalog      = new GuideFakeCatalog();
		$this->history      = new GuideFakeHistory();
		$this->locations    = new GuideFakeLocations();
		$this->publications = new GuideFakePublications();

		PluginSwap::swap( 'catalog', $this->catalog );
		PluginSwap::swap( 'history', $this->history );
		PluginSwap::swap( 'locations', $this->locations );
		PluginSwap::swap( 'publications', $this->publications );
	}

	/**
	 * Every step reports its missing state on a fresh install.
	 *
	 * @return void
	 */
	public function test_fresh_install_reports_all_steps_incomplete() {
		$statuses = SetupGuidePage::statuses();

		$this->assertSame( '(missing)', $statuses['locations']['badge'] );
		$this->assertFalse( $statuses['locations']['complete'] );
		$this->assertSame( '(map your categories)', $statuses['rules']['badge'] );
		$this->assertFalse( $statuses['rules']['complete'] );
		$this->assertSame( '(none recorded)', $statuses['anchors']['badge'] );
		$this->assertFalse( $statuses['anchors']['complete'] );
		$this->assertSame( '(not published yet)', $statuses['lists']['badge'] );
		$this->assertFalse( $statuses['lists']['complete'] );
		$this->assertSame( '(not run yet)', $statuses['check']['badge'] );
		$this->assertFalse( $statuses['check']['complete'] );
	}

	/**
	 * A fully configured shop reports all five steps complete.
	 *
	 * @return void
	 */
	public function test_configured_shop_reports_every_step_complete() {
		$this->locations->rows       = array( array( 'id' => 1 ), array( 'id' => 2 ) );
		$this->catalog->count        = 4;
		$this->history->anchor_count = 4;
		$this->publications->last    = array(
			'id'           => 7,
			'published_at' => '2026-09-26 08:00:00',
		);

		update_option(
			RuleConfig::OPTION,
			array(
				'legacy_category_slugs'  => array( 'kava', 'caj' ),
				'general_reference_date' => '2026-09-10',
				'legacy_reference_date'  => '2025-05-02',
			),
			false
		);

		set_transient(
			'sidrena_cijena_report_' . get_current_user_id(),
			array(
				'counts' => array(
					'ERROR'   => 0,
					'WARNING' => 2,
				),
			)
		);

		$statuses = SetupGuidePage::statuses();

		$this->assertSame( '(done)', $statuses['locations']['badge'] );
		$this->assertSame( 2, $statuses['locations']['count'] );
		$this->assertTrue( $statuses['locations']['complete'] );

		$this->assertSame( '(done)', $statuses['rules']['badge'] );
		$this->assertSame( array( 'kava', 'caj' ), $statuses['rules']['slugs'] );
		$this->assertTrue( $statuses['rules']['complete'] );

		$this->assertSame( '(done: 4 recorded)', $statuses['anchors']['badge'] );
		$this->assertTrue( $statuses['anchors']['complete'] );

		$this->assertSame( '(published)', $statuses['lists']['badge'] );
		$this->assertTrue( $statuses['lists']['complete'] );

		$this->assertSame( '(clean)', $statuses['check']['badge'] );
		$this->assertTrue( $statuses['check']['complete'] );
	}

	/**
	 * Partial anchor coverage is visible without marking the step failed.
	 *
	 * @return void
	 */
	public function test_partial_anchor_coverage_is_reported() {
		$this->catalog->count        = 5;
		$this->history->anchor_count = 2;

		$statuses = SetupGuidePage::statuses();

		$this->assertSame( '(done: 2 of 5 recorded)', $statuses['anchors']['badge'] );
		$this->assertTrue( $statuses['anchors']['complete'] );
		$this->assertSame( 5, $statuses['anchors']['total'] );
	}

	/**
	 * Stored validation errors mark the compliance step as incomplete.
	 *
	 * @return void
	 */
	public function test_stored_errors_mark_compliance_step_incomplete() {
		set_transient(
			'sidrena_cijena_report_' . get_current_user_id(),
			array( 'counts' => array( 'ERROR' => 3 ) )
		);

		$statuses = SetupGuidePage::statuses();

		$this->assertSame( '(errors found)', $statuses['check']['badge'] );
		$this->assertFalse( $statuses['check']['complete'] );
		$this->assertSame( 3, $statuses['check']['errors'] );
	}

	/**
	 * The rendered checklist shows every dynamic badge.
	 *
	 * @return void
	 */
	public function test_render_uses_dynamic_status_badges() {
		$this->locations->rows       = array( array( 'id' => 1 ) );
		$this->catalog->count        = 3;
		$this->history->anchor_count = 3;
		$this->publications->last    = array( 'id' => 9 );

		set_transient(
			'sidrena_cijena_report_' . get_current_user_id(),
			array( 'counts' => array( 'ERROR' => 0 ) )
		);

		ob_start();
		SetupGuidePage::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( '(done)', $html );
		$this->assertStringContainsString( '(done: 3 recorded)', $html );
		$this->assertStringContainsString( '(published)', $html );
		$this->assertStringContainsString( '(clean)', $html );
		$this->assertStringContainsString( '(map your categories)', $html );
	}
}
