<?php
/**
 * Reference-date resolution tests.
 *
 * @package Titanworks\SidrenaCijena\Tests
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Compliance;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Catalog\CatalogItem;
use Titanworks\SidrenaCijena\Compliance\ReferenceDateResolver;
use Titanworks\SidrenaCijena\Compliance\RuleConfig;

/**
 * @covers \Titanworks\SidrenaCijena\Compliance\ReferenceDateResolver
 */
class ReferenceDateResolverTest extends TestCase {

	/**
	 * Reset options before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['twbce_test_options'] = array();
		update_option( RuleConfig::OPTION, RuleConfig::defaults(), false );
	}

	/**
	 * Build a catalog item.
	 *
	 * @param array $overrides Field overrides.
	 * @return CatalogItem
	 */
	private function item( array $overrides = array() ) {
		return new CatalogItem(
			array_merge(
				array(
					'id'   => 1,
					'name' => 'Test item',
				),
				$overrides
			)
		);
	}

	/**
	 * General reference date is the default.
	 *
	 * @return void
	 */
	public function test_general_reference_date_is_default() {
		$resolved = ReferenceDateResolver::resolve( $this->item() );

		$this->assertSame( RuleConfig::DEFAULT_GENERAL_DATE, $resolved['date'] );
		$this->assertSame( ReferenceDateResolver::RULE_GENERAL, $resolved['rule'] );
		$this->assertFalse( $resolved['legacy'] );
	}

	/**
	 * Mapped categories use the legacy date.
	 *
	 * @return void
	 */
	public function test_mapped_category_uses_legacy_date() {
		update_option(
			RuleConfig::OPTION,
			array_merge( RuleConfig::defaults(), array( 'legacy_category_slugs' => array( 'hrana' ) ) ),
			false
		);

		$resolved = ReferenceDateResolver::resolve( $this->item( array( 'categories' => array( 'hrana', 'pica' ) ) ) );

		$this->assertSame( RuleConfig::DEFAULT_LEGACY_DATE, $resolved['date'] );
		$this->assertSame( ReferenceDateResolver::RULE_LEGACY, $resolved['rule'] );
		$this->assertTrue( $resolved['legacy'] );
	}

	/**
	 * Unmapped categories use the general date.
	 *
	 * @return void
	 */
	public function test_unmapped_category_uses_general_date() {
		update_option(
			RuleConfig::OPTION,
			array_merge( RuleConfig::defaults(), array( 'legacy_category_slugs' => array( 'hrana' ) ) ),
			false
		);

		$resolved = ReferenceDateResolver::resolve( $this->item( array( 'categories' => array( 'odjeca' ) ) ) );

		$this->assertSame( RuleConfig::DEFAULT_GENERAL_DATE, $resolved['date'] );
		$this->assertFalse( $resolved['legacy'] );
	}

	/**
	 * Disabled legacy treatment falls back to the general date.
	 *
	 * @return void
	 */
	public function test_disabled_legacy_treatment_uses_general_date() {
		update_option(
			RuleConfig::OPTION,
			array_merge(
				RuleConfig::defaults(),
				array(
					'legacy_enabled'        => false,
					'legacy_category_slugs' => array( 'hrana' ),
				)
			),
			false
		);

		$resolved = ReferenceDateResolver::resolve( $this->item( array( 'categories' => array( 'hrana' ) ) ) );

		$this->assertSame( RuleConfig::DEFAULT_GENERAL_DATE, $resolved['date'] );
		$this->assertFalse( $resolved['legacy'] );
	}

	/**
	 * Changing the configuration changes resolution without touching history.
	 *
	 * @return void
	 */
	public function test_configuration_change_changes_resolution() {
		$item = $this->item();

		$this->assertSame( RuleConfig::DEFAULT_GENERAL_DATE, ReferenceDateResolver::date( $item ) );

		RuleConfig::update( array( 'general_reference_date' => '2027-01-01' ), 7, 'Test change' );

		$this->assertSame( '2027-01-01', ReferenceDateResolver::date( $item ) );
		$this->assertCount( 1, RuleConfig::history() );
	}

	/**
	 * Settings API sanitization must leave the option write to WordPress.
	 *
	 * @return void
	 */
	public function test_rule_sanitizer_does_not_recursively_save_the_option() {
		$GLOBALS['twbce_test_option_sanitizers'][ RuleConfig::OPTION ] = array( RuleConfig::class, 'sanitize' );

		try {
			$sanitized = RuleConfig::sanitize( array( 'general_reference_date' => '2027-01-01' ) );
			$this->assertSame( RuleConfig::DEFAULT_GENERAL_DATE, RuleConfig::get( 'general_reference_date' ) );
			$this->assertSame( '2027-01-01', $sanitized['general_reference_date'] );
			$this->assertCount( 1, RuleConfig::history() );

			RuleConfig::update( array( 'legacy_enabled' => false ), 7, 'Test change' );
			$this->assertFalse( RuleConfig::get( 'legacy_enabled' ) );
		} finally {
			unset( $GLOBALS['twbce_test_option_sanitizers'][ RuleConfig::OPTION ] );
		}
	}

	/**
	 * The rule identifier helper matches resolve().
	 *
	 * @return void
	 */
	public function test_rule_helper() {
		update_option(
			RuleConfig::OPTION,
			array_merge( RuleConfig::defaults(), array( 'legacy_category_slugs' => array( 'hrana' ) ) ),
			false
		);

		$this->assertSame(
			ReferenceDateResolver::RULE_LEGACY,
			ReferenceDateResolver::rule( $this->item( array( 'categories' => array( 'hrana' ) ) ) )
		);
	}
}
