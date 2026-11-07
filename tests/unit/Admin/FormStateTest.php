<?php
/**
 * Form state helper tests.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit\Admin
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\FormState;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\FormState
 */
class FormStateTest extends TestCase {

	/**
	 * Reset the transient store.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['twbce_test_transients'] = array();
	}

	/**
	 * Stored values and errors are returned exactly once.
	 *
	 * @return void
	 */
	public function test_stored_values_and_errors_are_pulled_once() {
		FormState::store( 'demo-form', array( 'name' => 'Test' ), array( 'name' => 'Required.' ) );

		$first  = FormState::pull( 'demo-form' );
		$second = FormState::pull( 'demo-form' );

		$this->assertSame( array( 'name' => 'Test' ), $first['values'] );
		$this->assertSame( array( 'name' => 'Required.' ), $first['errors'] );
		$this->assertSame( array(), $second['values'] );
		$this->assertSame( array(), $second['errors'] );
	}

	/**
	 * Pulling without stored state is safe.
	 *
	 * @return void
	 */
	public function test_pull_without_state_returns_empty_arrays() {
		$state = FormState::pull( 'never-stored' );

		$this->assertSame( array(), $state['values'] );
		$this->assertSame( array(), $state['errors'] );
	}

	/**
	 * Invalid fields get aria attributes.
	 *
	 * @return void
	 */
	public function test_error_attributes_mark_invalid_controls() {
		$errors = array( 'name' => 'Required.' );

		$this->assertSame( '', FormState::error_attributes( $errors, 'other', 'sidrena-name' ) );

		$attributes = FormState::error_attributes( $errors, 'name', 'sidrena-name' );

		$this->assertStringContainsString( 'aria-invalid="true"', $attributes );
		$this->assertStringContainsString( 'aria-describedby="sidrena-name-error"', $attributes );
	}

	/**
	 * Only fields with an error print a message.
	 *
	 * @return void
	 */
	public function test_render_error_prints_the_field_message() {
		ob_start();
		FormState::render_error( array( 'name' => 'Required.' ), 'name', 'sidrena-name' );
		FormState::render_error( array(), 'other', 'sidrena-other' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="sidrena-name-error"', $html );
		$this->assertStringContainsString( 'Required.', $html );
		$this->assertStringNotContainsString( 'sidrena-other-error', $html );
	}
}
