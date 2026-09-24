# Feature Specification: Croatian Anchor Price ("Sidrena cijena") Compliance System

**Feature Branch**: `001-sidrena-cijena-compliance`

**Created**: 2026-09-21

**Status**: Draft

**Input**: User description: "Build a production-ready WordPress plugin for Croatian 'Sidrena cijena' compliance — a compliance-oriented pricing history, display, export, validation and audit system covering the additional/anchor price requirement (NN 101/2026-1212), the online price-list publication requirement (NN 101/2026-1213), the separate 30-day lowest-price rule for special forms of sale, multi-location price-list generation, archival retention, machine-readable public access, validation, import/export, WP-CLI tooling, security, privacy, performance, i18n, tests and documentation." (Extended brief: 43 sections; full text retained in the originating request.)

## Clarifications

### Session 2026-09-21

- Q: When an item is currently in a special sale, which price must be published as "maloprodajna cijena" in the price list? → A: The effective retail price consumers pay today (the sale price), with the special-sale indicator and name marking the promotion and the anchor price alongside.
- Q: Which users should be allowed to perform the different compliance operations in the admin? → A: One tier — any user granted the store-management capability can perform every compliance action, including legal reference-date and rule settings.
- Q: When a price changes, when must the system regenerate and republish the price list? → A: Products once per working day by 08:00; services queued immediately on every price change and published no later than 08:00 on the day of the change; manual generation always available.
- Q: How should the 30-day window for the lowest previous price be measured around a sale's start? → A: The 30 calendar days immediately before the sale start date, excluding the sale start date and including the day exactly 30 days before it, evaluated in the site's local time zone.
- Q: How much control should the merchant have over the generated price-list filename structure? → A: Required components and their order are fixed; only the separator character and letter case are configurable, with documented defaults (underscore separator, uppercase).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Record and display the anchor price ("Sidrena cijena") (Priority: P1)

A Croatian retailer or service provider must, from 1 October 2026, show an additional reference price next to the current retail price for every product and service offered to consumers. The merchant records, for each product/service (and each variation where applicable), the retail price that was valid on the applicable reference date and that did not include any special-sale discount. The system then displays the current price and the anchor price together — clearly, visibly and unambiguously — on product pages, archives and promotional surfaces of the merchant's website.

**Why this priority**: This is the core legal obligation from 1 October 2026 (Odluka NN 101/2026-1212) and the foundation on which the price list and validation features depend. Without reliable anchor-price records there is no compliance value at all.

**Independent Test**: Configure a reference date, create anchor records for a handful of products (simple, variable, with and without sale price), enable frontend display, and verify that each product page and archive item shows the current price with the correct anchor price and the configured Croatian label. Verify that a product with no anchor record shows a plain-language warning to administrators but does not break the storefront.

**Acceptance Scenarios**:

1. **Given** a product with a current price of €79.99 and a recorded anchor price of €99.99, **When** a visitor opens the product page, **Then** both prices are shown with the current price and the anchor price clearly distinguished and the default label "Sidrena cijena".
2. **Given** a variable product whose variations have different anchor prices, **When** a visitor selects a variation, **Then** the anchor price shown corresponds to the selected variation.
3. **Given** a product in a category mapped to the legacy treatment, **When** the anchor price is resolved, **Then** the price valid on the legacy reference date (default 2 May 2025) is used instead of the general reference date (default 10 September 2026).
4. **Given** a merchant who has not enabled anchor-price display, **When** a visitor browses the site, **Then** no anchor-price markup is rendered and the storefront behaves exactly as before.
5. **Given** an administrator corrects an anchor price, **When** the correction is saved, **Then** the original record remains retrievable and a new correction record is created with author, timestamp and reason.

---

### User Story 2 - Publish the daily machine-readable price list (Priority: P2)

A merchant with a website must publish the current price list of products and services in a machine-readable format (CSV or XML), updated every working day no later than 08:00 local time (and on every service price change), with each published version remaining publicly available for at least 30 days. The system generates the files, names them according to the legally required structure, validates them, publishes them, archives them and exposes them for automated retrieval.

**Why this priority**: This is the second mandatory obligation from 1 October 2026 (Odluka NN 101/2026-1213) and carries its own penalties. It is independent of the on-site display feature and can be delivered and tested on its own.

