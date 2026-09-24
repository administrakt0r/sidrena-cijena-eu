<?php
/**
 * Catalog item value object shared by all catalog providers.
 *
 * @package SidrenaCijena\Catalog
 */

namespace SidrenaCijena\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * A product, variation or service offered to consumers.
 */
final class CatalogItem {

	/**
	 * Catalog item ID (post ID for standalone items, product ID for WooCommerce).
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Variation ID, or 0 for simple items and parents.
	 *
	 * @var int
	 */
	public $variation_id = 0;

	/**
	 * Parent product ID for variations, otherwise 0.
	 *
	 * @var int
	 */
	public $parent_id = 0;

	/**
	 * Item type: `product` or `service`.
	 *
	 * @var string
	 */
	public $type = 'product';

	/**
	 * Display name.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Code/SKU.
	 *
	 * @var string
	 */
	public $sku = '';

	/**
	 * Brand name.
	 *
	 * @var string
	 */
	public $brand = '';

	/**
	 * Barcode (EAN/UPC as text).
	 *
	 * @var string
	 */
	public $barcode = '';

	/**
	 * Unit of measure when applicable.
	 *
	 * @var string
	 */
	public $unit = '';

	/**
	 * Price per unit of measure when applicable.
	 *
	 * @var float|null
	 */
	public $unit_price = null;

	/**
	 * Currency (ISO 4217).
	 *
	 * @var string
	 */
	public $currency = 'EUR';

	/**
	 * Availability: `available` or `unavailable`.
	 *
	 * @var string
	 */
	public $availability = 'available';

	/**
	 * Regular price.
	 *
	 * @var float|null
	 */
	public $regular_price = null;

	/**
	 * Sale price when a promotion is active.
	 *
	 * @var float|null
	 */
	public $sale_price = null;

	/**
	 * Effective current price for consumers.
	 *
	 * @var float|null
	 */
	public $current_price = null;

	/**
	 * Whether prices include tax.
	 *
	 * @var bool
	 */
	public $tax_inclusive = true;

	/**
	 * Catalog first-seen date (`Y-m-d`); a suggestion only, not proof of first offer.
	 *
	 * @var string|null
	 */
	public $first_seen = null;

	/**
	 * Category slugs used for legacy-treatment mapping.
	 *
	 * @var string[]
	 */
	public $categories = array();

	/**
	 * Catalog source: `woocommerce` or `standalone`.
	 *
	 * @var string
	 */
	public $source = 'standalone';

	/**
	 * Hydrate from an array, casting known fields.
	 *
	 * @param array<mixed> $data Field values.
	 */
	public function __construct( array $data = array() ) {
		$this->id            = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$this->variation_id  = isset( $data['variation_id'] ) ? (int) $data['variation_id'] : 0;
		$this->parent_id     = isset( $data['parent_id'] ) ? (int) $data['parent_id'] : 0;
		$this->type          = isset( $data['type'] ) && 'service' === $data['type'] ? 'service' : 'product';
		$this->name          = isset( $data['name'] ) ? (string) $data['name'] : '';
		$this->sku           = isset( $data['sku'] ) ? (string) $data['sku'] : '';
		$this->brand         = isset( $data['brand'] ) ? (string) $data['brand'] : '';
		$this->barcode       = isset( $data['barcode'] ) ? (string) $data['barcode'] : '';
		$this->unit          = isset( $data['unit'] ) ? (string) $data['unit'] : '';
		$this->unit_price    = self::to_float( isset( $data['unit_price'] ) ? $data['unit_price'] : null );
		$this->currency      = isset( $data['currency'] ) && $data['currency'] ? strtoupper( (string) $data['currency'] ) : 'EUR';
		$this->availability  = isset( $data['availability'] ) && '' !== (string) $data['availability'] ? (string) $data['availability'] : 'available';
		$this->regular_price = self::to_float( isset( $data['regular_price'] ) ? $data['regular_price'] : null );
		$this->sale_price    = self::to_float( isset( $data['sale_price'] ) ? $data['sale_price'] : null );
		$this->current_price = self::to_float( isset( $data['current_price'] ) ? $data['current_price'] : null );
		$this->tax_inclusive = ! isset( $data['tax_inclusive'] ) || (bool) $data['tax_inclusive'];
		$this->first_seen    = ! empty( $data['first_seen'] ) ? (string) $data['first_seen'] : null;
		$this->source        = isset( $data['source'] ) ? (string) $data['source'] : 'standalone';

		if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {
			$this->categories = array_values( array_filter( array_map( 'strval', $data['categories'] ) ) );
		}
	}

	/**
	 * Effective retail price: current price, then sale, then regular.
	 *
	 * @return float|null
	 */
	public function effective_price() {
		if ( null !== $this->current_price ) {
			return $this->current_price;
		}

		if ( null !== $this->sale_price ) {
			return $this->sale_price;
		}

		return $this->regular_price;
	}

	/**
	 * Whether this is a service.
	 *
	 * @return bool
	 */
	public function is_service() {
		return 'service' === $this->type;
	}

	/**
	 * Whether this is a variation.
	 *
	 * @return bool
	 */
	public function is_variation() {
		return $this->variation_id > 0;
	}

	/**
	 * Stable identity key for caches and maps.
	 *
	 * @return string
	 */
	public function key() {
		return $this->id . ':' . $this->variation_id;
	}

	/**
	 * Export-safe array representation.
	 *
	 * @return array<mixed>
	 */
	public function to_array() {
		return array(
			'id'            => $this->id,
			'variation_id'  => $this->variation_id,
			'parent_id'     => $this->parent_id,
			'type'          => $this->type,
			'name'          => $this->name,
			'sku'           => $this->sku,
			'brand'         => $this->brand,
			'barcode'       => $this->barcode,
			'unit'          => $this->unit,
			'unit_price'    => $this->unit_price,
			'currency'      => $this->currency,
			'availability'  => $this->availability,
			'regular_price' => $this->regular_price,
			'sale_price'    => $this->sale_price,
			'current_price' => $this->current_price,
			'tax_inclusive' => $this->tax_inclusive,
			'first_seen'    => $this->first_seen,
			'categories'    => $this->categories,
			'source'        => $this->source,
		);
	}

	/**
	 * Cast a value to float or null.
	 *
	 * @param mixed $value Value.
	 * @return float|null
	 */
	private static function to_float( $value ) {
		if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
			return null;
		}

		return (float) $value;
	}
}
