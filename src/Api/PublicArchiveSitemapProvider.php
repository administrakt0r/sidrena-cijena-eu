<?php
/**
 * Adds the public price-list archive to the WordPress sitemap.
 *
 * @package Titanworks\SidrenaCijena\Api
 */

namespace Titanworks\SidrenaCijena\Api;

defined( 'ABSPATH' ) || exit;

/**
 * One-URL sitemap provider for the generated price-list archive.
 */
final class PublicArchiveSitemapProvider extends \WP_Sitemaps_Provider {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name        = 'sidrena-cijena';
		$this->object_type = 'sidrena-cijena';
	}

	/**
	 * Return the archive URL.
	 *
	 * @param int    $page_num       Sitemap page.
	 * @param string $object_subtype Object subtype.
	 * @return array<int,array<string,string>>
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		unset( $object_subtype );

		if ( 1 !== (int) $page_num ) {
			return array();
		}

		return array( array( 'loc' => home_url( '/' . PublicPage::STEM_ARCHIVE . '/' ) ) );
	}

	/**
	 * There is only one sitemap page.
	 *
	 * @param string $object_subtype Object subtype.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		unset( $object_subtype );

		return 1;
	}
}
