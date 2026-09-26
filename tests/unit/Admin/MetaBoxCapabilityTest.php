<?php
/**
 * Meta box capability regression tests.
 *
 * The anchor and special-sale meta boxes are the only admin screens that can
 * expose compliance evidence (anchor price, reference date, 30-day lowest
 * price) and offer a form that posts to `admin-post.php`. Every other screen
 * in the plugin is registered behind `manage_sidrena_cijena`, so these two
 * must be gated at registration time as well.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\AnchorMetaBox;
use SidrenaCijena\Admin\SpecialSaleMetaBox;

/**
 * @covers \SidrenaCijena\Admin\AnchorMetaBox
 * @covers \SidrenaCijena\Admin\SpecialSaleMetaBox
 */
class MetaBoxCapabilityTest extends TestCase {

	/**
	 * Reset the capability and meta box recording state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['sidrena_test_can']        = true;
		$GLOBALS['sidrena_test_meta_boxes'] = array();
		$GLOBALS['sidrena_test_post_types'] = array( 'sidrena_item' );
	}

	/**
	 * Restore the default capability.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['sidrena_test_meta_boxes'] = array();
		$GLOBALS['sidrena_test_post_types'] = array();
		unset( $GLOBALS['sidrena_test_can'] );
	}

	/**
	 * Collect the registered IDs recorded by the add_meta_box stub.
	 *
	 * @param string $id Meta box ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function registered( $id ) {
		$boxes = isset( $GLOBALS['sidrena_test_meta_boxes'] ) && is_array( $GLOBALS['sidrena_test_meta_boxes'] )
			? $GLOBALS['sidrena_test_meta_boxes']
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
		$GLOBALS['sidrena_test_can'] = false;

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
		$GLOBALS['sidrena_test_can'] = false;

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
		$this->assertSame( 'manage_sidrena_cijena', \SidrenaCijena\Admin\Capabilities::capability() );
	}
}
