=== Sidrena cijena - Croatian Anchor Price Compliance ===
Contributors: administrakt0r
Tags: croatia, price, compliance, woocommerce, price list
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.11
License: GPLv2 or later
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

1. Upload the `sidrena-cijena` folder to `wp-content/plugins/`.
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
imports require evidenced historical prices. See docs/feature-acceptance.md for
the first-offer boundaries, sale classification and setup requirements.

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

1. Public price-list page with search, item types and downloads (local demo data).
2. Administrative compliance dashboard (local demo data).

== Changelog ==

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
* Agentard prompts refresh: comprehensive overhaul of all AI agent workflow prompts in AGENTARD/ with up-to-date quality gates, documentation paths, and release policies.
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
