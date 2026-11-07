<?php
/**
 * Rule configuration sanitization and audit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Compliance
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Compliance;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Compliance\RuleConfig;

/**
 * @covers \Titanworks\SidrenaCijena\Compliance\RuleConfig
 */
class RuleConfigTest extends TestCase {

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
	 * Category IDs are cast with absint and category slugs are sanitized with sanitize_title.
	 *
	 * @return void
	 */
	public function test_sanitize_sanitizes_category_ids_and_slugs() {
		$sanitized = RuleConfig::sanitize(
			array(
				'legacy_category_ids'   => array( '12', '-5', 'foo', 42, '42' ),
				'legacy_category_slugs' => array( 'Hrana i Pice', 'mlijeko', 'KAFA / CAJ' ),
			)
		);

		$this->assertSame( array( 12, 5, 42 ), $sanitized['legacy_category_ids'] );
		$this->assertSame( array( 'hrana-i-pice', 'mlijeko', 'kafa-caj' ), $sanitized['legacy_category_slugs'] );
	}
}
