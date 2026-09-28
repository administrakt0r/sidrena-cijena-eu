=== Sidrena Cijena EU ===
Contributors: titanworks
Tags: croatia, price, compliance, woocommerce, price list
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.18
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Croatian anchor prices, 30-day sale-price history and daily CSV/XML price lists for WooCommerce or a standalone catalog.

== Description ==

This plugin is a technical compliance aid for Croatian merchants who must
display an additional reference price ("sidrena cijena") next to the current
retail price, publish a daily machine-readable price list, and show the lowest
price of the previous 30 days during special forms of sale.

**It is not legal advice.** Merchants remain responsible for their own legal
compliance and should obtain professional legal or accounting advice.

Main features:

* Immutable anchor price records with reference dates, sources and a full audit trail.
* Frontend display of the current price and the anchor price, clearly labelled ("Sidrena cijena").
* Separate 30-day lowest-price engine for special forms of sale (akcija, sniženje, sezonsko sniženje, rasprodaja).
* Daily CSV and XML price-list generation per sales location, with a documented filename profile and at least 30-day retention.
* Public, unauthenticated machine-readable price endpoint (`/wp-json/sidrena-cijena/v1/prices`).
* Compliance dashboard, validator with severity levels, CSV import with preview and rollback, price-history audit screen, structured logs and WP-CLI commands.
* WooCommerce is optional: the compliance workflow also works with the built-in standalone catalog.

== Installation ==

