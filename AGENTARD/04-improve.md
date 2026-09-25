# Prompt 4 — Improvement pass (no Docker needed)

```
You are improving the WordPress plugin in the current directory. Keep every
change small, covered by tests, and committed atomically (feat:/fix:/test:/docs:).
Do not change legal semantics without reviewing `docs/legal-rules.md`.

Pick items in priority order; implement improvements, add tests, and release:

1. Performance:
   - Verify PriceListBuilder queries are batched; eliminate N+1 queries.
   - Public price-list page (/sidrena-cjenik/) uses RestCache to avoid recalculating
     catalog tables on every visit. Ensure cache invalidation triggers on price, anchor,
     sale, or settings updates.
2. Admin UX (Croatian-first):
   - Review admin screens under the Sidrena cijena menu: Setup Guide, Dashboard, Price lists,
     Anchor prices, Import, Sales locations, Price history, Compliance check, Settings, Logs, Audit.
   - Ensure clear labels, helpful descriptions, and consistent formatting (via Currency and Dates helpers).
   - Ensure Setup Guide checklist dynamically reflects current status across all 5 steps.
3. Accessibility:
   - Ensure form controls have visible labels, buttons have descriptive text, and ARIA attributes
     are used where appropriate.
   - Maintain color contrast in admin.css and frontend templates.
4. Robustness and Compatibility:
   - Add unit tests for uncovered branches in src/ (aim for growing, real behavior coverage).
   - Maintain strict PHP 7.4 to 8.4 compatibility (no PHP 8-only syntax in src/).
   - Ensure clean operation both with and without WooCommerce.
5. Quality Gates & Release:
   - Run all four static gates:
     `composer test:unit && composer lint && composer analyse && npm run lint:js`
   - If translation strings were updated, regenerate catalogs:
     `php tools/i18n/make-pot.php --locale=hr_HR`
   - Follow release procedure in AGENTARD/README.md:
     1. Bump version in sidrena-cijena.php, readme.txt, and package.json.
     2. Run `tools/build-zip.sh <version>`.
     3. Verify with `unzip -t versions/<version>/sidrena-cijena.zip`.
     4. Append release notes to docs/release-readiness.md and update docs/improvement-backlog.md.
     5. Commit all changes including versions/<version>/ and push to origin master.

Done when: improvements and tests are committed, all gates pass cleanly, and a new
version archive is built, verified, and pushed.
```
