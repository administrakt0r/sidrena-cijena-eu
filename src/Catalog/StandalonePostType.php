<?php
/**
 * Standalone catalog item post type.
 *
 * Used when WooCommerce is absent, so the full compliance workflow remains
 * available on a manually managed catalog (FR-084, FR-091).
 *
 * @package SidrenaCijena\Catalog
 */

namespace SidrenaCijena\Catalog;

use SidrenaCijena\Admin\Capabilities;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the private `sidrena_item` post type, its taxonomies, meta fields
 * and price-change capture.
 */
final class StandalonePostType {

	/**
	 * Post type name.
	 */
	const POST_TYPE = 'sidrena_item';

	/**
	 * Brand taxonomy.
	 */
	const TAX_BRAND = 'sidrena_brand';

	/**
	 * Item type taxonomy.
	 */
	const TAX_TYPE = 'sidrena_item_type';

	/**
	 * Register the post type and taxonomies.
	 *
	 * @return void
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Standalone catalog', 'titanworks-sidrena-bazna-cijena' ),
					'singular_name' => __( 'Catalog item', 'titanworks-sidrena-bazna-cijena' ),
					'add_new_item'  => __( 'Add catalog item', 'titanworks-sidrena-bazna-cijena' ),
					'edit_item'     => __( 'Edit catalog item', 'titanworks-sidrena-bazna-cijena' ),
					'search_items'  => __( 'Search catalog items', 'titanworks-sidrena-bazna-cijena' ),
					'not_found'     => __( 'No catalog items found.', 'titanworks-sidrena-bazna-cijena' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-tag',
				'supports'     => array( 'title', 'editor' ),
				'has_archive'  => false,
				'rewrite'      => false,
				'show_in_rest' => false,
			)
		);

		register_taxonomy(
			self::TAX_BRAND,
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Brands', 'titanworks-sidrena-bazna-cijena' ),
					'singular_name' => __( 'Brand', 'titanworks-sidrena-bazna-cijena' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'hierarchical' => false,
				'show_in_rest' => false,
			)
		);

		register_taxonomy(
			self::TAX_TYPE,
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Item types', 'titanworks-sidrena-bazna-cijena' ),
					'singular_name' => __( 'Item type', 'titanworks-sidrena-bazna-cijena' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'hierarchical' => false,
				'show_in_rest' => false,
			)
		);

		register_taxonomy_for_object_type( 'category', self::POST_TYPE );

		self::seed_terms();
	}

	/**
	 * Seed the product/service terms once.
	 *
	 * @return void
	 */
	public static function seed_terms() {
		if ( get_option( 'sidrena_cijena_terms_seeded' ) ) {
			return;
		}

		if ( ! term_exists( 'product', self::TAX_TYPE ) ) {
			wp_insert_term( 'product', self::TAX_TYPE, array( 'slug' => 'product' ) );
		}

		if ( ! term_exists( 'service', self::TAX_TYPE ) ) {
			wp_insert_term( 'service', self::TAX_TYPE, array( 'slug' => 'service' ) );
		}

		update_option( 'sidrena_cijena_terms_seeded', 1, false );
	}

