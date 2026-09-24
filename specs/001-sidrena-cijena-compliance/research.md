# Phase 0 Research: Croatian Anchor Price ("Sidrena cijena") Compliance System

**Feature**: `001-sidrena-cijena-compliance` | **Date**: 2026-09-21

This document resolves the technical unknowns and records the decisions that shape the design. Legal findings are based on publicly available primary and authoritative sources consulted on 2026-09-21 (see `docs/legal-rules.md` in the implementation phase).

## R1. Legal sources and obligations

**Decision**: Implement the following obligations as separate, configurable rule sets:

1. **Anchor/additional price** — Odluka o isticanju dodatne cijene kao mjera izravne kontrole cijena (NN 101/2026, no. 1212), effective 1 October 2026. Reference price for most items: the price valid on **10 September 2026** that did not include a special-sale discount. For the legacy categories (food, beverages, cosmetics, cleaning products, toiletries, household products) already covered by the 2025 regime: the price valid on **2 May 2025**. Applies to all B2C retailers and service providers and must accompany advertised prices, including on websites.
2. **Online price list** — Odluka o objavi cjenika proizvoda i usluga kao mjera izravne kontrole cijena (NN 101/2026, no. 1213), effective 1 October 2026. Products: updated every working day, no later than **08:00**; services: on every price change, no later than 08:00 on the day of publication; published versions must remain available **30 days**; formats **.csv or .xml**, suitable for automatic processing; filenames must include facility form, address, facility identifier, storage/sequence number and a submission timestamp; a chain's file has a single structure for all sales locations; automated retrieval must be enabled.
3. **30-day lowest price** — Croatian consumer-protection rules for special forms of sale (lowest applicable price of the previous 30 days). Kept strictly separate from the anchor price.

**Rationale**: The two Odluke are separate legal instruments with different scopes, update rhythms and penalties; the specification requires them to be modelled separately. Parent law: Zakon o iznimnim mjerama kontrole cijena (NN 40/2025). Display rules: Pravilnik o načinu isticanja maloprodajne cijene i cijene za jedinicu mjere proizvoda (NN 117/22).

**Alternatives considered**: Treating the anchor price and the 30-day price as one field (rejected — explicitly forbidden by the spec and legally wrong); hard-coding the reference dates (rejected — spec requires configurability and auditability).

**Documented uncertainties** (kept configurable, never invented):
- The Odluke do not prescribe an XML schema; the plugin defines a documented, versioned schema (`contracts/xml-format.md`).
- The Ministry clarification (22 September 2026) confirms the cjenik duty applies to traders and service providers with established websites, including informational/presentation sites; social profiles alone are excluded. Each physical facility needs its own file, with an additional file for the webshop.
- Products/services first offered after the applicable fixed baseline use their first-applied price as the anchor, with the first-offer date clearly shown. The merchant must evidence the historical price/date; current catalog data is not proof.
- The exact CSV delimiter/decimal convention is not prescribed; the plugin fixes and documents a Croatian convention (semicolon + comma) and documents it.

## R2. Runtime stack and compatibility floor

**Decision**: Support **PHP 7.4+** and **WordPress 6.8+**; develop and test primarily on PHP 8.3 and the latest stable WordPress (7.1 at planning time). Write PHP 7.4-compatible syntax only (no union types, `match`, promoted constructor properties, named arguments, enums, readonly). Use PHP 7.4 typed properties and namespaces.

**Rationale**: WordPress 7.x still supports PHP 7.4 (EOL but backward-compatible) while recommending 8.3+; WooCommerce's floor is also 7.4. Supporting 7.4 maximizes reach for Croatian hosting environments without blocking modern PHP. Testing on 8.3 matches the current WordPress recommendation.

**Alternatives considered**: Requiring PHP 8.1+ (rejected — needless exclusion of legacy Croatian hosting); PHP 7.2 floor (rejected — WordPress/WooCommerce require 7.4+).

## R3. Plugin architecture, autoloading and dependency policy

