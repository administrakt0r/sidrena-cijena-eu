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
use SidrenaCijena\Pricing\SpecialSaleOfferMatcher;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;
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
	 * Reset the active card tracker (kept for backward compatibility).
	 *
	 * @return void
	 */
	public static function reset_active_card() {
	}

	/**
	 * Reset the rendered product ID cache (kept for backward compatibility).
	 *
	 * @return void
	 */
	public static function reset_rendered_cache() {
	}

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
		add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_cart_item_price' ), 20, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'filter_checkout_item_subtotal' ), 20, 3 );
		add_filter( 'woocommerce_widget_cart_item_quantity', array( $this, 'filter_widget_cart_item_quantity' ), 20, 3 );
		add_filter( 'render_block', array( $this, 'filter_price_block' ), 20, 3 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_summary' ), (int) Settings::get( 'display_priority', 20 ) );
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_archive_item' ), (int) Settings::get( 'display_priority', 20 ) );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->register_cart_block_extension();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( $this, 'register_cart_block_extension' ) );
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register reference-price text for WooCommerce Cart and Checkout blocks.
	 *
	 * @return void
	 */
	public function register_cart_block_extension() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		foreach ( array( 'titanworks-bazna-cijena', 'titanworks-bazna-cijena' ) as $extension_namespace ) {
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => 'cart-item',
					'namespace'       => $extension_namespace,
					'schema_callback' => function () {
						return array(
							'cartPriceSuffix' => array(
								'description' => __( 'Reference price text for this cart item.', 'titanworks-bazna-cijena' ),
								'type'        => 'string',
								'context'     => array( 'view' ),
								'readonly'    => true,
							),
						);
					},
					'data_callback'   => function ( $cart_item ) {
						return array( 'cartPriceSuffix' => $this->cart_reference_text( $cart_item ) );
					},
				)
			);
		}
	}

	/**
	 * Add reference prices next to the unit price in the classic cart.
	 *
	 * @param string $price     Cart item price HTML.
	 * @param array  $cart_item Cart item.
	 * @param string $key       Cart item key.
	 * @return string
	 */
	public function filter_cart_item_price( $price, $cart_item, $key ) {
		unset( $key );

		return $this->append_cart_reference( $price, $cart_item );
	}

	/**
	 * Add reference prices next to checkout line subtotals in classic checkout.
	 *
	 * The classic checkout shows a line subtotal instead of a unit price.
	 *
	 * @param string $subtotal  Cart item subtotal HTML.
	 * @param array  $cart_item Cart item.
	 * @param string $key       Cart item key.
	 * @return string
	 */
	public function filter_checkout_item_subtotal( $subtotal, $cart_item, $key ) {
		unset( $key );

		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return $subtotal;
		}

		return $this->append_cart_reference( $subtotal, $cart_item );
	}

	/**
	 * Add reference prices next to mini-cart item prices.
	 *
	 * @param string $quantity  Mini-cart quantity and price HTML.
	 * @param array  $cart_item Cart item.
	 * @param string $key       Cart item key.
	 * @return string
	 */
	public function filter_widget_cart_item_quantity( $quantity, $cart_item, $key ) {
		unset( $key );

		return $this->append_cart_reference( $quantity, $cart_item );
	}

	/**
	 * Add the rendered reference block to a classic cart price string.
	 *
	 * @param string $price     Existing price HTML.
	 * @param array  $cart_item Cart item.
	 * @return string
	 */
	protected function append_cart_reference( $price, $cart_item ) {
		if ( false !== strpos( $price, 'sidrena-cijena' ) ) {
			return $price;
		}

		return $price . $this->cart_reference_html( $cart_item );
	}

	/**
	 * Reference prices for one cart item, labeled as per-item amounts.
	 *
	 * @param array $cart_item Cart item.
	 * @return string
	 */
	protected function cart_reference_html( $cart_item ) {
		$product = $this->cart_item_product( $cart_item );
		$html    = $product ? $this->render( $product, 'cart' ) : '';

		if ( '' === $html ) {
			return '';
		}

		return '<div class="sidrena-cijena__cart-reference"><span class="sidrena-cijena__cart-unit-label">' . esc_html__( 'Reference prices per item:', 'titanworks-bazna-cijena' ) . '</span> ' . $html . '</div>';
	}

	/**
	 * Plain text for the block Cart/Checkout price filter.
	 *
	 * @param array $cart_item Cart item.
	 * @return string
	 */
	protected function cart_reference_text( $cart_item ) {
		$html = $this->cart_reference_html( $cart_item );

		if ( '' === $html ) {
			return '';
		}

		$html = preg_replace( '~</(?:span|div)\s*>~i', ' ', $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, get_bloginfo( 'charset' ) );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Get the product instance from a WooCommerce cart item.
	 *
	 * @param mixed $cart_item Cart item.
	 * @return \WC_Product|null
	 */
	protected function cart_item_product( $cart_item ) {
		return is_array( $cart_item ) && isset( $cart_item['data'] ) && $cart_item['data'] instanceof \WC_Product
			? $cart_item['data']
			: null;
	}

	/**
	 * Append the anchor block to rendered price blocks.
	 *
	 * Block themes (and WooCommerce Blocks templates) build the product price
	 * with `woocommerce/product-price`, which never passes through
	 * `woocommerce_get_price_html`, so the anchor price would not be displayed
	 * there at all (FR-011).
	 *
	 * @param string                   $content        Rendered block content.
	 * @param array<mixed>             $block          Parsed block.
	 * @param \WP_Block|\stdClass|null $block_instance Rendered block instance.
	 * @return string
	 */
	public function filter_price_block( $content, $block, $block_instance = null ) {
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

		$product = $this->block_product( $block, $block_instance );

		if ( ! $product ) {
			return $content;
		}

		$context = $this->context( $product );

		return $content . $this->render( $product, $context );
	}

	/**
	 * Product a price block belongs to.
	 *
	 * @param array                    $block          Parsed block.
	 * @param \WP_Block|\stdClass|null $block_instance Rendered block instance.
	 * @return \WC_Product|null
	 */
	protected function block_product( array $block, $block_instance = null ) {
		$block_context = array();
		if ( is_object( $block_instance ) && isset( $block_instance->context ) && is_array( $block_instance->context ) ) {
			$block_context = $block_instance->context;
		} elseif ( isset( $block['context'] ) && is_array( $block['context'] ) ) {
			$block_context = $block['context'];
		}

		foreach ( array( 'productId', 'postId', 'product_id' ) as $key ) {
			if ( ! empty( $block_context[ $key ] ) && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( (int) $block_context[ $key ] );

				if ( $product ) {
					return $product;
				}
			}
		}

		if ( ! empty( $block['attrs']['productId'] ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( (int) $block['attrs']['productId'] );
			if ( $product ) {
				return $product;
			}
		}

		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( ! empty( $attrs['isDescendentOfSingleProductTemplate'] ) ) {
			$queried = function_exists( 'get_queried_object' ) ? get_queried_object() : null;

			if ( $queried instanceof \WP_Post && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( (int) $queried->ID );

				return $product instanceof \WC_Product ? $product : null;
			}

			return $this->current_product();
		}

		global $post;
		if ( $post instanceof \WP_Post && 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product instanceof \WC_Product ) {
				return $product;
			}
		}

		return null;
	}

	/**
	 * Append the anchor block to WooCommerce price HTML.
	 *
	 * @param string            $html    Price HTML.
	 * @param \WC_Product|mixed $product Product.
	 * @return string
	 */
	public function filter_price_html( $html, $product ) {
		if ( 'price_html' !== Settings::get( 'display_location' ) ) {
			return $html;
		}

		if ( ! $product instanceof \WC_Product ) {
			return $html;
		}

		if ( false !== strpos( (string) $html, 'sidrena-cijena' ) ) {
			return $html;
		}

		$context = $this->context( $product );
		$block   = $this->render( $product, $context );

		if ( '' !== $block ) {
			return $html . $block;
		}

		return $html;
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
	 * When display_location is 'summary', this outputs the anchor block below the price.
	 *
	 * @return void
	 */
	public function render_archive_item() {
		if ( 'price_html' === Settings::get( 'display_location' ) || ! Settings::get( 'display_archives' ) ) {
			return;
		}

		$product = $this->current_product();

		if ( ! $product instanceof \WC_Product ) {
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

		$is_product_page     = function_exists( 'is_product' ) && is_product();
		$is_archive          = ( function_exists( 'is_shop' ) && is_shop() )
			|| ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() )
			|| ( function_exists( 'is_post_type_archive' ) && is_post_type_archive( 'product' ) );
		$is_home_or_search   = ( function_exists( 'is_front_page' ) && is_front_page() )
			|| ( function_exists( 'is_home' ) && is_home() )
			|| ( function_exists( 'is_search' ) && is_search() );
		$is_cart_or_checkout = ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() );

		$should_enqueue = false;

		if ( $is_product_page && Settings::get( 'display_product_pages' ) ) {
			$should_enqueue = true;
		} elseif ( ( $is_archive || $is_home_or_search ) && Settings::get( 'display_archives' ) ) {
			$should_enqueue = true;
		} elseif ( $is_cart_or_checkout ) {
			$should_enqueue = true;
		}

		/**
		 * Filters whether to enqueue the frontend stylesheet and script.
		 *
		 * Allows themes and page builders (Divi, Elementor, etc.) to force asset loading
		 * when custom product templates or price elements are rendered on non-standard pages.
		 *
		 * @param bool $should_enqueue Whether to enqueue assets.
		 */
		$should_enqueue = (bool) apply_filters( 'sidrena_cijena_enqueue_frontend_assets', $should_enqueue );

		if ( ! $should_enqueue ) {
			return;
		}

		wp_enqueue_style(
			'sidrena-cijena-frontend',
			SIDRENA_CIJENA_URL . 'assets/css/frontend.css',
			array(),
			SIDRENA_CIJENA_VERSION
		);

		if ( Settings::get( 'display_product_pages' ) || Settings::get( 'display_archives' ) ) {
			wp_enqueue_script(
				'sidrena-cijena-frontend',
				SIDRENA_CIJENA_URL . 'assets/js/frontend.js',
				array(),
				SIDRENA_CIJENA_VERSION,
				true
			);
		}

		if ( wp_script_is( 'wc-blocks-checkout', 'registered' ) ) {
			wp_enqueue_script(
				'sidrena-cijena-cart-blocks',
				SIDRENA_CIJENA_URL . 'assets/js/cart-blocks.js',
				array( 'wc-blocks-checkout' ),
				SIDRENA_CIJENA_VERSION,
				true
			);
		}
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

		if ( ( 'archive' === $context && ! Settings::get( 'display_archives' ) ) || ( 'single' === $context && ! Settings::get( 'display_product_pages' ) ) ) {
			return '';
		}

		if ( 'archive' === $context && ! CardVisibility::is_visible( $product->get_id() ) ) {
			return '';
		}

		// A variable parent has no single retail price on single product pages.
		// Its selected variation supplies the matching anchor through WooCommerce variation data.
		// In archive product cards, render the anchor range or baseline so the card is not empty.
		if ( $product->is_type( 'variable' ) ) {
			if ( 'archive' === $context ) {
				return $this->render_variable_archive( $product, $context, $args );
			}
			return '';
		}

		$item = $this->catalog_item( $product );

		if ( ! $item ) {
			return '';
		}

		return $this->render_item( $item, $context, $args );
	}

	/**
	 * Render anchor price for a variable product in archive context.
	 *
	 * Variable products in shop loops/cards do not have a single selected variation,
	 * but customers need to see the baseline anchor price or range.
	 *
	 * @param \WC_Product         $product Variable product.
	 * @param string              $context Rendering context (e.g. 'archive').
	 * @param array<string,mixed> $args    Rendering overrides.
	 * @return string
	 */
	protected function render_variable_archive( $product, $context, array $args ) {
		$visible_ids = method_exists( $product, 'get_visible_children' ) ? (array) $product->get_visible_children() : array();
		$children    = ! empty( $visible_ids ) ? $visible_ids : (array) $product->get_children();

		$currency      = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
		$currency_mode = (string) Settings::get( 'currency_display', 'symbol' );

		$date_groups         = array();
		$representative_item = null;
		$sale_prices         = array();

		foreach ( $children as $child_id ) {
			if ( function_exists( 'wc_get_product' ) ) {
				$var_prod = wc_get_product( (int) $child_id );
				if ( $var_prod instanceof \WC_Product ) {
					if ( 'publish' !== $var_prod->get_status() ) {
						continue;
					}
					if ( '' === $var_prod->get_price() || ! $var_prod->is_purchasable() ) {
						continue;
					}
				}
			}

			$child_item = Plugin::instance()->catalog()->get_variation( (int) $product->get_id(), (int) $child_id );
			if ( ! $child_item || null === $child_item->current_price ) {
				continue;
			}

			if ( null === $representative_item ) {
				$representative_item = $child_item;
				$currency            = $child_item->currency;
			}

			// Collect qualifying 30-day sale prices across offered variations, tax-adjusting each variation's reference.
			$sale = $this->active_sale_for_item( $child_item );
			if ( $sale && null !== $sale['lowest_30_day_price'] ) {
				$adjusted_sale = self::display_price( (float) $sale['lowest_30_day_price'], $child_item );
				if ( null !== $adjusted_sale ) {
					$sale_prices[] = (float) $adjusted_sale;
				}
			}

			$anchor = $this->anchors->get_anchor( $child_item );
			if ( $anchor && null !== $anchor['price'] ) {
				$display_p = self::display_price( (float) $anchor['price'], $child_item );
				$current_p = self::display_price( (float) $child_item->current_price, $child_item );
				$ref_date  = ! empty( $anchor['reference_date'] ) ? (string) $anchor['reference_date'] : '';

				if ( ! isset( $date_groups[ $ref_date ] ) ) {
					$date_groups[ $ref_date ] = array(
						'anchors'             => array(),
						'has_difference'      => false,
						'representative_item' => $child_item,
					);
				}

				$date_groups[ $ref_date ]['anchors'][] = $display_p;
				if ( null === $current_p || $display_p !== $current_p ) {
					$date_groups[ $ref_date ]['has_difference'] = true;
				}
			}
		}

		$only_when_different = array_key_exists( 'only_when_different', $args )
			? (bool) $args['only_when_different']
			: (bool) Settings::get( 'only_when_different' );

		$rendered_anchors                   = '';
		$anchor_args                        = $args;
		$anchor_args['only_when_different'] = false;
		$anchor_args['show_30_day']         = false; // Multi-variation sale resolution is appended below.

		if ( empty( $date_groups ) ) {
			$item = $this->catalog_item( $product );
			if ( $item ) {
				$rendered_anchors .= $this->render_item( $item, $context, $anchor_args );
			}
		} else {
			foreach ( $date_groups as $group_date => $group ) {
				if ( $only_when_different && ! $group['has_difference'] ) {
					continue;
				}

				$group_anchors = $group['anchors'];
				$min_anchor    = min( $group_anchors );
				$max_anchor    = max( $group_anchors );

				if ( $min_anchor === $max_anchor ) {
					$anchor_display = Currency::format( $min_anchor, $currency, array( 'display' => $currency_mode ) );
				} else {
					$min_formatted  = Currency::format( $min_anchor, $currency, array( 'display' => $currency_mode ) );
					$max_formatted  = Currency::format( $max_anchor, $currency, array( 'display' => $currency_mode ) );
					$anchor_display = sprintf( '%1$s – %2$s', $min_formatted, $max_formatted );
				}

				$group_args                   = $anchor_args;
				$group_args['anchor_price']   = $min_anchor;
				$group_args['anchor_display'] = $anchor_display;
				if ( '' !== $group_date ) {
					$group_args['reference_date'] = $group_date;
				}

				$rendered_anchors .= $this->render_item( $group['representative_item'], $context, $group_args );
			}
		}

		if ( '' === $rendered_anchors && empty( $sale_prices ) ) {
			return '';
		}

		$html = $rendered_anchors;

		// Resolve qualifying 30-day sale references across all offered variations.
		if ( ! empty( $sale_prices ) && $representative_item ) {
			$min_sale = min( $sale_prices );
			$max_sale = max( $sale_prices );

			if ( $min_sale === $max_sale ) {
				$sale_display = Currency::format( $min_sale, $currency, array( 'display' => $currency_mode ) );
			} else {
				$min_sale_fmt = Currency::format( $min_sale, $currency, array( 'display' => $currency_mode ) );
				$max_sale_fmt = Currency::format( $max_sale, $currency, array( 'display' => $currency_mode ) );
				$sale_display = sprintf( '%1$s – %2$s', $min_sale_fmt, $max_sale_fmt );
			}

			$html .= $this->render_variable_thirty_day( $representative_item, $context, $sale_display, $min_sale, $args );
		}

		return $html;
	}

	/**
	 * Render the 30-day lowest price block for a variable product in archive context.
	 *
	 * @param CatalogItem  $item         Representative item.
	 * @param string       $context      Rendering context.
	 * @param string       $sale_display Formatted price or range.
	 * @param float        $min_sale     Minimum sale price.
	 * @param array<mixed> $args         Rendering overrides.
	 * @return string
	 */
	protected function render_variable_thirty_day( CatalogItem $item, $context, $sale_display, $min_sale, array $args ) {
		$show = array_key_exists( 'show_30_day', $args ) ? (bool) $args['show_30_day'] : true;

		if ( ! $show ) {
			return '';
		}

		$label = isset( $args['thirty_day_label'] ) && '' !== $args['thirty_day_label']
			? (string) $args['thirty_day_label']
			: (string) Settings::get( 'thirty_day_label', __( 'Najniža cijena u zadnjih 30 dana', 'titanworks-bazna-cijena' ) );

		$label = (string) apply_filters( 'sidrena_cijena_thirty_day_label', $label, $item, $context );

		$card_style = (string) Settings::get( 'card_display_style', 'same_as_product' );
		$style      = ( 'archive' === $context && 'same_as_product' !== $card_style && array_key_exists( $card_style, \SidrenaCijena\Support\DisplayStyles::presets() ) )
			? $card_style
			: (string) Settings::get( 'product_display_style', 'tooltip' );

		$card_item = new CatalogItem(
			array(
				'id'            => (int) $item->id,
				'variation_id'  => 0,
				'currency'      => $item->currency,
				'current_price' => $item->current_price,
			)
		);

		$template_args = array(
			'item'    => $card_item,
			'price'   => $min_sale,
			'label'   => $label,
			'display' => $sale_display,
			'style'   => $style,
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
	 * Render the anchor block for a catalog item.
	 *
	 * @param CatalogItem $item    Catalog item.
	 * @param string      $context Rendering context.
	 * @param array       $args    Rendering overrides.
	 * @return string
	 */
	public function render_item( CatalogItem $item, $context = 'single', array $args = array() ) {
		$anchor = $this->anchors->get_anchor( $item );

		$anchor_price = isset( $args['anchor_price'] )
			? (float) $args['anchor_price']
			: ( ( $anchor && null !== $anchor['price'] ) ? (float) $anchor['price'] : null );

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
		if ( ! isset( $args['anchor_price'] ) ) {
			$anchor_price = self::display_price( $anchor_price, $item );
		}
		$current_price = self::display_price( $current_price, $item );

		$only_when_different = array_key_exists( 'only_when_different', $args )
			? (bool) $args['only_when_different']
			: (bool) Settings::get( 'only_when_different' );

		$show_current = array_key_exists( 'show_current', $args ) ? (bool) $args['show_current'] : false;
		$show_anchor  = array_key_exists( 'show_anchor', $args ) ? (bool) $args['show_anchor'] : true;

		$html = '';

		if ( null !== $anchor_price
			&& ! ( $only_when_different && null !== $current_price && $current_price === $anchor_price ) ) {
			$raw_label = isset( $args['label'] ) && '' !== $args['label'] ? (string) $args['label'] : (string) Settings::get( 'anchor_label', __( 'Sidrena cijena', 'titanworks-bazna-cijena' ) );

			$reference_date         = isset( $args['reference_date'] ) ? (string) $args['reference_date'] : ( $anchor && ! empty( $anchor['reference_date'] ) ? (string) $anchor['reference_date'] : '' );
			$reference_date_display = Dates::format_date_display( $reference_date );

			$label = self::format_anchor_label( $raw_label, $reference_date_display, $reference_date );

			/**
			 * Filters the anchor-price label.
			 *
			 * @param string $label   Label.
			 * @param string $context Rendering context.
			 */
			$label = (string) apply_filters( 'sidrena_cijena_display_label', $label, $context );

			$anchor_title = self::format_anchor_title( $label, $reference_date_display );

			$is_card_context = 'archive' === $context;

			$card_style = (string) Settings::get( 'card_display_style', 'same_as_product' );
			if ( $is_card_context && 'same_as_product' !== $card_style && array_key_exists( $card_style, \SidrenaCijena\Support\DisplayStyles::presets() ) ) {
				$style = $card_style;
			} else {
				$style = (string) Settings::get( 'product_display_style', 'tooltip' );
			}

			$date_mode = $is_card_context
				? (string) Settings::get( 'card_date_mode', Settings::get( 'anchor_date_mode', 'tooltip' ) )
				: (string) Settings::get( 'anchor_date_mode', 'tooltip' );

			$font_weight = $is_card_context
				? (string) Settings::get( 'card_font_weight', Settings::get( 'anchor_font_weight', 'normal' ) )
				: (string) Settings::get( 'anchor_font_weight', 'normal' );

			$size = $is_card_context
				? (int) Settings::get( 'card_display_size', Settings::get( 'display_size', 40 ) )
				: (int) Settings::get( 'display_size', 40 );

			$template_args = array(
				'item'                   => $item,
				'context'                => $context,
				'label'                  => $label,
				'label_template'         => $raw_label,
				'anchor_price'           => $anchor_price,
				'anchor_display'         => isset( $args['anchor_display'] ) ? (string) $args['anchor_display'] : Currency::format( $anchor_price, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) ),
				'current_price'          => $current_price,
				'current_display'        => null === $current_price ? '' : Currency::format( $current_price, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) ),
				'show_current'           => $show_current,
				'show_anchor'            => $show_anchor,
				'reference_date'         => $reference_date,
				'reference_date_display' => $reference_date_display,
				'anchor_title'           => $anchor_title,
				'disclaimer'             => (string) Settings::get( 'disclaimer', '' ),
				'style'                  => $style,
				'date_mode'              => $date_mode,
				'tooltip_text'           => (string) Settings::get( 'anchor_tooltip_text', __( 'Sidrena (bazna) cijena je redovna maloprodajna cijena na propisani referentni datum prema mjerama izravne kontrole cijena.', 'titanworks-bazna-cijena' ) ),
				'font_weight'            => $font_weight,
				'size'                   => $size,
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
	 * Find the qualifying active special sale for a catalog item.
	 *
	 * @param CatalogItem $item Catalog item.
	 * @return array<string,mixed>|null
	 */
	public function active_sale_for_item( CatalogItem $item ) {
		$sales  = Plugin::instance()->special_sales();
		$active = $sales->active_map();
		$key    = (int) $item->id . ':' . (int) $item->variation_id;
		$sale   = isset( $active[ $key ] ) ? $active[ $key ] : null;

		if ( ! $sale || null === $sale['lowest_30_day_price'] ) {
			return null;
		}

		if ( 'woocommerce' === $item->source ) {
			$current    = Currency::to_storage( $item->effective_price() );
			$regular    = Currency::to_storage( $item->regular_price );
			$sale_price = Currency::to_storage( $item->sale_price );

			if ( null !== $regular && null !== $current ) {
				$promotional = $regular > 0 && $current < $regular;

				if ( ! $promotional && null !== $item->current_price && null !== $sale_price ) {
					$promotional = $current === $sale_price;
				}

				if ( ! $promotional ) {
					return null;
				}
			}

			$periods    = Plugin::instance()->history()->active_sale_period_map( array( (int) $item->id ) );
			$period_key = isset( $sale['product_id'] ) ? (int) $sale['product_id'] . ':' . (int) ( isset( $sale['variation_id'] ) ? $sale['variation_id'] : 0 ) : $key;
			$period     = isset( $periods[ $period_key ] ) ? $periods[ $period_key ] : null;

			if ( ! SpecialSaleOfferMatcher::matches( $item, $sale, $period ) ) {
				return null;
			}
		}

		return $sale;
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

		$sale = $this->active_sale_for_item( $item );

		if ( ! $sale || null === $sale['lowest_30_day_price'] ) {
			return '';
		}

		$price = (float) $sale['lowest_30_day_price'];
		$price = (float) self::display_price( $price, $item );

		$label = isset( $args['thirty_day_label'] ) && '' !== $args['thirty_day_label']
			? (string) $args['thirty_day_label']
			: (string) Settings::get( 'thirty_day_label', __( 'Najniža cijena u zadnjih 30 dana', 'titanworks-bazna-cijena' ) );

		/**
		 * Filters the 30-day lowest price display label.
		 *
		 * @param string      $label   Display label.
		 * @param CatalogItem $item    Catalog item.
		 * @param string      $context Rendering context.
		 */
		$label = (string) apply_filters( 'sidrena_cijena_thirty_day_label', $label, $item, $context );

		$template_args = array(
			'item'    => $item,
			'price'   => $price,
			'label'   => $label,
			'display' => Currency::format( $price, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) ),
			'style'   => (string) Settings::get( 'product_display_style', 'tooltip' ),
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
	 * @param \WC_Product|null $product Optional product being evaluated.
	 * @return string
	 */
	protected function context( $product = null ) {
		if ( function_exists( 'wc_get_loop_prop' ) && wc_get_loop_prop( 'name' ) ) {
			return 'archive';
		}

		if ( $product instanceof \WC_Product && function_exists( 'get_queried_object_id' ) ) {
			$queried_id = (int) get_queried_object_id();
			if ( $queried_id > 0 ) {
				$product_id = (int) $product->get_id();
				$parent_id  = (int) $product->get_parent_id();

				if ( $product_id !== $queried_id && $parent_id !== $queried_id ) {
					return 'archive';
				}
			}
		}

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

	/**
	 * Formats the anchor price label by resolving dynamic date tokens.
	 *
	 * Supported tokens:
	 * - {datum} or {date}: Formatted reference date (e.g. "10. 9. 2026." or "2. 5. 2025.")
	 * - {datum_iso}: Raw ISO date string (e.g. "2026-09-10")
	 *
	 * @param string $label                  Raw label text.
	 * @param string $reference_date_display Formatted date display.
	 * @param string $reference_date         Raw ISO date string.
	 * @return string
	 */
	public static function format_anchor_label( $label, $reference_date_display, $reference_date = '' ) {
		if ( false === strpos( $label, '{' ) ) {
			return $label;
		}

		$replacements = array(
			'{datum}'     => $reference_date_display,
			'{date}'      => $reference_date_display,
			'{datum_iso}' => $reference_date,
		);

		$formatted = str_replace( array_keys( $replacements ), array_values( $replacements ), $label );

		// Clean up dangling spacing or punctuation if reference date was empty.
		if ( empty( $reference_date_display ) ) {
			$formatted = preg_replace( '/\s+/', ' ', $formatted );
			$formatted = trim( (string) $formatted, " \t\n\r\0\x0B:()" );
		}

		return trim( (string) $formatted );
	}

	/**
	 * Formats the anchor element title attribute, avoiding date duplication.
	 *
	 * @param string $label                  Formatted label.
	 * @param string $reference_date_display Formatted date display.
	 * @return string
	 */
	public static function format_anchor_title( $label, $reference_date_display ) {
		if ( '' === $reference_date_display ) {
			return $label;
		}

		// If the label already contains the reference date, avoid duplicating it in parentheses.
		if ( false !== strpos( $label, $reference_date_display ) ) {
			return $label;
		}

		return sprintf( '%s (%s)', $label, $reference_date_display );
	}
}
