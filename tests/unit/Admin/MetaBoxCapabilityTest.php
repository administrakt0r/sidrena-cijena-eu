<?php
/**
 * Meta box capability regression tests.
 *
 * The anchor and special-sale meta boxes are the only admin screens that can
 * expose compliance evidence (anchor price, reference date, 30-day lowest
 * price) and offer a form that posts to `admin-post.php`. Every other screen
 * in the plugin is registered behind `twbce_manage_prices`, so these two
 * must be gated at registration time as well.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Admin
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\AnchorMetaBox;
use Titanworks\SidrenaCijena\Admin\SpecialSaleMetaBox;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\AnchorMetaBox
 * @covers \Titanworks\SidrenaCijena\Admin\SpecialSaleMetaBox
 */
class MetaBoxCapabilityTest extends TestCase {

	/**
	 * Reset the capability and meta box recording state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['twbce_test_can']        = true;
		$GLOBALS['twbce_test_meta_boxes'] = array();
		$GLOBALS['twbce_test_post_types'] = array( 'twbce_item' );
	}

	/**
	 * Restore the default capability.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['twbce_test_meta_boxes'] = array();
		$GLOBALS['twbce_test_post_types'] = array();
		unset( $GLOBALS['twbce_test_can'] );
	}

	/**
	 * Collect the registered IDs recorded by the add_meta_box stub.
	 *
	 * @param string $id Meta box ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function registered( $id ) {
		$boxes = isset( $GLOBALS['twbce_test_meta_boxes'] ) && is_array( $GLOBALS['twbce_test_meta_boxes'] )
			? $GLOBALS['twbce_test_meta_boxes']
			: array();

		return array_values(
			array_filter(
				$boxes,
				static function ( $box ) use ( $id ) {
					return isset( $box['id'] ) && $id === $box['id'];
				}
			)
		);
	}

	/**
	 * The anchor meta box stays hidden without the plugin capability.
	 *
	 * @return void
	 */
	public function test_anchor_meta_box_is_not_registered_without_the_capability() {
		$GLOBALS['twbce_test_can'] = false;

		AnchorMetaBox::register();

		$this->assertSame( array(), self::registered( AnchorMetaBox::ID ) );
	}

	/**
	 * The anchor meta box registers for an authorised administrator.
	 *
	 * @return void
	 */
	public function test_anchor_meta_box_is_registered_with_the_capability() {
		AnchorMetaBox::register();

		$this->assertNotEmpty( self::registered( AnchorMetaBox::ID ) );
	}

	/**
	 * The special-sale meta box stays hidden without the plugin capability.
	 *
	 * @return void
	 */
	public function test_special_sale_meta_box_is_not_registered_without_the_capability() {
		$GLOBALS['twbce_test_can'] = false;

		SpecialSaleMetaBox::register();

		$this->assertSame( array(), self::registered( SpecialSaleMetaBox::ID ) );
	}

	/**
	 * The special-sale meta box registers for an authorised administrator.
	 *
	 * @return void
	 */
	public function test_special_sale_meta_box_is_registered_with_the_capability() {
		SpecialSaleMetaBox::register();

		$this->assertNotEmpty( self::registered( SpecialSaleMetaBox::ID ) );
	}

	/**
	 * The gate uses the plugin capability, not a generic admin one.
	 *
	 * @return void
	 */
	public function test_the_gate_reads_the_plugin_capability() {
		$this->assertSame( 'twbce_manage_prices', \Titanworks\SidrenaCijena\Admin\Capabilities::capability() );
	}
}
