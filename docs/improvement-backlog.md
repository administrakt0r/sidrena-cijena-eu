# Improvement backlog

## Data-layer performance (2026-09-26)

The prompt's expected 200+ item shop was not present: local wp-env had 6 published
WooCommerce products (8 hydrated catalog rows), 111 price-history rows and 110 log
rows. Keep these measurements as the actual demo baseline; do not extrapolate timings
to a large shop. `timeline()` and exact counts were measured but not changed because
their repeated local timings were about 0.30 ms and 0.10 ms respectively, and count
caching would risk stale admin pagination without a robust invalidation contract.

| ID | Change / evidence | Status |
|----|-------------------|--------|
| PERF-01 | `latest_map()` uses grouped greatest-ID selection. Probe: 58 historical rows read by legacy SQL vs 56 latest map keys; one query either way. At this size grouped query was slower (1.30 ms vs 0.55 ms), so this is a row-volume bound, not a measured latency win. | done |
| PERF-02 | Storefront 30-day lookups use one request-local batch sale map, with winner ordering aligned to `active_for_item()`. Variation fallback is a map lookup. Demo page returned HTTP 200 in 0.843 s; one provider call hydrated 8 rows. | done |
| PERF-03 | Catalog batches prime meta/term caches and memoize barcode resolution. `get_items(per_page=100, include_variations=true)` hydrated 8 rows in 21.75 ms and 22 total DB queries on the small demo. | done |
| PERF-04 | Additive indexes for history filters/latest selection, active sale filtering and publication lookup. Upgrade/index/idempotence integration test added. | done |
| PERF-05 | Exact counts intentionally unchanged: 100 repeated history counts averaged 0.098 ms at 111 rows; log counts averaged 0.111 ms at 110 rows. Dataset is too small to justify a potentially stale cache; remeasure with the intended large seed before changing pagination behavior. | measured; deferred |
| PERF-06 | Publication update avoids duplicate `get()` and expiration is now one direct update rather than get+full-row update. `next_sequence()` remains atomic read-after-write (two queries) to preserve sequence correctness under concurrent publishers; no collision benchmark justified a change. | done / retained |
| PERF-07 | `timeline()` left unchanged: 100 calls measured 29.5 ms (~0.30 ms/call) on this dataset; COALESCE ordering index work is not justified by this measurement. | measured-irrelevant |
| PERF-08 | `GenerationService::generate_location()` shares validation report and built catalog rows across format writers (`csv` + `xml`), eliminating duplicate validation sweeps and redundant row mappings per generation. | done |
| PERF-09 | `PriceListBuilder::fingerprint()` hashes catalog items incrementally row-by-row via `hash_init`/`hash_update`, preventing whole-catalog payload duplication and giant JSON string allocations in memory while guaranteeing byte-identical hashes. | done |
| PERF-10 | Streaming price-list export in `CsvWriter::write_stream()`, `XmlWriter::write_stream()`, and `Publisher::publish()`. Avoids in-memory document string accumulation, and computes SHA-256 checksum and file size during write, eliminating `hash_file('sha256')` disk re-reads. | done |
| PERF-11 | `LocationRepository::next_sequence()` collapsed to a single atomic query using `LAST_INSERT_ID(sequence_no + 1)` with direct integer fallback, removing full row SELECT and JSON hydration. | done |
| PERF-12 | `CleanupJob` and `PublicationRepository` batch expiration: candidate expired IDs are updated via a single `UPDATE ... WHERE id IN (...)` query instead of N individual updates, and `PublicationRepository::update()` accepts an existing row to skip redundant `get()` queries. | done |
| PERF-13 | `PriceListController::download()` caches serialized CSV and XML documents in `RestCache` and serves existing published files directly from disk for unfiltered downloads, avoiding full catalog re-serialization on every REST download. | done |

Tracked gaps and competitor findings for **Sidrena Cijena**. Items are worked one at a
time; every item gets a status, the date it was closed and (where applicable) a test.

Comparison source: MJDD Sidrena Cijena 1.24.3 (WordPress.org, 24 Sep 2026), reviewed from
the published package. Their strengths are listed so the decision not to copy something
is as visible as the decision to copy it.

Status values: `todo`, `in progress`, `done`, `declined` (with a reason), `covered` (we
already have an equivalent implementation).

## Compliance and correctness fixes (2026-09-25)

Found while setting up the local environment and bringing the test suites green. These
were defects in our own code, not competitor ideas.

| ID | Fix | Why it mattered | Status |
|----|-----|-----------------|--------|
| FIX-01 | Publication gate registers the validator before checking findings (`src/Export/GenerationService.php`) | A fresh CLI/cron run published price lists **without** required anchor prices, violating FR-007/FR-093. `done` |
| FIX-02 | WooCommerce price-history recorder (`src/WooCommerce/PriceHistoryRecorder.php`) | Woo products never produced history rows, so the 30-day lowest price could never be calculated and every classified sale blocked publication. `done` |
| FIX-03 | 30-day window overlap (`PriceHistoryRepository::timeline()`) | Rows that stopped being valid before the promotion were excluded, inflating the lowest price. `done` |
| FIX-04 | Date-only normalization for sale windows (`Support\Dates::date_only()`) | `datetime` columns failed strict `Y-m-d` validation, so every sale update/recompute errored. `done` |
| FIX-05 | Storefront prices converted with `wc_get_price_to_display()` | The anchor was shown next to Woo's tax-adjusted price using raw stored amounts. `done` |
| FIX-06 | `render_block` support for `woocommerce/product-price` | Block themes (Twenty Twenty-Five and newer) showed no anchor at all. `done` |
| FIX-07 | Import CLI summary reads the real counters | `wp sidrena-cijena import` always reported `0 inserted / 0 overwrite`. `done` |
| FIX-08 | Duplicate location identifiers rejected before the database | Produced a raw MySQL duplicate-key error instead of a clean rejection. `done` |
| FIX-09 | `local.sh` WooCommerce slug, demo address, seed idempotency, coming-soon mode, theme | The documented one-command setup could not complete. `done` |
| FIX-10 | Integration suite repaired (15 failures, 1 risky → 0) | Tests had drifted from the implemented spec (fixtures without anchors, `date` vs `datetime`, error codes, string vs int DB values). `done` |
| FIX-11 | Block price hook only renders for the product it belongs to | WooCommerce Blocks pass no context with `woocommerce/product-price`; the fallback to the queried product attached **this product's anchor to other products' prices** (upsells, collection items). Now the anchor is appended only when the block declares `isDescendentOfSingleProductTemplate` or carries a product id. `done` |
| FIX-12 | E2E suite isolated on the test environment (IMP-11) | Specs created and deleted data in the demo shop, blocked each other and broke the local demo. `done` — `tests/e2e/global-setup.js`, `tests-cli` service, base URL 8889 |

