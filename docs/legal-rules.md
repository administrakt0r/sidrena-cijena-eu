# Legal rules implemented by this plugin

> **Disclaimer:** this plugin is a technical aid for publishing and tracking
> prices. It is **not legal advice**. The merchant is responsible for
> verifying their obligations with the competent authorities and their legal
> counsel. Where the law is ambiguous, the plugin makes the assumption
> configurable and documents it below instead of inventing a rule.

## Primary sources

* **NN 101/2026-1212** — [*Odluka o isticanju dodatne cijene kao mjera izravne kontrole cijena*](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html). Defines the anchor ("sidrena") price and duty to display it with the retail price at the point of sale/service and in advertising, including online advertising.
* **NN 101/2026-1213** — [*Odluka o objavi cjenika proizvoda i usluga kao mjera izravne kontrole cijena*](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html). Defines machine-readable `.csv`/`.xml` lists, content, filename, 30-day availability, and real-time automated retrieval.
* [Ministry of Economy clarification (22 September 2026)](https://mingo.gov.hr/vijesti/pojasnjenja-za-primjenu-dodatne-cijene-i-objavu-cjenika-od-1-listopada/10440), including the linked explanatory letter, clarifies new listings, website scope and location-specific lists.
* Consumer-protection rules on the **lowest price in the last 30 days**
  for special forms of sale (akcija, sniženje, sezonsko sniženje,
  rasprodaja and equivalents).
* **NN 117/22** — *Pravilnik* on clear and unambiguous price display.

## The anchor price (sidrena cijena)

The anchor is the retail price that was valid on the legal **reference
date**, **excluding** any special-sale discount. It is stored as immutable
historical data together with the reference date, source, original price,
currency, tax status, product/variation identity, timestamp, creator and —
for corrections — the reason. It is never silently overwritten; a
correction creates a new record linked to the original.

* **General reference date:** 10 September 2026 (`2026-09-10`).
* **Legacy reference date:** 2 May 2025 (`2025-05-02`) for the legacy categories carried over from the 2025
  regime: food, beverages, cosmetics, cleaning products, toiletries and
  household products.
* **2026 expansion:** from 1 October 2026 the duty covers all B2C
  retailers and service providers, not only the legacy categories.
* **Rule version:** `2026-10-01`, stored on every publication and
  changeable only through the audited legal-settings screen
  (`Settings → Legal rules`). Every change is logged with who, when and
  the reason, and never rewrites history.

For products/services first offered after the applicable fixed reference date, the anchor is the price applied when first offered; the first-applied date must be clearly stated. The plugin requires the merchant to enter and verify this evidence instead of inferring a historical price from a current catalog price. That date is rendered alongside the anchor and included in CSV/XML exports. The display label defaults to **"Sidrena cijena"** and is configurable. The plugin never renders "was / regular / original" wording unless the merchant configures it.

## The 30-day lowest price is a different concept

The lowest applicable price during the **30 calendar days immediately
before a special sale starts** (start date excluded, the day exactly 30
days before included, site local time) is computed by a separate engine,
stored with its exact calculation window and displayed with its own
label. It is **never substituted** for the anchor price and vice versa.
An unclassified promotion (sale price without a chosen sale type) is
reported as a compliance finding and never silently treated as a special
sale.

## Online price list (cjenik)

* One combined list per sales location and format (products first, then
  services, column `vrsta` distinguishes them).
* Products: column order `vrsta, naziv, sifra, marka, jedinica_mjere,
  cijena_za_jedinicu_mjere, maloprodajna_cijena,
  poseban_oblik_prodaje, naziv_posebnog_oblika_prodaje, sidrena_cijena,
  datum_sidrene_cijene, barkod, dostupnost`. The published *maloprodajna cijena* is the
  **effective** price the consumer pays today (promotional price during a
  sale), shown together with the sale indicator/name and the anchor.
* Services: `naziv usluge, maloprodajna cijena`, sale status/name and the
  anchor price.
* Regeneration: products once per working day ahead of the configured
  deadline (default `08:00`, with a 15-minute generation buffer); service
  price changes queue regeneration immediately. Manual
  **Generate now** is always available. The scheduled run creates a dated publication even when the list
  contents have not changed; previous versions remain available in the archive.
* Filename: `{FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT}`
  (e.g. `PRODAVAONICA_VLASKA68_ZAGREB_P1_01_01102026_0750.CSV`). Only the
  separator (`_`/`-`) and letter case are configurable. Each active location
  needs a street address and city; generation is blocked when either is absent.
* Published versions stay available for at least 30 days; cleanup deletes
  files only after `expires_at` and keeps the archive rows.
* Public machine-readable access: `/wp-json/sidrena-cijena/v1/prices`
  (JSON, plus `.csv`/`.xml` variants), no login, price data only.

## Documented uncertainties

* The official XML schema is not prescribed; the plugin uses the
  deterministic schema in `docs/price-list-format.md` (versioned root
  element, rule version, generation timestamp).
* Decision 1213 applies to all retailers and service providers with a website, including informational/presentation sites (not just e-shops); social profiles alone do not count. Merchants must configure every physical location and a separate webshop location/file where applicable. The plugin cannot infer the merchant's complete location inventory from WooCommerce.
* The CSV decimal convention is not prescribed; the plugin uses `;`
  delimiters with comma decimals (Croatian convention), documented in
  `docs/price-list-format.md`.
* New items require the merchant to record the price applied on first offer and the first-offer date from business records. The catalog creation date is only a suggestion, not proof. The plugin blocks publication when an anchor is missing, and for post-reference items it accepts a verified first-offer date on or after the statutory baseline; the law does not permit publishing a blank anchor for these items.

The publication gate requires a positive current retail price and a dated
anchor for each item. Missing catalog identifiers such as SKU or barcode and
other compliance findings remain visible in the validator but do not prevent
file generation. A location still needs the components required to form its
publication filename. Merchants must review the full compliance report before
using a generated cjenik.
