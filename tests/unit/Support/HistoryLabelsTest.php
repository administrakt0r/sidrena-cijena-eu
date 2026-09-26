<?php
/**
 * Unit tests for price history display labels.
 *
 * @package SidrenaCijena\Tests\Unit\Support
 */

namespace SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Support\HistoryLabels;

/**
 * @covers \SidrenaCijena\Support\HistoryLabels
 */
class HistoryLabelsTest extends TestCase {

	/**
	 * Every stored price type has a human label.
	 *
	 * @return void
	 */
	public function test_known_types_are_translated() {
		$this->assertSame( 'Regular price', HistoryLabels::type( 'regular' ) );
		$this->assertSame( 'Sale price', HistoryLabels::type( 'sale' ) );
		$this->assertSame( 'Anchor price', HistoryLabels::type( 'anchor' ) );
		$this->assertSame( 'Lowest price in the last 30 days', HistoryLabels::type( 'lowest_30_day' ) );
		$this->assertSame( 'Historical price', HistoryLabels::type( 'historical' ) );
	}

	/**
	 * Every repository type slug is covered by the label map.
	 *
	 * @return void
	 */
	public function test_all_repository_types_have_labels() {
		foreach ( PriceHistoryRepository::TYPES as $type ) {
			$this->assertNotSame( '', HistoryLabels::type( $type ) );
			$this->assertNotSame( $type, HistoryLabels::type( $type ) );
		}
	}

	/**
	 * Every stored source has a human label.
	 *
	 * @return void
	 */
	public function test_known_sources_are_translated() {
		$this->assertSame( 'WooCommerce', HistoryLabels::source( 'woocommerce' ) );
		$this->assertSame( 'Standalone catalog', HistoryLabels::source( 'standalone' ) );
		$this->assertSame( 'Manual entry', HistoryLabels::source( 'manual' ) );
		$this->assertSame( 'Bulk entry', HistoryLabels::source( 'bulk' ) );
		$this->assertSame( 'CSV import', HistoryLabels::source( 'import' ) );
		$this->assertSame( 'WP-CLI', HistoryLabels::source( 'cli' ) );
		$this->assertSame( 'Migration', HistoryLabels::source( 'migration' ) );
		$this->assertSame( 'System', HistoryLabels::source( 'system' ) );
		$this->assertSame( 'Observed first offer', HistoryLabels::source( 'observed_first_offer' ) );
	}

	/**
	 * Unknown slugs pass through unchanged.
	 *
	 * @return void
	 */
	public function test_unknown_slugs_pass_through() {
		$this->assertSame( 'custom_type', HistoryLabels::type( 'custom_type' ) );
		$this->assertSame( 'custom_source', HistoryLabels::source( 'custom_source' ) );
	}
}
