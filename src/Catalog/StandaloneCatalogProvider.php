<?php
/**
 * Catalog provider backed by the private `sidrena_item` post type.
 *
 * @package SidrenaCijena\Catalog
 */

namespace SidrenaCijena\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies catalog items from WordPress posts.
 */
class StandaloneCatalogProvider implements CatalogProvider {

	/**
	 * {@inheritDoc}
	 */
	public function get_source() {
		return 'standalone';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available() {
		return post_type_exists( StandalonePostType::POST_TYPE );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $id Item ID.
	 * @return CatalogItem|null
	 */
	public function get_item( $id ) {
		$post = get_post( (int) $id );

		if ( ! $post || StandalonePostType::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}

		return $this->build_item( $post );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $parent_id    Parent ID.
	 * @param int $variation_id Variation ID.
	 * @return CatalogItem|null
	 */
	public function get_variation( $parent_id, $variation_id ) {
		unset( $parent_id, $variation_id );

		return null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return CatalogItem[]
	 */
	public function get_items( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'page'     => 1,
				'per_page' => 100,
				'type'     => 'all',
				'include'  => array(),
				'exclude'  => array(),
				'category' => '',
				'search'   => '',
				'orderby'  => 'title',
				'order'    => 'ASC',
			)
		);

		$query_args = array(
			'post_type'      => StandalonePostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $args['per_page'] ),
			'paged'          => max( 1, (int) $args['page'] ),
			'orderby'        => 'title' === $args['orderby'] ? 'title' : 'ID',
			'order'          => 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC',
		);

		if ( ! empty( $args['include'] ) ) {
			$query_args['post__in'] = array_map( 'intval', (array) $args['include'] );
		}

		if ( ! empty( $args['exclude'] ) ) {
			$query_args['post__not_in'] = array_map( 'intval', (array) $args['exclude'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = (string) $args['search'];
		}

		$tax_query = $this->build_tax_query( $args );

		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$posts = get_posts( $query_args );

		$items = array();

		foreach ( $posts as $post ) {
			$items[] = $this->build_item( $post );
		}

		return $items;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return int
	 */
	public function count_items( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'type'     => 'all',
				'include'  => array(),
				'exclude'  => array(),
				'category' => '',
				'search'   => '',
			)
		);

		$query_args = array(
			'post_type'      => StandalonePostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		);

		if ( ! empty( $args['include'] ) ) {
			$query_args['post__in'] = array_map( 'intval', (array) $args['include'] );
		}

		if ( ! empty( $args['exclude'] ) ) {
			$query_args['post__not_in'] = array_map( 'intval', (array) $args['exclude'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = (string) $args['search'];
		}

		$tax_query = $this->build_tax_query( $args );

		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$query = new \WP_Query( $query_args );

		return (int) $query->found_posts;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return \Generator<CatalogItem>
	 */
	public function iterate( array $args = array() ) {
		$args     = wp_parse_args( $args, array( 'per_page' => 200 ) );
		$per_page = max( 1, (int) $args['per_page'] );
		$page     = 1;

		do {
			$items = $this->get_items(
				array_merge(
					$args,
					array(
						'page'     => $page,
						'per_page' => $per_page,
					)
				)
			);

			foreach ( $items as $item ) {
				yield $item;
			}

			$found = count( $items );

			++$page;
		} while ( $found === $per_page );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$categories = array();

		foreach ( $terms as $term ) {
			$categories[] = array(
				'id'   => (int) $term->term_id,
				'slug' => (string) $term->slug,
				'name' => (string) $term->name,
			);
		}

		return $categories;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $id Item ID.
	 * @return string[]
	 */
	public function get_item_categories( $id ) {
		$slugs = wp_get_post_terms( (int) $id, 'category', array( 'fields' => 'slugs' ) );

		return is_wp_error( $slugs ) ? array() : array_map( 'strval', $slugs );
	}

	/**
	 * Build a catalog item from a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return CatalogItem
	 */
	protected function build_item( $post ) {
		$type_terms = wp_get_post_terms( $post->ID, StandalonePostType::TAX_TYPE, array( 'fields' => 'slugs' ) );
		$type       = is_array( $type_terms ) && in_array( 'service', $type_terms, true ) ? 'service' : 'product';

		$brand_terms = wp_get_post_terms( $post->ID, StandalonePostType::TAX_BRAND, array( 'fields' => 'names' ) );
		$brand       = is_array( $brand_terms ) && ! empty( $brand_terms ) ? (string) $brand_terms[0] : '';

		$regular = get_post_meta( $post->ID, '_sidrena_regular_price', true );
		$sale    = get_post_meta( $post->ID, '_sidrena_sale_price', true );

		$regular = '' === $regular ? null : (float) $regular;
		$sale    = '' === $sale ? null : (float) $sale;

		$first_seen = get_post_meta( $post->ID, '_sidrena_first_seen', true );
		$first_seen = $first_seen ? (string) $first_seen : get_the_date( 'Y-m-d', $post );

		$availability = get_post_meta( $post->ID, '_sidrena_availability', true );
		$currency     = get_post_meta( $post->ID, '_sidrena_currency', true );
		$tax_meta     = get_post_meta( $post->ID, '_sidrena_tax_inclusive', true );

		return new CatalogItem(
			array(
				'id'            => (int) $post->ID,
				'type'          => $type,
				'name'          => (string) $post->post_title,
				'sku'           => (string) get_post_meta( $post->ID, '_sidrena_sku', true ),
				'brand'         => $brand,
				'barcode'       => (string) get_post_meta( $post->ID, '_sidrena_barcode', true ),
				'unit'          => (string) get_post_meta( $post->ID, '_sidrena_unit', true ),
				'unit_price'    => get_post_meta( $post->ID, '_sidrena_unit_price', true ),
				'currency'      => $currency ? (string) $currency : 'EUR',
				'availability'  => 'unavailable' === $availability ? 'unavailable' : 'available',
				'regular_price' => $regular,
				'sale_price'    => $sale,
				'current_price' => null !== $sale ? $sale : $regular,
				'tax_inclusive' => '' === $tax_meta ? true : (bool) $tax_meta,
				'first_seen'    => $first_seen ? $first_seen : null,
				'categories'    => $this->get_item_categories( $post->ID ),
				'source'        => 'standalone',
			)
		);
	}

	/**
	 * Build a tax query from supported filters.
	 *
	 * @param array<mixed> $args Query arguments.
	 * @return array<mixed>  */
	protected function build_tax_query( array $args ) {
		$tax_query = array();

		if ( isset( $args['type'] ) && 'all' !== $args['type'] && '' !== $args['type'] ) {
			$tax_query[] = array(
				'taxonomy' => StandalonePostType::TAX_TYPE,
				'field'    => 'slug',
				'terms'    => 'service' === $args['type'] ? 'service' : 'product',
			);
		}

		if ( ! empty( $args['category'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'category',
				'field'    => 'slug',
				'terms'    => (string) $args['category'],
			);
		}

		return $tax_query;
	}
}