**Independent Test**: With anchor prices recorded for a sample catalog, trigger generation manually, by schedule and by command line; verify the produced CSV and XML files, their filenames, the archive entries, their public URLs, the 30-day retention behaviour, and that the file content matches the current catalog data.

**Acceptance Scenarios**:

1. **Given** an active sales location with a configured identifier and a product catalog, **When** the daily generation runs, **Then** a CSV and an XML price list are produced, validated and published with filenames containing facility form, address, facility identifier, storage/sequence number and a submission timestamp.
2. **Given** a generated price list, **When** it is inspected, **Then** every required product field (name, code, brand, unit of measure where applicable, unit price where applicable, retail price, special-sale indicator and name, anchor price, barcode, availability) is present, and every required service field (service name, retail price, special-sale indicator and name, anchor price) is present.
3. **Given** a published file, **When** 29 days pass, **Then** the file is still publicly retrievable; **When** the retention period elapses, **Then** it may be removed by the cleanup process and not before.
4. **Given** no catalog change since the previous working day, **When** the daily generation runs, **Then** the system does not produce a duplicate file if the existing published file still satisfies the requirement, but the current required price list is always available.
5. **Given** generation fails validation, **When** the run completes, **Then** the failure is recorded with its findings, the previous valid publication remains available, and administrators are warned.

---

### User Story 3 - Calculate the lowest price of the previous 30 days for special sales (Priority: P3)

When a merchant places a product or service into a special form of sale (akcija, sniženje, sezonsko sniženje, rasprodaja, other), consumer-protection rules require the lowest applicable price of the previous 30 days to be shown. The merchant classifies the special sale, the system calculates and stores the lowest applicable price for the preceding 30-day period and displays it separately from the anchor price.

**Why this priority**: Legally distinct from the anchor price and independently valuable, but not the newest obligation; it is often already required today and must never be confused with the anchor price.

**Independent Test**: Create price-change and sale histories for sample products, start a special sale, and verify the calculated 30-day lowest price, its calculation period, its display and its separation from the anchor price, including boundary cases at exactly 30 days.

**Acceptance Scenarios**:

1. **Given** a product with regular price changes and a previous sale within the last 30 days, **When** a new special sale starts, **Then** the displayed lowest-price-of-30-days equals the lowest applicable price in the exact window of 30 calendar days immediately before the sale start date (start date excluded).
2. **Given** a product whose sale started exactly 30 days after a previous promotion ended, **When** the calculation runs, **Then** the boundary is handled deterministically and documented.
3. **Given** consecutive or overlapping promotions, **When** the lowest price is calculated, **Then** repeated sales do not reset or corrupt the calculation window.
4. **Given** a special sale with no type selected, **When** the compliance check runs, **Then** a finding is reported and the sale cannot silently be treated as a classified special form of sale.
5. **Given** an anchor price and a 30-day lowest price for the same product, **When** the storefront displays both, **Then** each is labelled distinctly and neither substitutes for the other.

---

### User Story 4 - Run a compliance check and see a dashboard (Priority: P4)

The merchant opens the compliance dashboard to see how much of the catalog has valid anchor prices, what is missing or needs verification, how current prices compare to anchor prices, which items have 30-day pricing issues, whether the latest price list was published successfully, and which validation findings require action. The merchant can run a full compliance check on demand and receive a detailed report with severity levels.

**Why this priority**: Without a validation layer the merchant cannot know whether the technical obligations are being met. It depends on the anchor and price-list features but is separable from them.

**Independent Test**: Seed a catalog containing each known problem type, run the compliance check, and verify every finding is reported with the correct severity and a plain-language Croatian explanation.

**Acceptance Scenarios**:

1. **Given** a catalog with missing anchor prices, missing SKUs, malformed data, an invalid filename, a stale price list and a missing 30-day price, **When** the merchant runs the compliance check, **Then** each issue is listed with severity (ERROR, WARNING, INFO), the affected item and a clear explanation.
2. **Given** the dashboard, **When** it loads, **Then** it shows counts of valid/missing/needs-verification anchors, current-vs-anchor comparison, 30-day warnings, export status, last successful scheduled export, next export and validation errors.
3. **Given** no issues, **When** the check runs, **Then** it reports a clean result without implying a legal guarantee.

---

### User Story 5 - Onboard historical anchor data safely (Priority: P5)

