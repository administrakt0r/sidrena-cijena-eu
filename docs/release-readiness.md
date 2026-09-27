# Release verification

## Release 2.0.2 — UI/UX Improvements Pass (2026-09-27)

- UI/UX improvements pass per `AGENTARDS/09-ui-ux-improvements.md` (Items 4, 5, 6, 7 / UX-04..UX-07).
- Centralised admin styles and scripts: removed inline style attributes across admin screens, extracted bulk anchor entry select-all/deselect-all and overwrite confirmation into `assets/js/admin.js`, added `:focus-visible` styles, added busy/disabled states for long-running submit buttons, added full status badge variant styles, fixed badge color contrast for WCAG AA compliance (>7:1 on light background), and enqueued admin assets on product edit screens.
- Public price list improvements: added catalog pagination (`const LIST_PER_PAGE = 50`), "showing X–Y of Z items" count, clear-filters affordance, helpful empty state action, horizontal scroll container, print media stylesheet rules for legally mandated printouts, translated XML/CSV feed links, and accessible filename aria-labels on archive download links.
- Consistent date and amount formatting: introduced `Dates::format_datetime_display()` across all admin screens for unified Croatian site-time display (`d.m.Y. H:i`), and unified amount formatting via `Currency::format()`.
- Accessibility and i18n hygiene: added `label_for` attributes across all settings screens, fixed `SaleTypesSettings` ID mismatch, added `<legend>` to checkbox fieldsets, added `aria-live="polite"` variation updates, cleaned up duplicate description in locations, and completed Croatian translation catalog (714 translated, 0 untranslated).
- Quality gates: 375 unit tests (1,343 assertions), 0 failures; PHPCS clean across 175 files; PHPStan level 6 (0 errors across 117 files); ESLint clean; Croatian translation catalog complete (714 translated strings / 0 untranslated in `languages/hr_HR.po`).
- Built `versions/2.0.2/sidrena-cijena-eu.zip` using `tools/build-zip.sh 2.0.2` and verified with `unzip -t`.

## Release 2.0.1 — Audit & Fix Pass (2026-09-27)

- Full static audit and security sweep per `AGENTARDS/01-audit-and-fix.md`.
- Added unit regression tests for upload path validation in `CsvParser` (`test_parse_file_rejects_empty_path`, `test_parse_file_rejects_path_traversal`, `test_parse_file_rejects_nonexistent_file`, `test_parse_file_rejects_file_outside_allowed_roots`, `test_parse_file_accepts_file_in_allowed_root`).
- Added unit regression tests for `WelcomeBanner::maybe_redirect()` security guards against missing capabilities, cron execution, AJAX requests, and network admin contexts.
- Quality gates: 370 unit tests (1,314 assertions), 0 failures; PHPCS clean across 175 files; PHPStan level 6 (0 errors across 117 files); ESLint clean; Croatian translation catalog complete (705 translated strings / 0 untranslated in `languages/hr_HR.po`).
- Built `versions/2.0.1/sidrena-cijena-eu.zip` using `tools/build-zip.sh 2.0.1` and verified with `unzip -t`.

## Release 2.0.0 — Sidrena Cijena EU (2026-09-26)

- Renamed the public-facing plugin to **Sidrena Cijena EU** and synchronized the
  plugin header, runtime version, `readme.txt`, npm metadata and lockfile at
  2.0.0. The WordPress.org text domain, translation catalogs and package folder
  use `sidrena-cijena-eu`; the main PHP file and established internal URLs keep
  their existing identifiers.
- Fixed the Styling tab destination to the registered settings page. Added a
  public archive page with publication dates and CSV/XML links, plus
  default-enabled controls for archive visibility, a footer link and a sitemap
  entry. Sitemap registration now runs at `init` priority 0 so WordPress has
  rewrite state ready before core initializes its sitemap registry.
- Reworked repository queries to expose `$wpdb->prepare()` calls inline and
  use `%i` for internal table identifiers. The unit-test WordPress stub now
  supports `%i`. Uninstaller cleanup uses the WordPress filesystem API.
- Quality gates: unit tests 361/361 (1,296 assertions); integration 96/96 (454 assertions);
  Playwright E2E 42/42 specs; PHPCS 175 files clean; PHPStan level 6, 0 errors across 117 files;
  ESLint clean; Croatian catalog 705 translated, 0 untranslated.
- Compliance remediation roadmap (completion-steps.md, Tasks 1–10) fully implemented
  and verified green across all test layers.
- Live smoke checks: the demo shop and archive returned HTTP 200; the archive
  showed dates and CSV/XML links; the footer contained the archive link; the
  WordPress sitemap index contained the archive provider URL.
- Built `versions/2.0.0/sidrena-cijena-eu.zip` from committed files with
  `tools/build-zip.sh 2.0.0`. The archive has the `sidrena-cijena-eu/` root;
  `unzip -t` and `SHA256SUMS.txt` verification passed. The package excludes
  development and owner-only documentation.
