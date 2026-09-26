<?php
/**
 * Anchor price for page-builder price elements that render their own markup.
 *
 * @package SidrenaCijena
 */

namespace SidrenaCijena\WooCommerce\Display;

use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Appends the anchor price to price elements built outside `woocommerce_get_price_html`.
 *
 * Most builders render a price through `$product->get_price_html()`, which the
 * `woocommerce_get_price_html` filter already covers. The hooks below are the price
 * elements that build their own markup (Divi, Avada/Fusion, Beaver Builder, Bricks,
 * Elementor). They only act on a single product page: inside a loop or a collection
 * there is no reliable way to tell which product a price belongs to, and showing a
 * wrong anchor is worse than showing none.
 */
class BuilderCompat {

	/**
	 * Anchor price renderer.
	 *
	 * @var PriceDisplay
	 */
	protected $display;

	/**
	 * Constructor.
	 *
	 * @param PriceDisplay $display Anchor price renderer.
	 */
	public function __construct( PriceDisplay $display ) {
		$this->display = $display;
	}

	/**
	 * Register the builder price filters.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'et_module_shortcode_output', array( $this, 'filter_divi' ), 20, 2 );
		add_filter( 'et_pb_wc_price_shortcode_output', array( $this, 'filter_content' ), 20 );
		add_filter( 'fusion_element_woo_price_content', array( $this, 'filter_content' ), 20 );
		add_filter( 'fl_builder_render_module_content', array( $this, 'filter_beaver' ), 20, 2 );
		add_filter( 'bricks/element/render', array( $this, 'filter_bricks' ), 20, 2 );
		add_filter( 'elementor/widget/render_content', array( $this, 'filter_content' ), 25 );
	}

	/**
	 * Filter a Divi module output.
	 *
	 * @param mixed $output      Module output.
	 * @param mixed $module_slug Module slug.
	 * @return mixed
	 */
	public function filter_divi( $output, $module_slug ) {
		if ( ! is_string( $module_slug ) || false === strpos( $module_slug, 'price' ) ) {
			return $output;
		}

		return $this->append( $output );
	}

	/**
	 * Filter a Beaver Builder module output.
	 *
	 * @param mixed $content Module content.
	 * @param mixed $module  Module settings object.
	 * @return mixed
	 */
	public function filter_beaver( $content, $module ) {
		$slug = is_object( $module ) && isset( $module->slug ) ? (string) $module->slug : '';

		if ( '' === $slug || false === strpos( $slug, 'price' ) ) {
			return $content;
		}

		return $this->append( $content );
	}

	/**
	 * Filter a Bricks element output.
	 *
	 * @param mixed $content Element content.
	 * @param mixed $element Element definition.
	 * @return mixed
	 */
	public function filter_bricks( $content, $element ) {
		$name = is_array( $element ) && isset( $element['name'] ) ? (string) $element['name'] : '';

		if ( '' === $name || false === strpos( $name, 'price' ) ) {
			return $content;
		}

		return $this->append( $content );
	}

	/**
	 * Filter a price element that renders itself (Avada, Elementor).
	 *
	 * @param string $content Element content.
	 * @return string
	 */
	public function filter_content( $content ) {
		return $this->append( $content );
	}

	/**
	 * Append the anchor price to a rendered price element.
	 *
	 * @param mixed $content Element content.
	 * @return mixed
	 */
	protected function append( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		// The block was already appended by the price filter or an earlier hook.
		if ( false !== strpos( $content, 'sidrena-cijena' ) ) {
			return $content;
		}

		if ( 'price_html' !== Settings::get( 'display_location' ) ) {
			return $content;
		}

		// Only a price element carries the amount markup.
		if ( false === strpos( $content, 'woocommerce-Price-amount' ) ) {
			return $content;
		}

		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $content;
		}

		$queried = (int) get_queried_object_id();
		$global  = isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof \WC_Product ? $GLOBALS['product'] : null;

		// Inside a related/upsell/widget loop the global product is the loop
		// item, not the queried product; appending the queried product's
		// anchor to such a price element would leak the wrong anchor.
		if ( $queried > 0 && $global instanceof \WC_Product && (int) $global->get_id() !== $queried ) {
			return $content;
		}

		$product = wc_get_product( $queried );

		if ( ! $product instanceof \WC_Product ) {
			$product = $global;
		}

		if ( ! $product instanceof \WC_Product ) {
			return $content;
		}

		return $content . $this->display->render( $product, 'single' );
	}
}