Before the obligations take effect the merchant must establish the historical anchor prices for the whole catalog. The system provides a guided onboarding process: detect existing historical data where available, import anchor data from a file with preview, validation, dry run, rollback and an error report, and enter data manually in bulk. Items whose reference price cannot be reliably reconstructed are clearly flagged instead of being guessed.

**Why this priority**: Anchor data must exist before display and publication are meaningful; a safe import path is essential for real catalogs. It is independent of display and export.

**Independent Test**: Import a file containing valid rows, invalid rows, duplicate rows and rows for non-existent products; verify the preview, the error report, that a dry run changes nothing, and that a failed import leaves the database unchanged.

**Acceptance Scenarios**:

1. **Given** an import file with SKU/product ID, reference date, anchor price, category and optional variation ID, **When** the merchant uploads it, **Then** a validated preview is shown before anything is committed.
2. **Given** the merchant confirms the import, **When** the import runs, **Then** all valid rows are committed atomically and no partial data is left behind if any unrecoverable failure occurs.
3. **Given** rows that would overwrite existing anchor records, **When** the import is previewed, **Then** the merchant must explicitly confirm overwriting before it is allowed.
4. **Given** products for which no reliable historical price exists, **When** onboarding completes, **Then** they are listed as needing attention with a plain-language explanation.
5. **Given** an existing third-party price-history data source, **When** the plugin detects it, **Then** the merchant is offered a migration path and duplicate data collection is avoided.

---

### User Story 6 - Manage multiple sales locations (Priority: P6)

A merchant with one or more physical stores and/or a webshop defines each sales location (name, type, address, identifier, active status, applicable products and applicable price list) and each location produces its own correctly named price list file without duplicating product data.

**Why this priority**: Required for the price-list filename and per-location publication, but the single-location and webshop cases work without it, so it can follow the core publication feature.

**Independent Test**: Configure a webshop, one store and a second store, generate price lists, and verify each location produces its own file with the correct facility metadata and that product data is shared rather than duplicated.

**Acceptance Scenarios**:

1. **Given** three active sales locations, **When** daily generation runs, **Then** three correctly named price lists are produced, archived and published.
2. **Given** a location marked inactive, **When** generation runs, **Then** no file is produced for it.
3. **Given** a merchant with only a webshop, **When** generation runs, **Then** a valid webshop price list is produced without requiring physical-store configuration.

---

### User Story 7 - Inspect and audit price history (Priority: P7)

For any product or variation the merchant can view a chronological price history showing date, type, price, source and the user or system that created each record. History is inspectable but not destructively editable; corrections create new records that preserve the original.

**Why this priority**: Auditability protects the merchant in inspections and disputes. It builds on data already captured by earlier stories and can be delivered after them.

**Independent Test**: Change a price several times, start and end sales, correct an anchor record, and verify the full chronological history with authors, timestamps, sources and correction reasons, and that no original record was lost.

**Acceptance Scenarios**:

1. **Given** a product with several historical price records, **When** the merchant opens its price history, **Then** records are listed chronologically with date, type, price, source and user/system.
2. **Given** an anchor correction, **When** it is saved, **Then** a new correction record is created containing author, timestamp and reason, and the original record remains visible.

---

### User Story 8 - Operate and administer the compliance system (Priority: P8)

A site administrator configures the system (display options, reference dates, legacy-category mapping, legal-rule version, locations, retention, generation schedule), monitors structured logs, runs generation/validation/import/cleanup operations from the command line for automation, and controls what happens on deactivation and uninstall. The admin experience is in Croatian, understandable to a non-developer, and always states that the plugin is a technical aid, not legal advice.

**Why this priority**: Necessary for production operation and safe lifecycle management, but it wraps capabilities delivered by earlier stories.

**Independent Test**: Change settings as an authorised administrator, verify they take effect and are logged; verify an unauthorised role cannot change them; run each command-line operation and check exit codes; deactivate and uninstall and verify data preservation defaults.

**Acceptance Scenarios**:

1. **Given** an administrator changes the general reference date, **When** saved, **Then** existing historical records are unchanged and the change is logged.
2. **Given** a user without the required capability, **When** they attempt to change settings, import data or run generation, **Then** the action is denied.
3. **Given** the plugin is deactivated, **When** the site is inspected, **Then** all historical and audit data remains; **Given** uninstall with data deletion not explicitly enabled, **Then** historical and audit data is preserved and catalog data is never deleted.
4. **Given** command-line operations (status, validate, generate with format selection, history, import, cleanup), **When** they run, **Then** they produce meaningful output and exit codes.
5. **Given** the admin UI, **When** a merchant views it, **Then** all text is available in Croatian and warnings are phrased in plain language.

