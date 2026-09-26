# Improvement backlog

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

## Preserved strengths

Keep these when borrowing ideas from the competitor:

- Append-only anchor history with correction chain, author and reason (single mutable value there).
- A real validator/dashboard and a **blocking** publication gate (theirs publishes regardless).
- Deterministic XML/CSV with checksums, per-location legal filenames and a retention archive.
- REST API, WP-CLI with meaningful exit codes, dry-run + transactional imports, audit logs.
- Standalone (non-WooCommerce) catalog support.
- The 30-day lowest price as a first-class engine instead of a read-only bridge to another plugin.
