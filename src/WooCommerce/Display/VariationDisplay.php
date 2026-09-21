<?php
/**
 * Variation anchor-price support for variable products.
 *
 * @package SidrenaCijena\WooCommerce\Display
 */

namespace SidrenaCijena\WooCommerce\Display;

use SidrenaCijena\Plugin;
use SidrenaCijena\Pricing\AnchorPriceService;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Adds anchor data to variation JSON and updates it on variation selection.
 */
class VariationDisplay {

	/**
	 * Anchor price service.
	 *
	 * @var AnchorPriceService
	 */
	protected $anchors;

	/**
	 * Variation payload map for the frontend script.
	 *
	 * @var array<int,array>
	 */
	protected $payload = array();

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
		if ( ! Settings::get( 'display_variations' ) ) {
			return;
		}

		add_filter( 'woocommerce_available_variation', array( $this, 'filter_available_variation' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'localize_variations' ), 20 );
	}

	/**
	 * Add anchor data to the variation JSON.
	 *
	 * @param array                 $data      Variation data.
	 * @param \WC_Product_Variable  $parent    Parent product.
	 * @param \WC_Product_Variation $variation Variation.
	 * @return array<mixed>  */
	public function filter_available_variation( $data, $parent, $variation ) {
		unset( $parent );

		$item = Plugin::instance()->catalog()->get_variation( $variation->get_parent_id(), $variation->get_id() );

		if ( ! $item ) {
			return $data;
		}

		$anchor = $this->anchors->get_anchor( $item );

		if ( ! $anchor || null === $anchor['price'] ) {
			$data['sidrena_cijena_anchor']     = null;
			$data['sidrena_cijena_anchor_raw'] = null;

			return $data;
		}

		$price = (float) $anchor['price'];

		$data['sidrena_cijena_anchor']         = Currency::format( $price, $item->currency, array( 'display' => (string) Settings::get( 'currency_display', 'symbol' ) ) );
		$data['sidrena_cijena_anchor_raw']     = $price;
		$data['sidrena_cijena_reference_date'] = isset( $anchor['reference_date'] ) ? (string) $anchor['reference_date'] : '';

		$this->payload[ (int) $variation->get_id() ] = array(
			'display'        => $data['sidrena_cijena_anchor'],
			'raw'            => $price,
			'reference_date' => $data['sidrena_cijena_reference_date'],
		);

		return $data;
	}

	/**
	 * Localize variation anchor data for the frontend script.
	 *
	 * @return void
	 */
	public function localize_variations() {
		if ( empty( $this->payload ) ) {
			return;
		}

		wp_localize_script(
			'sidrena-cijena-frontend',
			'sidrenaCijenaVariations',
			array(
				'variations' => $this->payload,
				'label'      => (string) apply_filters( 'sidrena_cijena_display_label', (string) Settings::get( 'anchor_label', __( 'Sidrena cijena', 'sidrena-cijena' ) ), 'single' ),
			)
		);
	}
}
