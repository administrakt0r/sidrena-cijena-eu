<?php
/**
 * Sales-location form markup tests.
 *
 * @package SidrenaCijena\Tests\Unit\Admin
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\LocationsPage;

/**
 * Probe exposing the protected location form renderer.
 */
class LocationsPageProbe extends LocationsPage {

	/**
	 * Capture the add/edit form markup.
	 *
	 * @param array<mixed>|null $location Location row.
	 * @return string
	 */
	public static function form( $location = null ) {
		ob_start();
		self::render_form( $location );

		return (string) ob_get_clean();
	}
}

/**
 * Covers label associations and markup correctness of the location form.
 *
 * @covers \SidrenaCijena\Admin\LocationsPage::render_form
 */
class LocationsPageTest extends TestCase {

	/**
	 * Reset option state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_options'] = array();
	}

	/**
	 * Every control is associated with a visible or screen-reader label.
	 *
	 * @return void
	 */
	public function test_form_controls_have_labels() {
		$html = LocationsPageProbe::form();

		$ids = array(
			'sidrena-location-name',
			'sidrena-location-type',
			'sidrena-location-address',
			'sidrena-location-city',
			'sidrena-location-identifier',
			'sidrena-location-applies',
			'sidrena-location-categories',
			'sidrena-location-separator',
			'sidrena-location-case',
		);

		foreach ( $ids as $id ) {
			$this->assertStringContainsString( 'id="' . $id . '"', $html, 'Missing control ' . $id );
			$this->assertStringContainsString( 'for="' . $id . '"', $html, 'Missing label for ' . $id );
		}
	}

	/**
	 * The category input has an accessible name and well-formed markup.
	 *
	 * @return void
	 */
	public function test_categories_field_is_labelled_and_markup_is_valid() {
		$html = LocationsPageProbe::form();

		$this->assertStringContainsString( '<label class="screen-reader-text" for="sidrena-location-categories">', $html );
		$this->assertStringContainsString( 'name="applies_categories"', $html );
		$this->assertStringNotContainsString( '<p><input', $html );
	}

	/**
	 * Checkbox controls reflect stored values and are wrapped in labels.
	 *
	 * @return void
	 */
	public function test_checkbox_controls_are_wrapped_in_labels() {
		$html = LocationsPageProbe::form();

		$this->assertStringContainsString( '<label><input type="checkbox" name="active" value="1" checked=\'checked\' />', $html );
		$this->assertStringContainsString( '<label style="margin-right:1em;"><input type="checkbox" name="formats[]"', $html );
	}

	/**
	 * Editing prefills the stored values.
	 *
	 * @return void
	 */
	public function test_form_prefills_edited_location() {
		$html = LocationsPageProbe::form(
			array(
				'id'                => 5,
				'name'              => 'Prodavaonica Centar',
				'type'              => 'prodavaonica',
				'address'           => 'Ilica 1',
				'city'              => 'Zagreb',
				'identifier'        => 'ZAG-1',
				'active'            => 1,
				'price_list_config' => array( 'formats' => array( 'csv' ) ),
				'applies_to'        => array(
					'mode'       => 'include',
					'categories' => array( 'kava' ),
				),
			)
		);

		$this->assertStringContainsString( 'value="Prodavaonica Centar"', $html );
		$this->assertStringContainsString( 'value="Ilica 1"', $html );
		$this->assertStringContainsString( 'value="ZAG-1"', $html );
		$this->assertStringContainsString( 'value="kava"', $html );
		$this->assertStringContainsString( 'sidrena_cijena_save_location', $html );
		$this->assertStringContainsString( 'Save location', $html );
	}
}