---

### Edge Cases

- Item introduced after the applicable reference date (no price existed on that date).
- Item whose price on the reference date was itself a special-sale price (a non-discounted reference price must be determined and evidenced).
- Item not in stock on the reference date but present in the assortment.
- Variable product where only some variations have anchor prices.
- Product with no SKU, no barcode, or duplicate SKU/barcode.
- Price is zero, negative, empty, or "on request".
- Unit-of-measure price absent when a unit of measure applies, or present without one.
- Multiple price changes and repeated/consecutive/overlapping promotions within 30 days.
- A sale starting exactly 30 days after a previous sale ended; boundaries at midnight and across daylight-saving changes.
- Time zone differences between site configuration, server clock and legal reference time.
- Sale end date missing, open-ended, or in the past.
- Product deleted, trashed or unpublished while historical records exist.
- WooCommerce absent, deactivated or uninstalled after data has been recorded.
- Currency missing or differing from the configured currency.
- Concurrent or repeated generation runs producing duplicate files.
- Filename collisions and special characters in facility name/address.
- Retention boundary: a file exactly at the end of its retention period.
- Very large catalogs (tens of thousands of items) and generation time/memory limits.
- Catalog price changes occurring during generation.
- Import rows with invalid dates, malformed numbers, unknown products or conflicting duplicates.
- Manual correction of an anchor price already used in a published price list.
- Public endpoint traffic spikes or abusive clients.
- Merchant places an item on sale while an anchor price is missing.

## Requirements *(mandatory)*

### Functional Requirements

**Legal model and reference rules**

- **FR-001**: The system MUST support two configurable reference dates: a general reference date (default 10 September 2026) and a legacy-category reference date (default 2 May 2025).
- **FR-002**: The system MUST allow administrators to map catalog categories to the legacy treatment; no product may be treated as legacy unless its category is explicitly mapped.
- **FR-003**: The anchor price for a product or service MUST be the retail price that was valid on the applicable reference date and that did not include any special-sale discount.
- **FR-004**: The system MUST store anchor prices as historical records preserving at minimum: reference date, source of the reference price, original retail price, currency, tax-inclusive status where applicable, product ID/SKU, variation ID where applicable, capture timestamp, and the user or system that created the record.
- **FR-005**: The system MUST never silently overwrite historical anchor records; any correction MUST create a new record that references the original and records the author, timestamp and reason.
- **FR-006**: Changing a reference date or category mapping MUST NOT rewrite or delete existing historical records; it affects only future resolution and capture.
- **FR-007**: The system MUST allow recording the merchant-verified price applied on first offer and its date for items first offered after the applicable fixed reference date; it MUST clearly flag missing evidence and block price-list publication until the anchor is recorded.
- **FR-008**: The system MUST retain an audit trail of changes to reference dates, category mapping, legacy-treatment enablement and the legal-rule version identifier.
- **FR-009**: The system MUST support a configurable legal-rule version identifier so that published data and findings can be traced to the rule set that produced them.
- **FR-010**: The system MUST allow administrators to record an evidenced, non-discounted reference price for items that were on sale on the reference date.

**Anchor-price display and advertising**

- **FR-011**: Wherever the merchant displays a current retail price on the website (product pages, archives, promotional blocks, widgets, merchant-placed content, and generated machine-readable output), the system MUST provide the applicable anchor price alongside it.
- **FR-012**: The default label MUST be "Sidrena cijena" and MUST be configurable by an administrator.
- **FR-013**: The system MUST NOT automatically present the anchor price using wording such as "was", "regular price" or "original price" unless the merchant explicitly configures such wording.
- **FR-014**: The displayed anchor price MUST be clear, visible, legible and unambiguously associated with the correct product or variation, and MUST NOT be visually misleading.
- **FR-015**: Administrators MUST be able to enable/disable frontend display, choose display locations and priority, enable display on archives, enable display for variations, and restrict display to cases where the anchor differs from the current price.
- **FR-016**: The system MUST provide a shortcode and a template function for merchant-controlled placement.
- **FR-017**: The system MUST NOT inject anchor-price markup into arbitrary third-party content without an explicit merchant action.
- **FR-018**: When anchor-price display is disabled, the system MUST render no anchor-price markup on the storefront.