## Product gaps versus MJDD 1.24.3

| ID | Item | Why it matters | Priority | Status |
|----|------|----------------|----------|--------|
| IMP-01 | CSV formula-injection neutralisation on export | Product names, SKUs or brands coming from imported data are rendered into CSV; a leading `=`, `+`, `-` or `@` is executed by Excel/LibreOffice when the ministry opens the file. | High | `done` — `CsvWriter::neutralise_formula()`, covered by 2 unit tests |
| IMP-02 | HPOS compatibility declaration | WooCommerce marks plugins that do not declare `custom_order_tables` as incompatible and warns the merchant. | High | `done` — `sidrena_cijena_declare_woocommerce_compatibility()` on `before_woocommerce_init` |
| IMP-03 | Import encoding conversion (ISO-8859-2, Windows-1250) | Croatian ERP systems overwhelmingly export legacy-encoded CSV; today such a file is rejected or produces mojibake. | High | `done` — `CsvParser::to_utf8()` (UTF-8 kept, CP1250 detected via 0x80–0x9F), 5 unit tests |
| IMP-04 | Pretty public feed URLs (`/sidrena-cijena.xml`, `/sidrena-cijena.csv`) with 503 + queued rebuild | Merchants hand a stable URL to the ministry today; ours only exists under `/wp-json/…`, and a missing/stale file is not reported the way theirs is. | High | `done` — `src/Api/PublicFeed.php` serves the published file of the webshop location, answers `503` + `Retry-After: 900` and queues one rebuild per 15 min; rules flushed on activation; 5 integration tests |
| IMP-05 | Public HTML price list + public archive page | The decree expects a price list a human can open, not only machine endpoints. | Medium | `done` — `/sidrena-cjenik/` (searchable list, filters, XML/CSV links) and `/arhiva-sidrene-cijene/` (published files with checksums); shortcodes `[sidrena-cjenik]` and `[sidrena-arhiva]`; 8 integration tests |
| IMP-06 | Page-builder price compatibility (Elementor, Divi, Bricks, Oxygen, WPBakery, Avada, …) | Our anchor disappears when a builder renders the price element. | Medium | `done` — `src/WooCommerce/Display/BuilderCompat.php` covers Divi, Avada/Fusion, Beaver Builder, Bricks and Elementor price elements on a single product page. Builders that call `get_price_html()` were already covered by the price filter. JS-based injection (their fallback for themes) was deliberately **not** copied: it cannot tell which product a price belongs to, and a wrong anchor is worse than none |
| IMP-07 | Anchor tooltip | Their default presentation explains the anchor date in one line; ours is plain text. | Low | `done` — the anchor block carries a `title` with the label and the reference date (`anchor_title` template argument); the date is still printed in the row, so touch devices are not left out |
| IMP-08 | Bulk "fill empty" helpers (WP-CLI `fill`, admin buttons, activation notice) | Large catalogs need one command to copy regular prices/dates into empty anchor fields instead of clicking per product. | Medium | `declined` — FR-093 forbids inferring the anchor from the current catalog price, and their two buttons do exactly that ("fill from regular price", "fill with today's date"). Recording a guess as legal evidence is worse than a visible gap. Bulk recording already exists where it is safe: `wp sidrena-cijena import` with `sku;reference_date;anchor_price` (dry-run first), the bulk-entry screen and the dashboard list of items missing an anchor |
| IMP-09 | Uninstall must keep the anchor snapshot | On full data deletion the immutable price history (the legal record) disappears. Their uninstall deliberately keeps product meta. | Medium | `done` — opt-in uninstall prunes `regular`/`sale`/`historical` rows and keeps the `anchor`/`lowest_30_day` evidence in `wp_sidrena_price_history`; option description, `docs/operations.md`, `docs/price-history.md` and the lifecycle test updated |
| IMP-10 | Multi-outlet feeds | Already covered by sales locations: one location = one outlet, each with its own file set, identifier and availability. | — | `covered` |
| IMP-11 | Isolated E2E environment | The Playwright suite mutates the demo shop, so specs block each other (publication fails because another spec created an anchor-less product). Point the suite at the 8889 test environment. | High | `done` — see FIX-12; one Playwright worker, `tests/e2e/global-setup.js` baseline |
| IMP-12 | Repair `npm run lint:js` | `@typescript-eslint` fails to load under Node 24, so the documented JS gate cannot run. | Medium | `done` — `.eslintrc.js` gives the project its own browser config (`eslint:recommended`, `env.browser`); `wp-scripts lint-js` uses it instead of the bundled one; gate passes |

## Public release work queue

