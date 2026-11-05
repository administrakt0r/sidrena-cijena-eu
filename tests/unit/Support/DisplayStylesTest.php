<?php
/**
 * Anchor display presets tests.
 *
 * @package SidrenaCijena\Tests\Unit\Support
 */

namespace SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Support\DisplayStyles;

/**
 * @covers \SidrenaCijena\Support\DisplayStyles
 */
class DisplayStylesTest extends TestCase {

	/**
	 * Verify that presets returns expected predefined styles and labels.
	 *
	 * @return void
	 */
	public function test_presets_returns_expected_style_map() {
		$presets = DisplayStyles::presets();

		$this->assertIsArray( $presets );
		$this->assertNotEmpty( $presets );

		$expected_keys = array(
			'tooltip',
			'plain',
			'badge',
			'card',
			'underline',
			'outline',
			'stripe',
			'soft',
			'mono',
			'pill',
			'minimal',
			'tag',
			'accent',
			'subtle',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $presets );
			$this->assertIsString( $presets[ $key ] );
			$this->assertNotEmpty( $presets[ $key ] );
		}
	}
}
