<?php
/**
 * Tests for the import preview table.
 *
 * @package SidrenaCijena\Tests\Unit
 */

namespace SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Admin\ImportPage;

/**
 * @covers \SidrenaCijena\Admin\ImportPage
 * @covers \SidrenaCijena\Admin\Pagination
 */
final class ImportPageTest extends TestCase {

	/**
	 * Reset request state between tests.
	 */
	protected function setUp(): void {
		parent::setUp();
		$_GET                               = array();
		$GLOBALS['sidrena_test_transients'] = array();
		$GLOBALS['sidrena_test_options']    = array();
	}

	/**
	 * Build a preview array with the given number of parsed rows.
	 *
	 * @param int $count Row count.
	 * @return array<string,mixed>
	 */
	private static function preview( $count ) {
		$rows = array();

		for ( $index = 0; $index < $count; $index++ ) {
			$rows[] = array(
				'index'    => $index,
				'status'   => 0 === $index ? 'invalid' : 'valid',
				'item'     => null,
				'errors'   => 0 === $index ? array( array( 'message' => 'Bad row.' ) ) : array(),
				'warnings' => array(),
				'values'   => array(
					'sku'            => 'SKU-' . $index,
					'reference_date' => '2026-09-10',
					'price'          => '12.00',
				),
			);
		}

		return array(
			'batch_id' => 'batch-test',
			'summary'  => array(
				'total'     => $count,
				'valid'     => max( 0, $count - 1 ),
				'invalid'   => min( 1, $count ),
				'duplicate' => 0,
				'overwrite' => 0,
				'unchanged' => 0,
			),
			'rows'     => $rows,
		);
	}

	/**
	 * Render the preview table markup.
	 *
	 * @param array<string,mixed> $preview Preview data.
	 * @return string
	 */
	private static function render( array $preview ) {
		ImportPageProbe::$preview = $preview;
		ob_start();
		ImportPageProbe::preview();
		return (string) ob_get_clean();
	}

	/**
	 * A 5,000-row import must not render 5,000 table rows: one page is rendered and the rest is paginated.
	 */
	public function test_preview_caps_the_rendered_rows_and_paginates(): void {
		$html = self::render( self::preview( 120 ) );

		$this->assertSame( 51, substr_count( $html, '<tr>' ), 'header row plus one page of 50 rows' );
		$this->assertStringContainsString( 'sidrena-cijena-import-preview', $html );
		$this->assertStringContainsString( 'tablenav-pages', $html );
		$this->assertStringContainsString( '120 item(s)', $html );
		$this->assertStringContainsString( 'SKU-49', $html );
		$this->assertStringNotContainsString( 'SKU-50', $html, 'rows beyond the first page are not rendered' );
	}

	/**
	 * Page two shows the next slice of rows.
	 */
	public function test_preview_renders_the_requested_page(): void {
		$_GET['paged'] = 3;
		$html          = self::render( self::preview( 120 ) );

		$this->assertStringContainsString( 'SKU-100', $html );
		$this->assertStringNotContainsString( '>SKU-99<', $html );
		$this->assertSame( 21, substr_count( $html, '<tr>' ), 'header row plus the remaining 20 rows' );
	}

	/**
	 * Statuses are resolved through the shared label resolver and the table is captioned.
	 */
	public function test_preview_translates_status_labels_and_has_a_caption(): void {
		$html = self::render( self::preview( 2 ) );

		$this->assertStringContainsString( '>Invalid</span>', $html );
		$this->assertStringContainsString( '>Valid</span>', $html );
		$this->assertStringNotContainsString( '>invalid</span>', $html );
		$this->assertStringContainsString( '<caption class="screen-reader-text">Import preview</caption>', $html );
		$this->assertStringContainsString( '<th scope="col">SKU</th>', $html );
		$this->assertStringContainsString( 'Bad row.', $html );
	}

	/**
	 * Tear down.
	 */
	protected function tearDown(): void {
		$_GET                     = array();
		ImportPageProbe::$preview = array();
		parent::tearDown();
	}
}

/**
 * Exposes the protected preview renderer.
 */
class ImportPageProbe extends ImportPage {

	/**
	 * Preview data handed to the renderer.
	 *
	 * @var array<string,mixed>
	 */
	public static $preview = array();

	/**
	 * Render the preview.
	 */
	public static function preview() {
		self::render_preview( self::$preview );
	}
}