1. Install **Sidrena Cijena EU** from **Plugins → Add New Plugin**, or upload
   the ZIP from the [latest GitHub release](https://github.com/administrakt0r/sidrena-cijena-eu/releases/latest).
2. Activate the plugin through the *Plugins* screen.
3. Open *Sidrena cijena → Postavke* and review the reference dates and display settings.
4. Record or import anchor prices for your catalog.
5. Generate the price lists and confirm the public endpoints.

== Frequently Asked Questions ==

= Is this legal advice? =

No. It is a technical aid that implements documented obligations; consult a
legal or accounting professional for your specific situation.

= Does it require WooCommerce? =

No. WooCommerce integration is enabled automatically when WooCommerce is
active; otherwise the standalone catalog is used.

= Which reference dates are used? =

The general reference date is 10 September 2026. Legacy categories (food,
beverages, cosmetics, cleaning products, toiletries, household products) use
2 May 2025. Both are configurable under *Sidrena cijena → Postavke → Pravna
pravila*, and every change is recorded in the audit trail. Map your actual legacy
category slugs there before publishing; the plugin does not guess categories.

= Does the plugin invent historical prices? =

Never. Newly created offers witnessed at publication can receive their actual
first-offer price and observed date automatically. Existing catalog gaps and
imports require evidenced historical prices. Merchants must verify the price,
date, product identity and applicable sale classification before publishing.

== External services ==

The plugin sends no catalog data or telemetry to an external service. Price
history, validation and file generation run on your WordPress installation.
WordPress itself may contact WordPress.org for standard plugin updates.

== Privacy ==

The plugin stores catalog prices, historical evidence, shop addresses, publication
records and operational logs in your WordPress database and uploads directory.
Audit records include the acting WordPress user's ID and reasons supplied for
corrections. Do not put customer details or unnecessary personal data in these
fields. This plugin does not add tracking cookies or collect customer orders.

Published catalog fields, sales-location addresses and generated price lists are
public by design, including the REST feed and retained archive. Restrict catalog
content to information intended for publication. Deactivation and ordinary
uninstall retain data. Optional uninstall cleanup removes operational data and
files but preserves anchor and 30-day evidence, including their audit attribution.
Review retained records with your data-protection and legal advisers.

== Screenshots ==

1. Public price-list page with search, item types and downloads.
2. Administrative compliance dashboard with anchor price coverage, validator findings and publication status.
3. Price history and immutable audit log of statutory reference dates.
4. Plugin settings and legal rules configuration.

== Changelog ==

= 2.0.18 =
* Mandatory Croatian language delivery: bundled compiled binary translation files (`sidrena-cijena-eu-hr.mo` and `sidrena-cijena-eu-hr_HR.mo`) directly in the release archive, preventing external sites from rendering fallback English strings.
* Public statutory price-list translation guarantee: enforced Croatian translation catalog loading on public `/arhiva-sidrene-cijene/` and `/sidrena-cjenik/` pages and shortcodes regardless of visitor or default site locale.
* Public archive cache invalidation: versioned frontend template cache keys and added LiteSpeed Cache no-cache controls, ensuring updated Croatian tables display immediately without stale cached English HTML.
* Packaging hardening: updated `.gitattributes` and `tools/build-zip.sh` so compiled translations are never stripped or treated as leaks in release archives.

= 2.0.17 =
* Mandatory price-list generation: ensured daily price lists are always generated and published even when catalog compliance errors exist (`allow_generation_with_errors`), safeguarding merchants from statutory non-publication penalties under Croatian law (NN 101/2026-1213).
* Actionable remediation proposals: introduced `RemediationProposer` engine generating concrete, contextual guidance for every compliance finding across admin screens, WP-CLI output, and published archive records.
* WooCommerce store settings inheritance: auto-populated default location address and city from WooCommerce store settings (`woocommerce_store_address`, `woocommerce_store_city`), eliminating false `missing_location_address` errors on standard stores.
* Admin UI and CLI feedback: added "Proposed change" column to the Compliance check report, rendered actionable remediation notices in WP-CLI `wp sidrena-cijena generate`, and added status notices on the Publications screen.
* Safe uninstaller fallback: added filesystem check in `Uninstaller::delete_directory()` preventing missing file errors during unit tests.
* Unit test expansion & complete translations: added unit tests for `RemediationProposer` and `GenerationService` (528 tests, 1,890 assertions, 0 errors); 100% complete Croatian translations (797 translated / 0 untranslated).

= 2.0.16 =
* CSV formula injection protection: neutralised formula prefixes (`=`, `+`, `-`, `@`) in `ImportService::report_csv()` on user-supplied values (such as SKU and error messages).
* CLI error resilience: modernized exception handling across all WP-CLI commands (`StatusCommand`, `GenerateCommand`, `ValidateCommand`, `HistoryCommand`, `ImportCommand`, `CleanupCommand`, `SeedTestDataCommand`) and `Dates::format_datetime_display()` from `\Exception` to `\Throwable`, properly capturing PHP `\Error` and `\TypeError` failures with clean error messages and structured exit codes.
* Admin redirect safety: hardened `AnchorMetaBox::redirect_back()` with safe fallback to `admin_url()` when edit post link is unavailable.
* Test coverage expansion: added complete unit test suites for `ImportService` (transactional commits, rollbacks, overwrite confirmations, formula neutralization) and `Cli` commands (`CliTest`, `StatusCommandTest`, `CleanupCommandTest`, `HistoryCommandTest`, `ValidateCommandTest`, `GenerateCommandTest`); suite grew from 480 to 512 tests (1,853 assertions) with 0 errors.

= 2.0.15 =
* Enqueue compliance: replaced PublicPage inline style tag with standard wp_enqueue_style calls adhering to WordPress.org guidelines.
* Settings sanitization: sanitized legacy category IDs with absint() and category slugs with sanitize_title() in RuleConfig Settings API sanitizer.
* Admin dashboard moderation: removed post-activation redirect and scoped welcome banner and admin notices strictly to plugin admin screens (Guideline 11).
* Internationalization packaging: configured .gitattributes export-ignore and build script stripping for .po and .mo files to let translate.wordpress.org manage community translations.
* Plugin author alignment: aligned Author header with WordPress.org submission username titanworks.

= 2.0.14 =
* Test coverage & robustness: achieved full test coverage for Admin AuditPage, LogsPage, standalone item hydration, query filtering, and meta-box saving. Hardened `$user_id` extraction and unified user label fallbacks in logs. Added unit test suites for `CleanupJob` and `LogRepository::prune`. All tests passing flawlessly (478 unit tests).

= 2.0.13 =
* UI/UX improvements & button busy states: added busy form handling (`sidrena-busy-form` and `sidrena-btn-busy`) to long-running administrative submit actions ("Run compliance check", "Generate now", and shop-wide bulk anchor setting), preventing duplicate submissions and giving clear visual feedback.
* Datetime and date formatting consistency: unified datetime and date presentation across screens using Croatian-first display helpers (`Dates::format_datetime_display()` and `Dates::format_date_display()`) for compliance report generation timestamps, public price list generation metadata, Setup Guide legal reference dates, Documentation guide tables, and CSV import preview rows.
* Form ergonomics & standard constants: added `inputmode="decimal"` and placeholder hints to bulk anchor price inputs, standardized pagination on `LogsPage` via class constant `PER_PAGE = 50`.
* Test coverage & quality gates: added unit test suites covering `CompliancePage` and `PublicationsPage` HTML rendering, empty states, and severity filtering (456 unit tests / 1623 assertions, 0 errors); PHPCS, PHPStan, and ESLint clean; Croatian translations 100% complete (768 translated / 0 untranslated).

= 2.0.12 =
* Robustness & WooCommerce product editor workflows: hardened anchor price and special sale meta boxes by binding dedicated actions via action query parameters and eliminating redundant `_wp_http_referer` nonce fields that could collide with main product editor saves.
* Safe admin form ergonomics: added graceful fallback and `get_edit_post_link()` redirect handling in `SpecialSaleMetaBox`, prevented accidental submission on Enter within inline evidence sub-forms in `admin.js`, and added intuitive placeholders and translated descriptions across locations, standalone post types, and evidence forms.
* Regression test coverage: added comprehensive `ProductEditorSaveTest` verifying ordinary WooCommerce product updates, dedicated anchor and sale recording handlers, variable product panel rendering, and form markup safety (no nested forms, no duplicate `_wpnonce`, no HTML5 `required` blockers); 451 unit tests (1,606 assertions) passing, complete Croatian translations (768 translated / 0 untranslated).

= 2.0.11 =
* Security sweep & capability enforcement: explicitly bound `register_setting` for settings and legal rules to `manage_sidrena_cijena` capability via `Capabilities::capability()`.
* Quality gates & test suite: added `SettingsPageTest` covering capability verification and tab dispatch, stubbed `register_setting` in unit test stubs; 438 unit tests (1,557 assertions), PHPCS clean across 181 files, PHPStan level 6 (0 errors across 117 files), ESLint clean, and complete Croatian translation catalog (749 translated strings / 0 untranslated).

= 2.0.10 =
* Static audit & database hardening: prepared queries with modern `%i` table identifier placeholders in `MigrationDetector` across own history table, schema inspection tables, and postmeta queries.
* Robust error and type safety: hardened `LowestPriceExplainer` against array-to-string conversion notices when sale array lacks `start_date`, ensured `PriceDisplay::block_product` strictly returns `WC_Product|null`, safely handled `mb_convert_encoding` failure in `CsvParser`, guarded `strtotime` parsing before `gmdate` in `Publisher` and `PriceListController`, and validated `preg_split` return in `LocationsPage`.
* Filesystem and query typing: strictly verified `WP_Filesystem_Base` instance in `Uninstaller` and `WP_Query` in public feeds/pages.
* Static analysis & tests: PHPStan Level 7 completely clean (0 errors), Level 6 clean (117 files), PHPCS clean (180 files), and added unit test suite for `MigrationDetector` (436 tests, 1,543 assertions).

= 2.0.9 =
* Static audit & security hardening: escaped active navigation tab CSS class attribute in settings page.
* Permission denial regression coverage: added unit regression tests verifying strict capability rejection (`manage_sidrena_cijena`) across all admin-post handlers (including import preview/commit/report, compliance run/export, welcome banner dismiss, and scheduler notice dismiss).
* Quality gates verified clean: 429 unit tests (1,525 assertions), PHPCS clean (179 files), PHPStan level 6 (0 errors across 117 files), ESLint clean, and complete Croatian translation catalog (749 translated strings / 0 untranslated).

= 2.0.8 =
* WordPress.org Plugin Check (PCP) compliance: verified 0 errors and 0 warnings against the official WordPress.org automated review ruleset.
* Database security & modern placeholders: converted database table references to WordPress 6.2+ `%i` identifier placeholders across all repositories (`LocationRepository`, `LogRepository`, `PriceHistoryRepository`, `PublicationRepository`, `SpecialSaleRepository`, and `Uninstaller`).
* Hardened prepared queries: resolved unescaped parameter and interpolation warnings on dynamic clauses, adding explicit precision annotations for legitimate custom table queries.
* VIPCS false positive mitigations: resolved query argument warnings (`PostNotIn_exclude`) for HTML form options and WooCommerce catalog queries.
* Modern translation loading: retired discouraged `load_plugin_textdomain()` call in bootstrap for WordPress 4.6+ just-in-time translation loading.

= 2.0.7 =
* Static security audit & regression coverage: verified capability and nonce checks across all admin action endpoints, prepared SQL parameterized queries, and output escaping.
* Path traversal hardening: added regression coverage verifying import file path containment within allowed directories and rejecting directory traversal patterns.
* Welcome banner activation redirect guards: added regression test ensuring REST request contexts suppress one-time activation redirection while preserving the option.

= 2.0.6 =
* Cron catch-up optimization: offloaded catch-up price-list generation to an asynchronous background event, and restricted front-end catch-up checks to administrative and CLI requests with a fast-fail flag.
* Canonical page caching: cached canonical row set per location in public price list, performing query filtering and pagination in PHP to eliminate cache fragmentation across search terms and filter variations.
* Generation-keyed hot paths: added short-TTL generation-keyed caching to compliance dashboard aggregates, public publication archive, and REST /publications and /status endpoints, strictly bypassing caching on compliance ERRORs or publication gate decisions.
* AnchorCache write optimization: eliminated autoloading of the anchor cache generation marker option, and bypassed transient DB round-trips entirely when an external persistent object cache is active.
* Textdomain consolidation: consolidated textdomain loading to a single init hook respecting active user locale.
* Targeted asset loading: restricted front-end styles and scripts to product pages and shop/product archives, and dropped unused wp-i18n script dependency from admin scripts.
* Settings memoization: memoized settings per-request to eliminate redundant options-table decodes across repeated product displays.
* REST throttle optimization: leveraged persistent object cache for REST per-IP rate-limiting counters when available.

= 2.0.5 =
* Lifecycle cleanup: opt-in uninstall cleanup now purges all plugin transients from options while strictly preserving the append-only anchor and 30-day legal evidence; plugin deactivation clears catch-up locks while retaining all data.
* Feed corruption protection: public XML and CSV feeds verify publication file readability, non-empty size, and SHA-256 checksums before serving, triggering automatic 503 recovery and queuing clean background rebuilds on file tampering or corruption.
* Resilient CSV date parsing: CSV import row validation now seamlessly normalizes common ERP date variations (including single-digit days/months, slashes, dashes, and trailing dots) into canonical Y-m-d.
* Admin forms and ergonomics: added clear format hints and placeholders across store locations, price history corrections, and product edit panels, and added decimal inputmode for mobile entry.
* Security and quality gates: added regression coverage for unauthorized admin-post execution across all plugin handlers; 400 unit tests (1,444 assertions) green, clean PHPCS, clean PHPStan level 6, clean ESLint, and 100% complete Croatian translations (749 translated strings, 0 untranslated).

= 2.0.4 =
* Public archive pagination: add pagination support, "showing X–Y of Z published price lists" count, and [sidrena-arhiva] paged parameter support to public price-list archive.
* Compliance findings pagination: paginate compliance report table (50 items per page) with full screen-reader navigation and accessible label.
* Date formatting: format sale periods, 30-day calculation explanation start dates, applicable reference dates, and recorded-for dates in WooCommerce product meta boxes using Croatian display format (d.m.Y.).
* Admin notice and value formatting: add dismissibility to publications failure notice and properly map string boolean values ('true'/'false') in legal settings change logs.
* In-dashboard documentation: document display presets (tooltip, minimal, tag, accent, subtle), date display modes, font weights, and statutory reference/footer developer hooks.
* Quality gates: 384 unit tests (1,393 assertions) green, PHPCS and PHPStan clean, 741 translated Croatian strings with 0 untranslated.

= 2.0.3 =
* Storefront presentation: introduce interactive tooltip preset with unobtrusive help trigger (?) and accessible explanation popup, customizable date display modes (tooltip, inline, subtext, hidden), font-weight controls (normal, semi-bold, bold), and new minimalist display presets (tooltip, minimal, tag, accent, subtle).
* Croatian-first wording: default 30-day statutory reference label set to "Najniža cijena u zadnjih 30 dana" with automatic backward-compatible migration for legacy English values, strictly distinguishing reference pricing from anchor pricing.
* Daily deadline input normalization: operations settings now accept single-digit hour and AM/PM notations (e.g. "8:00", "8:00 AM", "8:00 PM") and normalize cleanly to 24-hour HH:MM format.
* Public feed and archive robustness: add rewrite rule verification, fallback route parsing, and automatic rewrite flush during database setup.
* Quality gates and test suite: 382 unit tests (1,387 assertions) green, PHPCS and PHPStan clean, 736 translated Croatian strings with 0 untranslated.

= 2.0.2 =
* Centralize styles and scripts: extract inline style attributes into assets/css/admin.css, move bulk anchor selection and confirmation scripts to assets/js/admin.js, add busy/disabled state handling for long-running submit buttons, implement all status badge variant colors, fix low-contrast badge color for WCAG AA compliance, and enqueue admin assets on product edit screens.
* Public price list improvements: add catalog pagination (50 items per page), "showing X–Y of Z items" result count, clear-filters affordance, horizontal-scrolling responsive table wrapper, print media styling for legally mandated price-list printouts, translated XML/CSV feed links, and accessible aria-labels on archive download links.
* Formatting consistency: introduce Dates::format_datetime_display() across all admin screens for unified Croatian site-time display (d.m.Y. H:i), and unify amount formatting via Currency::format().
* Accessibility and i18n hygiene: add label_for across all settings screens, fix SaleTypesSettings label_for mismatch, add screen-reader legends to checkbox fieldsets, add aria-live variation updates, clean up duplicate description in locations, and complete Croatian translation catalog (714 translated, 0 untranslated).

= 2.0.1 =
* Security audit: add unit regression tests for CsvParser allowed upload and temp path validation.
* Security audit: add unit regression tests for WelcomeBanner post-activation redirect guards (missing capability, cron runs, AJAX requests, network admin).
* Quality gates verified clean: 370 unit tests (1,314 assertions), PHPCS clean (175 files), PHPStan level 6 (0 errors across 117 files), ESLint clean, and 0 untranslated strings in languages/hr_HR.po.

= 2.0.0 =
* Rename the plugin to Sidrena Cijena EU.
* Complete 10-task compliance remediation roadmap covering price-history reliability, WooCommerce promotional-state integration, variation-specific reference prices, customer-visible price locations, feed and promotional output coverage, Croatian customer wording, time/cache/sale-end behavior, administrator explainability, and a final regression pass.
* Fix the Styling tab so it opens the styling settings screen.
* Add a public HTML archive of generated price lists, with default-enabled footer and sitemap links and settings to control them.
* Harden optional uninstall cleanup and complete Croatian translations for the archive settings.
* 361 unit tests, 96 integration tests, 42 E2E specs — all green.

= 1.0.18 =
* Fix Legal rules saves that could cause a WordPress error page.
* Generate cjenici despite optional catalog findings; current price and dated anchor remain required.
* Add nine compact Sidrena cijena styles, live previews, and a size control.
* Restore the admin sidebar anchor icon and add an opt-in per-product shop-card display choice; product pages show the anchor by default.

= 1.0.17 =
* Remove personal identity fields from structured log context.
* Show each selected variation's own dated anchor beside its WooCommerce price.

= 1.0.16 =
* Fix WooCommerce product editor saves and move anchor/sale controls into a dedicated Product data tab.
* Add admin setup guidance, a prominent Sidrena cijena menu, and product display presets.

= 1.0.15 =
* Reduce price-history batch reads and batch storefront sale and catalog lookups.
* Add indexes for price history, active sales and publication lookups.

= 1.0.14 =
* Security: the anchor-price and special-sale meta boxes are now registered only for users holding `manage_sidrena_cijena`. They were the only admin screens that displayed privileged compliance evidence (the recorded anchor price, the applicable reference date and the 30-day lowest price) and offered a submission form without the capability check every other screen applies; the admin-post handlers themselves already verified capability and nonce.
* Audit: full static audit of the plugin. Every admin action pairs the capability check with a valid nonce, every echo is escaped, every query is parameterized, uploaded and imported paths are restricted to the uploads directory and the system temporary directory by `CsvParser::is_allowed_path()`, log contexts pass `Logger::sanitize_context()`, and the post-activation redirect keeps its bulk-activation, network-admin, cron, AJAX and REST guards.
* Tests: new regression coverage for the meta box capability gate, plus the first unit tests pinning the `manage_sidrena_cijena` contract (306 tests, 1083 assertions).

= 1.0.13 =
* Usability: internal slugs are no longer printed in the admin screens; statuses, log levels, log events, finding severities, sale types and import row statuses show translated labels from one shared resolver (301 tests, 1078 assertions).
* Tables: admin tables are captioned and paginate consistently; the operational log, the legal rule-change history and the import preview are capped and paginated, so a 5,000-row CSV no longer renders 5,000 table rows.
* Forms: validation errors keep the submitted values, mark the offending field (`aria-invalid`, `aria-describedby`) and move focus to the notice or the field; "Mark setup complete" now confirms the action and every notice is dismissible.

= 1.0.12 =
* Correctness: date-only values such as anchor reference dates no longer shift a day on sites west of UTC; the public price list and the price-history table render them in the Croatian `d.m.Y.` format (268 tests, 869 assertions).
* Dashboard: variable products that inherit a parent-level sale are no longer reported as having no price history, and an empty catalog skips the sale lookup.
* Accessibility: the applicable-categories field on the sales-location form now has a screen-reader label and well-formed markup.

= 1.0.11 =
* Robustness: trashing, deleting, or unpublishing a catalog item (standalone item, product, or variation) now flushes the cached price-list responses immediately instead of leaving the removed item visible until the cache expired.
* Usability: price history filters, table rows, the correction form, and the anchor meta box show translated labels (for example "Anchor price", "CSV import") instead of raw internal slugs.
* Tests: new unit coverage for catalog lifecycle cache invalidation, the compliance report (grouping, CSV quoting, meta derivation), the finding severity fallback, and history labels (253 tests, 807 assertions).

= 1.0.10 =
* Performance: the REST price endpoints (`/prices`, CSV and XML downloads) now serve canonical builds from the shared response cache instead of rebuilding the whole catalog on every request; invalidation triggers on price, anchor, sale, publication, location and settings changes are unchanged.
* Performance: the anchor price service shares the plugin anchor cache, so storefront lookups reuse a record until the next anchor invalidation or flush.
* Robustness: cache generations are monotonic, so two flushes inside the same second can no longer leave entries written between them visible.
* Tests: new unit coverage for cached REST builds (reuse, flush rebuild, per-location/type keys), anchor-cache wiring (shared lookups, recorded-item invalidation) and back-to-back flush behavior (231 tests, 722 assertions).

= 1.0.9 =
* Accessibility: every remaining admin table header (dashboard, compliance, publications, logs, audit, legal settings, documentation, import sources, sale history) now carries `scope="col"` so screen readers announce column context.
* Tests: new unit coverage for the anchor cache (get/set round trips, cached nulls, per-item and variation invalidation, generation flushes) and the special-sale service (FR-023 sale-type gate, date validation, not-found paths).
* Compatibility: static scan confirms no PHP 8-only syntax in `src/` (PHP 7.4 to 8.4 supported).

= 1.0.8 =
* Reliability: the daily 08:00 run no longer force-publishes duplicate price lists when a run happens just before the deadline, and service price changes queue the correct regeneration request.
* Special sales: the end date is inclusive (a sale is active until the end of its last day), one-day sales work, and promo prices entered with a comma decimal are stored correctly.
* 30-day lowest price: superseded prices no longer pin the lowest value forever, and stored timestamps are converted to site time before window comparison.
* Publication gate: locations with an incomplete street address or city block filename generation as documented.
* Import: re-importing the plugin's own CSV keeps the reference-date column, quoted multi-line fields parse correctly, and variation rows validate against the catalog.
* WooCommerce: price changes refresh the public feed, variation anchors respect the shop tax display settings, and builder-rendered prices no longer borrow the anchor of a different product.
* Admin: saving one settings tab no longer resets the flags of another, the uninstall data-deletion opt-in works, the bulk anchor screen paginates the items that actually lack anchors, and several labels and notices are translated and escaped.
* Rebuilt translation catalogs with 100% Croatian coverage (572 strings).

= 1.0.7 =
* Performance: batched anchor lookups in the bulk anchor setup screen and the price-list builder, eliminating N+1 queries on large catalogs.
* Fixed price-list metadata item counts for products and services.
* Cache invalidation now also runs when sales locations are saved, deactivated, or deleted.
* Setup Guide checklist badges show anchor coverage (recorded versus catalog total) and stay in sync with the current shop state.
* Accessibility: visible labels for the import file and public type filter, scoped admin table headers, ARIA labels on bulk controls, and higher-contrast disclaimers.
* Rebuilt translation catalogs with 100% Croatian coverage (569 strings).

= 1.0.6 =
* Hardened opt-in uninstaller options cleanup to purge REST cache generation, feed queue timestamps, and standalone terms flags.
* Documented datumSidreneCijene XML element in legal price-list format specification.
* Added comprehensive unit testing for Uninstaller lifecycle safeguards.
* Rebuilt translation catalogs with 100% complete Croatian coverage.

= 1.0.5 =
* Default Croatian language: plugin interface is now 100% in Croatian by default across all screens and sites.
* Bulk Anchor Setup: batch tool to set anchor prices for all products/variations in the shop from current catalog prices with a single click.
* Quick page selection: Master checkbox, "Select all on this page", and "Deselect all" controls for bulk anchor table.
* In-Dashboard Documentation: dedicated Croatian documentation screen (Dokumentacija) explaining legal rules, bulk tools, feeds, 08:00 cron automation, CLI commands, developer hooks, and compliance gates.
* Release packaging security: internal developer documentation, prompts, and temporary files excluded from distribution archives.

= 1.0.4 =
* Setup Guide enhancements: dynamic status badges across all five checklist steps, showing recorded anchor counts and compliance scan health.
* Welcome banner hardening: suppressed activation redirects during bulk plugin activation, network admin, cron, and REST requests.
* Agentards prompts refresh: comprehensive overhaul of all AI agent workflow prompts in AGENTARDS/ with up-to-date quality gates, documentation paths, and release policies.
* Cleaned up legacy specification and contract references throughout source code and documentation.
* Rebuilt translation catalogs with 100% complete Croatian translations (479/479 strings).

= 1.0.3 =
* First-run Setup Guide checklist covering locations, legal rules, anchor prices, price lists, and compliance check.
* Welcome banner with onboarding link and author free projects.
* Daily 08:00 deadline robustness: WordPress timezone update hooks, automatic realignment after daily runs preventing DST drift, working-day missed run detection, automatic catch-up generation throttled by transient lock, failure recording and dismissible admin notices.
* Failure visibility across Price lists screen, Compliance dashboard, and WP-CLI status.
* Performance: public price-list page caches rendered tables and refreshes when prices, settings, sales or publications change.
* GitHub Actions CI workflow covering static gates and wp-env integration suite with MySQL.

= 1.0.2 =
* Capture witnessed first offers automatically, preserving product/variation identity and historical-import exclusions.
* Publish an installable archive and checksum for every version, including patches.

= 1.0.1 =
* Document the retained filename profile and differences from the ministry example.

= 1.0.0 =
* First public release: audited anchor records, classified-sale history, daily CSV/XML publication, public price-list and archive pages, imports and WooCommerce display compatibility.
* Preserves missing-anchor findings instead of inferring historical prices from the current catalog.

= 0.1.0 =
* First packaged beta: anchor price records and display, 30-day lowest price, price-list generation and archive, public REST access, validator and dashboard, CSV import, locations, price history, logs and WP-CLI commands.
