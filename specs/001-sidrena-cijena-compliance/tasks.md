---
description: "Task list for Croatian Anchor Price (Sidrena cijena) compliance plugin implementation"
---

# Tasks: Croatian Anchor Price ("Sidrena cijena") Compliance System

**Input**: Design documents from `/specs/001-sidrena-cijena-compliance/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/, quickstart.md

**Tests**: Included — the specification requires automated unit, integration and E2E coverage (FR-089, SC-011) and the originating brief mandates a full test matrix.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

Plugin root is the repository root (`sidrena-cijena/`). Source in `src/`, tests in `tests/`, docs in `docs/`, contracts in `specs/001-sidrena-cijena-compliance/contracts/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization, tooling and directory skeleton

- [x] T001 Initialize the Git repository at the repository root and create `.gitignore` (ignore `node_modules/`, `vendor/`, `.wp-env` artifacts, generated export files, `*.log`, OS/editor files)
- [x] T002 Create the plugin bootstrap `sidrena-cijena.php` with plugin headers (`Requires PHP: 7.4`, `Requires at least: 6.8`, Text Domain `sidrena-cijena`), constants, direct-access guard, activation/deactivation hook registrations
- [x] T003 [P] Implement the PSR-4 autoloader in `src/Support/Autoloader.php` mapping `SidrenaCijena\` to `src/` and register it from `sidrena-cijena.php`
- [x] T004 [P] Create `composer.json` with dev-only dependencies (PHPUnit, WordPress Coding Standards, PHPCompatibilityWP, PHPStan, `szepeviktor/phpstan-wordpress`) and scripts `test:unit`, `test:integration`, `lint`, `analyse`
- [x] T005 [P] Create `package.json` with dev-only dependencies (`@wordpress/env`, `@playwright/test`, `@wordpress/scripts`) and scripts for `wp-env start/stop/clean/destroy`, `test:e2e`, `lint:js`
- [x] T006 [P] Create `.wp-env.json` (mount `.`, WordPress latest stable, `phpVersion` 8.3, WooCommerce install/activation, lifecycle scripts) and `.wp-env.test.json` for the isolated test environment using the documented `--config` pattern
- [x] T007 [P] Create `phpunit.xml.dist` (unit + integration suites), `tests/bootstrap.php`, `tests/integration/bootstrap.php`, `phpcs.xml.dist` (WordPress + PHPCompatibilityWP), `phpstan.neon.dist`, and `playwright.config.js`
- [x] T008 [P] Create the directory skeleton with `index.php` guards: `src/{Admin,Api,Catalog,Cli,Compliance,Cron,Database,Export,Import,Logging,Pricing,Support,WooCommerce}`, `assets/{css,js,images}`, `templates/`, `languages/`, `tests/{unit,integration,e2e,fixtures}`, `docs/`
- [x] T009 [P] Add `.editorconfig` and `.nvmrc`, then generate `composer.lock` and `package-lock.json`
- [x] T010 [P] Create `readme.txt` and `README.md` skeletons including the mandatory disclaimer that the plugin is a technical compliance aid, not legal advice
- [x] T011 [P] Set up translation scaffolding: `languages/sidrena-cijena.pot` generation script, `load_plugin_textdomain` wiring in `sidrena-cijena.php`, and placeholder `languages/hr_HR.po`/`en_US.po`
- [x] T012 Create `uninstall.php` with a guard that deletes plugin data only when the explicit `sidrena_cijena_delete_data_on_uninstall` option is enabled and the plugin's own constant is defined

**Checkpoint**: Repository builds, linters run, wp-env starts.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T013 Implement `src/Database/Schema.php` defining the five tables exactly per `data-model.md`: `{prefix}sidrena_price_history`, `{prefix}sidrena_special_sales`, `{prefix}sidrena_locations`, `{prefix}sidrena_publications`, `{prefix}sidrena_logs` with all columns, nullability, defaults, enums and indexes
- [x] T014 Implement `src/Database/Migrator.php` with explicit schema versions, `dbDelta()` execution, the `sidrena_cijena_db_version` option, and an `admin_init` upgrade check
- [x] T015 [P] Implement `src/Support/Settings.php` with typed defaults for all settings per `data-model.md` section 6 (display, formats, deadline, retention ≥ 30, log retention, delete-on-uninstall)
- [x] T016 [P] Implement `src/Compliance/RuleConfig.php`: general reference date (default `2026-09-10`), legacy reference date (default `2025-05-02`), legacy enabled flag, category mapping, legal-rule version identifier (default `2026-10-01`), and an append-only change audit in the `sidrena_cijena_rule_config_history` option
- [x] T017 [P] Implement `src/Catalog/CatalogItem.php` value object (id, variation_id, type product/service, name, sku, brand, barcode, unit, unit_price, currency, availability, regular/sale prices, first_seen) and `src/Catalog/CatalogProvider.php` interface (paginated iteration, single item lookup, variation lookup)
- [x] T018 Implement `src/Catalog/StandalonePostType.php` (private CPT `sidrena_item`, taxonomies `sidrena_brand` and `sidrena_item_type`, meta `_sidrena_sku`, `_sidrena_barcode`, `_sidrena_unit`, `_sidrena_unit_price`, `_sidrena_availability`, `_sidrena_regular_price`, `_sidrena_sale_price`, `_sidrena_first_seen`, `_sidrena_currency`, `_sidrena_tax_inclusive`) and `src/Catalog/StandaloneCatalogProvider.php`
- [x] T019 Implement `src/WooCommerce/WooCommerceDetector.php` and `src/WooCommerce/WooCommerceCatalogProvider.php` reading products and variations only through the WooCommerce CRUD API (`wc_get_product`, `wc_get_products`), including SKU, brand taxonomy when available, stock status and variation prices
- [x] T020 Implement `src/Database/PriceHistoryRepository.php`: append-only `insert()` and `insert_correction()` (requires `correction_of` + non-empty `correction_reason`), queries by product/variation/type/date range, no update/delete methods exposed
- [x] T021 [P] Implement `src/Database/LocationRepository.php` with CRUD, unique `identifier` enforcement, `sequence_no` increment, and active filtering
- [x] T022 [P] Implement `src/Database/PublicationRepository.php` with archive writes, status transitions (`pending → generated → validated → published`, any → `failed`, supersede logic), and retention queries by `expires_at`
- [x] T023 [P] Implement `src/Logging/Logger.php` and `src/Database/LogRepository.php` with event codes (`export_scheduled`, `export_manual`, `export_failed`, `import`, `validation_failure`, `history_correction`, `migration`, `settings_change`, `cleanup`), levels, JSON context, and a guarantee that no secrets or personal data are written
- [x] T024 Implement `src/Plugin.php` as the service container: instantiate services, select the active catalog provider based on WooCommerce availability, register hooks, and expose accessors; never fatal when WooCommerce is absent
- [x] T025 Implement `src/Admin/Capabilities.php`: register `manage_sidrena_cijena`, grant to `administrator` on activation, expose the `sidrena_cijena_capability` filter
- [x] T026 Implement `src/Admin/AdminMenu.php`: menu placement (WooCommerce submenu when active, otherwise Settings), page slugs, and shared helpers for capability checks, nonce verification and admin notices
- [x] T027 [P] Implement `src/Support/Currency.php` (EUR formatting, six-decimal storage, half-up two-decimal display/export formatting) and `src/Support/Dates.php` (site timezone conversions, date arithmetic helpers)
- [x] T028 [P] Implement `src/Cron/Scheduler.php`: WP-Cron daily event registration, single-event queueing, optional Action Scheduler use when available, unschedule on deactivation, and helpers to report next run
- [x] T029 Implement `src/Compliance/Finding.php` and `src/Compliance/Validator.php` skeleton with severity enum (`ERROR`, `WARNING`, `INFO`), stable finding codes and a rule registry
- [x] T030 Implement `src/Support/Install.php` (create tables, seed default settings and rule config, create the default webshop location, schedule cron) and `src/Support/Deactivator.php` (clear schedules, preserve all data)
- [x] T031 [P] Write foundational unit tests in `tests/unit/Database/MigratorTest.php`, `tests/unit/Support/SettingsTest.php`, `tests/unit/Support/CurrencyTest.php`, `tests/unit/Support/DatesTest.php`
- [x] T032 Write foundational integration tests in `tests/integration/test-activation.php` (activation creates tables/options/location, deactivation preserves data, re-activation is idempotent)

**Checkpoint**: Foundation ready — user story implementation can now begin.

---

## Phase 3: User Story 1 - Record and display the anchor price (Priority: P1) 🎯 MVP

**Goal**: Merchants record the reference price valid on the applicable reference date and the storefront shows current price and anchor price together, clearly labelled.

**Independent Test**: Configure reference dates, record anchors for simple/variable/sale products, enable display, and verify product pages, archives and shortcode output show the correct anchor price with the configured label; a missing anchor warns admins without breaking the storefront.

### Tests for User Story 1

- [x] T033 [P] [US1] Unit tests for reference-date resolution (general, legacy, mapped/unmapped categories, legacy disabled) in `tests/unit/Compliance/ReferenceDateResolverTest.php`
- [x] T034 [P] [US1] Unit tests for anchor resolution and correction semantics (no anchor → null + flag, manual record with reference date/source/reason, correction preserves original) in `tests/unit/Pricing/AnchorPriceServiceTest.php`
- [x] T035 [P] [US1] Integration test for anchor recording via admin and price-history append in `tests/integration/test-anchor-records.php`
- [x] T036 [P] [US1] Playwright test for anchor display on single product, archive and shortcode, including display disabled and variation switching, in `tests/e2e/anchor-display.spec.js`

### Implementation for User Story 1

- [x] T037 [P] [US1] Implement `src/Compliance/ReferenceDateResolver.php` using `RuleConfig`: legacy mapping when enabled and mapped, otherwise the general date; auditable, no history rewrites
- [x] T038 [P] [US1] Implement `src/Pricing/AnchorPriceService.php`: resolve the latest valid anchor record per item/variation, record anchors with `reference_date`, `source`, `currency`, `tax_inclusive`, `created_by`, and flag items with no anchor or introduced after the reference date
- [x] T039 [US1] Implement `src/Admin/AnchorMetaBox.php`: read-only anchor summary (price, reference date, source, status) on WooCommerce product and standalone item edit screens, with a nonce-protected record/verify action and a link to the full price history
- [x] T040 [US1] Implement `src/Pricing/AnchorCache.php`: object-cache/transient lookups keyed by product/variation with invalidation on price change, anchor change and settings change
- [x] T041 [US1] Implement `src/WooCommerce/Display/PriceDisplay.php`: `woocommerce_get_price_html` filter plus `woocommerce_single_product_summary` and archive loop actions, honoring enable/label/location/priority/archives/variations/only-when-different settings, rendering via `templates/anchor-price.php`
- [x] T042 [US1] Implement `src/WooCommerce/Display/VariationDisplay.php`: extend `woocommerce_available_variation` with anchor data and update it on variation selection via `assets/js/frontend.js`
- [x] T043 [P] [US1] Create `templates/anchor-price.php` and `assets/css/frontend.css` with stable classes (`sidrena-cijena`, `sidrena-cijena__current`, `sidrena-cijena__anchor`) and accessible, non-misleading markup
- [x] T044 [P] [US1] Implement `src/Api/TemplateFunctions.php` (`sidrena_cijena_get_anchor_price`, `sidrena_cijena_get_lowest_30_day_price`, `sidrena_cijena_render_anchor_price`), documented global wrappers, and the `[sidrena_cijena]` shortcode
- [x] T045 [US1] Implement `src/Admin/DisplaySettingsPage.php` (frontend display enable, anchor label default "Sidrena cijena", currency display, location, priority, archives, product pages, variations, only-when-different, administrative disclaimer) and `src/Admin/LegalSettingsPage.php` (reference dates, legacy mapping, legacy enable, rule version) using the Settings API with capability checks and logging
- [x] T046 [US1] Wire US1 services and hooks in `src/Plugin.php` and add the WooCommerce Store API product extension for block templates where technically possible
- [x] T047 [US1] Run US1 unit, integration and Playwright suites; validate quickstart V2; fix failures; commit

**Checkpoint**: Anchor prices are recorded and displayed; US1 is independently shippable.

---

## Phase 4: User Story 2 - Publish the daily machine-readable price list (Priority: P2)

**Goal**: Generate, validate, name, publish, archive and expose CSV/XML price lists per active location, meeting the 08:00 working-day deadline and 30-day retention.

**Independent Test**: Trigger generation manually, by schedule and by CLI; verify file contents against the format contracts, legally structured filenames, archive rows, public URLs, retention behaviour and REST retrieval.

### Tests for User Story 2

- [x] T048 [P] [US2] Unit tests for `FilenameGenerator` (component order, diacritic transliteration, separator/case config, sequence, collision handling) in `tests/unit/Export/FilenameGeneratorTest.php`
- [x] T049 [P] [US2] Unit tests for CSV and XML writers (delimiter, comma decimals, quoting, escaping, stable order, byte-identical determinism) in `tests/unit/Export/CsvWriterTest.php` and `tests/unit/Export/XmlWriterTest.php`
- [x] T050 [P] [US2] Integration test for generation → validation → publication → archive → retention in `tests/integration/test-publication.php`
- [x] T051 [P] [US2] Integration test for REST `/prices`, `/prices.csv`, `/prices.xml`, `/publications` including pagination, caching headers and throttling in `tests/integration/test-rest.php`
- [x] T052 [P] [US2] Playwright test for manual generation, archive page, CSV/XML download URLs and REST endpoint in `tests/e2e/publication.spec.js`

### Implementation for User Story 2

- [x] T053 [P] [US2] Implement `src/Export/FilenameGenerator.php` exactly per `contracts/filename.md` (fixed components/order, configurable separator and case, validation before use)
- [x] T054 [P] [US2] Implement `src/Export/PriceListBuilder.php` building canonical rows per `contracts/csv-format.md` (products then services, ordering by `sifra`/`naziv`/ID, effective retail price, special-sale indicator/name, anchor price, availability)
- [x] T055 [P] [US2] Implement `src/Export/CsvWriter.php` (streaming, UTF-8 without BOM, `;` delimiter, comma decimals, RFC 4180 quoting, LF)
- [x] T056 [P] [US2] Implement `src/Export/XmlWriter.php` (streaming, versioned root with rule version and generation timestamp, escaped values, omitted empty elements)
- [x] T057 [US2] Implement `src/Export/Publisher.php`: write under `wp-content/uploads/sidrena-cijena/{location-slug}/` with an `index.html` guard, compute SHA-256 checksum and size, create the publication archive row, mark previous publications `superseded`, increment the location sequence, and suppress duplicates via content fingerprint
- [x] T058 [US2] Implement `src/Export/GenerationService.php`: batched, streamed generation per active location and format, validation gate before publishing, status transitions, structured logging, and per-location/per-format results
- [x] T059 [US2] Implement the default webshop location bootstrap (used until US6 adds full location management) and wire generation for the single-location case
- [x] T060 [US2] Implement `src/Cron/DailyGeneration.php`: daily event scheduled before the configured deadline (default 08:00 site time), queued single-event regeneration for service price changes, idempotent re-runs, and failure recording
- [x] T061 [US2] Implement `src/Admin/PublicationsPage.php`: human-readable archive list (filename, format, location, published/expires, checksum, item count, status, URL) with a "Generate now" action
- [x] T062 [US2] Implement `src/Api/PriceListController.php`: REST routes `/prices`, `/prices.csv`, `/prices.xml`, `/publications` with explicit `permission_callback`, pagination (`per_page` ≤ 500), `ETag`/`Last-Modified`, cache headers and per-IP throttling per `contracts/rest-api.md`
- [x] T063 [US2] Implement `src/Api/RestCache.php` with cache keys per filter set and invalidation on price/anchor/sale/settings changes
- [x] T064 [US2] Implement `src/Cron/CleanupJob.php`: delete publication files only at/after `expires_at`, retain archive rows, prune logs per retention
- [x] T065 [US2] Wire US2 services, cron events and REST routes in `src/Plugin.php`; log scheduled/manual/failed exports
- [x] T066 [US2] Run US2 unit, integration and Playwright suites; validate quickstart V4–V6; fix failures; commit

**Checkpoint**: Price lists are generated, published, archived and machine-retrievable.

---

## Phase 5: User Story 3 - Calculate the lowest price of the previous 30 days (Priority: P3)

**Goal**: Classify special forms of sale and compute/store/display the lowest applicable price of the exact 30-day window, separately from the anchor price.

**Independent Test**: Create price and sale histories, start a classified sale, and verify the calculated lowest 30-day price, its window boundaries, its distinct display and its independence from the anchor price, including exact-boundary cases.

### Tests for User Story 3

- [x] T067 [P] [US3] Unit tests for the 30-day calculator: exact 30-day boundary, start-date exclusion, DST transitions, consecutive/overlapping sales, sale price changes, variations, stock, empty history, in `tests/unit/Pricing/LowestPrice30DayCalculatorTest.php`
- [x] T068 [P] [US3] Integration test for sale creation, classification, recomputation and persistence of window boundaries in `tests/integration/test-special-sales.php`
- [x] T069 [P] [US3] Playwright test for 30-day price display distinct from the anchor price in `tests/e2e/thirty-day.spec.js`

### Implementation for User Story 3

- [x] T070 [P] [US3] Implement `src/Pricing/LowestPrice30DayCalculator.php`: window = 30 calendar days immediately before the sale start date, start date excluded, day exactly 30 days before included, site time zone; returns price and window boundaries; pure and side-effect free
- [x] T071 [P] [US3] Implement `src/Database/SpecialSaleRepository.php` with CRUD and status transitions (`scheduled → active → ended`, `scheduled|active → cancelled`)
- [x] T072 [US3] Implement `src/Pricing/SpecialSaleService.php`: require a sale type, store type/name/start/end/promo price, compute and persist `lowest_30_day_price` with `window_start`/`window_end`, append a `lowest_30_day` history row, recompute when underlying history changes
- [x] T073 [US3] Implement `src/Admin/SpecialSaleMetaBox.php` on product/variation screens: classify sale, set dates and promotional price, display the computed lowest 30-day price and window
- [x] T074 [US3] Implement `src/Admin/SaleTypesSettings.php` with the default types (`akcija`, `sniženje`, `sezonsko sniženje`, `rasprodaja`, configurable other) and validation that unclassified promotions are never treated as special sales
- [x] T075 [US3] Implement 30-day display: `templates/thirty-day-price.php`, shortcode attribute `show_30_day`, and WooCommerce display integration with a distinct translatable label
- [x] T076 [US3] Wire special-sale data into exports (indicator, name, effective price) and REST `special_sale` payload
- [x] T077 [US3] Run US3 unit, integration and Playwright suites; validate quickstart V3; fix failures; commit

**Checkpoint**: 30-day pricing works and never substitutes for the anchor price.

---

## Phase 6: User Story 4 - Run a compliance check and see a dashboard (Priority: P4)

**Goal**: A central validator with severity levels and an admin dashboard summarizing anchor coverage, comparisons, 30-day warnings, export status and findings.

**Independent Test**: Seed every known problem type, run the compliance check and verify each finding's severity, entity and plain-language explanation; verify dashboard counts.

### Tests for User Story 4

- [x] T078 [P] [US4] Unit tests for every validator rule and severity mapping in `tests/unit/Compliance/ValidatorTest.php`
- [x] T079 [P] [US4] Integration test for dashboard aggregates and full compliance report output in `tests/integration/test-dashboard.php`
- [x] T080 [P] [US4] Playwright test for the dashboard and "Run compliance check" report in `tests/e2e/dashboard.spec.js`

### Implementation for User Story 4

- [x] T081 [US4] Implement all validator rules under `src/Compliance/Rules/`: missing anchor price, invalid anchor date, missing SKU, missing product name, missing barcode where required/available, invalid retail price, invalid unit price, invalid availability, missing sale type, missing 30-day price where required, malformed CSV, malformed XML, invalid filename, missing store identifier, stale price list, missing archived price list, invalid currency, duplicate product records
- [x] T082 [US4] Implement `src/Compliance/ComplianceReport.php`: severity counts, per-entity aggregation, plain-language messages (translatable) and export to CSV/JSON
- [x] T083 [US4] Implement `src/Admin/DashboardPage.php`: valid/missing/needs-verification anchors, current-vs-anchor comparison, missing historical data, 30-day warnings, export status, last successful scheduled export, next export, validation errors
- [x] T084 [US4] Implement the "Run compliance check" action with batched processing and progress feedback, nonce-protected and capability-gated
- [x] T085 [US4] Persist publication validation summaries and surface failed publications on the dashboard with links to details
- [x] T086 [US4] Run US4 unit, integration and Playwright suites; validate quickstart V7; fix failures; commit

**Checkpoint**: Merchants can see and act on compliance findings.

---

## Phase 7: User Story 5 - Onboard historical anchor data safely (Priority: P5)

**Goal**: Guided onboarding with CSV import (preview, validation, dry run, atomic commit/rollback), manual bulk entry, migration detection and clear warnings for unreconstructable history.

**Independent Test**: Import a file with valid, invalid, duplicate and unknown rows; verify preview, error report, no changes on dry run, atomic commit and overwrite confirmation.

### Tests for User Story 5

- [ ] T087 [P] [US5] Unit tests for import row validation, duplicate detection and overwrite detection in `tests/unit/Import/RowValidatorTest.php`
- [ ] T088 [P] [US5] Integration test for import preview, dry run, atomic commit, rollback on failure and error report in `tests/integration/test-import.php`
- [ ] T089 [P] [US5] Playwright test for the import screen, preview table and error report in `tests/e2e/import.spec.js`

### Implementation for User Story 5

- [ ] T090 [P] [US5] Implement `src/Import/CsvParser.php` accepting SKU or product ID, reference date, anchor price, category, optional variation ID, with encoding/delimiter handling and safe file reading (no traversal)
- [ ] T091 [P] [US5] Implement `src/Import/RowValidator.php`: required fields, date and price validation, unknown product, duplicate rows, and detection of rows that would overwrite existing anchor records
- [ ] T092 [US5] Implement `src/Import/ImportService.php`: preview, dry run, transactional commit with rollback, batch ID, per-row error report, audit logging and the `sidrena_cijena_import_completed` action
- [ ] T093 [US5] Implement `src/Admin/ImportPage.php`: upload, validated preview table, explicit overwrite confirmation, commit action, and downloadable error report
- [ ] T094 [US5] Implement `src/Admin/BulkAnchorEntryPage.php`: manual bulk entry with current-price suggestions clearly marked as unverified and never committed silently
- [ ] T095 [US5] Implement `src/Import/MigrationDetector.php`: detect existing price-history data sources/plugins, offer a migration path and avoid duplicating existing functionality
- [ ] T096 [US5] Implement onboarding guidance and missing-history warnings in plain language, plus the fixture `tests/fixtures/anchor-import.csv`
- [ ] T097 [US5] Run US5 unit, integration and Playwright suites; validate quickstart V2/V8; fix failures; commit

**Checkpoint**: Historical data can be established safely and reversibly.

---

## Phase 8: User Story 6 - Manage multiple sales locations (Priority: P6)

**Goal**: Full sales-location management and per-location price-list generation on a shared catalog.

**Independent Test**: Configure a webshop and two stores (one inactive), generate, and verify one correctly named file per active location with shared catalog data.

### Tests for User Story 6

- [ ] T098 [P] [US6] Integration test for location CRUD, unique identifier validation, applicability filtering, inactive exclusion and per-location sequences in `tests/integration/test-locations.php`
- [ ] T099 [P] [US6] Playwright test for the locations admin screen and per-location generation in `tests/e2e/locations.spec.js`

### Implementation for User Story 6

- [ ] T100 [US6] Implement `src/Admin/LocationsPage.php`: list and add/edit screens for name, type, address, city, identifier (required, unique), active status, applicable products and price-list configuration (format selection, separator/case), with capability checks and nonces
- [ ] T101 [US6] Implement per-location generation wiring in `src/Export/GenerationService.php`: applicability filter, per-location filename configuration and independent sequence counters
- [ ] T102 [US6] Implement location deletion guard (deactivate instead of delete when retained publications exist) and the REST location filter in `src/Api/PriceListController.php`
- [ ] T103 [US6] Run US6 integration and Playwright suites; validate quickstart V9; fix failures; commit

**Checkpoint**: Multi-location merchants produce compliant per-location files without data duplication.

---

## Phase 9: User Story 7 - Inspect and audit price history (Priority: P7)

**Goal**: Chronological, non-destructive price history per product/variation with auditable corrections.

**Independent Test**: Change prices, run sales, correct an anchor; verify the full chronological history with authors, timestamps, sources and reasons, and that no original record was lost.

### Tests for User Story 7

- [ ] T104 [P] [US7] Integration test for history listing, filters and corrections preserving originals in `tests/integration/test-price-history.php`
- [ ] T105 [P] [US7] Playwright test for the product price-history screen and correction flow in `tests/e2e/price-history.spec.js`

### Implementation for User Story 7

- [ ] T106 [US7] Implement `src/Admin/PriceHistoryPage.php`: per-product chronological table (date, type, price, source, user/system, correction info) with filters by type and date
- [ ] T107 [US7] Implement the correction flow: never delete, create a correction record with `correction_of`, author, timestamp and required reason, and show both original and correction
- [ ] T108 [US7] Implement audit views for rule-configuration changes and historical corrections in `src/Admin/AuditPage.php` (read-only)
- [ ] T109 [US7] Run US7 integration and Playwright suites; validate the history part of quickstart V2; fix failures; commit

**Checkpoint**: Full auditability for inspections and disputes.

---

## Phase 10: User Story 8 - Operate and administer the compliance system (Priority: P8)

**Goal**: Production operations: settings, logs, WP-CLI commands with exit codes, scheduling guidance, and explicit lifecycle/uninstall behaviour.

**Independent Test**: Run every CLI command and check exit codes; change operations settings and verify logging; verify unauthorized users are denied; verify deactivation/uninstall preservation defaults.

### Tests for User Story 8

- [ ] T110 [P] [US8] Integration test for all CLI commands, options and exit codes in `tests/integration/test-cli.php`
- [ ] T111 [P] [US8] Integration test for deactivation/uninstall behaviour and opt-in deletion in `tests/integration/test-lifecycle.php`
- [ ] T112 [P] [US8] Playwright test for operations settings, log viewer filtering and capability denial in `tests/e2e/admin-ops.spec.js`

### Implementation for User Story 8

- [ ] T113 [P] [US8] Implement `src/Cli/StatusCommand.php` (`wp sidrena-cijena status`, exit 0)
- [ ] T114 [P] [US8] Implement `src/Cli/ValidateCommand.php` (`validate` with format/location/severity options, exit 0/1/2)
- [ ] T115 [P] [US8] Implement `src/Cli/GenerateCommand.php` (`generate` with format/location/force/dry-run, exit 0/1/2)
- [ ] T116 [P] [US8] Implement `src/Cli/HistoryCommand.php` (`history <product_id>` with variation/type/limit/format options, exit 0/1)
- [ ] T117 [P] [US8] Implement `src/Cli/ImportCommand.php` (`import <file>` with dry-run/confirm-overwrite, exit 0/1/2/3)
- [ ] T118 [P] [US8] Implement `src/Cli/CleanupCommand.php` (`cleanup` with dry-run, exit 0)
- [ ] T119 [P] [US8] Implement `src/Cli/SeedTestDataCommand.php` creating the required scenarios: normal, sale, no SKU, barcode/no barcode, variable with multiple variations, legacy category, introduced after reference date, frequently re-priced, consecutive promotions, out-of-stock, unit pricing, missing anchor, price above/equal/below anchor
- [ ] T120 [US8] Implement `src/Admin/OperationsSettingsPage.php`: deadline, retention (minimum 30 enforced), log retention, format selection, delete-on-uninstall with explicit confirmation
- [ ] T121 [US8] Implement `src/Admin/LogsPage.php`: filter by level, event and date; no secrets or personal data displayed
- [ ] T122 [US8] Finalize `uninstall.php` (opt-in deletion only, never WooCommerce or WordPress data) and deactivation behaviour; document exactly what is deleted
- [ ] T123 [US8] Implement system-cron documentation and an admin notice when the next generation is overdue or cron is unhealthy
- [ ] T124 [US8] Run US8 integration and Playwright suites; validate quickstart V1/V10/V12; fix failures; commit

**Checkpoint**: The plugin is operable in production by a non-developer with CLI automation available.

---

## Phase 11: Polish & Cross-Cutting Concerns

**Purpose**: Documentation, hardening, performance, full regression and final acceptance

- [ ] T125 [P] Write `docs/legal-rules.md` covering the anchor price, reference dates, legacy 2025 categories, the 2026 expansion, the 30-day lowest-price distinction, online cjenik requirements, retention, machine-readable access, primary-source citations and documented uncertainties, plus the not-legal-advice disclaimer
- [ ] T126 [P] Write `docs/development.md` (Docker, Node LTS, clone, `npm install`, `npm run wp-env start`, http://localhost:8888, development-only credentials, test environment, stop/clean/destroy)
- [ ] T127 [P] Write `docs/price-history.md` (record types, append-only semantics, corrections, sources, audit)
- [ ] T128 [P] Write `docs/price-list-format.md` (CSV columns, XML schema, filename structure, decimal conventions, examples)
- [ ] T129 [P] Write `docs/woocommerce.md` (supported product types, hooks used, template functions/shortcode, brand/unit/barcode sources, blocks limitations)
- [ ] T130 [P] Write `docs/testing.md` (unit/integration/E2E commands, fixtures, test-product scenarios)
- [ ] T131 [P] Write `docs/troubleshooting.md` (missing anchors, stale price lists, cron issues, permissions, common findings)
- [ ] T132 [P] Create `tests/fixtures/example-price-list.csv` and `tests/fixtures/example-price-list.xml` matching the format contracts and verify they validate
- [ ] T133 [P] Generate and commit `languages/sidrena-cijena.pot`, complete `hr_HR.po`/`.mo` and `en_US.po`; verify no untranslated user-visible strings
- [ ] T134 Performance pass: verify indexes are used, anchor lookups are cached, archive pages issue no catalog-wide queries, and 10,000-item export streams within the batch window
- [ ] T135 Security pass: review capability checks, nonces, sanitization, escaping, prepared SQL, REST permission callbacks, file handling and directory traversal protection; add regression tests where gaps are found
- [ ] T136 [P] WooCommerce regression pass: homepage, product page, category, cart, checkout, account, admin dashboard, product editing, payment/shipping/coupons; confirm no plugin interference
- [ ] T137 [P] Run the complete automated suite (`composer test:unit`, `composer test:integration`, `npm run test:e2e`, `composer lint`, `composer analyse`) and require zero failures and zero static-analysis errors
- [ ] T138 Execute the final acceptance scenario from the originating brief (clean wp-env → install → activate → seed → anchors → price changes → sales → display → CSV/XML → validate → filename → archive → REST → machine access → compliance check → all test suites → checkout regression → log inspection → git review) and record results
- [ ] T139 Update `README.md` and `readme.txt` with the final feature list, setup/test commands, known limitations, legal assumptions and the disclaimer
- [ ] T140 Final Git review: inspect `git status` and `git diff`, confirm atomic commits for every logical change, no secrets or debug code, and clean history

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories
- **User Stories (Phases 3–10)**: All depend on Foundational completion
  - Stories may proceed in parallel with sufficient staffing, or sequentially in priority order (US1 → US8)
- **Polish (Phase 11)**: Depends on all desired user stories being complete

### User Story Dependencies

- **US1 (P1)**: No dependencies on other stories — MVP
- **US2 (P2)**: Depends on the anchor data model existing (shared foundational tables) but is independently testable with anchors absent (anchor column empty + warning)
- **US3 (P3)**: Independent; uses price history and the catalog abstraction only
- **US4 (P4)**: Independent; validates data produced by US1/US2/US3 but runs against any seeded state
- **US5 (P5)**: Independent; writes anchor records through the shared repository
- **US6 (P6)**: Extends US2's generation with multiple locations; US2 must exist
- **US7 (P7)**: Independent; reads the shared price-history ledger
- **US8 (P8)**: Wraps all stories for operations; the CLI commands depend on their respective services

### Within Each User Story

- Tests are written first and must fail before implementation
- Value objects and repositories before services
- Services before admin UI and REST/CLI surfaces
- Story verified independently before moving on

### Parallel Opportunities

- Setup: T003–T011 are parallel after T002
- Foundational: T015–T017, T021–T023, T027–T028, T031 are parallel
- After Foundational: US1–US5 and US7 can start in parallel (US6 needs US2, US8 needs the services it wraps)
- Within each story: test tasks marked [P] and independent implementation files marked [P] can run in parallel
- Polish: documentation tasks T125–T133 are parallel

---

## Parallel Example: User Story 1

```bash
# Launch all US1 tests together (write first, expect failures):
Task: "Unit tests for reference-date resolution in tests/unit/Compliance/ReferenceDateResolverTest.php"
Task: "Unit tests for anchor resolution in tests/unit/Pricing/AnchorPriceServiceTest.php"
Task: "Integration test for anchor recording in tests/integration/test-anchor-records.php"
Task: "Playwright test for anchor display in tests/e2e/anchor-display.spec.js"