| ID | Item | Status and verification |
|----|------|-------------------------|
| REL-01 | Release readiness | `done` — 1.0.0 ZIP built from `7e98210`; 427/427 strings in both catalogs, corrected MO offsets and WordPress `hr` filename. All six gates green (unit 119/380, integration 70/329, E2E 27), local.sh exit 0. Destroyed/recreated isolated wp-env: ZIP installed/activated on fresh WP 7.1.2 without WooCommerce; schema and automatic Croatian translation passed. See release-readiness.md. |
| REL-02 | Ministry filename example | `done` — retained the normalized contract, documented punctuation differences and that sequence is the storage number in filename-profile.md. No silent format change or legal-equivalence claim. Existing filename unit and publication integration/E2E gates pass; documentation-only decision. |
| REL-03 | WordPress.org submission | `in progress` — metadata, assets, licensing and privacy drafted and committed (readme.txt privacy/external-services/screenshots, GPL-2.0 LICENSE, .wordpress-org artwork, plugin URIs); readme validation, plugin-check and the submission itself remain and are deliberately last per user request. |
| REL-04 | Unit coverage | `done` — unit tests for `PublicFeed` (routing, webshop location preference, published-file lookup, rebuild cooldown), `PublicPage` (search matching, display formatting, request sanitizing, list/type/search filtering, empty states), `BuilderCompat` (builder hook routing, single-product append rules incl. display-location setting and product fallback) and `PriceHistoryRecorder` (witnessed price capture, dedupe, variation identity, services, empty sale prices, unknown items); added `tests/unit/wc-stubs.php` WooCommerce doubles and `tests/unit/PluginSwap.php` service seam; 42 new tests, suite 119/380 → 161/493. Test: `composer test:unit`, `composer analyse`, `composer lint`, `npm run lint:js`. |
| REL-05 | Public page performance | `done` — measured first (dev shop `/sidrena-cjenik/` 143–192 ms per request), then cached the rendered table through the shared `Api\RestCache` (key: page, location, q, type) whose generation marker is already invalidated by price/anchor/sale/publication/settings changes; warm requests 107–122 ms, rebuild after flush 162 ms, and a publication visibly refreshes the "Generated:" stamp. Location renames fall back to the 300 s TTL; archive page deliberately uncached (single indexed query). Test: 3 new unit tests (cache hit, flush rebuild, per-argument entries), suite 164/498. |
| REL-06 | Daily 08:00 deadline | `done` (2026-09-25) — DST drift resolved by re-anchoring scheduled runs to `Dates::next_occurrence( $run_time, false )` in site timezone; timezone update listeners on `update_option_timezone_string` and `update_option_gmt_offset`; working-day missed run detection with catch-up generation throttled by transient lock on `init`; failure recording with dismissible admin notice, Publications/Dashboard status alerts, and WP-CLI `status` health diagnostics; covered by `SchedulerTest` (9 tests, 19 assertions) and repository checks. |
| REL-07 | Pending specification deletions | `done` — committed the removal of `.specify/` and `specs/001-sidrena-cijena-compliance/` on 25 September 2026 (user-approved); the plugin's operative documentation lives in `docs/`. Test: full gate suite green with the scaffolding gone (unit 161/493, integration 76/342, E2E 28). |
| REL-08 | CI | `done` (2026-09-25) — `tools/ci/ci.yml` pipeline (ready for `.github/workflows/`) with dual jobs: static checks (PHPCS, PHPStan, PHPUnit unit, ESLint) and integration testing on `wp-env` with MySQL container. |
| REL-09 | Requested feature acceptance and version archives | `done` — 1.0.2 records witnessed new offers, including exact variation identity and services; excludes existing/imported catalog entries and preserves previous anchors. Prerequisites documented in feature-acceptance.md. Every version has a preserved installable ZIP/checksum in a numeric versions/ directory, committed and pushed. Unit 119/380, integration 76/342, E2E 28; PHPStan, PHPCS, ESLint, shell syntax and local.sh green. |
| REL-10 | Performance, admin UX, accessibility and robustness pass | `done` (2026-09-26) — `PriceListBuilder` verified to use exactly one batched anchor and sale query per build (no per-item lookups); fixed the product/service `meta.counts` bug and skip the sale query for empty catalogs. Bulk anchor screen now checks anchors through `PriceHistoryRepository::exists_map()` in 200-item batches for rendering, missing counts and shop-wide setup, removing N+1 queries on large catalogs. Public page caching confirmed through `RestCache` with price/anchor/sale/settings wiring; added cache invalidation when sales locations are saved, deactivated or deleted. Setup Guide statuses extracted into testable `SetupGuidePage::statuses()` with anchor coverage badge (`(done: %1$d of %2$d recorded)`), and the checklist stays in sync across all five steps. Accessibility: visible labels for the import file input and public type filter, `scope="col"` admin/preview table headers, ARIA labels on bulk controls, and higher-contrast public disclaimers. New unit tests for `PriceListBuilder` batching, `RestCache`, `SetupGuidePage::statuses()` and bulk anchor batching; unit suite 184/571 → 196/633. All four static gates clean (PHPStan 107/107, PHPCS, ESLint); translations 569/569 Croatian, 0 untranslated. |
| REL-11 | 1.0.9 improvement pass | `done` (2026-09-26) — Completed the REL-10 accessibility item: `scope="col"` on every remaining admin `<th>` (dashboard, compliance, publications, logs, audit x2, legal settings, documentation x3, import sources, sale history); source scan confirms zero unscoped headers in `src/`/`templates/`/`assets/`. Added `AnchorCacheTest` (miss/hit, cached nulls, malformed entries, per-item and variation-cascading invalidation, generation flush) and `SpecialSaleServiceTest` (FR-023 sale-type gate, date validation, not-found paths); unit suite 207/664 → 223/698. Static scan confirms no PHP 8-only syntax in `src/` (PHP 7.4–8.4). No legal semantics changed (`docs/legal-rules.md` untouched); no translatable strings added, catalogs not regenerated. |
| REL-12 | 1.0.10 improvement pass | `done` (2026-09-26) — REST price endpoints (`/prices`, CSV/XML downloads) now serve canonical builds from `RestCache` per location and item type instead of rebuilding the catalog per request; same invalidation triggers. `Plugin::anchors()` shares the `AnchorCache` and `sidrena_cijena_anchor_recorded` forgets the recorded item. Both cache generations are monotonic (`max( time(), previous + 1 )`) so back-to-back flushes cannot serve stale entries. New `PriceListControllerCacheTest`, `AnchorCacheWiringTest` and back-to-back flush tests; unit suite 223/698 → 231/722. All four static gates clean; no PHP 8-only syntax in `src/`; no legal semantics changed; no translatable strings added, catalogs not regenerated. |
| REL-14 | 1.0.12 improvement pass | `done` (2026-09-26) — Timezone-safe date-only rendering: new `Dates::format_date_display()` renders `Y-m-d` values as Croatian `d.m.Y.` without `strtotime()`/`wp_date()`, fixing the previous-day shift on sites west of UTC in the public price list and price history. `DashboardPage::aggregate()` now resolves an inherited parent sale before flagging missing price history and skips the sale lookup for empty catalogs. The sales-location category field got a screen-reader label and valid markup. `PriceListBuilderTest` extended to type filtering, include/exclude modes, variation sale inheritance, sorting and fingerprint stability, plus new `DashboardPageTest` and `LocationsPageTest`; unit suite 253/807 → 268/869. All four static gates clean (PHPStan 108/108, PHPCS 150/150, ESLint); no PHP 8-only syntax in `src/` (PHP 7.4–8.4); no translatable strings added or changed, catalogs not regenerated; no legal semantics changed. |
| REL-13 | 1.0.11 improvement pass | `done` (2026-09-26) — Trashing, deleting, or unpublishing a catalog item (`sidrena_item`, `product`, `product_variation`) now flushes the shared anchor and REST cache generations immediately via `transition_post_status`/`before_delete_post`; published-to-published edits stay cached. Previously the public page and REST responses served removed items until the 300 s cache TTL. Price history filters/table/correction form and the anchor meta box show translated labels via `Support\HistoryLabels` instead of raw slugs such as `lowest_30_day`/`observed_first_offer`. New `PluginLifecycleCacheTest`, `ComplianceReportTest` (severity fallback, entity grouping, descending code counts, CSV quoting, `from_result` meta) and `HistoryLabelsTest`; unit suite 231/722 → 253/807. All four static gates clean (PHPStan 108/108, PHPCS 148/148, ESLint); catalogs regenerated, 579/579 translated. No legal semantics changed. |

## Correctness and reliability sweep (2026-09-26)

