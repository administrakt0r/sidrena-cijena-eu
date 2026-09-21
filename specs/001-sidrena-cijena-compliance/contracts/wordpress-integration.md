# Contract: WordPress/WooCommerce integration surface

**Feature**: `001-sidrena-cijena-compliance` | **Version**: 1.0

The plugin integrates through official extension points only and never modifies core or theme files (FR-085). This contract lists what the plugin registers and what it exposes for themes and other plugins.

## Frontend display

| Hook | Type | Purpose |
|------|------|---------|
| `woocommerce_get_price_html` | filter | Append the anchor price to the price HTML on product pages and archives |
| `woocommerce_single_product_summary` | action | Render the anchor-price block on single product pages (priority configurable) |
| `woocommerce_before_shop_loop_item_title` / `woocommerce_after_shop_loop_item_title` | action | Render the anchor-price block in archive loops (priority configurable) |
| `woocommerce_available_variation` | filter | Add anchor data to variation JSON so the correct variation anchor updates on selection |
| `woocommerce_store_api_product_data` (Blocks/Store API) | filter | Expose `extensions.sidrena_cijena` (current, anchor, 30-day) for block-based templates where technically possible |
| `wp_enqueue_scripts` | action | Enqueue the small frontend stylesheet/script only when display is enabled |

If WooCommerce is absent, none of the above is registered. The standalone catalog and all admin/compliance features continue to work.

## Public template API

Namespaced functions in `SidrenaCijena\Api`:

```php
sidrena_cijena_get_anchor_price( int $product_id, int $variation_id = 0 ): ?float
sidrena_cijena_get_lowest_30_day_price( int $product_id, int $variation_id = 0 ): ?float
sidrena_cijena_render_anchor_price( int $product_id, array $args = [] ): string
```

Thin global wrappers are provided for themes (`functions.php`) and documented in `docs/woocommerce.md`; they are the only global functions the plugin introduces.

## Shortcode

```text
[sidrena_cijena product_id="123" variation_id="0" show_current="yes" show_anchor="yes" show_30_day="no" label="Sidrena cijena"]
```

Output is escaped, minimal markup with stable CSS classes (`sidrena-cijena`, `sidrena-cijena__current`, `sidrena-cijena__anchor`, `sidrena-cijena__30-day`). Defaults come from settings.

## Actions emitted

| Hook | Arguments | When |
|------|-----------|------|
| `sidrena_cijena_price_changed` | `$product_id, $variation_id, $old, $new, $source` | After a price change is recorded |
| `sidrena_cijena_anchor_recorded` | `$record_id, $product_id, $variation_id, $reference_date` | After an anchor record is created |
| `sidrena_cijena_publication_published` | `$publication_id, $location_id, $format` | After a price list is published |
| `sidrena_cijena_publication_failed` | `$location_id, $format, $findings` | After a generation/validation failure |
| `sidrena_cijena_import_completed` | `$batch_id, $inserted, $skipped, $errors` | After an import commits |

## Filters exposed

| Hook | Arguments | Purpose |
|------|-----------|---------|
| `sidrena_cijena_display_label` | `$label, $context` | Change the anchor-price label |
| `sidrena_cijena_display_html` | `$html, $item, $context` | Change the rendered anchor block |
| `sidrena_cijena_anchor_price` | `$price, $item` | Adjust the resolved anchor price |
| `sidrena_cijena_export_row` | `$row, $item, $location, $format` | Adjust an exported row |
| `sidrena_cijena_filename` | `$filename, $components, $location, $format` | Adjust the generated filename (still validated) |
| `sidrena_cijena_rest_price_item` | `$data, $item` | Adjust a REST price payload |
| `sidrena_cijena_capability` | `$capability` | Change the capability required for compliance operations |

## Admin integration

- Menu: `WooCommerce → Sidrena cijena` when WooCommerce is active, otherwise `Settings → Sidrena cijena` (FR-068, brief §7).
- Settings registered through the WordPress Settings API with sanitization callbacks and capability checks; every change logged.
- Product edit screen: a meta box shows the item's anchor price, reference date, source and 30-day status with a link to the full history (read-only; corrections happen on the history screen so the original is preserved).
- Standalone catalog: a private `sidrena_item` post type with the same meta box.

## Data ownership

- The plugin never writes to WooCommerce price meta directly; it reads through the CRUD API and writes only its own tables/options.
- WooCommerce checkout, payment, shipping and coupon logic is untouched.
- Deactivating or uninstalling WooCommerce leaves plugin data intact; the standalone catalog becomes the active source if configured.
