<?php
/**
 * Admin post handlers capability regression tests.
 *
 * Verifies that every admin-post handler strictly enforces capabilities
 * and terminates unauthorized calls via wp_die.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\AnchorMetaBox;
use SidrenaCijena\Admin\BulkAnchorEntryPage;
use SidrenaCijena\Admin\CompliancePage;
use SidrenaCijena\Admin\ImportPage;
use SidrenaCijena\Admin\LocationsPage;
use SidrenaCijena\Admin\PriceHistoryPage;
use SidrenaCijena\Admin\PublicationsPage;
use SidrenaCijena\Admin\SetupGuidePage;
use SidrenaCijena\Admin\SpecialSaleMetaBox;
use SidrenaCijena\Admin\WelcomeBanner;
use SidrenaCijena\Cron\Scheduler;

/**
 * @covers \SidrenaCijena\Admin\AnchorMetaBox::handle_record
 * @covers \SidrenaCijena\Admin\SpecialSaleMetaBox::handle_save
 * @covers \SidrenaCijena\Admin\PriceHistoryPage::handle_correct
 * @covers \SidrenaCijena\Admin\LocationsPage::handle_save
 * @covers \SidrenaCijena\Admin\LocationsPage::handle_delete
 * @covers \SidrenaCijena\Admin\PublicationsPage::handle_generate
 * @covers \SidrenaCijena\Admin\BulkAnchorEntryPage::handle_save
 * @covers \SidrenaCijena\Admin\BulkAnchorEntryPage::handle_set_all_current
 * @covers \SidrenaCijena\Admin\SetupGuidePage::handle_complete
 * @covers \SidrenaCijena\Admin\SetupGuidePage::handle_reopen
 * @covers \SidrenaCijena\Admin\ImportPage::handle_preview
 * @covers \SidrenaCijena\Admin\ImportPage::handle_commit
 * @covers \SidrenaCijena\Admin\ImportPage::handle_report
 * @covers \SidrenaCijena\Admin\CompliancePage::handle_run
 * @covers \SidrenaCijena\Admin\CompliancePage::handle_export
 * @covers \SidrenaCijena\Admin\WelcomeBanner::handle_dismiss
 * @covers \SidrenaCijena\Cron\Scheduler::handle_dismiss_failure
 */
class AdminPostCapabilityTest extends TestCase {

	/**
	 * Reset capability state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_can'] = false;
	}

	/**
	 * Restore capability state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['sidrena_test_can'] );

		parent::tearDown();
	}

	/**
	 * Anchor record rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_anchor_record_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You are not allowed to record anchor prices.' );

		AnchorMetaBox::handle_record();
	}

	/**
	 * Special sale save rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_special_sale_save_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to manage special sales.' );

		SpecialSaleMetaBox::handle_save();
	}

	/**
	 * Price history correct rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_price_history_correct_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to perform this action.' );

		PriceHistoryPage::handle_correct();
	}

	/**
	 * Location save rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_locations_save_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to perform this action.' );

		LocationsPage::handle_save();
	}

	/**
	 * Location delete rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_locations_delete_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to perform this action.' );

		LocationsPage::handle_delete();
	}

	/**
	 * Publication generate rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_publications_generate_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to perform this action.' );

		PublicationsPage::handle_generate();
	}

	/**
	 * Bulk anchor save rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_bulk_anchor_save_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to record anchor prices.' );

		BulkAnchorEntryPage::handle_save();
	}

	/**
	 * Bulk anchor set all current rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_bulk_anchor_set_all_current_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to record anchor prices.' );

		BulkAnchorEntryPage::handle_set_all_current();
	}

	/**
	 * Setup guide complete rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_setup_guide_complete_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to perform this action.' );

		SetupGuidePage::handle_complete();
	}

	/**
	 * Setup guide reopen rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_setup_guide_reopen_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to perform this action.' );

		SetupGuidePage::handle_reopen();
	}

	/**
	 * Import preview rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_import_preview_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to import anchor data.' );

		ImportPage::handle_preview();
	}

	/**
	 * Import commit rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_import_commit_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to import anchor data.' );

		ImportPage::handle_commit();
	}

	/**
	 * Import report rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_import_report_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to download the report.' );

		ImportPage::handle_report();
	}

	/**
	 * Compliance run rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_compliance_run_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to run the compliance check.' );

		CompliancePage::handle_run();
	}

	/**
	 * Compliance export rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_compliance_export_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to export the compliance report.' );

		CompliancePage::handle_export();
	}

	/**
	 * Welcome banner dismiss rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_welcome_banner_dismiss_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to perform this action.' );

		WelcomeBanner::handle_dismiss();
	}

	/**
	 * Scheduler dismiss failure notice rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_scheduler_dismiss_failure_rejects_unauthorized_user() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have permission to perform this action.' );

		Scheduler::handle_dismiss_failure();
	}
}