- Plugin Check 2.1.0 scanned the exact ZIP contents in WordPress 7.1.2 with
  `--slug=sidrena-cijena-eu`: **0 errors, 63 warnings**. There are no
  text-domain or package-slug mismatch findings. Remaining warnings are
  advisory custom-table query/cache notices, prepared-query analyzer warnings
  for parameter arrays and internal SQL fragments, and WordPress VIP caution
  notices for intentional catalog exclusions. The private owner note records
  the counts and review basis for each category.
- See the locally excluded owner note at `docs/wordpress-org-upload-2.0.0.md`
  for submission steps. It and the Plugin Check CSV are excluded from Git and
  from the ZIP.
- Published the source at <https://github.com/administrakt0r/sidrena-cijena-eu>.
  The [v2.0.0 GitHub release](https://github.com/administrakt0r/sidrena-cijena-eu/releases/tag/v2.0.0)
  includes the upload ZIP and SHA-256 file. WordPress.org directory submission
  remains for the owner to complete using the private instructions.

## 1.0.4

Setup Guide status badges, activation redirect hardening, documentation/spec references cleanup, full AGENTARDS prompts refresh, and translation catalogs:
- Unit: 175 tests, 525 assertions passed (`composer test:unit`).
- Static analysis & linting: PHPStan 106/106 files clean (0 errors), PHPCS 133/133 files clean, ESLint clean (`npm run lint:js`).
- Integration: 76 tests, 342 assertions passed on `wp-env` with MySQL (`composer test:integration`).
- Local environment: `./local.sh` exited 0; seeded 17 compliance test scenarios, forced generation, status healthy.
- Setup Guide checklist: Added dynamic status badges for all 5 steps: step 3 reports recorded anchor counts (`(done: %d recorded)` / `(none recorded)`), step 5 reports stored compliance report status (`(clean)`, `(errors found)`, or `(not run yet)`).
- Welcome banner hardening: Enhanced `WelcomeBanner::maybe_redirect()` to guard against redirects during bulk activation (`$_GET['activate-multi']`), network admin (`is_network_admin()`), cron runs, and REST requests.
- AGENTARDS prompts refresh: Overhauled `AGENTARDS/README.md` and prompts 01 through 05 with current quality gates (including `npm run lint:js`), strict isolation (E2E on port 8889), accurate documentation paths, and mandatory version release workflow.
- Cleaned up obsolete references to deleted `specs/001-sidrena-cijena-compliance/` and `contracts/` across `src/Database/Schema.php`, `src/Export/PriceListBuilder.php`, `src/Export/CsvWriter.php`, `src/Export/XmlWriter.php`, `docs/development.md`, and `docs/filename-profile.md`.
- Translations: Full Croatian translation (479/479 strings translated, 0 untranslated in `hr_HR.po` / `sidrena-cijena-hr.mo`).
- Release archive: Built via `tools/build-zip.sh 1.0.4`, checksum generated, `unzip -t` passed.

## 1.0.3

Setup guide onboarding, public page caching, resilient 08:00 daily generation (REL-06), CI pipeline (REL-08), translations and failure visibility:
- Unit: 173 tests, 517 assertions passed (`composer test:unit`).
- Static analysis & linting: PHPStan 106/106 files clean (0 errors), PHPCS 133/133 files clean, ESLint clean (`npm run lint:js`).
- Integration: 76 tests, 342 assertions passed on `wp-env` with MySQL (`composer test:integration`).
- Local environment: `./local.sh` exited 0; seeded 17 compliance test scenarios, forced generation, verified status reporting `Scheduler: healthy`.
- Deadline robustness: Re-anchored WP-Cron daily recurrence after runs to eliminate DST drift; timezone change listeners (`timezone_string`, `gmt_offset`); working-day missed run detection with catch-up generation throttled by transient lock on `init`; failure recording with dismissible admin notice, Publications/Dashboard status alerts, and WP-CLI `status` health diagnostics; covered by `SchedulerTest`.
- CI automation: `tools/ci/ci.yml` pipeline (ready for `.github/workflows/`) executing static quality gates and `wp-env` integration tests.
- First-run onboarding: `SetupGuidePage` (5-step progress checklist) and `WelcomeBanner` with author links and one-time activation redirect.
- Caching: Rendered HTML public price list caching in `PublicPage` with invalidation through `RestCache`.
- Translations: Full Croatian translation (474/474 strings translated, 0 untranslated in `hr_HR.po` / `sidrena-cijena-hr.mo`).
- Release archive: Built via `tools/build-zip.sh 1.0.3`, checksum generated, `unzip -t` passed.

## 1.0.2

Prospective first-offer capture and version archives: unit 119 tests/380 assertions,
integration 76/342, E2E 28 passed (4.2 minutes, port 8889), PHPStan, PHPCS and
ESLint clean; build script syntax checked; `./local.sh` exited 0. Integration
covers standalone products/services, zero prices, pre-baseline observations,
existing entries, copied eligibility and preserved anchors. Real WooCommerce
E2E covers draft/publication, edits, exact variation identity and imported
products including subsequent edits. Every bumped version is archived in a
numeric version directory with an installable ZIP/checksum; the builder refuses
to overwrite an existing ZIP.

## 1.0.0

Release preparation started 25 September 2026. The plugin header, runtime
constant, npm manifest/lock and WordPress stable tag agree on 1.0.0.

The translation generator reads the plugin header. Both source catalogs contain
427 translated strings and zero untranslated strings. The Croatian MO compiler
now calculates offsets for its separate original/translation string blocks;
every dictionary entry was round-tripped through WordPress's MO reader.
The runtime catalog uses `sidrena-cijena-hr.mo`: WordPress's Croatian locale
identifier is `hr`, although the development dictionary is named `hr_HR.php`.

Verification so far:

- Unit: 119 tests, 380 assertions.
- Integration: 70 tests, 329 assertions after `sg docker -c 'npx wp-env clean tests'`.
  Existing E2E location/publication rows had contaminated the initial run.
- PHPStan, PHPCS and ESLint: clean.
- `sg docker -c './local.sh'`: exit 0.
- E2E: 27 passed (4.1 minutes), exclusively on port 8889.
- Clean ZIP installation: passed on fresh WordPress 7.1.2 / PHP 8.3 without
  WooCommerce. A disposable wp-env on ports 8890/8891 was destroyed and recreated.
  The final ZIP (source commit `7e98210`) installed and activated on its pristine
  test instance. Verification checked runtime version, every schema table,
  Croatian translation through `switch_to_locale('hr')` and `__()`, and absence
  of development dependencies. `unzip -t` also passed.

The integration bootstrap no longer predefines constants owned by the plugin,
eliminating duplicate-constant warnings during actual WordPress loading.

Build from a clean checkout of the release commit with
`tools/build-zip.sh 1.0.0`. An isolated checkout allows the developer's unrelated
working-tree changes to remain untouched. The archive contains runtime sources,
assets, templates, catalogs and documentation; development dependencies are excluded.

## Release 1.0.5 (2026-09-26)

- **Default Croatian Language**: Interface displays 100% in Croatian across all screens and sites by default.
- **Bulk Anchor Entry Tools**: Added shop-wide batch setup card to set anchor prices from current catalog prices in one click; added "Select all on this page", "Deselect all", and master header checkbox for instant table selection.
- **In-Dashboard Documentation**: Added rich Croatian reference screen (`sidrena-cijena-docs`) with direct links from Setup Guide, Welcome Banner, and Dashboard covering legal rules, 08:00 cron automation, CLI commands, developer hooks, and compliance gates.
- **Packaging Hardening**: Excluded developer notes, internal documentation, AI prompt files (`PROMPT.md`, `AGENTARDS/`), and temporary files from the distribution release archive.
- **Quality Gates**: All static gates pass cleanly (PHPUnit 180 tests / 546 assertions, PHPStan 0 errors, PHPCS clean, ESLint clean).

## Release 1.0.6 (2026-09-26)

- **Opt-in Uninstaller Hardening**: Extended `Uninstaller::$options` with `sidrena_cijena_rest_generation`, `sidrena_cijena_public_feed_last_queue`, and `sidrena_cijena_terms_seeded` ensuring pristine cleanup while strictly preserving legal anchor and 30-day evidence rows.
- **XML Price-List Specification Alignment**: Documented `datumSidreneCijene` in `docs/price-list-format.md` conforming with the verified XML generator implementation.
- **Integration Test Isolation**: Added table reset to `ActivationTest::set_up()` and `Test_Cli::set_up()` ensuring idempotent test runs regardless of prior E2E execution state.
- **Unit Test Coverage**: Added `tests/unit/Support/UninstallerTest.php` covering evidence preservation, option lists, and opt-in execution gates (unit suite increased from 180 to 184 tests, 571 assertions).
- **All Quality Gates Green**: PHPUnit unit (184/571 passed), PHPStan (107/107 clean, 0 errors), PHPCS (137/137 clean), ESLint clean, integration (76/342 passed), Playwright E2E (28/28 passed), and `./local.sh` clean exit 0.
- **Translations**: Croatian translation catalogs fully compiled (567/567 strings translated, 0 untranslated in `hr_HR.po` / `sidrena-cijena-hr.mo`).

## Release 1.0.7 (2026-09-26)

Improvement pass over performance, admin UX, accessibility and robustness.
No legal semantics, reference dates, filename profile or retention rules were
touched; `docs/legal-rules.md` is unchanged.

- **Performance**: `PriceListBuilder` verified to issue exactly one batched
  anchor lookup (`PriceHistoryRepository::latest_map()`) and one active-sale map
  per build, with a unit test asserting zero per-item `latest()` calls; empty
  catalogs now skip both pricing queries. Bulk Anchor Setup renders rows, counts
  missing anchors and sets shop-wide anchors through
  `PriceHistoryRepository::exists_map()` in 200-item chunks, removing the former
  per-item N+1 queries on large catalogs. `RestCache` keys, generation bumps and
  warm hits are covered by unit tests.
- **Cache invalidation**: price, anchor, sale, publication and settings changes
  already flushed the REST/public-page cache; saving, deactivating or deleting a
  sales location now does too.
- **Correctness**: fixed `PriceListBuilder` `meta.counts`, which always reported
  zero products and services because lookup keys and row values differed.
- **Admin UX (Croatian-first)**: Setup Guide completion data moved into
  `SetupGuidePage::statuses()`, consumed by all five checklist steps; the anchor
  step now shows coverage (`(done: %1$d of %2$d recorded)`) and the checklist
  reflects the current shop state instead of static badges.
- **Accessibility**: visible label for the import file input and the public
  type filter, `scope="col"` on admin, history, location and import-preview
  table headers, ARIA labels for bulk checkboxes and inputs, and a darker
  disclaimer color (`#646970`) in the public stylesheet.
- **Quality gates**: unit suite 184/571 → 196/633 passed; PHPStan 107/107
  clean (0 errors); PHPCS clean; ESLint clean. Integration/E2E were not rerun
  this pass (no Docker on the releasing machine); PHP 7.4–8.4 compatibility is
  unchanged and remains guarded by PHPCompatibilityWP.
- **Translations**: regenerated to 569/569 Croatian strings, 0 untranslated.
- **Archive**: `tools/build-zip.sh 1.0.7` from source commit `488b58c`;
  `unzip -t` reports no errors; 272K. SHA-256
  `01a687c2739c8a96ba146aa1fab5f787edb56e73ca22ff5c905647d0d6c6f2d5`.

## Release 1.0.8 (2026-09-26)

Correctness and reliability sweep across cron, sales, pricing, export, import,
WooCommerce display and admin screens. No legal semantics were changed:
reference dates, the 30-day window, the filename pattern, retention and the
append-only history are untouched, and `docs/legal-rules.md` is unchanged.

- **Scheduler / deadline (REL-06 follow-up)**: a run inside the 15-minute buffer
  no longer requeues itself every 30 seconds, so the same location/format is not
  force-published dozens of times before the deadline; reschedules after a run
  pass `false` to `Scheduler::schedule_daily()`. Service price-change requests
  now reach the cron callback as a single positional argument instead of an
  associative array (`Unknown named parameter` on PHP 8). `is_healthy()` treats
  an event overdue beyond one hour as unhealthy, so `wp sidrena-cijena status`
  reports `NOT SCHEDULED OR OVERDUE` instead of a stale "healthy".
- **Special sales**: end dates are stored inclusively (`23:59:59`), so sales are
  active on their last day and one-day sales work; promo prices are parsed via
  `Currency::to_storage()` (comma decimals like `7,50` no longer collapse to 7).
  `refresh_statuses()` now runs as part of the daily generation.
- **30-day lowest price**: an open-ended `regular`/`sale` row is bounded by the
  next row of the same type, so a superseded cheaper price no longer pins every
  later window; `created_at` fallbacks are converted from UTC to site time
  before window comparisons.
- **Export / publication**: locations with an incomplete street address or city
  block filename generation (`missing_location_address`) as documented; date-only
  anchor corrections change the content fingerprint; the published row records
  the real warning count; `CleanupJob` only marks a file expired when deletion
  succeeded, and `Publisher` removes an orphaned file if the archive insert
  fails. The CLI `generate --dry-run` now runs the same compliance gate.
- **Import**: re-importing the plugin's own CSV keeps `datum_sidrene_cijene`,
  quoted multi-line fields parse as one record, variation rows resolve in the
  catalog index (`include_variations`), failed inserts surface instead of a
  stale id, dry-run counters match commit semantics, and rollback catches
  `\Throwable`.
- **WooCommerce display**: variation anchors use the same tax-aware
  `PriceDisplay::display_price()` helper, stored `tax_inclusive` anchors are not
  re-taxed, `BuilderCompat` only appends the queried product (no related/upsell
  leakage), and price changes fire `sidrena_cijena_price_changed`.
- **Admin / settings**: saving one settings tab preserves the flags of the other
  tabs (hidden companion inputs), the uninstall data-deletion opt-in reads the
  `Settings` option, the bulk anchor screen paginates the filtered missing set,
  notices are URL-encoded, and remaining hardcoded admin strings are translated.
- **Quality gates**: unit suite 196/633 → 207/664 passed; PHPStan 107/107 clean;
  PHPCS 140/140 clean; ESLint clean. Integration on wp-env + MySQL: 77 tests,
  349 assertions. E2E: 28 specs ran with one flaky login timeout in the import
  spec (docker containers inspected concurrently); the spec passed on targeted
  rerun (3/3). `./local.sh` exited 0 and `wp sidrena-cijena status` reports the
  corrected overview.
- **ZIP smoke**: archive extracted and activated on the wp-env WordPress with
  WooCommerce active and again with WooCommerce deactivated: runtime constant
  `SIDRENA_CIJENA_VERSION` is `1.0.8`, the five `wp_sidrena_*` tables exist, the
  Croatian catalog loads (`Najniža cijena u zadnjih 30 dana`), and no dev
  dependencies ship inside the archive.
- **Translations**: 572/572 Croatian strings, 0 untranslated.
- **Archive**: `tools/build-zip.sh 1.0.8` from source commit `f8a2abb`;
  `unzip -t` reports no errors; 280,703 bytes (276K). SHA-256
  `a9d262080b9b402d6920ed30cec9856e83a5facdae79d1130440429dabfe5593`.

## Release 1.0.10 (2026-09-26)

Improvement pass: public REST price lists are now served from the shared
response cache, the anchor price service shares the anchor cache, and both
cache generations are monotonic. No legal semantics were changed: reference
dates, the 30-day window, the filename pattern, retention and the append-only
history are untouched, and `docs/legal-rules.md` is unchanged.

- **Performance**: `PriceListController::cached_build()` keeps canonical
  builds per location and item type in `RestCache`, so `/prices` and the CSV /
  XML downloads no longer walk the whole catalog on every request. Request
  filters (`sale_only`, `updated_since`, pagination) apply afterwards and are
  not part of the key. Invalidation triggers are unchanged (price, anchor,
  sale, publication, location and settings changes).
- **Performance**: `Plugin::anchors()` now constructs `AnchorPriceService`
  with the shared `AnchorCache`, so storefront lookups reuse a record until
  the next invalidation or flush. `sidrena_cijena_anchor_recorded`
  additionally forgets the recorded item via
  `Plugin::invalidate_anchor_cache()`, covering anchor writes that reach the
  history table through any path.
- **Robustness**: `RestCache::flush()` and `AnchorCache::flush()` use
  `max( time(), previous + 1 )`, so two flushes inside the same second can no
  longer leave entries written between them visible.
- **Tests**: new `PriceListControllerCacheTest` (cache reuse, rebuild after
  flush, per-location/type keys), `AnchorCacheWiringTest` (shared lookups,
  recorded-item invalidation through the hook and through `record()`), and
  back-to-back flush tests for both caches; unit suite 223/698 → 231/722.
- **Quality gates**: unit tests pass; PHPStan 107/107 clean; PHPCS clean;
  ESLint clean. No translatable strings were added or changed, so the
  Croatian catalogs were not regenerated.
- **Archive**: `tools/build-zip.sh 1.0.10` from source commit `9ab5d62`;
  `unzip -t` reports no errors; 282,117 bytes (276K). SHA-256
  `f2ede613c5d28e04da6d008ae267338c1405286b9421fdc3f6e939d26f269dc7`.

## Release 1.0.9 (2026-09-26)

Improvement pass: accessibility completion, new unit coverage for the anchor
cache and the special-sale service, and a PHP 7.4–8.4 syntax scan. No legal
semantics were changed: reference dates, the 30-day window, the filename
pattern, retention and the append-only history are untouched, and
`docs/legal-rules.md` is unchanged.

- **Accessibility**: all remaining admin `<th>` elements now carry
  `scope="col"` (dashboard publications/findings, compliance findings,
  publications, logs, both audit tables, recent rule changes, all three
  documentation tables, import sources, sale history meta box), completing the
  1.0.7 pass that had scoped only part of the screens. Verified by source scan:
  no unscoped `<th>` remains in `src/`, `templates/` or `assets/`.
- **Tests**: new `AnchorCacheTest` (miss/hit semantics, cached nulls,
  malformed entries, per-item and variation-cascading invalidation, generation
  flush) and `SpecialSaleServiceTest` (FR-023 sale-type gate, start/end date
  validation, not-found paths for update/recompute/cancel, null active sale).
- **Compatibility**: static scan of `src/` finds no PHP 8-only syntax (no
  nullsafe operator, `match`, `str_contains`/`str_starts_with`/
  `str_ends_with`, `readonly`, enums); PHP 7.4 to 8.4 supported. Behavior is
  unchanged with and without WooCommerce (no Woo-dependent code touched).
- **Quality gates**: unit suite 207/664 → 223/698 passed; PHPStan 107/107
  clean; PHPCS 142/142 clean; ESLint clean. No translatable strings were
  added or changed, so the Croatian catalogs were not regenerated.
- **Archive**: `tools/build-zip.sh 1.0.9` from source commit `b39c49c`;
  `unzip -t` reports no errors; 281,028 bytes (276K). SHA-256
  `89391821e8878cbaa3d558d2fd915a6ec26ab444ca6504527920e6b21aad67dd`.

## Release 1.0.11 (2026-09-26)

Improvement pass: catalog lifecycle cache invalidation, localized price
history labels, and new unit coverage for the compliance report value
objects. No legal semantics were changed: reference dates, the 30-day
window, the filename pattern, retention and the append-only history are
untouched, and `docs/legal-rules.md` is unchanged.

- **Robustness**: trashing, deleting, or unpublishing a catalog item
  (`sidrena_item`, `product`, `product_variation`) now flushes the shared
  anchor and REST cache generations immediately (`transition_post_status`
  and `before_delete_post` in `Plugin`). Previously the public price-list
  page and the REST `/prices`, CSV and XML endpoints kept serving removed
  items until the 300 s cache TTL expired. Content edits of published items
  do not change catalog membership and keep the cache warm.
- **Usability (Croatian-first)**: price history filters, table rows, the
  correction form, and the anchor meta box now show translated labels
  ("Sidrena cijena", "Najniža cijena u zadnjih 30 dana", "CSV uvoz",
  "Automatski zabilježena prva ponuda", …) through the new
  `Support\HistoryLabels` helper instead of raw slugs such as
  `lowest_30_day` or `observed_first_offer`; stored values are unchanged.
- **Tests**: new `PluginLifecycleCacheTest` (trash/delete/publish flushes,
  warm-cache on content edits and non-catalog types), `ComplianceReportTest`
  (finding severity fallback and defaults, entity grouping, descending code
  counts, CSV quoting of separator-containing fields, `from_result` meta
  derivation) and `HistoryLabelsTest`; unit suite 231/722 → 253/807 passed.
- **Quality gates**: PHPStan 108/108 clean; PHPCS 148/148 clean; ESLint
  clean. Translation catalogs regenerated with
  `php tools/i18n/make-pot.php --locale=hr_HR`: 579 strings, 579 Croatian
  translations, 0 untranslated.
- **Archive**: `tools/build-zip.sh 1.0.11` from source commit `105fc27`;
  `unzip -t` reports no errors; 283,940 bytes (280K). SHA-256
  `5f49169de618df4defce6c96ea692bb8d7af41b267d298c41367ee5d78146b3d`.


## Release 1.0.12 (2026-09-26)

Improvement pass: timezone-safe date-only rendering, dashboard accuracy for
variable products, and an accessible location-form fix. No legal semantics
were changed: reference dates, the 30-day window, the filename pattern,
retention and the append-only history are untouched, and
`docs/legal-rules.md` is unchanged. No translatable strings were added or
changed, so the Croatian catalogs were not regenerated.

- **Correctness**: new `Dates::format_date_display()` renders date-only values
  (`Y-m-d`) as the Croatian `d.m.Y.` format without a timestamp conversion.
  Previously the public price list passed the reference date through
  `strtotime()` and `wp_date()`, so sites west of UTC displayed the previous
  calendar day; the price-history table now uses the same helper. The unit
  `wp_timezone()` stub honours an overridable `$GLOBALS['sidrena_test_timezone']`
  so the regression is pinned with an `America/New_York` case.
- **Dashboard**: `DashboardPage::aggregate()` resolves a variation's inherited
  parent sale before counting "Items with no price history", so variable
  products with a parent-level sale are no longer reported as missing history;
  an empty catalog now skips the sale lookup entirely. New
  `DashboardPageTest` pins the two batched `latest_map()` calls and the single
  `active_map()` call plus the classification counters.
- **Accessibility (Croatian-first)**: the applicable-categories field on the
  sales-location form uses a screen-reader label and well-formed markup
  instead of an unclosed element with an inline `aria-label`; a new
  `LocationsPageTest` asserts every control has a matching label.
- **Tests**: `PriceListBuilderTest` also covers the `type` argument,
  include/exclude category modes, variation sale inheritance and precedence,
  row ordering and fingerprint stability. Unit suite 253/807 → 268/869.
- **Quality gates**: PHPStan 108/108 clean; PHPCS 150/150 clean; ESLint clean.
- **Archive**: `tools/build-zip.sh 1.0.12` from source commit `66df7f1`;
  `unzip -t` reports no errors; 284,421 bytes (278K). SHA-256
  `fc24a6a380ee5ed974bcdfbb0b036c837f4e219e23a295e784f4a570a0ecb560`.

## Release 1.0.13 (2026-09-26)

UI/UX pass on the admin screens (session items UX-01..UX-03; the remaining
UX-04..UX-07 are tracked in `docs/improvement-backlog.md`). No legal semantics
changed: `docs/legal-rules.md`, the CSV/XML contracts and the filename pattern
are untouched.

- **Labels (UX-01)**: one shared `Support\Labels` resolver translates every
  internal vocabulary (publication and sale statuses, log levels, log events,
  finding severities, entity types, sale types, location types, import row
  statuses, rule-change keys, generation run sources) and is used by every
  admin screen that printed raw slugs (`DashboardPage`, `CompliancePage` cards
  and filters, `PublicationsPage`, `ImportPage`, `LogsPage`, `AuditPage`,
  `LocationsPage`, `SpecialSaleMetaBox`, `LegalSettingsPage`). Unknown slugs
  pass through unchanged. The resolver replaces `Support\HistoryLabels`; its
  price-type and source labels are carried over verbatim.
- **Tables and pagination (UX-02)**: shared `Admin\Pagination` renders the
  WordPress tablenav wrapper with a screen-reader pagination label on
  `PublicationsPage`, `PriceHistoryPage`, `LogsPage`, `BulkAnchorEntryPage`,
  `ImportPage` and `AuditPage`; admin tables gained `<caption>` elements. The
  audit log is now reachable page by page (50/page), the rule-change history is
  capped at 20 entries per page, and the import preview renders 50 rows per
  page instead of the whole parsed CSV.
- **Form error UX (UX-03)**: `Admin\Notice` renders one dismissible,
  focus-managed notice for every screen (several were missing
  `is-dismissible`), and `Admin\FormState` keeps submitted values and attaches
  per-field errors (`aria-invalid`, `aria-describedby`) on the price-history
  correction form and the sales-location form. `assets/js/admin.js` moves focus
  to the first invalid field or the notice. "Mark setup complete" and "Reopen
  setup" now confirm their action.
- **Tests**: `LabelsTest`, `PaginationTest`, `ImportPageTest`, `NoticeTest`,
  `FormStateTest`, `PriceHistoryPageTest` and new `LocationsPageTest` cases.
  Unit suite 268/869 → 301/1078. E2E 28/28 green on port 8889, including an
  updated price-history spec that asserts the labelled type and source.
- **Translations**: 632 strings in `languages/hr_HR.po`, 0 untranslated;
  `tools/i18n/make-pot.php --locale=hr_HR` regenerated the POT/PO/MO.
- **Quality gates**: PHPCS clean; PHPStan level 6 clean; ESLint clean.
- **Archive**: `tools/build-zip.sh 1.0.13` from source commit `9c2e9a7`;
  `unzip -t` reports no errors; 291,982 bytes (285K). SHA-256
  `e240aa801d6ad7234bb5467e9df935ca42c76c1caaae0b21ab0e92e7d94b6319`.



## Release 1.0.14 (2026-09-26)

Full static audit run (AGENTARDS/01-audit-and-fix.md), no Docker required. No
legal semantics changed: `docs/legal-rules.md`, the CSV/XML contracts, the
filename pattern and the append-only history contract are untouched.

- **Security sweep (all six criteria verified)**:
  - *Admin actions*: every `admin_post_` handler (`AnchorMetaBox`,
    `SpecialSaleMetaBox`, `PublicationsPage`, `CompliancePage` run/export,
    `ImportPage` preview/commit/report, `BulkAnchorEntryPage`,
    `LocationsPage` save/delete, `PriceHistoryPage`, `SetupGuidePage`
    complete/reopen, `WelcomeBanner`, `Scheduler` dismiss) pairs
    `Capabilities::current_user_can()` with `check_admin_referer()`;
    `StandalonePostType::save()` pairs `wp_verify_nonce()` with
    `current_user_can( Capabilities::capability() )`. Every menu and submenu is
    registered with `Capabilities::capability()`.
  - *Escaping*: PHPCS runs `WordPress.Security.EscapeOutput` over `src/`,
    `templates/` and the bootstrap; the audit reviewed each
    `phpcs:ignore OutputNotEscaped` site (CSV/XML download bodies, JSON/CSV
    report bodies, `FormState::error_attributes()` attribute markup) and found
    no unescaped echo.
  - *SQL*: `src/Database/*` is exempted from the PreparedSQL sniffs, so every
    query was read by hand — all values go through `$wpdb->prepare()` or
    `$wpdb->insert/update/delete` formats, `build_where()` emits placeholders
    only, `sanitize_orderby()` whitelists `ORDER BY`, and `LogRepository`
    prepares the dynamic `WHERE`. Only the plugin-owned table name is ever
    interpolated.
  - *Uploaded paths*: `CsvParser::parse_file()` rejects anything outside
    `wp_upload_dir()['basedir']` and `sys_get_temp_dir()` before reading;
    `ImportService::preview()` and `commit()` both go through it.
  - *Logs*: every `Logger::{info,warning,error}()` call site passes counters,
    codes and IDs; `Logger::sanitize_context()` drops sensitive keys
    (pass/secret/token/api_key/card/iban/email/phone/customer/session/cookie/…)
    recursively and truncates values to 500 characters.
  - *Post-activation redirect*: `WelcomeBanner::maybe_redirect()` guards the
    redirect option, the capability, AJAX, WP-CLI, cron, `REST_REQUEST`,
    `is_network_admin()`, `$_GET['activate-multi']` (which clears the option),
    the current screen and the `plugins` screen before `wp_safe_redirect()`.
- **Gap fixed**: `AnchorMetaBox::register()` and `SpecialSaleMetaBox::register()`
  were the only admin entry points without the capability check — they exposed
  the recorded anchor price, its reference date and the 30-day lowest price on
  every supported edit screen, and offered a form whose handler answered with
  `wp_die()`. Both now return early without `manage_sidrena_cijena`.
- **Tests**: new `MetaBoxCapabilityTest` (5 cases) plus `post_type_exists()` /
  `add_meta_box()` stubs in `tests/unit/wp-stubs.php` — the first unit tests to
  pin the `manage_sidrena_cijena` contract. Unit suite 301/1078 → **306/1083**.
- **Translations**: 632 strings, **0 untranslated**; no translatable string was
  added or changed, `tools/i18n/make-pot.php` and `--locale=hr_HR` only
  refreshed the POT/PO timestamps.
- **Quality gates**: `composer test:unit` 306/1083 green; PHPCS 159/159 clean;
  PHPStan level 6 111/111 clean; ESLint clean.
- **Archive**: `tools/build-zip.sh 1.0.14` from source commit `e4aa6da`;
  `unzip -t` reports no errors, 154 files; 292,396 bytes (286K). SHA-256
  `a931403390ec6bfb2f55ba29d2bc777b3a01236788c1e19616d06d3bfc661644`.

## Release 1.0.15 (2026-09-26)

- **Data-layer performance**: see `docs/improvement-backlog.md` PERF-01–PERF-07.
  Demo measurement was limited to 6 published WooCommerce products / 8 hydrated
  catalog rows (not the prompt's expected 200+); observed query and timing values
  are recorded there without extrapolating or claiming unsupported latency gains.
- **Schema**: additive schema version 1.1.0 adds indexes only; no price-history
  rows were rewritten and `docs/legal-rules.md` was not changed.
- **Tests and gates**: unit 306/1083; integration 80/363 after `npx wp-env clean tests`;
  PHPCS 159/159 clean; PHPStan 111/111 clean; ESLint clean. Integration was run
  inside `tests-cli` with the repository's PHPUnit 9.6 (`composer test:integration`
  on the host cannot locate `/wordpress-phpunit`).
- **Demo measurements**: forced generation was 2.44 s at end vs 4.31 s initially,
  but publication/catalog state changed, so this is not attributed to the patch.
  Public page measured HTTP 200 / 0.843 s at end; no valid before value was captured.
- **Archive**: `versions/1.0.15/sidrena-cijena.zip`, 293,153 bytes (288K), 154
  files; `unzip -t` reports no errors. SHA-256
  `e5e13ed936f4f794ec49cf1cffc947a28ef0f1b9f80df30fe41ff11e386b90e3`.

## Release 1.0.16 (2026-09-26)

- **Robustness**: fixed WooCommerce product editor submission by removing nested forms,
  separating plugin actions/nonces from normal Update, and removing browser-required sale
  fields that blocked unrelated product saves. Anchor and sale controls now use the native
  Product data editor tab.
- **Admin/storefront UX**: added the blue top-level Sidrena cijena menu with anchor icon,
  translated setup and entry guidance, and plain/badge/card product display presets.
- **Prompt 4 regression scope**: prompt now directs future passes to proactively reproduce
  admin-save workflows beyond known backlog items and fix discovered form/nonce/action failures.
- **Translations**: regenerated Croatian catalogs; 652 strings translated, 0 untranslated.
- **Verification**: `composer test:unit` 307 tests / 1085 assertions; `composer lint`
  clean (159 files); `composer analyse` clean (111 files); `npm run lint:js` clean;
  integration 81 tests / 371 assertions after `npx wp-env clean tests`; full
  `npm run test:e2e` 31/31 passed, including ordinary WooCommerce Update and the
  dedicated anchor evidence action; `./local.sh` completed and generated the demo
  lists (3.24 s).
- **Archive**: `versions/1.0.16/sidrena-cijena.zip`, 298,762 bytes; `unzip -t`
  reports no errors. SHA-256
  `1e291101bf91cb9473ea76e7d4583a3366c3a0dfcaca8c88187204c10fb43f70`.

## Release 1.0.17 (2026-09-26)

- Static audit: baseline unit suite 307 tests / 1085 assertions; PHPCS, PHPStan and ESLint clean. Admin action capability and nonce checks, output escaping, SQL parameterization, import path containment and activation redirect guards reviewed.
- Privacy fix: structured log context now drops personal name, address and IP address fields; regression test added. Price history was not modified.
- Variable products: a parent price range does not display a single anchor; WooCommerce's selected variation HTML carries that variation's own price and dated anchor, including equal-price variations. The variation script updates only the selected product's markup.
- Translations: 652 strings, 0 untranslated; no source strings changed.
- Final static gates: `composer test:unit` 310 tests / 1091 assertions; `composer lint` clean (160 files); `composer analyse` no errors (111 files); `npm run lint:js` clean.
- Archive: `versions/1.0.17/sidrena-cijena.zip`, 299,118 bytes; `unzip -t` reports no errors. SHA-256 `e3eccd3aba523386efbbb4a98b4d542daff6d02328d44a644e96c57daba997ad`.

## Release 1.0.18 (2026-09-26)

- Fixed Legal rules saves that could re-enter WordPress option sanitization and show an error page. The same settings path handles malformed plugin option payloads without a PHP type error. A browser test changed and saved the legal rule version successfully.
- Cjenik generation now permits optional catalog findings such as missing SKU and barcode. It still requires a positive current retail price, a dated anchor, and the location data needed for the required filename. Other findings remain in the compliance report and count toward publication warnings. Price-history tables remain append-only.
- Added nine compact storefront presets with a live preview gallery and size control (40–160%, default 40% scale with a 12 px text floor). Replaced the missing sidebar Dashicon with an SVG anchor icon. WooCommerce product cards hide the anchor by default; each product can opt in through the native Sidrena product tab. Product pages remain visible by default.
- WordPress.org profile `@titanworks` displays `administrakt0r`; corrected `readme.txt` Contributors to the account username and documented submission and SVN steps in `docs/wordpress-org-publishing.md`.
- Verification: `composer test:unit` 315 tests / 1103 assertions; PHPCS 164 files clean; PHPStan 114 files with 0 errors; ESLint clean; WordPress integration 82 tests / 374 assertions; Playwright E2E 33/33 passed on the test site. Croatian catalog 663/663 strings translated, 0 untranslated.
- Archive: `versions/1.0.18/sidrena-cijena.zip`, 304,534 bytes; `unzip -t` reports no errors. SHA-256 `31007d900569fccb20e1120bf094b19839764ec5cb4a70be948fe1cf7f6ddea7`.
