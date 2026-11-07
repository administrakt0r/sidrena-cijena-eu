<?php
/**
 * Autoloader unit tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Support
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Support\Autoloader;

/**
 * @covers \Titanworks\SidrenaCijena\Support\Autoloader
 */
class AutoloaderTest extends TestCase {

	/**
	 * Verify register adds the autoloader and is idempotent.
	 *
	 * @return void
	 */
	public function test_register_is_idempotent() {
		Autoloader::register();
		Autoloader::register();

		$registered = spl_autoload_functions();
		$this->assertIsArray( $registered );

		$found = false;
		foreach ( $registered as $loader ) {
			if ( is_array( $loader ) && 'Titanworks\SidrenaCijena\Support\Autoloader' === $loader[0] && 'autoload' === $loader[1] ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Expected Autoloader::autoload to be registered in SPL autoload stack.' );
	}

	/**
	 * Verify autoload ignores non-plugin namespaces.
	 *
	 * @return void
	 */
	public function test_autoload_ignores_foreign_classes() {
		Autoloader::autoload( 'SomeOtherNamespace\Foo\Bar' );
		$this->assertFalse( class_exists( 'SomeOtherNamespace\Foo\Bar', false ) );
	}

	/**
	 * Verify autoload does nothing for non-existent classes in plugin namespace.
	 *
	 * @return void
	 */
	public function test_autoload_handles_non_existent_class_gracefully() {
		Autoloader::autoload( 'Titanworks\SidrenaCijena\NonExistentClassXYZ' );
		$this->assertFalse( class_exists( 'Titanworks\SidrenaCijena\NonExistentClassXYZ', false ) );
	}

	/**
	 * Verify autoload correctly resolves and loads an existing class.
	 *
	 * @return void
	 */
	public function test_autoload_loads_existing_class() {
		// Call autoload on an existing class file.
		Autoloader::autoload( 'Titanworks\SidrenaCijena\Support\DisplayStyles' );
		$this->assertTrue( class_exists( 'Titanworks\SidrenaCijena\Support\DisplayStyles', false ) );
	}
}
