# Data Model: Croatian Anchor Price ("Sidrena cijena") Compliance System

**Feature**: `001-sidrena-cijena-compliance` | **Date**: 2026-09-21

All plugin tables use `$wpdb->prefix` and are created by versioned migrations. Price history is **append-only**: rows are never updated or deleted by normal operations; corrections add new rows that reference the original.

## Entity overview

```text
Catalog item (WooCommerce product/variation OR standalone sidrena_item)
  ├── 1..n Price history record      (regular | sale | anchor | lowest_30_day | historical)
  ├── 1..n Anchor price record       (a price history record with price_type = anchor)
  └── 0..n Special sale              (with computed lowest 30-day price)

Sales location 1..n Price-list publication (csv | xml, retained ≥ 30 days)

Legal-rule configuration (options) — audited; never rewrites history
Log entry (structured, no personal data)
Validation finding (computed on demand; publication findings persisted with the publication)
```

## 1. Price history record — `{prefix}sidrena_price_history`

The single chronological ledger for every price, including anchor prices and computed 30-day lowest prices.

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | no | — | Primary key |
| `product_id` | BIGINT UNSIGNED | no | — | Catalog item ID (post ID for standalone items) |
| `variation_id` | BIGINT UNSIGNED | yes | NULL | Variation ID when applicable |
| `item_type` | VARCHAR(20) | no | `product` | `product` \| `service` |
| `sku` | VARCHAR(191) | yes | NULL | Denormalized for import/export and audit |
| `price_type` | VARCHAR(20) | no | — | `regular` \| `sale` \| `anchor` \| `lowest_30_day` \| `historical` |
| `price` | DECIMAL(20,6) | yes | NULL | NULL only for "price on request" items |
| `currency` | CHAR(3) | no | `EUR` | ISO 4217 |
| `tax_inclusive` | TINYINT(1) | no | 1 | Whether the price includes tax |
| `valid_from` | DATETIME | yes | NULL | Start of validity in site local time |
| `valid_to` | DATETIME | yes | NULL | NULL = open-ended |
| `reference_date` | DATE | yes | NULL | Required for `anchor` records; optional for `historical` |
| `source` | VARCHAR(50) | no | — | `woocommerce` \| `standalone` \| `manual` \| `bulk` \| `import` \| `cli` \| `migration` \| `system` |
| `special_sale_type` | VARCHAR(50) | yes | NULL | Set for `sale` rows linked to a classified sale |
| `correction_of` | BIGINT UNSIGNED | yes | NULL | FK to the record this row corrects |
| `correction_reason` | TEXT | yes | NULL | Required when `correction_of` is set |
| `created_at` | DATETIME | no | — | UTC |
| `updated_at` | DATETIME | yes | NULL | Set only for non-price bookkeeping, never rewrites price |
| `created_by` | BIGINT UNSIGNED | yes | NULL | User ID; NULL for system |
| `metadata` | LONGTEXT | yes | NULL | JSON: evidence notes, import batch ID, original payload hash |

**Indexes**: `product_id`, `variation_id`, `sku`, `price_type`, `valid_from`, `reference_date`, composite `(product_id, variation_id, price_type)`, composite `(product_id, price_type, reference_date)`.

**Validation rules**
- `price_type` must be one of the five values; `anchor` requires `reference_date` and a non-empty `source`.
- `correction_of` must reference an existing row of the same product/variation and `price_type`.
- Anchor rows are immutable; a change creates a new row with `correction_of` and `correction_reason`.
- `price` must be NULL or a non-negative decimal; negative values are invalid (validator finding, import error).
- `currency` must be a 3-letter code; default `EUR`.
- `valid_to` must be NULL or later than `valid_from`.

## 2. Special sale — `{prefix}sidrena_special_sales`

A merchant-classified special form of sale with its computed 30-day lowest price.

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | no | — | Primary key |
| `product_id` | BIGINT UNSIGNED | no | — | Catalog item |
| `variation_id` | BIGINT UNSIGNED | yes | NULL | Variation when applicable |
| `sale_type` | VARCHAR(50) | no | — | `akcija` \| `snizenje` \| `sezonsko_snizenje` \| `rasprodaja` \| `other:<label>` |
| `sale_name` | VARCHAR(191) | yes | NULL | Display name of the special form of sale |
| `start_date` | DATETIME | no | — | Sale start (site local time) |
| `end_date` | DATETIME | yes | NULL | NULL = open-ended |
| `promo_price` | DECIMAL(20,6) | yes | NULL | Current promotional price |
| `lowest_30_day_price` | DECIMAL(20,6) | yes | NULL | Computed lowest price for the window |
| `window_start` | DATE | yes | NULL | First day of the calculation window (sale start − 30 days) |
| `window_end` | DATE | yes | NULL | Last day of the calculation window (sale start − 1 day) |
| `status` | VARCHAR(20) | no | `scheduled` | `scheduled` \| `active` \| `ended` \| `cancelled` |
| `created_at` | DATETIME | no | — | UTC |
| `updated_at` | DATETIME | yes | NULL | |
| `created_by` | BIGINT UNSIGNED | yes | NULL | |
| `metadata` | LONGTEXT | yes | NULL | JSON: calculation inputs hash, recompute reason |