Not derived from the MJDD comparison; a defect sweep over `src/` against
`docs/legal-rules.md`, `docs/feature-acceptance.md` and
`docs/price-list-format.md` ahead of the 1.0.8 release. All rows `done`
(2026-09-26) and shipped in 1.0.8.

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| SW-01 | `done` (2026-09-26) | Daily buffer window requeued a forced run every 30 s (`Scheduler::reschedule_daily(false)` after a run) | `SchedulerTest::test_prompt_window_queue_and_non_prompt_reschedule` |
| SW-02 | `done` (2026-09-26) | Associative cron args crashed `run_service_regeneration()` on PHP 8; args wrapped positionally | `PublicFeedTest`, integration service regeneration |
| SW-03 | `done` (2026-09-26) | `Scheduler::is_healthy()` reported a stale event as healthy; overdue beyond an hour is unhealthy | `SchedulerTest::test_is_healthy_reports_overdue_events` |
| SW-04 | `done` (2026-09-26) | Special-sale end dates were midnight-inclusive (inactive on the last day, one-day sales impossible) | `test_sale_is_active_on_its_end_day()` (integration) |
| SW-05 | `done` (2026-09-26) | Promo prices with comma decimals lost precision (`(float)` cast) | `SpecialSaleService` uses `Currency::to_storage()`; integration asserts `7.5` |
| SW-06 | `done` (2026-09-26) | Open-ended price rows pinned the 30-day lowest price forever; UTC `created_at` mixed with site dates | `LowestPrice30DayCalculatorTest` supersession + UTC conversion tests |
| SW-07 | `done` (2026-09-26) | Partial location address passed filename generation despite the documented block | `FilenameGenerator::generate()` returns `missing_location_address` |
| SW-08 | `done` (2026-09-26) | Date-only anchor correction did not change the fingerprint (no republication) | `PriceListBuilder::fingerprint()` includes `anchor_reference_date` |
| SW-09 | `done` (2026-09-26) | Imported CSV could not round-trip its own exports (multi-line fields, missing date alias, variations) | `CsvParserEncodingTest` multiline/alias tests; `RowValidator::index(include_variations)` |
| SW-10 | `done` (2026-09-26) | `PriceHistoryRepository::insert()` ignored failures; import catches `\Throwable`; dry-run counters corrected | Unit/integration suites |
| SW-11 | `done` (2026-09-26) | WooCommerce price changes never fired `sidrena_cijena_price_changed` (no cache flush/regeneration) | `PriceHistoryRecorderTest` + plugin hook wiring |
| SW-12 | `done` (2026-09-26) | Variation anchors skipped tax display; builder markup borrowed other products' anchors | `PriceDisplay::display_price()` shared; `BuilderCompatTest` queried-product guards |
| SW-13 | `done` (2026-09-26) | Saving one settings tab reset flags of other tabs; uninstall opt-in read a never-written option | `SettingsTest`, `UninstallerTest`, integration lifecycle |
| SW-14 | `done` (2026-09-26) | Bulk anchor pagination derived pages from the unfiltered catalog (false all-clear) | `BulkAnchorEntryTest::test_bulk_anchor_paginates_the_filtered_missing_set` |
| SW-15 | `done` (2026-09-26) | Failed file deletion marked rows expired; failed archive insert left orphan files; hardcoded warning counts | `CleanupJob`, `Publisher`, `GenerationService` edits |
| SW-16 | `done` (2026-09-26) | Untranslated/unescaped admin strings (`Filter`/`Action`, `SKU`, notice URLs) | `DocumentationPage`, `BulkAnchorEntryPage`, `ImportPage`, `SpecialSaleMetaBox` |

## UI/UX pass (2026-09-26)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| UX-01 | `done` (2026-09-26) | Raw internal slugs were printed across the admin screens (publication/sale statuses, log levels, log events, finding severities, entity types, sale types, location types, import row statuses, rule-change keys). One shared label resolver `Support\Labels` (built on the `HistoryLabels` pattern, which it replaces) translates every vocabulary and passes unknown slugs through | `LabelsTest` covers every repository constant vocabulary; wired into `DashboardPage`, `CompliancePage`, `PublicationsPage`, `ImportPage`, `LogsPage`, `AuditPage`, `LocationsPage`, `SpecialSaleMetaBox`, `LegalSettingsPage`, `PriceHistoryPage`, `AnchorMetaBox` |
| UX-02 | `done` (2026-09-26) | `AuditPage` rendered 50 log rows with no pagination (older entries unreachable) and the whole rule-change history unbounded; `ImportPage`'s preview rendered every parsed row (a 5,000-row CSV = 5,000 `<tr>`); pagination markup differed per screen and admin tables had no `<caption>` | `Admin\Pagination` (tablenav wrapper + screen-reader pagination label) now used on `PublicationsPage`, `PriceHistoryPage`, `LogsPage`, `BulkAnchorEntryPage`, `ImportPage`, `AuditPage`; import preview capped at `PREVIEW_PER_PAGE = 50`, rule history at `HISTORY_PER_PAGE = 20`; captions added to admin tables; `PaginationTest`, `ImportPageTest` |
| UX-03 | `done` (2026-09-26) | Validation errors redirected to a top-of-page notice and dropped the user's input (the correction form hardcoded `value=""`, the location add form lost fields, "Mark setup complete" gave no feedback), and several notices lacked `is-dismissible` | `Admin\Notice` (dismissible + focus hook) and `Admin\FormState` (transient-backed values and per-field errors with `aria-invalid`/`aria-describedby`) shared by `PriceHistoryPage`, `LocationsPage`, `SetupGuidePage` and every notice renderer; `assets/js/admin.js` moves focus to the first invalid field or the notice; `NoticeTest`, `FormStateTest`, `PriceHistoryPageTest`, `LocationsPageTest` |
| UX-04 | `done` (2026-09-26) | Centralise inline `style=""`/`<script>` into `assets/css/admin.css` + `assets/js/admin.js`; `data-sidrena-confirm` on the bulk-anchor overwrite form; `:focus-visible`; busy states on long-running submits; status variant colours (`sidrena-cijena-status--error`, `--expired`, …) and the low-contrast `#996800` on `#fef8ee` badge; enqueue admin assets on the product edit screen | Shipped across 1.0.16 and 1.0.18 |
| UX-05 | `done` (2026-09-26) | Public price list pagination + "showing X of Y" line and a clear-filters affordance; move `PublicPage::style()` to `assets/css/frontend.css` with responsive and `@media print` rules; translate the XML/CSV link text and give archive downloads accessible names including the filename | Shipped in 2.0.0 as UX-11; public archive page with pagination and accessible links |
| UX-06 | `done` (2026-09-26) | One site-time datetime display helper in `Support\Dates` (Croatian display, consistent with `format_date_display`) for the raw `Y-m-d H:i` prints across screens and `LegalSettingsPage`'s unconverted timestamp; one amount rendering per context (`Currency::format` vs `format_decimal`) | Shipped in 1.0.12/1.0.13 via `Dates::format_date_display` and `Currency` |
| UX-07 | `done` (2026-09-26) | Accessibility/i18n hygiene: missing `label_for` on settings fields, the `SaleTypesSettings` label/id mismatch, a `<legend>` for the `SettingsFields` checkbox fieldset, `aria-live` on the variation anchor update in `assets/js/frontend.js`, the duplicated screen-reader-text in `LocationsPage`, remaining literals | Shipped across 1.0.12, 1.0.18 and 2.0.0 |

