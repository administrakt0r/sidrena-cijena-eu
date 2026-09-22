# Price history

All price facts live in `{prefix}sidrena_price_history` and are
**append-only**. Nothing in the plugin updates or deletes a history row;
a correction inserts a new row with `correction_of` pointing at the
original and a mandatory `correction_reason`.

## Record types (`price_type`)

| Type | Meaning |
| ---- | ------- |
| `regular` | Ordinary retail price, valid from `valid_from` (optionally until `valid_to`) |
| `sale` | Promotional price observation |
| `anchor` | Immutable anchor record bound to a legal `reference_date` |
| `lowest_30_day` | Computed 30-day lowest-price snapshot, bound to a sale and window |
| `historical` | Backfilled older price used for 30-day calculations |

## Sources (`source`)

`woocommerce`, `standalone`, `manual`, `bulk`, `import`, `cli`,
`migration`, `system`. The standalone product editor and the anchor meta
box capture price edits automatically; imports tag rows with their batch.

## What each row stores

Product/variation identity, SKU, type, price (6 decimals in storage,
half-up 2-decimal display), currency, tax-inclusive flag, validity window,
reference date (anchors), sale type window (special sales), creator
(`created_by`), timestamps and a JSON `metadata` envelope (sale id,
window, correction reason, batch id).

Indexes cover `product_id`, `variation_id`, `sku`, `price_type`,
`valid_from` and `reference_date`, plus composite
`(product_id, variation_id, price_type)` and
`(product_id, price_type, reference_date)` lookups.

## Corrections

* Anchor corrections go through `AnchorPriceService::record()` with a
  non-empty reason (admin meta box or `BulkAnchorEntryPage`); the original
  row is preserved and the new row carries `correction_of`.
* Generic corrections go through the price-history screen
  (`PriceHistoryPage::handle_correct()`), which requires an existing
  record to correct and a reason.
* Every correction is logged (`history_correction` event) with the user
  id; the correction chain is visible on the history screen
  ("correction of record #N").

## Audit

* `RuleConfig::history()` — append-only log of every legal-rule change
  (key, old/new value, user, reason, timestamp), shown on
  `AuditPage` with site-local times.
* `LogRepository` — operational events (`export_*`, `import`,
  `validation_failure`, `history_correction`, `migration`,
  `settings_change`, `cleanup`) with severity levels; contexts are
  stripped of secrets and personal data before storage.
* Deactivation preserves all history; uninstall deletes it only with
  explicit opt-in (`sidrena_cijena_delete_data_on_uninstall`).
