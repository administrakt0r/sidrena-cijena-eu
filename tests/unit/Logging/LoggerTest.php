<?php
/**
 * Security regression tests for log context sanitization.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Logging
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Logging;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Logging\Logger;

/**
 * Ensures secrets and personal data never reach stored log contexts.
 *
 * @covers \Titanworks\SidrenaCijena\Logging\Logger::sanitize_context
 */
class LoggerTest extends TestCase {

	/**
	 * Sensitive keys are stripped at any depth.
	 *
	 * @return void
	 */
	public function test_sensitive_keys_are_stripped() {
		$clean = Logger::sanitize_context(
			array(
				'password'      => 'hunter2',
				'api_key'       => 'abc',
				'customer_name' => 'Iva Horvat',
				'email'         => 'iva@example.com',
				'session_id'    => 'xyz',
				'nested'        => array(
					'token' => 't',
					'price' => 9.99,
				),
				'product_id'    => 12,
			)
		);

		$this->assertArrayNotHasKey( 'password', $clean );
		$this->assertArrayNotHasKey( 'api_key', $clean );
		$this->assertArrayNotHasKey( 'customer_name', $clean );
		$this->assertArrayNotHasKey( 'email', $clean );
		$this->assertArrayNotHasKey( 'session_id', $clean );
		$this->assertArrayNotHasKey( 'token', $clean['nested'] );
		$this->assertSame( 9.99, $clean['nested']['price'] );
		$this->assertSame( 12, $clean['product_id'] );
	}

	/**
	 * Personal data under common field names must not enter log context.
	 *
	 * @return void
	 */
	public function test_personal_identity_fields_are_stripped() {
		$clean = Logger::sanitize_context(
			array(
				'first_name' => 'Iva',
				'last_name'  => 'Horvat',
				'address'    => 'Main Street 1',
				'ip_address' => '192.0.2.1',
				'product_id' => 12,
			)
		);

		$this->assertSame( array( 'product_id' => 12 ), $clean );
	}

	/**
	 * Long strings are truncated and objects are neutralized.
	 *
	 * @return void
	 */
	public function test_values_are_truncated_and_neutralized() {
		$clean = Logger::sanitize_context(
			array(
				'message' => str_repeat( 'a', 600 ),
				'object'  => new \stdClass(),
			)
		);

		$this->assertSame( 503, strlen( $clean['message'] ) );
		$this->assertSame( '[object]', $clean['object'] );
	}

	/**
	 * Deep recursion is cut off.
	 *
	 * @return void
	 */
	public function test_depth_is_limited() {
		$deep = array( 'price' => 1.0 );

		for ( $i = 0; $i < 8; $i++ ) {
			$deep = array( 'nested' => $deep );
		}

		$result = Logger::sanitize_context( $deep );
		$level  = $result;

		for ( $i = 0; $i < 6; $i++ ) {
			$this->assertArrayHasKey( 'nested', $level );
			$level = $level['nested'];
		}

		$this->assertSame( array(), $level );
	}
}
