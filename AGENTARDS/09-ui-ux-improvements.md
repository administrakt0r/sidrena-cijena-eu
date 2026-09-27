# Prompt 9 — UI/UX pass: admin screens, public price list, accessibility (no Docker needed, optional for E2E)

> **Status (v2.0.13):** COMPLETED. All UI/UX passes (UX-01 through UX-14) implemented, tested, and shipped.

```
You are improving the user experience of the WordPress plugin in the current directory
(sidrena-cijena.php is the bootstrap): the Sidrena Cijena admin screens and the public
price-list output. Croatian-first: every visible string goes through the
`sidrena-cijena-eu` textdomain. Work only inside this plugin; never touch WordPress core,
themes or WooCommerce. Do not change legal semantics (docs/legal-rules.md), the CSV/XML
contracts, or the filename pattern. Implement 2-3 focused items per session, each with
tests where behaviour changes, committed atomically (feat:/fix:/test:/docs:).

Baseline (v2.0.13): 456 unit tests / 1623 assertions green — keep green, grow the suite.
Check tests/e2e/*.spec.js before renaming markup or text those specs assert on.

Status (v2.0.13): all work items 1–7 below have shipped as UX-01..UX-07, with subsequent UI/UX
passes expanded through UX-14 (see docs/improvement-backlog.md and docs/release-readiness.md).

Work items (priority order — read the listed files first, fix what is really there):
1. Kill raw slugs in the UI. Statuses, log levels, event names, finding severities, sale
   types, location types and import row statuses are printed as internal slugs
   (e.g. `published`, `needs_verification`, `lowest_30_day`, `prodavaonica`) in
   DashboardPage, CompliancePage (cards AND filter buttons), PublicationsPage, ImportPage
   (preview table), LogsPage (level badge + event filter options), AuditPage,
   LocationsPage (type column has translated labels already in types()), SpecialSaleMetaBox
   (sale_type options + status) and LegalSettingsPage. Build one shared label resolver on
   the existing Support\Labels pattern (which replaced Support\HistoryLabels) and use it
   everywhere. Add unit tests for the
   resolver.
2. Tables and pagination. AuditPage renders log rows with PER_PAGE=50 but no paginate_links
   (older entries unreachable) and prints the whole rule-change history unbounded;
   ImportPage's preview renders every parsed row (a 5,000-row CSV = 5,000 <tr>) — cap and
   paginate both. Unify pagination markup (tablenav / tablenav-pages wrapper +
   screen-reader pagination label) across PublicationsPage, PriceHistoryPage, LogsPage and
   BulkAnchorEntryPage, and add <caption> to admin tables (frontend templates already have
   them).
3. Form error UX. Validation errors currently redirect to a top-of-page notice and drop
   the user's input (PriceHistoryPage::render_correction_form hardcodes value="",
   LocationsPage's add form loses fields, SetupGuidePage's "Mark setup complete" gives no
   feedback at all). Preserve submitted values on error, show errors next to the offending
   field (aria-invalid + aria-describedby), move focus to the notice or first invalid
   field, and extract one dismissible notice helper used by all screens (several notices
   are missing is-dismissible today).
4. Centralise styles and scripts. Inline style="" attributes (DashboardPage,
   SetupGuidePage, BulkAnchorEntryPage, ImportPage, LocationsPage, AnchorMetaBox,
   SettingsFields) move into assets/css/admin.css; the inline <script> select-all logic in
   BulkAnchorEntryPage moves into assets/js/admin.js. Replace the inline
   onsubmit="return confirm(…)" on the bulk-anchor overwrite form (the most destructive
   action in the plugin) with the existing data-sidrena-confirm mechanism in admin.js.
   Add :focus-visible styles, a busy/disabled state on long-running submit buttons
   ("Generate now", "Run compliance check", bulk save), and CSS for status variants that
   are emitted but unstyled (sidrena-cijena-status--error, --expired, …). Fix the
   low-contrast badge colour (#996800 on #fef8ee is below 4.5:1). Enqueue admin.css/
   admin.js on the product edit screen too, where AnchorMetaBox and SpecialSaleMetaBox
   render (hook suffix gating in AdminMenu::enqueue_assets).
5. Public price list (templates/public-price-list.php + Api/PublicPage.php). The full
   filtered catalog is rendered as one table with no pagination and no result count.
   Add pagination and a "showing X of Y" line, a clear-filters affordance, and keep the
   empty state helpful. Move the inline <style> printed by PublicPage::style() into
   assets/css/frontend.css, then add responsive rules (wrapping search bar, horizontal
   scroll container or stacked rows for the 12-column table on small screens) and
   @media print rules — this is a legally mandated price list people print. Translate the
   raw XML/CSV link text (also in templates/public-archive.php) and give archive download
   links accessible names that include the filename instead of repeating "Download".
6. Formatting consistency. Datetimes print as raw ISO `Y-m-d H:i` across DashboardPage,
   PublicationsPage, PriceHistoryPage, LogsPage, AuditPage and LocationsPage, while
   LegalSettingsPage prints the raw stored timestamp with no site-time conversion at all
   (AuditPage shows the same event differently). Add one site-time datetime display helper
   in Support\Dates (Croatian display, consistent with format_date_display) and use it
   everywhere. Unify amount rendering: Currency::format() ("1.234,56 €"),
   Currency::format_decimal() ("1234,56") and manual `format_decimal . ' ' . currency`
   concatenation are used interchangeably for the same values — pick one per context and
   apply it consistently.
7. Accessibility and i18n hygiene. Settings fields lack label_for (DisplaySettingsPage,
   LegalSettingsPage, OperationsSettingsPage) so inputs render without <label>; the
   SaleTypesSettings label_for points at id `sidrena_cijena_sale_types` while the
   textarea id is `sale_types` — fix the mismatch. Add a <legend> to the checkbox
   fieldset in SettingsFields. Add aria-live to the variation anchor update in
   assets/js/frontend.js (the value is swapped silently today; the info is also only in a
   title attribute in templates/anchor-price.php). Remove the duplicated
   screen-reader-text + visible description sentence in LocationsPage. Translate the
   remaining literals (ImportPage's `<th>SKU</th>`, AuditPage's 'true'/'false').

After string changes: update tools/i18n/en_US.php and tools/i18n/hr_HR.php, run
`php tools/i18n/make-pot.php --locale=hr_HR`, and confirm 768 translated strings / 0 untranslated in
languages/hr_HR.po.

Gates: `composer test:unit && composer lint && composer analyse && npm run lint:js`;
with Docker also `npm run test:e2e` (42 Playwright specs, port 8889 only — never point Playwright at 8888) and
update specs when markup intentionally changes.

Release (mandatory per AGENTARDS/README.md): bump the version in all 4 places, run
`tools/build-zip.sh <version>`, verify `unzip -t versions/<version>/sidrena-cijena-eu.zip`,
update docs/improvement-backlog.md (UX-xx items) and docs/release-readiness.md, commit and
push to origin master.

Done when: the chosen items are fixed and tested, no raw slugs remain in the touched
screens, translations are complete (0 untranslated), all gates green, and the release
archive is built and pushed.
```