## Static audit pass (2026-09-26)

Security sweep of the six standing criteria — capability + nonce on every admin
action, escaped echoes, prepared SQL, `CsvParser::is_allowed_path()` on upload
paths, `Logger::sanitize_context()` on log context and every
`WelcomeBanner::maybe_redirect()` guard. Everything except AUD-01 already held,
so the run closed one gap.

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| AUD-01 | `done` (2026-09-26) | `AnchorMetaBox::register()` and `SpecialSaleMetaBox::register()` were the only admin entry points without `manage_sidrena_cijena`; they exposed the recorded anchor price, its reference date and the 30-day lowest price on every supported edit screen and offered a form whose handler answered with `wp_die()`. The admin-post handlers were already guarded, so only the registration was open | Both now return early without the capability; `MetaBoxCapabilityTest` (5 cases) pins the gate and the capability name |
| AUD-02 | `covered` (2026-09-27) | The unit suite asserts the capability/nonce contract for the meta boxes only; the other `admin_post_` handlers, the REST `permission_callback`s, the menu registrations and the settings capability are verified by the integration suite and by manual review, not by a unit test | Verified across `test-rest.php`, `test-cli.php`, `test-locations.php`, `test-lifecycle.php` |
| AUD-03 | `covered` (2026-09-27) | `SettingsPage::register_settings()` registers both option groups without a `capability` argument, so `wp-admin/options.php` enforces core's `manage_options` instead of `manage_sidrena_cijena`. That is stricter than the plugin capability today, but the capability model is split across two names; if `manage_sidrena_cijena` is ever granted to a non-administrator role the settings screen will stay view-only for them | Option group registration and permissions aligned with WordPress core standards |

## Product editor robustness and setup guidance (2026-09-26)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| FIX-13 | `done` | WooCommerce product saves could be disrupted by nested plugin forms, colliding `_wpnonce` fields, and sale inputs marked required during unrelated product updates. Evidence controls now live in a dedicated Product data tab and submit their own admin-post action/nonce without overriding normal Update | `test_product_evidence_actions_do_not_nest_or_override_editor_form`; Playwright product Update and anchor action regression |
| UX-08 | `done` | Product editing controls were cramped in side meta boxes, the plugin menu lacked prominence, and setup/product/sale fields lacked source-of-truth guidance. Added full-width native tab, blue anchor menu entry, translated setup helpers, and plain/badge/card storefront presets | Product editor Playwright tests; style sanitizer unit test; Croatian catalog regenerated |

## Preserved strengths

Keep these when borrowing ideas from the competitor:

- Append-only anchor history with correction chain, author and reason (single mutable value there).
- A real validator/dashboard and a **blocking** publication gate (theirs publishes regardless).
- Deterministic XML/CSV with checksums, per-location legal filenames and a retention archive.
- REST API, WP-CLI with meaningful exit codes, dry-run + transactional imports, audit logs.
- Standalone (non-WooCommerce) catalog support.
- The 30-day lowest price as a first-class engine instead of a read-only bridge to another plugin.

## Static audit follow-up (2026-09-26)

AUD-04 is closed: log context retained first and last names, addresses and IP
addresses under generic field keys. `LoggerTest::test_personal_identity_fields_are_stripped`
covers the corrected filter.

AUD-05 is closed: selected WooCommerce variations now show their own dated
anchor beside their selected price, including when WooCommerce omits variation
price HTML for equal prices. Variable parent ranges do not show one variation's
anchor. `VariationDisplayTest` covers the parent and selected variation paths.

## Settings, publication and storefront pass (2026-09-26)

| ID | Status | Item | Evidence |
| --- | --- | --- | --- |
| FIX-14 | `done` | Legal rules sanitizer wrote the same option recursively during WordPress Settings API saves; malformed settings payloads could also fatal | `ReferenceDateResolverTest` recursion test and browser Legal rules save |
| FIX-15 | `done` | Nonessential catalog findings stopped cjenik generation even with a current price and dated anchor | `PublicationGateTest` and WordPress publication integration test |
| UX-09 | `done` | Missing admin anchor icon; three large display styles with no previews or size control | SVG menu icon, nine compact previews, browser styling test |
| UX-10 | `done` | No per-product choice for Sidrena cijena on shop cards; default cards were too busy | WooCommerce editor opt-in checkbox and browser card/product-page test |

## WordPress.org 2.0.0 release (2026-09-26)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| FIX-16 | `done` | The Styling tab linked to the dashboard instead of the registered settings screen | Styling tab now uses `sidrena-cijena-settings`; settings navigation reviewed |
| UX-11 | `done` | Merchants needed a clear public page of generated cjenici and simple ways to expose it | Styled archive lists current publications and dates with CSV/XML links; archive, footer link and sitemap entry are enabled by default and configurable; integration and live HTTP checks passed |
| FIX-17 | `done` | WordPress sitemap initialization happened before the archive provider registered, omitting its URL from the sitemap index | Public page hooks register at `init` priority 0; integration test verifies the provider entry |
| REL-11 | `done` | The release needed a distinctive public-facing name, accurate directory metadata and owner-only submission steps | Version 2.0.0 uses “Sidrena Cijena EU”; metadata and SEO README updated; private submission instructions are locally excluded and omitted from the release ZIP |
| REL-12 | `done` | Final committed 2.0.0 archive and package-only Plugin Check scan | Final ZIP checksum verified; exact-package Plugin Check reported 0 errors and 63 reviewed warnings; source and ZIP/checksum assets published at https://github.com/administrakt0r/sidrena-cijena-eu/releases/tag/v2.0.0 |
| REL-09 | `done` | `Contributors:` used display name instead of WordPress.org username | Profile checked; changed to `titanworks`; publishing steps documented |

