<?php
/**
 * Per-product anchor visibility in WooCommerce product cards.
 *
 * @package SidrenaCijena\WooCommerce\Display
 */

namespace SidrenaCijena\WooCommerce\Display;

use SidrenaCijena\Admin\Capabilities;
use SidrenaCijena\Support\Settings;

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
	 * Enabled by default across all products unless explicitly set to '0'
	 * on this product or disabled in global settings.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_visible( $product_id ) {
		$value = (string) get_post_meta( (int) $product_id, self::META_KEY, true );

		if ( '' === $value ) {
			return (bool) Settings::get( 'card_display_default', true );
		}

		return '1' === $value;
	}

	/**
	 * Render the preference in the native WooCommerce product editor form.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function render_control( $product_id ) {
		wp_nonce_field( self::FIELD, self::NONCE, false );
		echo '<p class="form-field"><label for="sidrena-cijena-show-in-cards">' . esc_html__( 'Product cards', 'titanworks-bazna-cijena' ) . '</label>';
		echo '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="0" />';
		echo '<input type="checkbox" id="sidrena-cijena-show-in-cards" name="' . esc_attr( self::FIELD ) . '" value="1" ' . checked( self::is_visible( $product_id ), true, false ) . ' /> ';
		echo '<span class="description">' . esc_html__( 'Show Sidrena cijena on this product’s shop cards. Enabled by default across all products; uncheck to hide on this item.', 'titanworks-bazna-cijena' ) . '</span></p>';
	}

	/**
	 * Persist the preference and barcode during WooCommerce's validated product save.
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
		if ( isset( $_POST[ self::FIELD ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) );
			$product->update_meta_data( self::META_KEY, '1' === $value ? '1' : '0' );
		}

		if ( isset( $_POST['_sidrena_barcode'] ) ) {
			$barcode = sanitize_text_field( wp_unslash( $_POST['_sidrena_barcode'] ) );
			$product->update_meta_data( '_sidrena_barcode', $barcode );
		}
	}
}
