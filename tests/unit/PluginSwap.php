<?php
/**
 * Test seam for replacing Plugin services without changing production code.
 *
 * @package SidrenaCijena\Tests\Unit
 */

namespace SidrenaCijena\Tests\Support;

use SidrenaCijena\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Injects test doubles into the Plugin container's lazy service properties.
 */
class PluginSwap {

	/**
	 * Replace a lazy Plugin service (catalog, history, locations, publications, special_sales).
	 *
	 * @param string $name    Property name.
	 * @param mixed  $service Replacement service; null resets the lazy default.
	 * @return void
	 */
	public static function swap( $name, $service ) {
		$property = new \ReflectionProperty( Plugin::class, $name );
		$property->setAccessible( true );
		$property->setValue( Plugin::instance(), $service );
	}
}
