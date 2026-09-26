# WooCommerce integration

WooCommerce is **optional**. The plugin never fatals when it is absent,
disabled or uninstalled: `WooCommerceDetector` gates every integration
point, and a standalone catalog (`sidrena_item` post type) covers shops
without WooCommerce. No WooCommerce, core or theme files are ever
modified.

## Supported products

* Simple products (regular + sale prices, SKU, stock status).
* Variable products and their variations (variation-specific anchors,
  variation payload extension, per-variation display).
* Brands from the `product_brand` taxonomy when present; units, unit
  prices and barcodes from common meta keys (barcode keys filterable via
  `sidrena_cijena_barcode_meta_keys`).
* Item classification (product vs service) via `_sidrena_item_type` meta
  or the `sidrena_cijena_wc_item_type` filter.

Native price fields are only **read** (CRUD getters). The plugin never
writes WooCommerce price meta and never touches checkout, payment,
shipping or coupons.

## Hooks used

* `woocommerce_get_price_html` (priority 20) — inline anchor block.
* `woocommerce_single_product_summary` /
  `woocommerce_after_shop_loop_item_title` (configurable priority) —
  summary-block display.
* `woocommerce_available_variation` — per-variation anchor payload.
* `woocommerce_store_api_product_data` — block-template coverage.
* `woocommerce_new_product` / `woocommerce_update_product` and variation
  equivalents — regular and active sale price observations and the
  `sidrena_cijena_price_changed` signal. WooCommerce scheduled-sale callbacks
  and legacy batch hooks record effective start/end boundaries after the
  price state is applied.
* `wp_enqueue_scripts` — `assets/css/frontend.css`,
  `assets/js/frontend.js` (variation-price swapping).

The standard WooCommerce admin editor, REST controllers, built-in importer,
bulk editor and programmatic `WC_Product::save()` path use these CRUD hooks.
Integrations that change product prices with direct SQL or direct post-meta
writes bypass WooCommerce CRUD and are unsupported for automatic history
capture. A sale removal appends a null-price closure observation; prior history
rows are retained.

An active WooCommerce offer is determined from its current effective price,
not merely from a saved future sale price. Sale classifications are matched to
the first captured sale price after the last sale-closure event, including the
offer start date and starting promotional price. Progressive reductions remain
part of that offer and keep its original 30-day reference snapshot. After a
sale ends, the price-list builder stops marking the restored regular price as a
special sale; a later sale cannot reuse the earlier classification. If a
discounted item has no matching classification, no positive reference for the
30 days before that offer, or a current price that is not below the reference,
cjenik publication is blocked. The plugin does not infer legal exceptions;
unclear eligibility needs human review.

## Template functions, shortcode, blocks

* PHP: `sidrena_cijena_get_anchor_price()`,
  `sidrena_cijena_get_lowest_30_day_price()`,
  `sidrena_cijena_render_anchor_price()` (also namespaced under
  `SidrenaCijena\Api`).
* Shortcode: `[sidrena_cijena product_id="…" variation_id="…"
  show_current="yes" show_anchor="yes" show_30_day="no" label="…"]`
  rendering `.sidrena-cijena__current`, `__anchor` and `__30-day` blocks.
* Templates in `templates/` are overridable via
  `your-theme/sidrena-cijena/anchor-price.php` (same for
  `thirty-day-price.php`).
* Block themes: anchor output flows through the Store API product data
  and the price-HTML filter; a dedicated Gutenberg block is not shipped
  (limitation — use the shortcode block).

## Admin

When WooCommerce is active the menu lives under WooCommerce; otherwise
under Settings. The product edit screen gets a read-only anchor summary
meta box with a link to the full history; anchor recording happens from
there or the bulk-entry screen.