# Then launch independent implementation files together:
Task: "Implement src/Compliance/ReferenceDateResolver.php"
Task: "Implement src/Pricing/AnchorPriceService.php"
Task: "Create templates/anchor-price.php and assets/css/frontend.css"
Task: "Implement src/Api/TemplateFunctions.php and the [sidrena_cijena] shortcode"
```

## Parallel Example: User Story 2

```bash
Task: "Unit tests for FilenameGenerator in tests/unit/Export/FilenameGeneratorTest.php"
Task: "Unit tests for CsvWriter and XmlWriter in tests/unit/Export/"
Task: "Implement src/Export/FilenameGenerator.php"
Task: "Implement src/Export/CsvWriter.php"
Task: "Implement src/Export/XmlWriter.php"
Task: "Implement src/Export/PriceListBuilder.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (critical — blocks everything)
3. Complete Phase 3: US1 (anchor recording and display)
4. **STOP and VALIDATE** quickstart V2 independently
5. Deploy/demo as the MVP

### Incremental Delivery

1. Setup + Foundational → foundation ready
2. US1 → validate V2 → MVP
3. US2 → validate V4–V6 → price-list obligation covered
4. US3 → validate V3 → 30-day rule covered
5. US4 → validate V7 → compliance visibility
6. US5 → validate V2/V8 → safe onboarding
7. US6 → validate V9 → multi-location
8. US7 → auditability
9. US8 → validate V1/V10/V12 → production operations
10. Polish → docs, hardening, full acceptance

### Parallel Team Strategy

1. Team completes Setup + Foundational together
2. Then: Developer A → US1; Developer B → US2; Developer C → US3; Developer D → US4/US5
3. US6 after US2; US7 and US8 after the services they depend on
4. Polish phase shared

---

## Notes

- [P] tasks touch different files and have no incomplete dependencies
- [Story] labels map every story-phase task to its user story for traceability
- Each user story is independently completable and testable at its checkpoint
- Verify tests fail before implementing
- Commit after each task or logical group with the message conventions from the brief (`feat:`, `fix:`, `test:`, `docs:`)
- Stop at any checkpoint to validate a story independently
- Never declare work complete because code compiles; run the relevant suites and the regression checks