**Decision**: Single plugin, PSR-4 namespace `SidrenaCijena\` mapped to `src/`, served by a small custom autoloader shipped in the plugin. Composer is used for development tooling only; no runtime Composer dependencies and no `vendor/` requirement in production. Bootstrap composes services in a lightweight container (`src/Plugin.php`) and registers hooks.

**Rationale**: Avoids shipping a `vendor/` directory and avoids version conflicts with other plugins; keeps activation fast and predictable; satisfies the brief's "no unnecessary frameworks" and "use WordPress APIs" constraints.

**Alternatives considered**: Composer runtime autoload with `vendor/` (rejected — deployment friction, conflict risk); a DI framework (rejected — unnecessary weight).

## R4. Database strategy and migrations

**Decision**: Five custom tables created with `dbDelta()` through an explicit versioned migration runner (`src/Database/Migrations`), keyed by a `sidrena_cijena_db_version` option. Price history is append-only; corrections are new rows referencing the original. All queries use `$wpdb->prepare()` with table names from `$wpdb->prefix`.

**Rationale**: The brief requires a dedicated history table with indexes and explicit schema versions; `dbDelta` is the WordPress-native migration mechanism; append-only rows protect auditability.

**Alternatives considered**: Post meta only (rejected — poor query performance, weak history semantics); a custom ORM (rejected — unnecessary); Doctrine migrations (rejected — runtime dependency).

**Tables**: `{prefix}sidrena_price_history`, `{prefix}sidrena_special_sales`, `{prefix}sidrena_locations`, `{prefix}sidrena_publications`, `{prefix}sidrena_logs` (details in `data-model.md`).

## R5. Catalog abstraction (WooCommerce optional)

**Decision**: Define `CatalogProvider` and `CatalogItem` interfaces in `src/Catalog/`. Provide:
- `WooCommerceCatalogProvider` (`src/WooCommerce/`) — active only when WooCommerce is detected; reads products/variations through the WooCommerce CRUD API (`wc_get_product`, `wc_get_products`), never through direct table access.
- `StandaloneCatalogProvider` (`src/Catalog/`) — backed by a private custom post type `sidrena_item` with taxonomies (`sidrena_brand`, `sidrena_item_type` = product/service) and post meta (SKU, barcode, unit, unit price, availability, regular price, sale price, first-seen date).

Price history, anchor prices, validation and exports operate on catalog-item identifiers, so both sources use the same compliance core.

**Rationale**: Meets FR-084/091 and the brief's "core independent of WooCommerce" requirement while keeping WooCommerce integration API-based (FR-085). CPT is the idiomatic WordPress way to get admin UI, import and search for the standalone catalog.

**Alternatives considered**: WooCommerce-only (rejected by the user clarification); a custom catalog table (rejected — loses WP admin integration for little gain at the target scale); mirroring WooCommerce products into plugin tables (rejected — duplication and sync risk).

## R6. Price-change capture and history population

**Decision**: Capture price changes through WooCommerce APIs and hooks:
- `woocommerce_before_product_object_save` (snapshot previous prices) and `woocommerce_after_product_object_save` (compare and append history rows for `regular_price`, `sale_price`, and effective price).
- `woocommerce_product_object_updated_props` for prop-level detection.
- `woocommerce_admin_process_variation_object` and variation save hooks for variations.
- A daily reconciliation job that samples current prices against the last stored record and appends `historical` rows for missed changes (guards against direct database edits and third-party imports).
- The standalone catalog uses `save_post_sidrena_item` with the same comparison logic.

Price changes never overwrite or delete history; they append rows.

**Rationale**: The save hooks are the documented WooCommerce extension points; the reconciliation job covers changes made outside hooks (bulk edits, imports, direct SQL), which is a real-world failure mode.

**Alternatives considered**: `updated_post_meta` on `_price` (rejected — fires repeatedly during a save, noisy); pure cron polling (rejected — would miss intraday sale windows and duplicate work).

## R7. Reference-date resolution and anchor records

**Decision**: A `ReferenceDateResolver` returns the applicable reference date per item: the legacy date (default 2025-05-02) when the item's category is mapped to the legacy treatment and legacy treatment is enabled, otherwise the general date (default 2026-09-10). Category mapping is administrator-configured and audited. Anchor records are created by (a) manual entry/bulk entry, (b) CSV import, (c) optional migration from detected price-history sources, and (d) an assisted onboarding flow that pre-fills the item's current regular price as a *suggestion* (clearly marked as unverified) rather than committing it silently.

**Rationale**: Satisfies FR-001/002/090 and the brief's "do not blindly assume today's price is the historical anchor price".

**Alternatives considered**: Automatic capture of the current price as the anchor (rejected — legally unsafe); deriving the anchor from the 30-day engine (rejected — different legal concept).

## R8. 30-day lowest-price engine

**Decision**: Pure domain calculator that, given the price timeline (regular, sale and historical records with validity intervals) and a sale start date, computes the lowest applicable price over the window: the 30 calendar days immediately before the sale start date, excluding the start date, including the day exactly 30 days before it, evaluated in the site time zone. Results are stored with the window boundaries and recomputed when the underlying history changes; consecutive/overlapping sales use the same window rule.

**Rationale**: Implements the clarified FR-019 deterministically and testably. Calendar-day arithmetic is auditable and matches how "the last 30 days" is applied in practice.

**Alternatives considered**: 720-hour rolling window (rejected by clarification); including the sale start day (rejected — would admit the discounted price).

## R9. Scheduling and generation reliability

**Decision**: A `Scheduler` abstraction with three triggers: WP-Cron (daily event scheduled before the configured deadline, default 08:00 local time), queued single events for service price changes, and manual/WP-CLI execution. WP-Cron is treated as unreliable by design; the admin documentation explains configuring a real system cron (`wp cron event run --due-now`) and the WP-CLI command supports the same operations. If Action Scheduler (bundled with WooCommerce) is available, it is used for queued service regeneration; otherwise a WP-Cron single event is used. Generation is idempotent: a content fingerprint (checksum of the canonical data + rule version + location) prevents duplicate publications while guaranteeing the current required price list exists.

**Rationale**: Satisfies FR-041/042/044 and the brief's explicit warning that WP-Cron alone is insufficient.

**Alternatives considered**: WP-Cron only (rejected); hard dependency on Action Scheduler (rejected — must work without WooCommerce); real-time regeneration for all items (rejected by clarification Q3).

## R10. Export formats

**Decision**:
- **CSV**: UTF-8 without BOM, `;` delimiter, `,` decimal separator, `.`-free numbers for non-decimal fields, LF line endings, stable header row and column order, RFC 4180-style quoting when a field contains delimiter/quote/newline, no HTML, no serialization, no hidden metadata. Column set and semantics in `contracts/csv-format.md`.
- **XML**: UTF-8, deterministic element order, escaped values, root element carrying a format version and rule version; Croatian field names transliterated to ASCII (for example `naziv`, `sifra`, `maloprodajnaCijena`, `sidrenaCijena`, `dostupnost`). Schema documented in `contracts/xml-format.md`.
- Both formats are always produced (FR-032); each can be downloaded on demand.
- **One combined price list per location/format**, containing both product and service rows distinguished by a `vrsta` column. The Odluka prescribes different field sets for products and services, and the prescribed filename components do not include an item type; a combined file avoids filename collisions and preserves the prescribed filename structure. Required per-type fields are validated independently.
- Writes are streamed (open handle, write row by row) so a 10,000-item catalog does not exhaust memory.

**Rationale**: Determinism and documentation are the legal interoperability requirement; the Croatian convention (semicolon/comma) matches local tooling and Excel. A fixed, documented format avoids the "incompatible proprietary format" risk.

**Alternatives considered**: Comma delimiter with period decimals (rejected as default — Croatian convention preferred; documented as an explicit choice); separate product/service files (rejected — filename collision or a non-prescribed type segment in the name); one format only (rejected — spec requires both); adding a proprietary column set (rejected).

## R11. Filename generation

**Decision**: A dedicated `FilenameGenerator` builds names from fixed components in fixed order:

`{FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT}`

with defaults: underscore separator and uppercase (both configurable, per clarification Q5). Address normalization transliterates Croatian diacritics (Č→C, Ć→C, Đ→D, Š→S, Ž→Z), removes spaces and unsafe filesystem characters, and preserves identifying content. The sequence number is a per-location counter incremented per generation ("broj pohrane"). The timestamp is the submission time in site local time. Names are validated before use; arbitrary filenames are rejected.

**Rationale**: Matches the Odluke's component list and the brief's example structure without hard-coding the example; deterministic and testable.

**Alternatives considered**: Fully fixed format (rejected by clarification Q5); free-form admin patterns (rejected — validation risk).

## R12. File storage, public exposure and retention

**Decision**: Files are written under `wp-content/uploads/sidrena-cijena/{location-slug}/` with an `index.html` guard and no directory listing; each file is recorded in `{prefix}sidrena_publications` with checksum, size, format, location, rule version, status and timestamps. Public access is by direct URL (no authentication), plus a machine-readable publications index endpoint. The cleanup job deletes files only after the retention period (default 30 days, minimum 30) while keeping archive records for audit. Paths are generated internally and validated against the base directory to prevent traversal; no user-supplied paths are used.

**Rationale**: Direct URLs satisfy "available without login" and keep caching simple; the archive table provides the audit trail the brief requires.

**Alternatives considered**: Serving files through a PHP route (rejected — slower, more fragile, no caching benefit); a protected directory (rejected — the files must be public); storing files in the database (rejected).

## R13. Public machine-readable access (REST)

**Decision**: REST namespace `sidrena-cijena/v1`:
- `GET /prices` — paginated JSON (current price, anchor price, identifiers, SKU, barcode, availability, sale status).
- `GET /prices.csv`, `GET /prices.xml` — current price list downloads.
- `GET /publications` — machine-readable index of retained published versions.
Public read access with no authentication; pagination (`page`, `per_page` capped), `Last-Modified`/`ETag` headers, transient/object-cache caching with invalidation on price change, and basic per-IP throttling. Management routes (settings, generation, imports) require the plugin capability. No customer data is ever exposed. Contract in `contracts/rest-api.md`.

**Rationale**: Implements FR-053–057; unauthenticated access is required for automated retrieval; caching and pagination address FR-054 and the performance goals.

**Alternatives considered**: Authenticated endpoint (rejected by spec default); a custom rewrite endpoint instead of REST (rejected — REST is the WordPress standard and testable).

## R14. Security model

**Decision**: One capability (`manage_sidrena_cijena`) for all compliance operations (per clarification Q2), granted to administrators by default and assignable to other roles; nonce verification on every admin action; sanitization on input and escaping on output; `$wpdb->prepare()` for all dynamic SQL; internal-only file paths; REST permission callbacks explicit on every route; no secrets in code or logs; uninstall data deletion gated behind an explicit option plus confirmation. Public endpoints expose price data only.

**Rationale**: Implements FR-071/078–080 and the WordPress security standards.

**Alternatives considered**: Two-tier capability model (rejected by clarification Q2); reusing `manage_options` (rejected — too broad and not assignable).

## R15. Internationalization

**Decision**: English source strings in code; text domain `sidrena-cijena`; ship `languages/sidrena-cijena.pot`, a complete `hr_HR` translation and an `en_US` catalogue. The default anchor label is the translatable string "Sidrena cijena". No Croatian strings hard-coded in business logic.

**Rationale**: FR-072/086 and WordPress i18n standards; Croatian is delivered as a translation rather than source strings.

**Alternatives considered**: Croatian source strings with English translation (rejected — violates WordPress convention and the brief's "do not hard-code Croatian strings").

## R16. Testing strategy

**Decision**:
- **Unit (PHPUnit, no WordPress bootstrap)**: reference-date resolution, legacy mapping, price calculations and rounding, 30-day engine (including exact-boundary, DST and consecutive-sale cases), sale classification, filename generation and normalization, CSV/XML writers, validator rules, currency handling.
- **Integration (PHPUnit + WordPress test suite in wp-env)**: activation/deactivation, migrations, repositories, WooCommerce product and variation integration, settings, import preview/rollback, REST routes, scheduled events, cleanup, uninstall policy.
- **E2E (Playwright + wp-env)**: activation, product editing, anchor-price display on page/archive, admin settings, dashboard, manual generation, CSV/XML URLs, REST endpoint, and WooCommerce checkout/product regression.
- **Fixtures**: WP-CLI seeding command plus `tests/fixtures/` definitions for the required scenarios (normal, sale, no SKU, barcode present/absent, variable, multiple variations, legacy category, introduced after reference date, frequently re-priced, consecutive promotions, out-of-stock, unit pricing, missing anchor, price above/equal/below anchor).
- **Static analysis**: PHPCS with WordPress and PHPCompatibilityWP rulesets, PHPStan (level 6+ with `phpstan-wordpress`), plus syntax lint.

**Rationale**: Matches FR-089 and the brief's required test matrix, and uses the documented WordPress wp-env + Playwright tooling.

**Alternatives considered**: Codeception (rejected — WordPress ecosystem standard is PHPUnit + wp-env); Cypress (rejected — WordPress documents Playwright).

## R17. Development environment

**Decision**: `@wordpress/env` as a local dev dependency; `.wp-env.json` mounts the plugin (`.`) and installs WooCommerce, uses PHP 8.3 and the latest stable WordPress core; a separate `.wp-env.test.json` provides an isolated test environment via the documented `--config` pattern (the `testsEnvironment` option is deprecated). npm scripts wrap `wp-env start/stop/clean`, PHPUnit, PHPCS, PHPStan and Playwright. Development credentials are documented as development-only.

**Rationale**: Meets the brief's wp-env requirements and current wp-env documentation (including the deprecation of `testsEnvironment`).

**Alternatives considered**: Docker Compose by hand (rejected — wp-env is the official tooling); a global wp-env install (rejected — project-local dependency required).

## R18. Logging, privacy and lifecycle

**Decision**: Structured logs in `{prefix}sidrena_logs` with levels, event codes and JSON context; admin viewer with filters; no secrets or personal data; log retention configurable (default 180 days) cleaned by the same scheduled job. No customer personal data is collected; no privacy-policy integration is required. Deactivation preserves everything; `uninstall.php` deletes plugin data only when the explicit option is enabled, and never touches WooCommerce or WordPress data.

**Rationale**: FR-074–077/081 and the brief's privacy and uninstall requirements.

**Alternatives considered**: File-based logs (rejected — harder to view and rotate safely); deleting data on uninstall by default (rejected).

## R19. Repository and version control

**Decision**: The implementation phase must initialize a Git repository at the plugin root (the directory is not currently a Git repository) and follow the brief's atomic-commit workflow. Generated artifacts (exports, uploads) and dev dependencies (`node_modules/`, `vendor/`) are ignored; `languages/*.mo` and fixtures are committed.

**Rationale**: The brief mandates version control and atomic commits; no repository exists yet.

**Alternatives considered**: Deferring version control (rejected — violates the brief).