**Special forms of sale and the 30-day lowest price**

- **FR-019**: The system MUST maintain a separate calculation engine for the lowest applicable price during the 30 days preceding a special form of sale, distinct from the anchor-price mechanism. The window MUST be the 30 calendar days immediately before the sale start date, excluding the sale start date and including the day exactly 30 days before it, evaluated in the site's local time zone.
- **FR-020**: The system MUST store each 30-day lowest-price result together with the exact calculation period it covers.
- **FR-021**: The system MUST display the 30-day lowest price separately from the anchor price and MUST NOT use the anchor price as a substitute for it.
- **FR-022**: The engine MUST handle repeated sales, consecutive or overlapping sales, regular price changes, sale price changes, variations and stock availability.
- **FR-023**: The system MUST support configurable special-sale types (including at least akcija, sniženje, sezonsko sniženje, rasprodaja and a configurable "other" type) and MUST require the merchant to classify each special sale.
- **FR-024**: The system MUST store, for each special sale, the sale type, start date, end date (where applicable), current promotional price and the calculated 30-day lowest price.
- **FR-025**: The system MUST NOT automatically classify arbitrary promotions as special forms of sale.

**Price history and auditability**

- **FR-026**: The system MUST maintain a chronological price history per product and variation showing date, type, price, source and the creating user or system.
- **FR-027**: Price history MUST be inspectable; destructive editing MUST NOT be possible from the admin interface.
- **FR-028**: Corrections MUST create adjustment records and MUST never delete the original record.
- **FR-029**: Historical price records MUST NOT be destroyed merely because a catalog price changes or an item is edited.
- **FR-030**: Importing or bulk-entering historical data MUST NOT overwrite existing records without explicit confirmation.
- **FR-031**: The system MUST preserve a complete audit trail of historical corrections including author, timestamp and reason.

**Price-list generation and formats**

- **FR-032**: The system MUST generate the current price list in both CSV and XML.
- **FR-033**: Product price lists MUST contain at least: product name, code, brand, unit of measure where applicable, unit-of-measure price where applicable, retail price, special-sale indicator, special-sale name, anchor price, barcode, and availability (available/unavailable). The published retail price MUST be the price effective for consumers at publication time; when a special sale applies, the promotional price is the published retail price and the special-sale indicator and name MUST identify the promotion.
- **FR-034**: Service price lists MUST contain at least: service name, retail price, special-sale indicator, special-sale name, and anchor price; the published retail price follows the same effective-price rule as products.
- **FR-035**: CSV output MUST use UTF-8, a stable column order, a consistent delimiter, correct escaping/quoting, a documented decimal format, and MUST contain no HTML, no serialized objects and no arbitrary hidden metadata.
- **FR-036**: XML output MUST be valid, UTF-8 encoded, deterministically structured, correctly escaped, and MUST carry a format/schema version identifier.
- **FR-037**: The exact CSV and XML formats MUST be documented, with example files provided.
- **FR-038**: Generated filenames MUST include the form/type of the sales location, the location address, the location identifier, the storage/sequence number and a timestamp containing the date and time of submission.
- **FR-039**: Filenames MUST be produced by a dedicated filename generator; arbitrary filenames MUST NOT be accepted; characters MUST be normalized safely while preserving the required identifying information.
- **FR-040**: Filename construction MUST keep the required components and their order fixed; administrators MAY configure only the separator character and letter case, with documented defaults (underscore separator, uppercase). The resulting construction MUST be documented.
- **FR-041**: Product price lists MUST be generated/updated every working day no later than 08:00 local time; service price lists MUST be updated on every price change, no later than 08:00 on the day the changed list is published. Product price changes MUST NOT force immediate republication — the next daily run suffices — while service price changes MUST trigger a queued regeneration promptly. Manual generation MUST remain available at any time. The deadline MUST be configurable.
- **FR-042**: The system MUST avoid generating duplicate publications when nothing has changed and the existing published file still satisfies the requirement, while always ensuring that the currently required price list is available.
- **FR-043**: Every generated price list MUST be validated before publication and the result (success or failure with findings) MUST be recorded.
- **FR-044**: Generation MUST be triggerable by schedule, manually from the admin interface, and from the command line; documentation MUST explain how to run generation from a real system scheduler rather than relying on the site's built-in scheduler alone.
- **FR-045**: On-demand CSV and XML export MUST be available to authorised administrators.

