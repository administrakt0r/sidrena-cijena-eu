# Prompt 3 — Full wp-env verification (needs Docker)

```
You are verifying the plugin in the current directory inside a real
WordPress environment. Requires Docker, Node 18+, PHP + Composer.

1. Setup:
   npm install
   composer install
   npm run wp-env start
   npx wp-env run cli wp plugin activate sidrena-cijena
   Site: http://localhost:8888 (admin/password, development only).
2. Run `composer test:integration` until green. Fix failures (tests first
   if the test contradicts the spec, otherwise fix src/). WooCommerce is
   provisioned by .wp-env.json - verify both with and without it
   (`wp plugin deactivate woocommerce` must never fatal).
3. Run `npm run test:e2e` (Playwright, chromium). Fix failures the same way.
4. Run the quickstart scenarios V1-V12 from
   specs/001-sidrena-cijena-compliance/quickstart.md manually and record
   the result of each (pass/fail + notes).
5. WooCommerce regression: homepage, product page, category, cart,
   checkout, account, admin dashboard, product editing,
   payment/shipping/coupons - confirm no interference.
6. Final acceptance (spec task T138): clean wp-env → install → activate →
   seed (`wp sidrena-cijena seed-test-data --yes`) → anchors → price
   changes → sales → display → CSV/XML → validate → filename → archive →
   REST → compliance check → all suites → checkout regression → log
   inspection → git review. Record results per step.
7. Commit fixes atomically (feat:/fix:/test:). Push nothing; report the
   full result table.

Done when: integration suite, E2E suite, V1-V12 and the T138 walkthrough
are recorded; all fixable failures are fixed and committed.
```