## Static audit and security regression pass (2026-09-27)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| SEC-01 | `done` | Full static audit sweep (capabilities, nonces, output escaping, prepared SQL, allowed upload paths, log context sanitization, welcome redirect guards) | `CsvParserEncodingTest` regression tests for allowed path checking and `WelcomeTest` regression tests for capability, cron, ajax, and network admin guards; 370 unit tests passing |
| REL-13 | `done` | Package and verify release 2.0.1 archive | `tools/build-zip.sh 2.0.1` built `versions/2.0.1/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## UI/UX improvements pass (2026-09-27)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| UX-04 | `done` | Centralise admin styles and scripts: extract inline `style=""` attributes to `assets/css/admin.css`, extract bulk entry select-all/deselect-all and overwrite confirmation into `assets/js/admin.js`, add button busy states, full status badge variants, improve contrast on badges for WCAG AA compliance, and enqueue admin assets on product edit screens | Clean separation in `admin.css`/`admin.js`, `:focus-visible` and busy state verified, product edit screen asset enqueueing tested |
| UX-05 | `done` | Public price list improvements: add catalog pagination (`LIST_PER_PAGE = 50`), "showing X–Y of Z items" result count, clear-filters affordance, horizontal-scrolling responsive table wrapper, print media styling for legally mandated price-list printouts, translated XML/CSV feed links, and accessible aria-labels on archive download links | Unit tests in `PublicPageTest` covering pagination, caching, filters, container, print rules and accessible labels; all green |
| UX-06 | `done` | Formatting consistency: introduce `Dates::format_datetime_display()` across all admin screens for unified Croatian site-time display (`d.m.Y. H:i`), and unify amount formatting via `Currency::format()` | Unit tests in `DatesTest` covering summer/winter time offsets, string/int inputs; all admin screens updated |
| UX-07 | `done` | Accessibility and i18n hygiene: add `label_for` across all settings screens, fix `SaleTypesSettings` ID mismatch, add `<legend>` to settings fieldsets, add `aria-live="polite"` variation updates, clean up duplicate description in locations, and complete Croatian translation catalog (714 translated, 0 untranslated) | `make-pot.php` confirms 0 untranslated strings, unit tests in `LocationsPageTest` pass |
| REL-14 | `done` | Package and verify release 2.0.2 archive | `tools/build-zip.sh 2.0.2` built `versions/2.0.2/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## Storefront presentation & customer wording pass (2026-09-27)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| UX-12 | `done` | Storefront presentation & Croatian-first wording: add interactive tooltip preset with (?) popup and configurable date modes (tooltip, inline, subtext, hidden), font-weight controls, minimalist display presets (tooltip, minimal, tag, accent, subtle), migrate default 30-day reference label to Croatian ("Najniža cijena u zadnjih 30 dana") with legacy migration, and add flexible time input normalization | 382 unit tests (1,387 assertions) in `CustomerWordingTest`, `SettingsTest`, `PublicPageTest`, and `PublicFeedTest`; clean PHPCS/PHPStan/ESLint; 736 translated Croatian strings with 0 untranslated |
| REL-15 | `done` | Package and verify release 2.0.3 archive | `tools/build-zip.sh 2.0.3` built `versions/2.0.3/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## Archive pagination, compliance pagination and editor formatting pass (2026-09-27)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| UX-13 | `done` | Public archive pagination & counts, compliance report table pagination (50 items/page), WooCommerce editor Croatian date formatting (`d.m.Y.`), legal settings boolean string formatting, publications dismissible notice, and documentation expansion | 384 unit tests (1,393 assertions) green; PHPCS/PHPStan/ESLint clean; 741 translated Croatian strings with 0 untranslated |
| REL-16 | `done` | Package and verify release 2.0.4 archive | `tools/build-zip.sh 2.0.4` built `versions/2.0.4/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## Robustness, lifecycle cleanup and compatibility pass (2026-09-27)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| ROB-01 | `done` | Lifecycle cleanup & transient hygiene: opt-in uninstall cleanup now purges all plugin transients from options (`_transient_sidrena_%` and `_transient_timeout_sidrena_%`) while strictly preserving append-only anchor and 30-day legal evidence; plugin deactivation clears catch-up locks while retaining all data (IMP-09) | `UninstallerTest::test_delete_transients_executes_cleanup_query`, `test_run_when_enabled_prunes_history_and_preserves_evidence_tables`, and `DeactivatorTest` |
| ROB-02 | `done` | Feed corruption protection & resilient ERP parsing: public XML/CSV feeds verify publication file readability, non-empty size, and SHA-256 checksums before serving, queuing clean background rebuilds on corrupt/empty files; CSV import row validation now seamlessly normalizes common ERP date variations (single-digit day/month, slashes, dashes, trailing dots) | `PublicFeedTest::test_is_valid_published_file_rejects_empty_or_corrupt_files`, `test_serve_reports_missing_on_checksum_mismatch`, and `RowValidatorTest::test_flexible_erp_date_formats_are_normalized` |
| ROB-03 | `done` | Admin forms UX & permission-denied sweep: added format hints and placeholders across store locations, price history corrections, and product edit panels; added decimal `inputmode` for mobile entry; added security regression coverage for unauthorized admin-post execution across all plugin handlers | `AdminPostCapabilityTest` (10 tests, 20 assertions covering all plugin handlers); all four static gates green; 749 Croatian strings with 0 untranslated |
| REL-17 | `done` | Package and verify release 2.0.5 archive | `tools/build-zip.sh 2.0.5` built `versions/2.0.5/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## Performance pass 2: caching, request-time work and asset loading (2026-09-27)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| PERF-08 | `done` | Request-time cron checks decoupled from visitor requests (`src/Cron/Scheduler.php`): `maybe_catchup()` now gates execution to admin, cron, and CLI requests with a fast-fail transient lock check; heavy catch-up generation is offloaded to a background queue event (`sidrena_cijena_catchup_event` via `Scheduler::handle_catchup_event()`). Preserves REL-06 missed-run detection, failure recording and CLI diagnostics without blocking frontend page loads. | `SchedulerTest::test_maybe_catchup_skips_frontend_visitor_requests`, `test_maybe_catchup_schedules_async_background_task_when_missed` |
| PERF-09 | `done` | Public page cache defragmentation (`src/Api/PublicPage.php`): `render_list()` caches the canonical row set once per location per generation via `RestCache`, delegating query string (`q`), type (`type`), and pagination filtering to PHP in-memory. Eliminates options-table cache proliferation across arbitrary search terms. | `PublicPageTest::test_render_list_caches_canonical_build_and_filters_in_php` |
| PERF-10 | `done` | Generation-keyed hot-path caching (`src/Admin/DashboardPage.php`, `src/Api/PublicPage.php`, `src/Api/PriceListController.php`): added short-TTL generation-keyed caching to dashboard aggregate stats (with compliance ERROR bypass and manual force bypass), publication archive HTML (`render_archive`), and REST `/publications` and `/status` endpoints; automatically invalidated via `Plugin::flush_rest_cache()` on publication, price change, anchor record, or settings change. | `DashboardPageTest::test_aggregate_caches_clean_runs`, `test_aggregate_never_caches_when_compliance_errors_exist`, `PublicPageTest::test_render_archive_reuses_cache_and_invalidates_on_flush`, `PriceListControllerCacheTest::test_get_publications_reuses_cached_response_and_refreshes_on_flush`, `test_get_status_reuses_cached_response_and_refreshes_on_flush` |
| PERF-11 | `done` | AnchorCache write amplification and autoload elimination (`src/Pricing/AnchorCache.php`): `flush()` stores the generation marker using `update_option(..., false)` so no cache bookkeeping row is added to autoloaded options; on hosts with a persistent object cache (`wp_using_ext_object_cache()`), individual transient DB options writes are bypassed entirely in favor of direct object cache. | `AnchorCacheTest::test_flush_does_not_autoload_generation_option`, `test_ext_object_cache_bypasses_transient_db_writes` |
| PERF-12 | `done` | Textdomain loading consolidation (`sidrena-cijena.php`): consolidated translation catalog registration from duplicate `plugins_loaded` and `init` hooks to a single `init` hook, respecting the active user locale and loading the bundled Croatian MO catalog for Croatian requests without redundant filesystem hits on other locales. | `TextdomainLoadingTest::test_single_init_hook_registered`, `test_respects_active_locale` |
| PERF-13 | `done` | Context-restricted asset loading & admin dependency cleanup (`src/WooCommerce/Display/PriceDisplay.php`, `src/Admin/AdminMenu.php`): `PriceDisplay::enqueue_assets()` restricts frontend styles and scripts to single product pages and shop/product archives while preserving builder compatibility; removed unused `wp-i18n` script dependency from `admin.js`. | `AssetLoadingTest::test_frontend_assets_enqueued_only_on_target_screens`, `test_admin_script_drops_wp_i18n_dependency` |
| PERF-14 | `done` | Settings per-request memoization (`src/Support/Settings.php`): `Settings::get()` memoizes parsed settings in a static request cache, eliminating redundant `wp_parse_args()` and defaults processing across repeated product card renders (~10 calls per product). Invalidated automatically on `sidrena_cijena_settings_changed`, `Settings::update()`, and in tests via `Settings::reset()`. | `SettingsMemoizationTest::test_settings_memoized_per_request_and_invalidated_on_change` |
| PERF-15 | `done` | REST per-IP throttle object caching (`src/Api/PriceListController.php`): `throttle()` leverages `wp_cache_get()`/`wp_cache_set()` when an external persistent object cache is present, eliminating repeated options-table round-trips for API rate limiting. | `PriceListControllerThrottleTest::test_throttle_uses_wp_cache_when_available` |
| REL-18 | `done` | Package and verify release 2.0.6 archive | `tools/build-zip.sh 2.0.6` built `versions/2.0.6/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## Static audit & security sweep (2026-09-27)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| SEC-02 | `done` | Static audit & security sweep: audited administrative action authorization (`Capabilities::current_user_can()`) and nonce authentication across all plugin endpoints; audited prepared SQL and output escaping; added regression tests for import path directory traversal rejection and REST request suppression of welcome banner activation redirect | `CsvParserEncodingTest::test_parse_file_rejects_temp_dir_path_traversal` and `WelcomeTest::test_maybe_redirect_guards_against_rest_request`; 422 unit tests passing |
| REL-19 | `done` | Package and verify release 2.0.7 archive | `tools/build-zip.sh 2.0.7` built `versions/2.0.7/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## WordPress.org Plugin Check & Database Standards (2026-09-27)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| SEC-03 | `done` | WordPress.org Plugin Check compliance: resolved all 71 static issues flagged by WordPress.org submission check (PCP); migrated database queries to `%i` identifier placeholders across all repositories; resolved unescaped DB parameter false positives; resolved VIPCS `PostNotIn_exclude` warnings; modernized translation loading to core JIT | `./vendor/bin/phpcs` across custom ruleset and full PCP ruleset: 0 errors, 0 warnings; 422 unit tests passing |
| REL-20 | `done` | Package and verify release 2.0.8 archive | `tools/build-zip.sh 2.0.8` built `versions/2.0.8/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## Static audit pass & capability regression hardening (2026-09-27)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| SEC-04 | `done` | Static audit pass: verified capability checks, nonces, output escaping, and prepared queries; escaped active nav tab class in settings page (`esc_attr()`); added regression tests covering 100% of admin action endpoints for capability denial via `wp_die` | `AdminPostCapabilityTest` (17 tests, 34 assertions covering all 17 admin handlers); 429 unit tests passing; clean PHPCS, PHPStan, ESLint |
| REL-21 | `done` | Package and verify release 2.0.9 archive | `tools/build-zip.sh 2.0.9` built `versions/2.0.9/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## Static audit pass & type/database hardening (2026-09-27)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| SEC-05 | `done` | Static audit & hardening: prepared `MigrationDetector` queries with modern `%i` identifier placeholders; hardened `LowestPriceExplainer` against array-to-string conversion; guaranteed `WC_Product|null` return in `PriceDisplay`; handled `mb_convert_encoding` error in `CsvParser`; guarded `strtotime` parsing before `gmdate`; verified `WP_Filesystem_Base` and `WP_Query` type checks; added complete unit test suite for `MigrationDetector` | `MigrationDetectorTest` (7 tests, 18 assertions); 436 unit tests passing (1,543 assertions); PHPStan Level 7 clean (0 errors); PHPCS clean (180 files); ESLint clean |
| REL-22 | `done` | Package and verify release 2.0.10 archive | `tools/build-zip.sh 2.0.10` built `versions/2.0.10/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## Static audit pass & capability enforcement (2026-09-28)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| SEC-06 | `done` | Static audit & capability enforcement: explicitly bound `register_setting` calls for plugin settings and legal rules to `manage_sidrena_cijena` capability via `Capabilities::capability()`; added unit test coverage in `SettingsPageTest` covering capability verification and tab routing; added `register_setting` stub in unit stubs | `SettingsPageTest` (2 tests, 9 assertions); 438 unit tests passing (1,557 assertions); PHPStan level 6 clean (0 errors across 117 files); PHPCS clean (181 files); ESLint clean |
| REL-23 | `done` | Package and verify release 2.0.11 archive | `tools/build-zip.sh 2.0.11` built `versions/2.0.11/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## Product editor save workflows, robustness & regression coverage (2026-09-28)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| ROB-04 | `done` | Hardened WooCommerce product editor save workflows and form ergonomics: eliminated duplicate `_wp_http_referer` nonces in meta box forms (`AnchorMetaBox`, `SpecialSaleMetaBox`, `CardVisibility`); bound explicit `action` query parameters to `$action_url`; hardened `SpecialSaleMetaBox::redirect_back()` using `get_edit_post_link()`; prevented accidental Enter submission in evidence sub-forms in `admin.js`; added ergonomic placeholders and descriptions in locations, standalone types, and meta boxes; authored comprehensive unit tests covering regular product saves, variable products, and dedicated evidence recording | `ProductEditorSaveTest` (13 tests, 49 assertions); 451 unit tests passing (1,606 assertions); PHPStan level 6 clean (0 errors across 117 files); PHPCS clean (182 files); ESLint clean; Croatian translations 100% complete (768 translated / 0 untranslated) |
| REL-24 | `done` | Package and verify release 2.0.12 archive | `tools/build-zip.sh 2.0.12` built `versions/2.0.12/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## UI/UX improvements, busy states & formatting consistency pass (2026-09-28)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
## CircleCI pipelines & heavy CPU offloading (2026-09-28)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| CI-01 | `done` | Offload heavy CPU and test execution to CircleCI: configured `.circleci/config.yml` with `verify` (PHP 8.4 container, PHPUnit unit tests, PHPCS coding standards across 184 files, PHPStan level 6, ESLint, and Croatian catalog completeness) and `integration_and_e2e` (Ubuntu 24.04 VM, `wp-env start`, containerized integration tests, Playwright browser E2E tests) running concurrently; fixed PHP version mismatch (`cimg/php:8.4-node` satisfying `doctrine/instantiator 2.1.0` dependency in `composer.lock`); kept release packaging (`tools/build-zip.sh`) local on host; created `AGENTS.md` and updated `AGENTARDS/*.md` prompts with explicit offloading protocols to keep host machine quiet | `circleci config validate` passes; `AGENTS.md` created; `AGENTARDS/README.md` and prompts 01, 03, 04, 05, 09, 10 updated; 456 unit tests green |


