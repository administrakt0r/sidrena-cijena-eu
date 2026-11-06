<?php
/**
 * Template API and shortcode.
 *
 * @package SidrenaCijena\Api
 */

namespace SidrenaCijena\Api;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Settings;
use SidrenaCijena\WooCommerce\Display\PriceDisplay;

defined( 'ABSPATH' ) || exit;

/**
 * Public template functions and the `[sidrena_cijena]` shortcode.
 */
final class TemplateFunctions {

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'sidrena_cijena', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Anchor price for an item.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return float|null
	 */
	public static function get_anchor_price( $product_id, $variation_id = 0 ) {
		$item = self::item( $product_id, $variation_id );

		if ( ! $item ) {
			return null;
		}

		return Plugin::instance()->anchors()->get_anchor_price( $item );
	}

	/**
	 * Lowest 30-day price of the active special sale for an item.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return float|null
	 */
	public static function get_lowest_30_day_price( $product_id, $variation_id = 0 ) {
		$active = Plugin::instance()->special_sales()->active_map();
		$key    = (int) $product_id . ':' . (int) $variation_id;
		$sale   = isset( $active[ $key ] ) ? $active[ $key ] : null;

		if ( ! $sale || null === $sale['lowest_30_day_price'] ) {
			return null;
		}

		return (float) $sale['lowest_30_day_price'];
	}

	/**
	 * Render the anchor price block.
	 *
	 * @param int          $product_id Product ID.
	 * @param array<mixed> $args       `variation_id`, `show_current`, `show_anchor`, `show_30_day`, `label`,
	 *                                 `only_when_different`, `context`.
	 * @return string
	 */
	public static function render_anchor_price( $product_id, array $args = array() ) {
		$variation_id = isset( $args['variation_id'] ) ? (int) $args['variation_id'] : 0;
		$item         = self::item( $product_id, $variation_id );

		if ( ! $item ) {
			return '';
		}

		$display = new PriceDisplay( Plugin::instance()->anchors() );

		return $display->render_item( $item, isset( $args['context'] ) ? (string) $args['context'] : 'shortcode', $args );
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array<mixed> $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id'       => 0,
				'variation_id'     => 0,
				'show_current'     => 'yes',
				'show_anchor'      => 'yes',
				'show_30_day'      => 'yes',
				'label'            => '',
				'thirty_day_label' => '',
			),
			$atts,
			'sidrena_cijena'
		);

		$product_id = (int) $atts['product_id'];

		if ( ! $product_id ) {
			global $product;

			if ( $product instanceof \WC_Product ) {
				$product_id = (int) $product->get_id();
			}
		}

		if ( ! $product_id ) {
			return '';
		}

		$html = self::render_anchor_price(
			$product_id,
			array(
				'variation_id'     => (int) $atts['variation_id'],
				'show_current'     => self::is_yes( $atts['show_current'] ),
				'show_anchor'      => self::is_yes( $atts['show_anchor'] ),
				'show_30_day'      => self::is_yes( $atts['show_30_day'] ),
				'label'            => (string) $atts['label'],
				'thirty_day_label' => (string) $atts['thirty_day_label'],
			)
		);

		return $html;
	}

	/**
	 * Render the separate 30-day price block.
	 *
	 * @param int         $product_id   Product ID.
	 * @param int         $variation_id Variation ID.
	 * @param string|null $label        Optional display label.
	 * @return string
	 */
	public static function render_30_day_price( $product_id, $variation_id = 0, $label = null ) {
		$price = self::get_lowest_30_day_price( $product_id, $variation_id );

		if ( null === $price ) {
			return '';
		}

		$item = self::item( $product_id, $variation_id );

		if ( ! $item ) {
			return '';
		}

		if ( 'woocommerce' === $item->source ) {
			$regular    = \SidrenaCijena\Support\Currency::to_storage( $item->regular_price );
			$current    = \SidrenaCijena\Support\Currency::to_storage( $item->effective_price() );
			$sale_price = \SidrenaCijena\Support\Currency::to_storage( $item->sale_price );

			if ( null !== $regular && null !== $current ) {
				$promotional = $regular > 0 && $current < $regular;

				if ( ! $promotional && null !== $item->current_price && null !== $sale_price ) {
					$promotional = $current === $sale_price;
				}

				if ( ! $promotional ) {
					return '';
				}
			}

			$active = Plugin::instance()->special_sales()->active_map();
			$key    = (int) $product_id . ':' . (int) $variation_id;
			$sale   = isset( $active[ $key ] ) ? $active[ $key ] : null;

			if ( ! $sale ) {
				return '';
			}

			$periods    = Plugin::instance()->history()->active_sale_period_map( array( (int) $item->id ) );
			$period_key = isset( $sale['product_id'] ) ? (int) $sale['product_id'] . ':' . (int) ( isset( $sale['variation_id'] ) ? $sale['variation_id'] : 0 ) : $key;
			$period     = isset( $periods[ $period_key ] ) ? $periods[ $period_key ] : null;

			if ( ! \SidrenaCijena\Pricing\SpecialSaleOfferMatcher::matches( $item, $sale, $period ) ) {
				return '';
			}
		}

		$display_label = null !== $label && '' !== $label
			? (string) $label
			: (string) Settings::get( 'thirty_day_label', __( 'Najniža cijena u zadnjih 30 dana', 'titanworks-bazna-cijena' ) );

		/**
		 * Filters the 30-day lowest price display label.
		 *
		 * @param string      $label   Display label.
		 * @param CatalogItem $item    Catalog item.
		 * @param string      $context Rendering context.
		 */
		$display_label = (string) apply_filters( 'sidrena_cijena_thirty_day_label', $display_label, $item, 'template' );

		$args = array(
			'item'    => $item,
			'price'   => $price,
			'label'   => $display_label,
			'display' => \SidrenaCijena\Support\Currency::format( $price, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) ),
		);

		$template = locate_template( array( 'sidrena-cijena/thirty-day-price.php' ) );

		if ( ! $template ) {
			$template = SIDRENA_CIJENA_DIR . 'templates/thirty-day-price.php';
		}

		if ( ! is_readable( $template ) ) {
			return '';
		}

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $args, EXTR_SKIP );
		include $template;

		return (string) ob_get_clean();
	}

	/**
	 * Catalog item lookup.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return \SidrenaCijena\Catalog\CatalogItem|null
	 */
	protected static function item( $product_id, $variation_id = 0 ) {
		$catalog = Plugin::instance()->catalog();

		if ( $variation_id ) {
			return $catalog->get_variation( $product_id, $variation_id );
		}

		return $catalog->get_item( $product_id );
	}

	/**
	 * Interpret a shortcode boolean.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	protected static function is_yes( $value ) {
		return in_array( strtolower( (string) $value ), array( 'yes', 'true', '1', 'on' ), true );
	}
}