**Publication, archive and retention**

- **FR-046**: Every published file MUST be archived with at least: filename, creation timestamp, publication timestamp, checksum, format, location, file URL/path, plugin/legal-rule version and generation status.
- **FR-047**: Published files MUST remain publicly available for at least 30 days from publication or change; the retention period MUST be configurable but MUST never be set below 30 days.
- **FR-048**: The system MUST NOT delete a published file before its retention period expires.
- **FR-049**: A cleanup process MUST remove files only after the retention period, and MUST NOT remove archive records required for audit.

**Sales locations**

- **FR-050**: The system MUST provide a sales-location management area with at least: location name, location type, address, identifier, active/inactive status, applicable products and applicable price list.
- **FR-051**: Each active location MUST be able to produce its own price-list file.
- **FR-052**: The architecture MUST support a webshop only, a single physical store, multiple physical stores, and any combination of stores plus webshop, without unnecessarily duplicating product data; all locations share one price catalog and per-location differences are limited to applicable products and separately generated price-list files.

**Machine-readable public access**

- **FR-053**: The system MUST expose current price data in a machine-readable form that external software can retrieve in real time, covering at least: current price, anchor price, product identifier, SKU, barcode, availability and sale status.
- **FR-054**: The public access interface MUST support pagination and appropriate caching.
- **FR-055**: The system MUST support download of the current price data as CSV and XML as well as a structured data response.
- **FR-056**: Public price access MUST NOT require a logged-in user unless the applicable regulation requires or permits otherwise.
- **FR-057**: Public access MUST NOT expose private customer data or any information beyond the intended price data.

**Validation and compliance reporting**

- **FR-058**: The system MUST provide a central compliance validator that detects at minimum: missing anchor price, invalid anchor date, missing SKU, missing product name, missing barcode where required or available, invalid retail price, invalid unit price, invalid availability, missing special-sale type, missing 30-day price where required, malformed CSV, malformed XML, invalid filename, missing location identifier, stale price list, missing archived price list, invalid currency and duplicate product records.
- **FR-059**: Findings MUST have severity levels ERROR, WARNING and INFO, and MUST identify the affected item and a plain-language explanation.
- **FR-060**: Administrators MUST be able to run a compliance check on demand and receive a detailed report.
- **FR-061**: Validation MUST be runnable without publishing or modifying data.

**Import and onboarding**

- **FR-062**: The system MUST support CSV import of historical/anchor data containing at least: SKU or product ID, reference date, anchor price, category and optional variation ID.
- **FR-063**: Imports MUST support preview, validation, dry run, transactional commit or rollback, and an error report.
- **FR-064**: A failed import MUST NOT partially corrupt stored data.
- **FR-065**: The system MUST provide a manual bulk-entry tool for anchor data.
- **FR-066**: The system MUST detect existing pricing-history data sources and offer a migration path, avoiding duplication of functionality where an existing source already provides the data.
- **FR-067**: Items for which the historical reference price cannot be reliably reconstructed MUST be clearly reported to the administrator rather than assigned a guessed value.

**Administration, settings and logging**

- **FR-068**: The system MUST provide a compliance dashboard showing at least: items with valid anchor prices, items missing anchor prices, items needing verification, comparison of current price to anchor price, items with missing historical data, 30-day pricing warnings, export status, last successful scheduled export, next export and validation errors.
- **FR-069**: The system MUST provide settings for: frontend display enablement, anchor label, currency display, display location, display priority, archives, product pages, variations, "only when anchor differs", and the administrative disclaimer.
- **FR-070**: The system MUST provide settings for the general reference date, the legacy reference date, category mapping, legacy-treatment enablement and the legal-rule version identifier.
- **FR-071**: All compliance operations MUST be gated by a single capability tier that administrators hold by default and that can be granted to other roles such as shop managers; users without the capability MUST be denied, and every settings change MUST be logged.
- **FR-072**: The admin interface MUST be available in Croatian with clear, plain-language terminology and warnings (for example: "Proizvod nema evidentiranu sidrenu cijenu za referentni datum.").
- **FR-073**: The admin interface and documentation MUST state clearly that the system is a technical compliance aid and not legal advice, and MUST NOT claim guaranteed legal compliance.
- **FR-074**: The system MUST implement structured logging of scheduled exports, manual exports, failed exports, imports, validation failures, historical corrections, migrations and major configuration changes.
- **FR-075**: Logs MUST NOT contain passwords, payment data, customer personal data or unnecessary sensitive information.
- **FR-076**: The system MUST provide an admin log viewer with filtering.

