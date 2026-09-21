<?php
/**
 * WooCommerce frontend anchor-price display.
 *
 * @package SidrenaCijena\WooCommerce\Display
 */

namespace SidrenaCijena\WooCommerce\Display;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Plugin;
use SidrenaCijena\Pricing\AnchorPriceService;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the anchor price next to current prices on the storefront.
 */
class PriceDisplay {

	/**
	 * Anchor price service.
	 *
	 * @var AnchorPriceService
	 */
	protected $anchors;

	/**
	 * Constructor.
	 *
	 * @param AnchorPriceService $anchors Anchor service.
	 */
	public function __construct( AnchorPriceService $anchors ) {
		$this->anchors = $anchors;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 20, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_summary' ), (int) Settings::get( 'display_priority', 20 ) );
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_archive_item' ), (int) Settings::get( 'display_priority', 20 ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Append the anchor block to WooCommerce price HTML.
	 *
	 * @param string      $html    Price HTML.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public function filter_price_html( $html, $product ) {
		if ( 'price_html' !== Settings::get( 'display_location' ) ) {
			return $html;
		}

		$block = $this->render( $product, $this->context() );

		return $html . $block;
	}

	/**
	 * Render on the single product summary.
	 *
	 * @return void
	 */
	public function render_single_summary() {
		if ( 'price_html' === Settings::get( 'display_location' ) || ! Settings::get( 'display_product_pages' ) ) {
			return;
		}

		$product = $this->current_product();

		if ( ! $product ) {
			return;
		}

		echo $this->render( $product, 'single' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render in archive loops.
	 *
	 * @return void
	 */
	public function render_archive_item() {
		if ( 'price_html' === Settings::get( 'display_location' ) || ! Settings::get( 'display_archives' ) ) {
			return;
		}

		$product = $this->current_product();

		if ( ! $product ) {
			return;
		}

		echo $this->render( $product, 'archive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Enqueue the frontend stylesheet and script.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! Settings::get( 'display_enabled' ) ) {
			return;
		}

		if ( ! Settings::get( 'display_product_pages' ) && ! Settings::get( 'display_archives' ) ) {
			return;
		}

		wp_enqueue_style(
			'sidrena-cijena-frontend',
			SIDRENA_CIJENA_URL . 'assets/css/frontend.css',
			array(),
			SIDRENA_CIJENA_VERSION
		);

		wp_enqueue_script(
			'sidrena-cijena-frontend',
			SIDRENA_CIJENA_URL . 'assets/js/frontend.js',
			array(),
			SIDRENA_CIJENA_VERSION,
			true
		);
	}

	/**
	 * Render the anchor block for a product.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @param string      $context `single`, `archive`, `price_html` or `shortcode`.
	 * @param array       $args    Rendering overrides.
	 * @return string
	 */
	public function render( $product, $context = 'single', array $args = array() ) {
		if ( ! Settings::get( 'display_enabled' ) ) {
			return '';
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return '';
		}

		$item = $this->catalog_item( $product );

		if ( ! $item ) {
			return '';
		}

		return $this->render_item( $item, $context, $args );
	}

	/**
	 * Render the anchor block for a catalog item.
	 *
	 * @param CatalogItem $item    Catalog item.
	 * @param string      $context Rendering context.
	 * @param array       $args    Rendering overrides.
	 * @return string
	 */
	public function render_item( CatalogItem $item, $context = 'single', array $args = array() ) {
		$anchor = $this->anchors->get_anchor( $item );

		$anchor_price = ( $anchor && null !== $anchor['price'] ) ? (float) $anchor['price'] : null;

		/**
		 * Filters the resolved anchor price before rendering.
		 *
		 * @param float|null  $anchor_price Anchor price.
		 * @param CatalogItem $item         Catalog item.
		 */
		$anchor_price = apply_filters( 'sidrena_cijena_anchor_price', $anchor_price, $item );

		$current_price = null !== $item->current_price ? (float) $item->current_price : null;

		$only_when_different = array_key_exists( 'only_when_different', $args )
			? (bool) $args['only_when_different']
			: (bool) Settings::get( 'only_when_different' );

		$show_current = array_key_exists( 'show_current', $args ) ? (bool) $args['show_current'] : false;
		$show_anchor  = array_key_exists( 'show_anchor', $args ) ? (bool) $args['show_anchor'] : true;

		$html = '';

		if ( null !== $anchor_price
			&& ! ( $only_when_different && null !== $current_price && $current_price === $anchor_price ) ) {
			$label = isset( $args['label'] ) && '' !== $args['label'] ? (string) $args['label'] : (string) Settings::get( 'anchor_label', __( 'Sidrena cijena', 'sidrena-cijena' ) );

			/**
			 * Filters the anchor-price label.
			 *
			 * @param string $label   Label.
			 * @param string $context Rendering context.
			 */
			$label = (string) apply_filters( 'sidrena_cijena_display_label', $label, $context );

			$template_args = array(
				'item'            => $item,
				'context'         => $context,
				'label'           => $label,
				'anchor_price'    => $anchor_price,
				'anchor_display'  => Currency::format( $anchor_price, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) ),
				'current_price'   => $current_price,
				'current_display' => null === $current_price ? '' : Currency::format( $current_price, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) ),
				'show_current'    => $show_current,
				'show_anchor'     => $show_anchor,
				'reference_date'  => $anchor && ! empty( $anchor['reference_date'] ) ? (string) $anchor['reference_date'] : '',
				'disclaimer'      => (string) Settings::get( 'disclaimer', '' ),
				'product_id'      => (int) $item->id,
				'variation_id'    => (int) $item->variation_id,
			);

			$html = $this->load_template( $template_args );
		}

		$html .= $this->render_thirty_day( $item, $context, $args );

		/**
		 * Filters the rendered anchor-price block.
		 *
		 * @param string      $html    Rendered HTML.
		 * @param CatalogItem $item    Catalog item.
		 * @param string      $context Rendering context.
		 */
		return (string) apply_filters( 'sidrena_cijena_display_html', $html, $item, $context );
	}

	/**
	 * Render the 30-day lowest price block for an item in a special sale.
	 *
	 * The 30-day price is rendered separately from the anchor price and never
	 * substitutes for it (FR-021).
	 *
	 * @param CatalogItem  $item    Catalog item.
	 * @param string       $context Rendering context.
	 * @param array<mixed> $args    Rendering overrides.
	 * @return string
	 */
	protected function render_thirty_day( CatalogItem $item, $context, array $args ) {
		$show = array_key_exists( 'show_30_day', $args ) ? (bool) $args['show_30_day'] : true;

		if ( ! $show ) {
			return '';
		}

		$sales = Plugin::instance()->special_sales();
		$sale  = $sales->active_for_item( (int) $item->id, (int) $item->variation_id );

		if ( ( ! $sale || null === $sale['lowest_30_day_price'] ) && $item->is_variation() ) {
			$sale = $sales->active_for_item( (int) $item->id, 0 );
		}

		if ( ! $sale || null === $sale['lowest_30_day_price'] ) {
			return '';
		}

		$price = (float) $sale['lowest_30_day_price'];

		$template_args = array(
			'item'    => $item,
			'price'   => $price,
			'label'   => (string) Settings::get( 'thirty_day_label', __( 'Lowest price in the last 30 days', 'sidrena-cijena' ) ),
			'display' => Currency::format( $price, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) ),
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
		extract( $template_args, EXTR_SKIP );
		include $template;

		return (string) ob_get_clean();
	}

	/**
	 * Render the template to a string.
	 *
	 * @param array<mixed> $args Template arguments.
	 * @return string
	 */
	protected function load_template( array $args ) {
		$template = locate_template( array( 'sidrena-cijena/anchor-price.php' ) );

		if ( ! $template ) {
			$template = SIDRENA_CIJENA_DIR . 'templates/anchor-price.php';
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
	 * Catalog item for a WooCommerce product.
	 *
	 * @param \WC_Product $product Product.
	 * @return CatalogItem|null
	 */
	protected function catalog_item( $product ) {
		$catalog = Plugin::instance()->catalog();

		if ( $product->is_type( 'variation' ) ) {
			return $catalog->get_variation( $product->get_parent_id(), $product->get_id() );
		}

		return $catalog->get_item( $product->get_id() );
	}

	/**
	 * Current product in the loop.
	 *
	 * @return \WC_Product|null
	 */
	protected function current_product() {
		global $product;

		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Display context for the current request.
	 *
	 * @return string
	 */
	protected function context() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return 'single';
		}

		return 'archive';
	}
}
