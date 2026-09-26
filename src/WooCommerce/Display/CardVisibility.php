<?php
/**
 * Per-product anchor visibility in WooCommerce product cards.
 *
 * @package SidrenaCijena\WooCommerce\Display
 */

namespace SidrenaCijena\WooCommerce\Display;

use SidrenaCijena\Admin\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Stores a display preference without altering legal price evidence.
 */
final class CardVisibility {
	/**
	 * Product metadata key.
	 */
	const META_KEY = '_sidrena_cijena_show_in_cards';

	/**
	 * Form field and nonce action.
	 */
	const FIELD = 'sidrena_cijena_show_in_cards';
	const NONCE = 'sidrena_cijena_card_visibility_nonce';

	/**
	 * Whether the anchor appears in an archive product card.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_visible( $product_id ) {
		return '1' === (string) get_post_meta( (int) $product_id, self::META_KEY, true );
	}

	/**
	 * Render the preference in the native WooCommerce product editor form.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function render_control( $product_id ) {
		wp_nonce_field( self::FIELD, self::NONCE );
		echo '<p class="form-field"><label for="sidrena-cijena-show-in-cards">' . esc_html__( 'Product cards', 'sidrena-cijena' ) . '</label>';
		echo '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="0" />';
		echo '<input type="checkbox" id="sidrena-cijena-show-in-cards" name="' . esc_attr( self::FIELD ) . '" value="1" ' . checked( self::is_visible( $product_id ), true, false ) . ' /> ';
		echo '<span class="description">' . esc_html__( 'Show Sidrena cijena on this product’s shop cards. The product page and price-list evidence stay visible.', 'sidrena-cijena' ) . '</span></p>';
	}

	/**
	 * Persist the preference during WooCommerce's validated product save.
	 *
	 * @param \WC_Product $product Product being saved.
	 * @return void
	 */
	public static function save( $product ) {
		if ( ! Capabilities::current_user_can() || ! isset( $_POST[ self::NONCE ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) );

		if ( ! wp_verify_nonce( $nonce, self::FIELD ) ) {
			return;
		}

		// The hidden companion field makes an unchecked box explicit.
		if ( ! isset( $_POST[ self::FIELD ] ) ) {
			return;
		}

		$value = sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) );
		$product->update_meta_data( self::META_KEY, '1' === $value ? '1' : '0' );
	}
}
