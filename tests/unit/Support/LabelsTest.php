<?php
/**
 * Unit tests for shared display labels.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Support
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Compliance\Finding;
use Titanworks\SidrenaCijena\Compliance\RuleConfig;
use Titanworks\SidrenaCijena\Database\LocationRepository;
use Titanworks\SidrenaCijena\Database\LogRepository;
use Titanworks\SidrenaCijena\Database\PriceHistoryRepository;
use Titanworks\SidrenaCijena\Database\PublicationRepository;
use Titanworks\SidrenaCijena\Database\SpecialSaleRepository;
use Titanworks\SidrenaCijena\Support\Labels;

/**
 * @covers \Titanworks\SidrenaCijena\Support\Labels
 */
class LabelsTest extends TestCase {

	/**
	 * Every stored price type has a human label.
	 *
	 * @return void
	 */
	public function test_known_price_types_are_translated() {
		$this->assertSame( 'Regular price', Labels::price_type( 'regular' ) );
		$this->assertSame( 'Sale price', Labels::price_type( 'sale' ) );
		$this->assertSame( 'Anchor price', Labels::price_type( 'anchor' ) );
		$this->assertSame( 'Lowest price in the last 30 days', Labels::price_type( 'lowest_30_day' ) );
		$this->assertSame( 'Historical price', Labels::price_type( 'historical' ) );
	}

	/**
	 * Every repository price type slug is covered by the label map.
	 *
	 * @return void
	 */
	public function test_all_price_types_have_labels() {
		foreach ( PriceHistoryRepository::TYPES as $type ) {
			$this->assertNotSame( '', Labels::price_type( $type ) );
			$this->assertNotSame( $type, Labels::price_type( $type ) );
		}
	}

	/**
	 * Every stored source has a human label.
	 *
	 * @return void
	 */
	public function test_known_sources_are_translated() {
		$this->assertSame( 'WooCommerce', Labels::source( 'woocommerce' ) );
		$this->assertSame( 'Standalone catalog', Labels::source( 'standalone' ) );
		$this->assertSame( 'Manual entry', Labels::source( 'manual' ) );
		$this->assertSame( 'Bulk entry', Labels::source( 'bulk' ) );
		$this->assertSame( 'CSV import', Labels::source( 'import' ) );
		$this->assertSame( 'WP-CLI', Labels::source( 'cli' ) );
		$this->assertSame( 'Migration', Labels::source( 'migration' ) );
		$this->assertSame( 'System', Labels::source( 'system' ) );
		$this->assertSame( 'Observed first offer', Labels::source( 'observed_first_offer' ) );
	}

	/**
	 * Every repository source slug is covered by the label map.
	 *
	 * @return void
	 */
	public function test_all_sources_have_labels() {
		foreach ( PriceHistoryRepository::SOURCES as $source ) {
			$this->assertNotSame( '', Labels::source( $source ) );
			$this->assertNotSame( $source, Labels::source( $source ) );
		}
	}

	/**
	 * Every publication status has a human label.
	 *
	 * @return void
	 */
	public function test_all_publication_statuses_have_labels() {
		foreach ( PublicationRepository::STATUSES as $status ) {
			$this->assertNotSame( '', Labels::publication_status( $status ) );
			$this->assertNotSame( $status, Labels::publication_status( $status ) );
		}
	}

	/**
	 * Every special-sale status has a human label.
	 *
	 * @return void
	 */
	public function test_all_sale_statuses_have_labels() {
		foreach ( SpecialSaleRepository::STATUSES as $status ) {
			$this->assertNotSame( '', Labels::sale_status( $status ) );
			$this->assertNotSame( $status, Labels::sale_status( $status ) );
		}
	}

	/**
	 * Every log level has a human label.
	 *
	 * @return void
	 */
	public function test_all_log_levels_have_labels() {
		$this->assertSame( 'Info', Labels::log_level( 'info' ) );
		$this->assertSame( 'Warning', Labels::log_level( 'warning' ) );
		$this->assertSame( 'Error', Labels::log_level( 'error' ) );

		foreach ( LogRepository::LEVELS as $level ) {
			$this->assertNotSame( $level, Labels::log_level( $level ) );
		}
	}

