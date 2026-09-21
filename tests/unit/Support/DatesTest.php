<?php
/**
 * Date helper tests.
 *
 * @package SidrenaCijena\Tests\Unit
 */

namespace SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Support\Dates;

/**
 * @covers \SidrenaCijena\Support\Dates
 */
class DatesTest extends TestCase {

	/**
	 * Date validation is strict.
	 *
	 * @return void
	 */
	public function test_is_valid_date() {
		$this->assertTrue( Dates::is_valid_date( '2026-09-10' ) );
		$this->assertTrue( Dates::is_valid_date( '2025-05-02' ) );
		$this->assertFalse( Dates::is_valid_date( '2026-9-10' ) );
		$this->assertFalse( Dates::is_valid_date( '2026-02-30' ) );
		$this->assertFalse( Dates::is_valid_date( 'not-a-date' ) );
		$this->assertFalse( Dates::is_valid_date( '' ) );
	}

	/**
	 * Calendar arithmetic crosses month boundaries.
	 *
	 * @return void
	 */
	public function test_add_days() {
		$this->assertSame( '2026-10-01', Dates::add_days( '2026-09-10', 21 ) );
		$this->assertSame( '2026-08-31', Dates::add_days( '2026-09-10', -10 ) );
		$this->assertSame( '2026-09-10', Dates::add_days( '2026-09-10', 0 ) );
	}

	/**
	 * Day differences are signed whole calendar days.
	 *
	 * @return void
	 */
	public function test_diff_days() {
		$this->assertSame( 30, Dates::diff_days( '2026-09-01', '2026-10-01' ) );
		$this->assertSame( -30, Dates::diff_days( '2026-10-01', '2026-09-01' ) );
		$this->assertSame( 0, Dates::diff_days( '2026-09-10', '2026-09-10' ) );
	}

	/**
	 * Working-day detection excludes weekends.
	 *
	 * @return void
	 */
	public function test_is_working_day() {
		$this->assertTrue( Dates::is_working_day( '2026-09-21' ) ); // Monday.
		$this->assertTrue( Dates::is_working_day( '2026-09-25' ) ); // Friday.
		$this->assertFalse( Dates::is_working_day( '2026-09-26' ) ); // Saturday.
		$this->assertFalse( Dates::is_working_day( '2026-09-27' ) ); // Sunday.
	}

	/**
	 * UTC and site time round-trip through Europe/Zagreb.
	 *
	 * @return void
	 */
	public function test_utc_roundtrip() {
		$site = Dates::parse_site( '2026-09-10 08:00:00' );

		$this->assertNotNull( $site );

		$utc = Dates::to_utc( $site );

		// September is UTC+2 in Zagreb.
		$this->assertSame( '2026-09-10 06:00:00', $utc );

		$back = Dates::from_utc( $utc );

		$this->assertNotNull( $back );
		$this->assertSame( '2026-09-10 08:00:00', $back->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Next occurrence is in the future and keeps the requested time.
	 *
	 * @return void
	 */
	public function test_next_occurrence() {
		$timestamp = Dates::next_occurrence( '08:00' );

		$this->assertGreaterThan( time() - DAY_IN_SECONDS, $timestamp );

		$site = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( Dates::timezone() );

		$this->assertSame( '08:00', $site->format( 'H:i' ) );
	}

	/**
	 * Working-day-only occurrences skip weekends.
	 *
	 * @return void
	 */
	public function test_next_occurrence_working_days_only() {
		$timestamp = Dates::next_occurrence( '08:00', true );
		$site      = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( Dates::timezone() );

		$this->assertNotContains( (int) $site->format( 'N' ), array( 6, 7 ) );
	}

	/**
	 * Verifies that now_utc returns a MySQL datetime.
	 *
	 * @return void
	 */
	public function test_now_utc_format() {
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', Dates::now_utc() );
	}
}