**Lifecycle, security, privacy, performance and compatibility**

- **FR-077**: Deactivation MUST preserve all data; uninstall MUST preserve historical and audit data unless the administrator has explicitly enabled deletion with confirmation; uninstall MUST NOT delete catalog data belonging to other systems.
- **FR-078**: The system MUST enforce capability checks, nonces, input sanitization, validation, output escaping and parameterized data access for all administrative and public operations.
- **FR-079**: The system MUST prevent unauthorized price manipulation, imports, exports, settings changes and access to private data.
- **FR-080**: The system MUST use secure file handling, MUST prevent directory traversal, and MUST NOT perform arbitrary file writes.
- **FR-081**: The system MUST NOT collect or store customer personal data (names, emails, addresses, payment information, order contents).
- **FR-082**: The system MUST NOT perform catalog-wide queries or full export generation on ordinary frontend page requests; frontend display MUST add minimal overhead.
- **FR-083**: The system MUST use caching, pagination, batching and background/scheduled processing appropriate to large catalogs, and MUST NOT perform expensive compliance operations synchronously during checkout.
- **FR-084**: The system MUST function without WooCommerce installed; WooCommerce-specific capabilities MUST be enabled only when WooCommerce is available and MUST be disabled gracefully when it is absent, without fatal errors.
- **FR-085**: The system MUST integrate through official extension points and MUST NOT modify WordPress core, WooCommerce core or the active theme, and MUST NOT overwrite native catalog price fields or existing checkout, payment, shipping or coupon logic.
- **FR-086**: All user-visible strings MUST be translatable, with Croatian as the primary admin/frontend language and English prepared as a translation.
- **FR-087**: The system MUST use a unique plugin prefix/namespace and follow current WordPress coding standards, using clear class/service responsibilities and proper plugin lifecycle hooks.
- **FR-088**: The system MUST provide command-line operations for at least: status, validation, generation (with format selection), price history lookup, import and cleanup, with meaningful exit codes.
- **FR-089**: The system MUST provide documentation covering: overview and setup, development environment, legal rules and sources, price history model, price-list formats, WooCommerce integration, testing and troubleshooting.
- **FR-090**: The system MUST provide an onboarding path that does not assume the current price is the historical anchor price.

### Clarified Requirements (Resolved 2026-09-21)

- **FR-091**: The system MUST support both WooCommerce-backed catalog items (when WooCommerce is available) and a standalone, manually managed or imported catalog of products and services (when WooCommerce is absent), applying the same anchor-price, history, validation and export rules to both sources.
- **FR-092**: Sales locations MUST share a single price catalog; per-location differences are limited to applicable products and the location's own generated price-list file. Per-location price overrides are out of scope.
- **FR-093**: For items first offered after the applicable fixed reference date, the system MUST record the actual price applied on first offer and that first-offer date as the anchor. It MUST NOT infer the historical price from current catalog price or treat the creation date as proof; missing/unsupported records block publication.

### Key Entities *(include if data involved)*