**Indexes**: `product_id`, `(product_id, variation_id)`, `start_date`, `end_date`, `status`, `sale_type`.

**State transitions**: `scheduled → active → ended`; `scheduled|active → cancelled`. Recalculation of `lowest_30_day_price` is allowed while `scheduled` or `active` (the underlying price history may change); recomputation writes a `lowest_30_day` row to the price history ledger and updates this row's cache fields.

**Validation rules**
- `sale_type` is mandatory; an unclassified promotion is never treated as a special sale (FR-025).
- `end_date` must be NULL or later than `start_date`.
- The 30-day window is exactly: `window_end = start_date − 1 day`, `window_start = start_date − 30 days` (calendar days, site time zone).
- `promo_price` must be present for an active sale with a price.

## 3. Sales location — `{prefix}sidrena_locations`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | no | — | Primary key |
| `name` | VARCHAR(191) | no | — | Human-readable name |
| `type` | VARCHAR(50) | no | — | `prodavaonica` \| `webshop` \| `poslovnica` \| `usluzno_mjesto` \| `other:<label>` |
| `address` | VARCHAR(255) | yes | NULL | Street and number |
| `city` | VARCHAR(191) | yes | NULL | City |
| `identifier` | VARCHAR(50) | no | — | Facility identifier used in filenames (for example `P1`) |
| `active` | TINYINT(1) | no | 1 | Inactive locations are not published |
| `applies_to` | LONGTEXT | yes | NULL | JSON: product/category applicability (empty = all items) |
| `price_list_config` | LONGTEXT | yes | NULL | JSON: per-location overrides of format selection and filename separator/case |
| `sequence_no` | INT UNSIGNED | no | 0 | Last used storage/sequence number |
| `created_at` | DATETIME | no | — | UTC |
| `updated_at` | DATETIME | yes | NULL | |

**Indexes**: unique `identifier`, `active`, `type`.

**Validation rules**
- `identifier` is required and unique (validator finding "missing store identifier" when empty).
- `address` is required for physical locations (not for `webshop`).
- A location may be deleted only when it has no retained publications; otherwise it is deactivated (audit preservation).

## 4. Price-list publication — `{prefix}sidrena_publications`

Archive record for every generated file.

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | no | — | Primary key |
| `location_id` | BIGINT UNSIGNED | no | — | FK to locations |
| `format` | VARCHAR(10) | no | — | `csv` \| `xml` |
| `filename` | VARCHAR(255) | no | — | Generated name (validated) |
| `path` | VARCHAR(500) | no | — | Absolute path under the plugin uploads directory |
| `url` | VARCHAR(500) | no | — | Public URL |
| `checksum` | CHAR(64) | no | — | SHA-256 of file contents |
| `content_fingerprint` | CHAR(64) | no | — | Fingerprint of source data + rule version (duplicate suppression) |
| `file_size` | BIGINT UNSIGNED | no | 0 | Bytes |
| `rule_version` | VARCHAR(50) | no | — | Legal-rule version identifier in force |
| `status` | VARCHAR(20) | no | `pending` | `pending` \| `generated` \| `validated` \| `published` \| `failed` \| `superseded` |
| `sequence_no` | INT UNSIGNED | no | — | Storage/sequence number used in the filename |
| `item_count` | INT UNSIGNED | no | 0 | Number of exported rows |
| `validation_summary` | LONGTEXT | yes | NULL | JSON: finding counts by severity |
| `created_at` | DATETIME | no | — | UTC |
| `published_at` | DATETIME | yes | NULL | UTC |
| `expires_at` | DATETIME | yes | NULL | `published_at` + retention (≥ 30 days) |
| `created_by` | BIGINT UNSIGNED | yes | NULL | NULL for scheduled runs |

**Indexes**: `location_id`, `format`, `status`, `published_at`, `expires_at`, `checksum`, `content_fingerprint`, `filename`.

