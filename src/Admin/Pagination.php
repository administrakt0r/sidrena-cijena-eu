<?php
/**
 * Shared admin table pagination.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders one pagination markup for every admin table.
 */
class Pagination {

	/**
	 * Render the unified pagination markup below an admin table.
	 *
	 * @param int   $page     Current page (1-based).
	 * @param int   $total    Total rows across all pages.
	 * @param int   $per_page Rows per page.
	 * @param array $args     Optional. `query_arg` name carrying the page number.
	 * @return void
	 */
	public static function render( $page, $total, $per_page, array $args = array() ) {
		$query_arg = isset( $args['query_arg'] ) ? (string) $args['query_arg'] : 'paged';
		$per_page  = max( 1, (int) $per_page );
		$total     = max( 0, (int) $total );
		$page      = max( 1, (int) $page );
		$pages     = (int) ceil( $total / $per_page );

		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo '<span class="displaying-num">' . esc_html(
			sprintf(
				/* translators: %s: number of rows. */
				__( '%s item(s)', 'sidrena-cijena' ),
				number_format_i18n( $total )
			)
		) . '</span>';

		if ( $pages > 1 ) {
			echo '<nav class="tablenav-pages-nav" aria-label="' . esc_attr__( 'Table pagination', 'sidrena-cijena' ) . '">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'               => add_query_arg( $query_arg, '%#%' ),
						'format'             => '',
						'current'            => $page,
						'total'              => $pages,
						'before_page_number' => '<span class="screen-reader-text">' . __( 'Page', 'sidrena-cijena' ) . ' </span>',
					)
				)
			);
			echo '</nav>';
		}

		echo '</div></div>';
	}
}
