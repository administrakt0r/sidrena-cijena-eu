<?php
/**
 * Shared admin entity edit links.
 *
 * @package Titanworks\SidrenaCijena\Admin
 */

namespace Titanworks\SidrenaCijena\Admin;

use Titanworks\SidrenaCijena\Support\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves edit URLs and renders accessible admin navigation links for catalog entities,
 * locations, and settings.
 */
class AdminLinks {

	/**
	 * Resolve an admin edit URL for a given entity type and ID.
	 *
	 * @param string $entity_type Entity type slug ('product', 'item', 'variation', 'location', 'rule').
	 * @param int    $entity_id   Entity ID.
	 * @return string|null Edit URL if resolvable and permitted, null otherwise.
	 */
	public static function edit_url( $entity_type, $entity_id ) {
		$id = (int) $entity_id;
		if ( $id <= 0 && 'rule' !== $entity_type ) {
			return null;
		}

		$entity_type = (string) $entity_type;

		if ( in_array( $entity_type, array( 'catalog_item', 'product', 'item', 'variation' ), true ) ) {
			if ( function_exists( 'get_edit_post_link' ) ) {
				$url = get_edit_post_link( $id );
				return $url ? (string) $url : null;
			}
			return null;
		}

		if ( 'location' === $entity_type ) {
			return add_query_arg(
				array(
					'page'     => LocationsPage::SLUG,
					'location' => $id,
				),
				admin_url( 'admin.php' )
			);
		}

		if ( 'publication' === $entity_type ) {
			return add_query_arg(
				array(
					'page' => PublicationsPage::SLUG,
				),
				admin_url( 'admin.php' )
			);
		}

		if ( 'rule' === $entity_type ) {
			return add_query_arg(
				array(
					'page' => SettingsPage::SLUG,
					'tab'  => 'legal',
				),
				admin_url( 'admin.php' )
			);
		}

		return null;
	}

	/**
	 * Render an accessible HTML edit link for an entity, falling back to plain escaped text.
	 *
	 * @param string $entity_type  Entity type slug.
	 * @param int    $entity_id    Entity ID.
	 * @param string $custom_label Optional custom display text.
	 * @return string Safe HTML string.
	 */
	public static function render_entity_link( $entity_type, $entity_id, $custom_label = '' ) {
		$label = '' !== $custom_label
			? $custom_label
			: Labels::entity_type( $entity_type ) . ' #' . (int) $entity_id;

		$url = self::edit_url( $entity_type, $entity_id );
		if ( $url ) {
			return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}

		return esc_html( $label );
	}

	/**
	 * Render an accessible HTML edit link for a product or standalone item.
	 *
	 * @param int    $product_id   Product / item post ID.
	 * @param string $display_text Label or product name to display.
	 * @return string Safe HTML string.
	 */
	public static function render_item_link( $product_id, $display_text ) {
		$url = self::edit_url( 'product', $product_id );
		if ( $url ) {
			return '<a href="' . esc_url( $url ) . '">' . esc_html( $display_text ) . '</a>';
		}

		return esc_html( $display_text );
	}
}