**State transitions**: `pending → generated → validated → published`; any step may transition to `failed`; a newer publication for the same location/format marks the previous `published` row as `superseded` (the file remains available until `expires_at`). Cleanup removes the file at/after `expires_at` and marks the row `expired` in metadata while retaining the record.

**Validation rules**
- `expires_at` must be at least `published_at` + 30 days.
- `status = published` requires `url`, `checksum` and `published_at`.
- Files are only removed after `expires_at`; archive rows are never deleted by cleanup.

## 5. Log entry — `{prefix}sidrena_logs`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | no | — | Primary key |
| `level` | VARCHAR(10) | no | — | `info` \| `warning` \| `error` |
| `event` | VARCHAR(50) | no | — | `export_scheduled`, `export_manual`, `export_failed`, `import`, `validation_failure`, `history_correction`, `migration`, `settings_change`, `cleanup` |
| `message` | TEXT | no | — | Human-readable summary |
| `context` | LONGTEXT | yes | NULL | JSON; must not contain secrets or personal data |
| `user_id` | BIGINT UNSIGNED | yes | NULL | NULL for system events |
| `created_at` | DATETIME | no | — | UTC |

**Indexes**: `level`, `event`, `created_at`, `user_id`.

## 6. Options (not tables)

| Option | Shape | Notes |
|--------|-------|-------|
| `sidrena_cijena_settings` | array | Display settings (enable, label, locations, priority, archives, product pages, variations, only-when-different, disclaimer, formats, deadline, retention, log retention, delete-on-uninstall) |
| `sidrena_cijena_rule_config` | array | General reference date (default 2026-09-10), legacy reference date (default 2025-05-02), legacy enabled, category mapping, legal-rule version identifier |
| `sidrena_cijena_rule_config_history` | array | Append-only audit of rule-configuration changes (who, when, old, new, reason) |
| `sidrena_cijena_db_version` | string | Schema version for migrations |
| `sidrena_cijena_last_generation` | array | Last run per location/format, outcome, duration |

Changing a reference date or category mapping writes to `sidrena_cijena_rule_config_history` and never rewrites price history rows.

## 7. Standalone catalog item (custom post type `sidrena_item`)

Used only when WooCommerce is absent. Private (`public => false`, `show_ui => true`), supports title and editor, with:

- Taxonomies: `sidrena_brand` (brand), `sidrena_item_type` (`product` | `service`).
- Meta: `_sidrena_sku`, `_sidrena_barcode`, `_sidrena_unit`, `_sidrena_unit_price`, `_sidrena_availability` (`available` | `unavailable`), `_sidrena_regular_price`, `_sidrena_sale_price`, `_sidrena_first_seen` (date), `_sidrena_currency`, `_sidrena_tax_inclusive`.
- Price changes are captured on `save_post_sidrena_item` and written to the price history ledger with `source = standalone`.

## 8. Validation finding (computed)

Findings are computed on demand by the validator and are not stored as first-class rows; publication runs persist their summary in `sidrena_publications.validation_summary`. Each finding has:

- `severity`: `ERROR` | `WARNING` | `INFO`
- `code`: stable identifier (for example `missing_anchor`, `invalid_reference_date`, `missing_sku`, `missing_barcode`, `invalid_retail_price`, `invalid_unit_price`, `invalid_availability`, `missing_sale_type`, `missing_30_day_price`, `malformed_csv`, `malformed_xml`, `invalid_filename`, `missing_store_identifier`, `stale_price_list`, `missing_publication`, `invalid_currency`, `duplicate_product`)
- `entity`: affected catalog item, location or publication
- `message`: plain-language explanation (translatable, Croatian in `hr_HR`)
- `rule_reference`: the requirement/rule that triggered it

## 9. Referential integrity and deletion rules

- Price history and special sales reference catalog items by ID; deleting a catalog item does not delete its history (orphan history is retained for audit and flagged as `INFO`).
- Publications reference locations; locations with retained publications are deactivated, never deleted.
- Uninstall (opt-in) deletes plugin tables, options, publications and standalone items; it never touches WooCommerce products, orders or other plugins' data.
- Deactivation deletes nothing.

## 10. Rounding and currency

- Monetary values are stored with 6 decimal places and formatted for display/export to 2 decimal places (documented in `contracts/csv-format.md`), using half-up rounding on the internal decimal value.
- The 30-day lowest price and anchor price are compared using the stored 6-decimal values, not display-formatted values.
- Currency is per record; a catalog item whose currency differs from the configured currency raises an `ERROR` finding (FR-058).