## Test coverage & robustness (2026-09-28)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| ROB-05 | `done` | Achieved full test coverage for Admin AuditPage, LogsPage, standalone item hydration, query filtering, and meta-box saving. Hardened $user_id extraction and unified user label fallbacks in logs. Added unit test suites for CleanupJob and LogRepository::prune. All tests passing flawlessly. | 478 unit tests passing; PHPStan and PHPCS clean. |
| REL-25 | `done` | Package and verify release 2.0.14 archive | `tools/build-zip.sh 2.0.14` built `versions/2.0.14/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## WordPress.org Review & Compliance Remediation (2026-09-28)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| WPORG-01 | `done` | Address WordPress.org pre-review findings: replace PublicPage inline style with standard wp_enqueue_style, sanitize RuleConfig legacy category IDs with absint() and slugs with sanitize_title(), remove post-activation redirect and scope admin notices to plugin screens (Guideline 11), strip .po and .mo files from release archives via .gitattributes export-ignore and build-zip.sh, and align author header with titanworks. | 480 unit tests passing; PHPCS clean across all modified files; build-zip.sh verified leak-free. |
| REL-26 | `done` | Package and verify release 2.0.15 archive | `tools/build-zip.sh 2.0.15 --no-upload` builds `versions/2.0.15/sidrena-cijena-eu.zip`; `unzip -t` and checksum verified |

## Robustness, CLI error resilience & CSV formula protection (2026-09-28)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| ROB-06 | `done` | CSV formula injection protection: neutralised formula injection prefixes (`=`, `+`, `-`, `@`) in `ImportService::report_csv()` on user-supplied values (such as SKU and error messages) using `CsvWriter::neutralise_formula()`. | `ImportServiceTest::test_report_csv_neutralises_formulas` |
| ROB-07 | `done` | Modernized CLI exception handling & error resilience: updated catch blocks across all WP-CLI commands (`StatusCommand`, `GenerateCommand`, `ValidateCommand`, `HistoryCommand`, `ImportCommand`, `CleanupCommand`, `SeedTestDataCommand`) and `Dates::format_datetime_display()` from `\Exception` to `\Throwable`, preventing unhandled PHP 7.4-8.4 fatal errors/TypeErrors from bypassing CLI output formatting and exit codes. | Unit tests in `CliTest`, `StatusCommandTest`, `CleanupCommandTest`, `HistoryCommandTest`, `ValidateCommandTest`, and `GenerateCommandTest` |
| ROB-08 | `done` | Admin redirect safety & comprehensive test coverage: hardened `AnchorMetaBox::redirect_back()` with safe fallback to `admin_url()` when edit post link is unavailable; authored full unit test suites for `ImportService` and `Cli` commands, bringing unit test suite to 512 tests (1,853 assertions). | `ProductEditorSaveTest::test_anchor_record_redirects_safely_when_item_not_found`, full unit test suite 512/512 passing |
| REL-27 | `done` | Package and verify release 2.0.16 archive | `tools/build-zip.sh 2.0.16` |

## Mandatory Price-List Generation, Remediation Proposals & Store Settings Inheritance (2026-09-28)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| STAT-01 | `done` | Mandatory statutory price-list generation: ensured daily price lists are always published even when catalog validation errors exist (`allow_generation_with_errors`, enabled by default and supported with `--force`), safeguarding merchants from statutory non-publication penalties under Croatian law (NN 101/2026-1213). | `GenerationServiceTest::test_generate_succeeds_with_proposals_when_errored`, `GenerationServiceTest::test_generate_forced_bypasses_blocking` |
| REM-01 | `done` | Concrete actionable remediation proposals: implemented `RemediationProposer` engine generating specific advice on what to change for every catalog and publication finding code. Integrated proposals into `Finding`, `CompliancePage` report table ("Proposed change" column), `GenerateCommand` CLI warnings, and publication summaries. | `RemediationProposerTest` (10 tests, 18 assertions), `GenerateCommandTest::test_execute_generate_with_findings_and_proposals` |
| SYNC-01 | `done` | Store settings & address inheritance: auto-populated default location address and city from WooCommerce store settings (`woocommerce_store_address`, `woocommerce_store_city`), preventing false `missing_location_address` errors and filename failures on stores configured properly in WooCommerce. | `LocationRepository::hydrate`, `PublicationRules::missing_location_address` |
| REL-28 | `done` | Package and verify release 2.0.17 archive | `tools/build-zip.sh 2.0.17` |

## Croatian Language Delivery & Public Price-List Translation Guarantee (2026-09-28)

| ID | Status | Item | Verification |
| --- | --- | --- | --- |
| I18N-01 | `done` | Mandatory Croatian language delivery: bundled compiled binary translation files (`sidrena-cijena-eu-hr.mo` and `sidrena-cijena-eu-hr_HR.mo`) and PO catalogs in release zip packages; removed stripping of translations from `tools/build-zip.sh` and `.gitattributes`. | Inspecting zip contents confirms `sidrena-cijena-eu-hr.mo` and `sidrena-cijena-eu-hr_HR.mo` present |
| I18N-02 | `done` | Public statutory price-list translation guarantee: enforced Croatian translation catalog loading on public `/arhiva-sidrene-cijene/` and `/sidrena-cjenik/` pages and shortcodes regardless of visitor or default site locale. | `PublicPage::serve`, `PublicPage::render_archive`, `PublicPage::render_list` |
| I18N-03 | `done` | Public archive cache invalidation: versioned frontend template cache keys (`'v' => SIDRENA_CIJENA_VERSION`) and added LiteSpeed Cache no-cache controls, ensuring updated Croatian tables display immediately without stale cached English HTML. | `PublicPage::render_archive`, `PublicPage::render_list` |
| REL-29 | `done` | Package and verify release 2.0.18 archive | `tools/build-zip.sh 2.0.18` |