- **Catalog item (product or service)**: An item offered to consumers; has a name, code/SKU, brand, unit of measure, barcode, availability, currency, tax status, and a source (WooCommerce-backed or manually managed). May be a simple item, a parent with variations, or a service.
- **Variation**: A purchasable variant of a parent item with its own price and anchor price where applicable.
- **Anchor price record**: An immutable historical record of the reference price valid on an applicable reference date, with source, currency, tax-inclusive status, identifiers, capture timestamp and creator. Corrections reference the original.
- **Price history record**: A chronological record of any price (regular, sale, anchor, lowest-30-day, historical) with validity period, source and creator.
- **Special sale**: A merchant-classified special form of sale with type, start/end dates, promotional price and the calculated lowest price of the preceding 30 days.
- **Sales location**: A physical store or webshop with name, type, address, identifier, active status, applicable products and applicable price list.
- **Price-list publication**: A generated and published CSV or XML file with filename, timestamps, checksum, format, location, URL/path, rule version and generation status, retained for the required period.
- **Validation finding**: A detected issue with severity, affected item, explanation and rule reference.
- **Legal-rule configuration**: Reference dates, legacy category mapping, legacy enablement and rule version identifier, with a change audit trail.
- **Audit/log entry**: A structured record of significant system and administrator actions, without personal or sensitive data.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A merchant can establish anchor prices for a 10,000-item catalog through import with preview, validation and rollback in a single session; a failed import leaves zero partial records.
- **SC-002**: When display is enabled, 100% of catalog items shown with a current price on the website also show the applicable anchor price, correctly associated with the item or variation.
- **SC-003**: Price-list files are published for every active sales location every working day no later than 08:00 local time, with 100% of legally required fields present or explicitly marked as unavailable.
- **SC-004**: Every published price list remains publicly retrievable for at least 30 days; no file is removed before its retention period expires.
- **SC-005**: External software can retrieve the complete current price data for a 10,000-item catalog without authentication, paginated, with each page returned in under 2 seconds under normal load.
- **SC-006**: A single compliance check run reports every seeded missing or invalid required data point with a correct severity and item reference.
- **SC-007**: The site continues to serve all storefront pages with no fatal errors and no visible change when WooCommerce is absent or deactivated.
- **SC-008**: Enabling the anchor-price display does not measurably degrade product page or archive load time.
- **SC-009**: After a correction, the original anchor record remains retrievable and the correction's author, timestamp and reason are visible in the price history.
- **SC-010**: 100% of admin-facing interface text is available in Croatian.
- **SC-011**: A new developer can start the local environment, activate the system and run the complete automated test suite (unit, integration and browser-level) using only the documented commands, with all tests passing.
- **SC-012**: The 30-day lowest-price calculation produces the correct result for all documented boundary cases, including sales starting exactly 30 days after a previous promotion.

## Assumptions

- The deliverable is a WordPress plugin. WooCommerce is optional: the compliance core operates independently, and WooCommerce-specific capabilities activate only when WooCommerce is present.
- The catalog supports both WooCommerce-backed items and manually managed/imported items and services; a site without WooCommerce can operate the full compliance workflow on its standalone catalog.
- All sales locations share a single price catalog; per-location differences are limited to applicable products and separately generated price-list files. Per-location price overrides are out of scope.
- The merchant operates B2C sales in the Republic of Croatia and has a website; B2B/B2G sales are out of scope.
- Prices are expressed in euro; a single currency applies per price list, and each stored price record carries its currency.
- The site time zone (Europe/Zagreb) governs reference dates, sale windows, deadlines and timestamps; stored timestamps are unambiguous.
- Legal sources relied upon: Zakon o iznimnim mjerama kontrole cijena (NN 40/2025); Odluka o isticanju dodatne cijene kao mjera izravne kontrole cijena (NN 101/2026, no. 1212); Odluka o objavi cjenika proizvoda i usluga kao mjera izravne kontrole cijena (NN 101/2026, no. 1213); Pravilnik o načinu isticanja maloprodajne cijene i cijene za jedinicu mjere proizvoda (NN 117/22); Croatian consumer-protection rules on special forms of sale and the lowest price of the previous 30 days. The 2025 legacy regime covered food, beverages, cosmetics, cleaning products, toiletries and household products with reference date 2 May 2025.
- The regulation does not prescribe a single XML schema; the system defines a documented, deterministic, versioned format, and this uncertainty is documented.
- The regulation's applicability of the online price-list obligation to a specific merchant may be legally uncertain; the system therefore makes the obligation configurable and documents the uncertainty rather than hard-coding it.
- The exact daily publication deadline is configurable with a default of 08:00 local time, per the published guidance.
- Items first offered after the applicable fixed reference date use their verified first-offer price as the anchor, with the first-offer date clearly stated. The plugin must block publication until the merchant records this evidence; it must not infer the original price from the current catalog price.
- The public price interface is unauthenticated by default because the regulation requires automated retrieval; only price data is exposed.
- The plugin is a technical aid and not legal advice; merchants must obtain professional legal/accounting advice for their specific situation.
- The development environment uses containerized WordPress tooling with documented commands; development-only credentials are never used in production.
- Automated tests are expected at unit, integration and browser level, including fixtures covering the required test-product scenarios (normal item, sale item, missing SKU, barcode present/absent, variable product, multiple variations, legacy category item, item introduced after the reference date, frequently re-priced item, consecutive promotions, out-of-stock item, unit pricing, missing anchor, and current price above/equal/below anchor).
