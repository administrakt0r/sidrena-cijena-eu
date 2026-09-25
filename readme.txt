=== Sidrena cijena - Croatian Anchor Price Compliance ===
Contributors: administrakt0r
Tags: croatia, price, compliance, woocommerce, price list
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.3
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
