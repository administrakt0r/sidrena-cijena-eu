=== Sidrena cijena - Croatian Anchor Price Compliance ===
Contributors: sidrenacijena
Tags: croatia, price, compliance, woocommerce, price list
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Records, displays, validates and publishes the Croatian anchor price, the daily machine-readable price list and the 30-day lowest price for special forms of sale.

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
* Daily CSV and XML price-list generation per sales location, with legally structured filenames and 30-day retention.
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
pravila*, and every change is recorded in the audit trail.

= Does the plugin invent historical prices? =

Never. Items introduced after the reference date receive no anchor price and
are reported for merchant attention instead.

== Changelog ==

= 1.0.0 =
* First public release: audited anchor records, classified-sale history, daily CSV/XML publication, public price-list and archive pages, imports and WooCommerce display compatibility.
* Preserves missing-anchor findings instead of inferring historical prices from the current catalog.

= 0.1.0 =
* First packaged beta: anchor price records and display, 30-day lowest price, price-list generation and archive, public REST access, validator and dashboard, CSV import, locations, price history, logs and WP-CLI commands.
