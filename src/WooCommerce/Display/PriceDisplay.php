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
		add_filter( 'render_block', array( $this, 'filter_price_block' ), 20, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_summary' ), (int) Settings::get( 'display_priority', 20 ) );
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_archive_item' ), (int) Settings::get( 'display_priority', 20 ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Append the anchor block to rendered price blocks.
	 *
	 * Block themes (and WooCommerce Blocks templates) build the product price
	 * with `woocommerce/product-price`, which never passes through
	 * `woocommerce_get_price_html`, so the anchor price would not be displayed
	 * there at all (FR-011).
	 *
	 * @param string $content Rendered block content.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function filter_price_block( $content, $block ) {
		if ( 'price_html' !== Settings::get( 'display_location' ) ) {
			return $content;
		}

		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

		if ( ! in_array( $name, array( 'woocommerce/product-price', 'woocommerce/price', 'core/price' ), true ) ) {
			return $content;
		}

		if ( false !== strpos( (string) $content, 'sidrena-cijena' ) ) {
			return $content;
		}

		$product = $this->block_product( $block );

		if ( ! $product ) {
			return $content;
		}

		return $content . $this->render( $product, $this->context() );
	}

	/**
	 * Product a price block belongs to.
	 *
	 * @param array $block Parsed block.
	 * @return \WC_Product|null
	 */
	protected function block_product( array $block ) {
		$block_context = isset( $block['context'] ) && is_array( $block['context'] ) ? $block['context'] : array();

		foreach ( array( 'productId', 'postId', 'product_id' ) as $key ) {
			if ( ! empty( $block_context[ $key ] ) && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( (int) $block_context[ $key ] );

				if ( $product ) {
					return $product;
				}
			}
		}

		// WooCommerce Blocks pass no context with the price block, so the block has
		// to declare whose price it is. Only a price inside a single-product
		// template belongs to the product being viewed: guessing there would put
		// this product's anchor next to another product's price (upsells, related
		// products, collection items), which is worse than showing nothing.
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( empty( $attrs['isDescendentOfSingleProductTemplate'] ) ) {
			return null;
		}

		$queried = function_exists( 'get_queried_object' ) ? get_queried_object() : null;

		if ( $queried instanceof \WP_Post && function_exists( 'wc_get_product' ) ) {
			return wc_get_product( (int) $queried->ID );
		}

		return $this->current_product();
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

		// WooCommerce stores prices excluding tax and adjusts them for display,
		// so the anchor has to be shown on the same basis as the price next to
		// it (FR-014).
		$anchor_price  = self::display_price( $anchor_price, $item );
		$current_price = self::display_price( $current_price, $item );

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

			$reference_date         = $anchor && ! empty( $anchor['reference_date'] ) ? (string) $anchor['reference_date'] : '';
			$date_parts             = explode( '-', $reference_date );
			$reference_date_display = 3 === count( $date_parts )
				? sprintf( '%d. %d. %d.', (int) $date_parts[2], (int) $date_parts[1], (int) $date_parts[0] )
				: '';

			$template_args = array(
				'item'                   => $item,
				'context'                => $context,
				'label'                  => $label,
				'anchor_price'           => $anchor_price,
				'anchor_display'         => Currency::format( $anchor_price, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) ),
				'current_price'          => $current_price,
				'current_display'        => null === $current_price ? '' : Currency::format( $current_price, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) ),
				'show_current'           => $show_current,
				'show_anchor'            => $show_anchor,
				'reference_date'         => $reference_date,
				'reference_date_display' => $reference_date_display,
				'anchor_title'           => '' !== $reference_date_display ? sprintf( '%s (%s)', $label, $reference_date_display ) : $label,
				'disclaimer'             => (string) Settings::get( 'disclaimer', '' ),
				'product_id'             => (int) $item->id,
				'variation_id'           => (int) $item->variation_id,
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

		$sales  = Plugin::instance()->special_sales();
		$active = $sales->active_map();
		$key    = (int) $item->id . ':' . (int) $item->variation_id;
		$sale   = isset( $active[ $key ] ) ? $active[ $key ] : null;

		if ( ( ! $sale || null === $sale['lowest_30_day_price'] ) && $item->is_variation() ) {
			$key  = (int) $item->id . ':0';
			$sale = isset( $active[ $key ] ) ? $active[ $key ] : null;
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

	/**
	 * Convert a stored price to the basis WooCommerce shows to customers.
	 *
	 * Exported price lists keep the stored amounts; only the storefront
	 * display is tax-adjusted so the anchor matches the adjacent price.
	 *
	 * @param float|null  $amount Stored amount.
	 * @param CatalogItem $item   Catalog item.
	 * @return float|null
	 */
	public static function display_price( $amount, CatalogItem $item ) {
		if ( null === $amount || ! function_exists( 'wc_get_price_to_display' ) || ! function_exists( 'wc_get_product' ) ) {
			return $amount;
		}

		// An anchor stored gross already is the customer-facing retail price.
		// Passing it through wc_get_price_to_display() in a shop that enters
		// prices net of tax would add tax again and overstate the legal price.
		if ( ! empty( $item->tax_inclusive ) ) {
			return $amount;
		}

		$product_id = (int) $item->variation_id ? (int) $item->variation_id : (int) $item->id;
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return $amount;
		}

		return (float) wc_get_price_to_display( $product, array( 'price' => $amount ) );
	}
}
