# Prompt 9 — UI/UX pass: admin screens, public price list, accessibility (no Docker needed, optional for E2E)

> **Status (v2.0.21):** COMPLETED. All UI/UX passes (UX-01 through UX-15) implemented, tested, and shipped.

```
You are improving the user experience of the WordPress plugin in the current directory
(sidrena-cijena.php is the bootstrap): the Sidrena Cijena admin screens and the public
price-list output. Croatian-first: every visible string goes through the
`sidrena-cijena-eu` textdomain. Work only inside this plugin; never touch WordPress core,
themes or WooCommerce. Do not change legal semantics (docs/legal-rules.md), the CSV/XML
contracts, or the filename pattern. Implement 2-3 focused items per session, each with
tests where behaviour changes, committed atomically (feat:/fix:/test:/docs:).

MANDATORY BRANCH & PULL REQUEST WORKFLOW:
1. Checkout a dedicated branch before making changes:
   `git checkout -b ux/<feature-or-screen>`
2. Commit each item atomically with conventional prefixes.
3. Push branch and open a Pull Request for user review:
   `git push -u origin ux/<feature-or-screen>`
   `gh pr create --base master --title "ux: <brief summary of UX improvements>" --body "..."`
   Do NOT push directly to master or merge the PR yourself.

Baseline (v2.0.20): 535 unit tests / 1916 assertions green — keep green, grow the suite.
Check tests/e2e/*.spec.js before renaming markup or text those specs assert on.

Status (v2.0.21): all work items 1–7 below have shipped as UX-01..UX-07, with subsequent UI/UX
passes expanded through UX-15 (see docs/improvement-backlog.md and docs/release-readiness.md).

Work items (priority order — read the listed files first, fix what is really there):
1. Kill raw slugs in the UI. Statuses, log levels, event names, finding severities, sale
   types, location types and import row statuses are printed as internal slugs. Build one
   shared label resolver on the existing Support\Labels pattern and use it everywhere.
2. Tables and pagination. AuditPage renders log rows with PER_PAGE=50 but no paginate_links;
   ImportPage preview renders every parsed row — cap and paginate both. Unify pagination markup
   and add <caption> to admin tables.
3. Form error UX. Validation errors redirect to top-of-page notice and drop user input.
   Preserve submitted values on error, show errors next to the offending field (aria-invalid +
   aria-describedby), move focus, and use dismissible notices.
4. Centralise styles and scripts. Inline style="" attributes move into assets/css/admin.css;
   inline scripts move into assets/js/admin.js. Replace inline onsubmit with data-sidrena-confirm.
   Add :focus-visible styles and busy/disabled states on submit buttons.
5. Public price list (templates/public-price-list.php + Api/PublicPage.php). Add pagination,
   result count, clear-filters affordance, and responsive styles for mobile viewports.
6. Formatting consistency. Site-time datetime display helper in Support\Dates and unify amount
   rendering across admin tables.
7. Accessibility and i18n hygiene. Settings fields label_for fixes, fieldset legend, aria-live
   on dynamic variation updates, and translate remaining literals.
8. Display Settings label presets UX (v2.0.19+): Verify preset pills, quick-insert {datum} button,
   and live preview of statutory category dates (2. 5. 2025. legacy vs 10. 9. 2026. general).
9. Tooltip rendering: Ensure circular ? icon, hover-gap bridge, screen boundary clamping, and
   correct statutory description (NN 101/2026 & NN 110/2026).

After string changes: update tools/i18n/en_US.php and tools/i18n/hr_HR.php, run
`php tools/i18n/make-pot.php --locale=hr_HR`, and confirm 809 translated strings / 0 untranslated.

Gates: fast local unit test suite `composer test:unit`.

Done when: the chosen items are fixed and tested, fast local checks pass, translations are
complete (0 untranslated), branch is pushed, and a Pull Request is opened for user review.
```
