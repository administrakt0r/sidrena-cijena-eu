<?php
/**
 * Reference-date resolution tests.
 *
 * @package SidrenaCijena\Tests
 */

namespace SidrenaCijena\Tests\Unit\Compliance;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Compliance\ReferenceDateResolver;
use SidrenaCijena\Compliance\RuleConfig;

/**
 * @covers \SidrenaCijena\Compliance\ReferenceDateResolver
 */
class ReferenceDateResolverTest extends TestCase {

	/**
	 * Reset options before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_options'] = array();
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