	/**
	 * Register the meta box.
	 *
	 * @return void
	 */
	public static function register_meta_box() {
		add_meta_box(
			'sidrena-cijena-item-fields',
			__( 'Sidrena cijena - item data', 'titanworks-sidrena-bazna-cijena' ),
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Meta box fields.
	 *
	 * @return array<string,array<mixed>>
	 */
	public static function fields() {
		return array(
			'_sidrena_sku'           => array(
				'label'       => __( 'SKU / code', 'titanworks-sidrena-bazna-cijena' ),
				'type'        => 'text',
				'placeholder' => __( 'e.g. ART-001', 'titanworks-sidrena-bazna-cijena' ),
				'description' => __( 'Catalog SKU or internal product code.', 'titanworks-sidrena-bazna-cijena' ),
			),
			'_sidrena_barcode'       => array(
				'label'       => __( 'Barcode', 'titanworks-sidrena-bazna-cijena' ),
				'type'        => 'text',
				'placeholder' => __( 'e.g. 3850123456789', 'titanworks-sidrena-bazna-cijena' ),
				'description' => __( 'EAN, UPC, or GTIN barcode.', 'titanworks-sidrena-bazna-cijena' ),
			),
			'_sidrena_unit'          => array(
				'label'       => __( 'Unit of measure', 'titanworks-sidrena-bazna-cijena' ),
				'type'        => 'text',
				'placeholder' => __( 'e.g. kom, kg, l', 'titanworks-sidrena-bazna-cijena' ),
				'description' => __( 'Standard unit of measure (e.g. piece, kg, liter).', 'titanworks-sidrena-bazna-cijena' ),
			),
			'_sidrena_unit_price'    => array(
				'label'       => __( 'Price per unit of measure', 'titanworks-sidrena-bazna-cijena' ),
				'type'        => 'price',
				'placeholder' => __( 'e.g. 9.99', 'titanworks-sidrena-bazna-cijena' ),
				'description' => __( 'Calculated selling price per unit of measure.', 'titanworks-sidrena-bazna-cijena' ),
			),
			'_sidrena_regular_price' => array(
				'label'       => __( 'Regular price', 'titanworks-sidrena-bazna-cijena' ),
				'type'        => 'price',
				'placeholder' => __( 'e.g. 19.99', 'titanworks-sidrena-bazna-cijena' ),
				'description' => __( 'Regular selling price before discounts.', 'titanworks-sidrena-bazna-cijena' ),
			),
			'_sidrena_sale_price'    => array(
				'label'       => __( 'Sale price', 'titanworks-sidrena-bazna-cijena' ),
				'type'        => 'price',
				'placeholder' => __( 'e.g. 14.99', 'titanworks-sidrena-bazna-cijena' ),
				'description' => __( 'Promotional selling price if currently discounted.', 'titanworks-sidrena-bazna-cijena' ),
			),
			'_sidrena_availability'  => array(
				'label'   => __( 'Availability', 'titanworks-sidrena-bazna-cijena' ),
				'type'    => 'select',
				'options' => array(
					'available'   => __( 'Available', 'titanworks-sidrena-bazna-cijena' ),
					'unavailable' => __( 'Unavailable', 'titanworks-sidrena-bazna-cijena' ),
				),
			),
			'_sidrena_currency'      => array(
				'label'       => __( 'Currency', 'titanworks-sidrena-bazna-cijena' ),
				'type'        => 'text',
				'placeholder' => __( 'e.g. EUR', 'titanworks-sidrena-bazna-cijena' ),
				'description' => __( 'Three-letter currency code (defaults to EUR).', 'titanworks-sidrena-bazna-cijena' ),
			),
			'_sidrena_first_seen'    => array(
				'label'       => __( 'First appearance date', 'titanworks-sidrena-bazna-cijena' ),
				'type'        => 'date',
				'description' => __( 'Date the item was first offered in the store (YYYY-MM-DD).', 'titanworks-sidrena-bazna-cijena' ),
			),
			'_sidrena_tax_inclusive' => array(
				'label' => __( 'Prices include tax', 'titanworks-sidrena-bazna-cijena' ),
				'type'  => 'checkbox',
			),
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'sidrena_cijena_save_item', 'sidrena_cijena_item_nonce' );

		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( self::fields() as $key => $field ) {
			$value       = get_post_meta( $post->ID, $key, true );
			$placeholder = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '';

			echo '<tr>';
			echo '<th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . '</label></th>';
			echo '<td>';

			switch ( $field['type'] ) {
				case 'select':
					echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
					foreach ( $field['options'] as $option_value => $option_label ) {
						echo '<option value="' . esc_attr( $option_value ) . '"' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
					}
					echo '</select>';
					break;
				case 'checkbox':
					echo '<input type="checkbox" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="1"' . checked( (bool) $value, true, false ) . ' />';
					break;
				case 'date':
					echo '<input type="date" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '" />';
					break;
				default:
					echo '<input type="text" class="regular-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '"' . ( '' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' ) . ' />';
					break;
			}

			if ( ! empty( $field['description'] ) ) {
				echo '<p class="description">' . esc_html( (string) $field['description'] ) . '</p>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';

		echo '<p class="description">' . esc_html__( 'Price changes are recorded in the price history; the anchor price is recorded on the Sidrena cijena screens.', 'titanworks-sidrena-bazna-cijena' ) . '</p>';
	}

	/**
	 * Persist meta box fields and capture price changes.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['sidrena_cijena_item_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['sidrena_cijena_item_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'sidrena_cijena_save_item' ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::capability() ) ) {
			return;
		}

		$previous_regular = get_post_meta( $post_id, '_sidrena_regular_price', true );
		$previous_sale    = get_post_meta( $post_id, '_sidrena_sale_price', true );

		foreach ( self::fields() as $key => $field ) {
			if ( 'checkbox' === $field['type'] ) {
				$value = isset( $_POST[ $key ] ) ? 1 : 0;
			} elseif ( isset( $_POST[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field type below.
				$value = wp_unslash( $_POST[ $key ] );

				if ( in_array( $field['type'], array( 'price', 'unit_price' ), true ) ) {
					$value = Currency::to_storage( $value );
				} elseif ( 'date' === $field['type'] ) {
					$value = sanitize_text_field( (string) $value );
					$value = Dates::is_valid_date( $value ) ? $value : '';
				} else {
					$value = sanitize_text_field( (string) $value );
				}
			} else {
				continue;
			}

			update_post_meta( $post_id, $key, $value );
		}

		// Item type taxonomy.
		if ( isset( $_POST['_sidrena_item_type'] ) ) {
			$type = sanitize_key( wp_unslash( $_POST['_sidrena_item_type'] ) );
			wp_set_object_terms( $post_id, in_array( $type, array( 'product', 'service' ), true ) ? $type : 'product', self::TAX_TYPE );
		}

		$regular = get_post_meta( $post_id, '_sidrena_regular_price', true );
		$sale    = get_post_meta( $post_id, '_sidrena_sale_price', true );

		if ( '' === $regular && '' === get_post_meta( $post_id, '_sidrena_first_seen', true ) ) {
			update_post_meta( $post_id, '_sidrena_first_seen', get_the_date( 'Y-m-d', $post_id ) );
		}

		self::capture_price_changes( $post_id, $previous_regular, $previous_sale, $regular, $sale );
	}

	/**
	 * Append history rows for changed regular/sale prices.
	 *
	 * @param int          $post_id          Item ID.
	 * @param string|float $previous_regular Previous regular price.
	 * @param string|float $previous_sale    Previous sale price.
	 * @param string|float $regular          New regular price.
	 * @param string|float $sale             New sale price.
	 * @return void
	 */
	private static function capture_price_changes( $post_id, $previous_regular, $previous_sale, $regular, $sale ) {
		$history = Plugin::instance()->history();

		$item = Plugin::instance()->catalog()->get_item( $post_id );

		if ( ! $item ) {
			return;
		}

		$now = Dates::now_site()->format( 'Y-m-d H:i:s' );

		$new_regular = Currency::to_storage( $regular );
		$new_sale    = Currency::to_storage( $sale );

		$last_regular = $history->latest( $post_id, 0, 'regular' );
		$last_sale    = $history->latest( $post_id, 0, 'sale' );

		if ( null !== $new_regular && ( ! $last_regular || (float) $last_regular['price'] !== $new_regular ) ) {
			$history->insert(
				array(
					'product_id'    => $post_id,
					'item_type'     => $item->type,
					'sku'           => $item->sku,
					'price_type'    => 'regular',
					'price'         => $new_regular,
					'currency'      => $item->currency,
					'tax_inclusive' => $item->tax_inclusive,
					'valid_from'    => $now,
					'source'        => 'standalone',
					'created_by'    => get_current_user_id(),
				)
			);
		}

		if ( null !== $new_sale && ( ! $last_sale || (float) $last_sale['price'] !== $new_sale ) ) {
			$history->insert(
				array(
					'product_id'    => $post_id,
					'item_type'     => $item->type,
					'sku'           => $item->sku,
					'price_type'    => 'sale',
					'price'         => $new_sale,
					'currency'      => $item->currency,
					'tax_inclusive' => $item->tax_inclusive,
					'valid_from'    => $now,
					'source'        => 'standalone',
					'created_by'    => get_current_user_id(),
				)
			);
		}

		if ( null !== $new_regular && (float) $previous_regular !== $new_regular ) {
			/**
			 * Fires after a catalog price change is recorded.
			 *
			 * @param int         $post_id      Item ID.
			 * @param int         $variation_id Variation ID.
			 * @param float|null  $old          Previous price.
			 * @param float|null  $new          New price.
			 * @param string      $source       Source identifier.
			 */
			do_action( 'sidrena_cijena_price_changed', $post_id, 0, Currency::to_storage( $previous_regular ), $new_regular, 'standalone' );
		}

		if ( null !== $new_sale && (float) $previous_sale !== $new_sale ) {
			do_action( 'sidrena_cijena_price_changed', $post_id, 0, Currency::to_storage( $previous_sale ), $new_sale, 'standalone' );
		}
	}
}
