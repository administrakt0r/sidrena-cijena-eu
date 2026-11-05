# Prompt 7 — Performance pass 2: caching, request-time work and asset loading (Docker optional, needed for measurements)

> **Status (v2.0.0):** COMPLETED. Caching, runtime optimization, textdomain loading, and asset loading improvements completed and verified.

```
You are reducing per-request cost of the WordPress plugin in the current directory
(sidrena-cijena.php is the bootstrap). Goal: no wasted work on requests that do not need
it, cache only what is safe to cache, and never serve stale legal data. Measure before
and after.

Rules: work only inside this plugin; never touch WordPress core, themes or WooCommerce.
Legal semantics must not change (docs/legal-rules.md). Compliance findings and the
publication gate are legal state — a cached compliance result must never hide a new
ERROR. Keep the plugin working with and without WooCommerce. Commit each item separately
(perf: / fix:), with tests. PHP 7.4-8.4 only.

Baseline (v2.0.20): 535 unit tests / 1916 assertions green — keep green, grow the suite.

Measurement (first and last; Docker optional for the fixes):
- `./local.sh` → http://localhost:8888 (admin / password). Wrap docker/wp-env in
  `sg docker -c '…'` if needed. NEVER run `wp plugin install <zip> --force` (deletes the
  bind-mounted repository).
- Record before/after for: TTFB of /sidrena-cjenik/ (warm and with ?q= search), the shop
  archive with 20+ visible products, the admin Dashboard screen, and a REST /prices call;
  plus query counts per request. Numbers go into docs/improvement-backlog.md (PERF-xx).

Work items — verify each against the real code first:
1. Request-time cron checks (highest impact): src/Cron/Scheduler.php hooks
   maybe_catchup() on `init` for EVERY front-end and admin request. Past the daily
   deadline it runs is_missed_run_today() → locations->all(true) + publications
   ->last_successful() per location (1+N queries per pageview), and it can run a FULL
   generation inline inside a real visitor's request. Gate the check so ordinary
   front-end requests skip it (admin/cron/CLI only, or one cheap option-flag test that
   fails fast), and hand the heavy work to WP-Cron / Action Scheduler instead of the page
   request. Preserve the REL-06 behaviour: missed-run detection, catch-up generation,
   failure notice, `wp sidrena-cijena status` diagnostics.
2. Cache fragmentation: Api/PublicPage::render_list() caches the rendered HTML per
   (page, location, q, type) through Api/RestCache (300 s TTL). Every distinct search
   term = one full catalog build + a full HTML copy living in wp_options on hosts without
   a persistent object cache. Cache the canonical row set once per generation and filter/
   render per request in PHP instead. Keep the "Generated:" stamp refreshing on publication.
3. Uncached hot paths: DashboardPage::aggregate() runs a mini-pipeline (counts + 500-item
   walk + latest_map calls + validator) on every screen load; PublicPage::render_archive()
   queries publications on every request; REST /publications and /status are uncached.
   Add short-TTL caches keyed through the existing generation markers (RestCache /
   AnchorCache) and flush them on the existing invalidation hooks. Never cache away a
   compliance ERROR or a publication gate decision.
4. AnchorCache write amplification (src/Pricing/AnchorCache.php): a cold cache writes one
   transient per product/variation (TTL 1 day) and flush() uses a no-expiry transient that
   lands in the autoloaded options table. Prefer batched entries or wp_cache where a
   persistent object cache exists; make sure no cache bookkeeping row is autoloaded.
5. Textdomain loading: sidrena-cijena.php registers sidrena_cijena_load_textdomain twice
   (plugins_loaded and init) and explicitly loads languages/sidrena-cijena-eu-hr.mo on every
   request regardless of the active locale. Load once, respect the current locale, keep
   Croatian strings working in tests and in the hr catalog.
6. Asset loading: assets/css/frontend.css and assets/js/frontend.js are enqueued on EVERY
   front-end page (PriceDisplay::enqueue_assets on wp_enqueue_scripts). Restrict to the
   contexts the display settings target (product pages, shop/product archives) without
   breaking builder compatibility (src/WooCommerce/Display/BuilderCompat.php). Also drop
   the unused wp-i18n dependency of admin.js (src/Admin/AdminMenu::enqueue_assets).
7. Settings::get() (src/Support/Settings.php) re-parses defaults and runs wp_parse_args on
   every call, ~10× per rendered product. Memoise per request and invalidate on
   `sidrena_cijena_settings_changed` (and in tests).
8. REST per-IP throttle (src/Api/PriceListController.php) costs two options-table round
   trips per request on hosts without a persistent object cache — use wp_cache when
   available.

Constraints:
- Cache invalidation must stay correct: price, anchor, sale, publication and settings
  changes flush what they touch today (Plugin::flush_caches). Extend, never weaken.
- If a test encodes a bug, fix the implementation, not the test — unless it contradicts
  docs/legal-rules.md, in which case stop and report.
- Gates: `composer test:unit && composer lint && composer analyse && npm run lint:js`;
  with Docker also `npx wp-env clean tests && composer test:integration`.

Release (mandatory per AGENTARDS/README.md): bump the version in all 4 places, run
`tools/build-zip.sh <version>`, verify `unzip -t versions/<version>/sidrena-cijena-eu.zip`,
update docs/improvement-backlog.md (PERF-xx) and docs/release-readiness.md, commit and
push to origin master.

Done when: measured per-request cost improved with numbers recorded, invalidation hooks
verified by tests, all gates green, legal rules untouched, release archive built and pushed.
```
