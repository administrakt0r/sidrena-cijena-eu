<?php
/**
 * WooCommerce availability detection.
 *
 * @package SidrenaCijena\WooCommerce
 */

namespace SidrenaCijena\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Detects whether WooCommerce is active and usable.
 */
final class WooCommerceDetector {

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * WooCommerce version, when available.
	 *
	 * @return string
	 */
	public static function version() {
		if ( defined( 'WC_VERSION' ) ) {
			return (string) WC_VERSION;
		}

		return '';
	}

	/**
	 * Whether a product exists.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function product_exists( $product_id ) {
		return self::is_active() && wc_get_product( (int) $product_id ) instanceof \WC_Product;
	}
}