	/**
	 * Every log event has a human label.
	 *
	 * @return void
	 */
	public function test_all_log_events_have_labels() {
		foreach ( LogRepository::EVENTS as $event ) {
			$this->assertNotSame( '', Labels::log_event( $event ) );
			$this->assertNotSame( $event, Labels::log_event( $event ) );
		}

		$this->assertSame( 'Scheduled generation', Labels::log_event( 'export_scheduled' ) );
		$this->assertSame( 'Validation failure', Labels::log_event( 'validation_failure' ) );
	}

	/**
	 * Every finding severity has a human label.
	 *
	 * @return void
	 */
	public function test_all_severities_have_labels() {
		$this->assertSame( 'Error', Labels::severity( Finding::ERROR ) );
		$this->assertSame( 'Warning', Labels::severity( Finding::WARNING ) );
		$this->assertSame( 'Info', Labels::severity( Finding::INFO ) );
	}

	/**
	 * Known entity types have human labels.
	 *
	 * @return void
	 */
	public function test_known_entity_types_are_translated() {
		$this->assertSame( 'Catalog item', Labels::entity_type( 'catalog_item' ) );
		$this->assertSame( 'Sales location', Labels::entity_type( 'location' ) );
		$this->assertSame( 'Publication', Labels::entity_type( 'publication' ) );
	}

	/**
	 * The default sale types have human labels.
	 *
	 * @return void
	 */
	public function test_default_sale_types_are_translated() {
		foreach ( array( 'akcija', 'snizenje', 'sezonsko_snizenje', 'rasprodaja' ) as $type ) {
			$this->assertNotSame( '', Labels::sale_type( $type ) );
			$this->assertNotSame( $type, Labels::sale_type( $type ) );
		}
	}

	/**
	 * Every location type has a human label.
	 *
	 * @return void
	 */
	public function test_all_location_types_have_labels() {
		foreach ( LocationRepository::TYPES as $type ) {
			$this->assertNotSame( '', Labels::location_type( $type ) );
			$this->assertNotSame( $type, Labels::location_type( $type ) );
		}
	}

	/**
	 * Every import row status has a human label.
	 *
	 * @return void
	 */
	public function test_all_import_statuses_have_labels() {
		foreach ( array( 'valid', 'invalid', 'duplicate', 'overwrite', 'unchanged' ) as $status ) {
			$this->assertNotSame( '', Labels::import_status( $status ) );
			$this->assertNotSame( $status, Labels::import_status( $status ) );
		}
	}

	/**
	 * Known run sources have human labels.
	 *
	 * @return void
	 */
	public function test_known_run_sources_are_translated() {
		$this->assertSame( 'Scheduled run', Labels::run_source( 'scheduled' ) );
		$this->assertSame( 'Manual run', Labels::run_source( 'manual' ) );
		$this->assertSame( 'WP-CLI', Labels::run_source( 'cli' ) );
		$this->assertSame( 'custom_run_source', Labels::run_source( 'custom_run_source' ) );
	}

	/**
	 * Every legal-rule setting key has a human label.
	 *
	 * @return void
	 */
	public function test_rule_settings_are_translated() {
		foreach ( array_keys( RuleConfig::defaults() ) as $key ) {
			$this->assertNotSame( '', Labels::rule_setting( $key ) );
			$this->assertNotSame( $key, Labels::rule_setting( $key ) );
		}

		$this->assertSame( 'custom_setting', Labels::rule_setting( 'custom_setting' ) );
	}

	/**
	 * Unknown slugs pass through unchanged instead of being hidden.
	 *
	 * @return void
	 */
	public function test_unknown_slugs_pass_through() {
		$this->assertSame( 'custom_type', Labels::price_type( 'custom_type' ) );
		$this->assertSame( 'custom_source', Labels::source( 'custom_source' ) );
		$this->assertSame( 'custom_status', Labels::publication_status( 'custom_status' ) );
		$this->assertSame( 'custom_status', Labels::sale_status( 'custom_status' ) );
		$this->assertSame( 'custom_level', Labels::log_level( 'custom_level' ) );
		$this->assertSame( 'custom_event', Labels::log_event( 'custom_event' ) );
		$this->assertSame( 'CUSTOM', Labels::severity( 'CUSTOM' ) );
		$this->assertSame( 'custom_entity', Labels::entity_type( 'custom_entity' ) );
		$this->assertSame( 'custom_sale_type', Labels::sale_type( 'custom_sale_type' ) );
		$this->assertSame( 'custom_location_type', Labels::location_type( 'custom_location_type' ) );
		$this->assertSame( 'custom_import_status', Labels::import_status( 'custom_import_status' ) );
	}
}
