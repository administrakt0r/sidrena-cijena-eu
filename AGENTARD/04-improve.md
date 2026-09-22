# Prompt 4 — Improvement pass (no Docker needed)

```
You are improving the WordPress plugin in the current directory. Keep every
change small, covered by tests, and committed atomically
(feat:/fix:/test:/docs:). Do not change legal semantics without updating
docs/legal-rules.md and the spec checklist.

Pick items in priority order; stop after the batch below and report:

1. Performance: profile PriceListBuilder on large catalogs (use
   `wp sidrena-cijena seed-test-data --yes` + generated variations in
   wp-env, or a unit benchmark with a stubbed provider). Eliminate N+1
   queries: batch anchor lookups (latest_map) and sale lookups
   (active_map) wherever single-row queries run in loops. Verify archive
   pages issue no catalog-wide queries.
2. Admin UX (Croatian-first): walk every screen under the Sidrena cijena
   menu; fix unclear labels, missing descriptions, inconsistent date/price
   formatting (use Currency + Dates helpers everywhere), and add inline
   guidance where merchants can misconfigure reference dates or sale types.
3. Docs: re-read docs/*.md and fix anything the code has outgrown;
   add examples to docs/price-list-format.md if edge cases are missing.
4. Robustness: add unit tests for uncovered branches in src/Pricing,
   src/Export and src/Import (aim: no trivially-testable public method
   without a test). Keep PHP 7.4 compatibility (no arrow-function-only or
   PHP 8-only syntax in src/).
5. Accessibility: labels for every admin input, keyboard-operable controls,
   sufficient contrast in assets/css (admin + frontend).

Verify with `composer test:unit && composer lint && composer analyse`
(zero failures/errors) and report: what improved, measured effect,
commits, what you deliberately left alone.
```
