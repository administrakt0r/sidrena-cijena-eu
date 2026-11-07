<?php
/**
 * Tests for the shared admin pagination helper.
 *
 * @package Titanworks\SidrenaCijena\Tests\Unit
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Admin\Pagination;

/**
 * @covers \Titanworks\SidrenaCijena\Admin\Pagination
 */
final class PaginationTest extends TestCase {

	/**
	 * Render helper output.
	 *
	 * @param int   $page     Current page.
	 * @param int   $total    Total rows.
	 * @param int   $per_page Rows per page.
	 * @param array $args     Optional arguments.
	 * @return string
	 */
	private static function render( $page, $total, $per_page, array $args = array() ) {
		ob_start();
		Pagination::render( $page, $total, $per_page, $args );
		return (string) ob_get_clean();
	}

	/**
	 * The wrapper markup matches the WordPress tablenav pattern with a screen-reader count.
	 */
	public function test_render_wraps_the_links_in_tablenav_markup(): void {
		$html = self::render( 1, 120, 50 );

		$this->assertStringContainsString( '<div class="tablenav"><div class="tablenav-pages">', $html );
		$this->assertStringContainsString( '<span class="displaying-num">120 item(s)</span>', $html );
		$this->assertStringContainsString( 'aria-label="Table pagination"', $html );
		$this->assertStringContainsString( 'page-numbers current', $html );
		$this->assertStringContainsString( '<span class="screen-reader-text">Page </span>', $html );
	}

	/**
	 * A single page renders the count but no navigation.
	 */
	public function test_single_page_renders_no_navigation(): void {
		$html = self::render( 1, 10, 50 );

		$this->assertStringContainsString( '10 item(s)', $html );
		$this->assertStringNotContainsString( 'tablenav-pages-nav', $html );
		$this->assertStringNotContainsString( 'page-numbers', $html );
	}

	/**
	 * The page query argument is configurable so two tables can paginate independently.
	 */
	public function test_custom_query_arg_is_used_in_the_links(): void {
		$html = self::render( 2, 120, 20, array( 'query_arg' => 'history_paged' ) );

		$this->assertStringContainsString( 'history_paged=1', $html );
		$this->assertStringContainsString( 'history_paged=3', $html );
		$this->assertStringNotContainsString( '?paged=', $html );
	}

	/**
	 * The count goes through the locale-aware number formatter.
	 */
	public function test_count_is_formatted_for_the_locale(): void {
		$html = self::render( 1, 1234, 50 );

		$this->assertStringContainsString( '1234 item(s)', $html );
	}
}
