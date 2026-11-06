# Prompt 6 — Performance pass 1: database and query tuning (Docker optional, needed for measurements)

> **Status (v2.0.0):** COMPLETED. Work items 1–4, 6 shipped as PERF-01–PERF-04, PERF-06; items 5 and 7 measured as PERF-05, PERF-07 (see docs/improvement-backlog.md).

```
You are optimising the data layer of the WordPress plugin in the current directory
(titanworks-sidrena-bazna-cijena.php is the bootstrap). Goal: fewer, smaller, indexed queries with
identical observable behaviour. Measure before and after; never optimise blind, and
never "simplify" a query in a way that changes which rows win.

Rules: work only inside this plugin; never touch WordPress core, themes or WooCommerce.
The price-history table (wp_sidrena_price_history) is append-only legal evidence: schema
changes are additive only (new indexes/columns, never rewritten rows) and legal semantics
must not change (docs/legal-rules.md). Keep the plugin working with and without
WooCommerce.

MANDATORY BRANCH & PULL REQUEST WORKFLOW:
1. Checkout a dedicated branch before making changes:
   `git checkout -b perf/db-query-tuning`
2. Commit each item separately (perf: / fix:), with tests.
3. Push branch and open a Pull Request for user review:
   `git push -u origin perf/db-query-tuning`
   `gh pr create --base master --title "perf: database and query tuning" --body "..."`
   Do NOT push directly to master or merge the PR yourself.

Baseline (v2.0.20): 535 unit tests / 1916 assertions green — keep green, grow the suite.

Measurement (do this first and again at the end; Docker optional for code fixes):
- Demo shop: `./local.sh` → http://localhost:8888 (admin / password) with 200+ seeded
  items. If the shell lacks docker group access, wrap commands in `sg docker -c '…'`.
  CRITICAL: never run `wp plugin install <zip> --force` — the repository is bind-mounted
  and the upgrader deletes it.
- Query count and timing, for example:
  `npx wp-env run cli wp eval '…SAVEQUERIES / microtime…'`,
  `time npx wp-env run cli wp sidrena-cijena generate --force`,
  `curl -so /dev/null -w '%{time_total}\n' http://localhost:8888/sidrena-cjenik/`.
- Record before/after numbers in docs/improvement-backlog.md (PERF-xx items) or the commit
  message. An improvement without a number is not done.

Work items — verify each against the real code first, then fix in priority order:
1. PriceHistoryRepository::latest_map() (src/Database/PriceHistoryRepository.php) pulls
   EVERY historical row per price type for the whole batch (SELECT * … ORDER BY id ASC,
   no LIMIT) and keeps the newest per key in PHP. Rewrite so only the newest row per
   (product_id, variation_id) is fetched (e.g. JOIN on MAX(id) … GROUP BY), preserving
   exact semantics for corrections and variation identity. Callers: PriceListBuilder::build,
   DashboardPage::aggregate (twice: anchor + regular), Compliance\Validator.
2. Storefront N+1: WooCommerce/Display/PriceDisplay::render_thirty_day() calls
   SpecialSaleRepository::active_for_item() per rendered product (twice with the variation
   fallback), and Api/TemplateFunctions does the same per shortcode render. Batch through
   the existing SpecialSaleRepository::active_map() (or one memoised lookup per request) so
   a shop loop of N products issues O(1) sale queries. Rendered 30-day output must not change.
3. Catalog hydration N+1: WooCommerceCatalogProvider::build_item() runs ~8-10 meta/term
   lookups per item (barcode probing up to 6 meta keys, _sidrena_item_type,
   _sidrena_first_seen, product_brand, categories). Prime post meta/term caches for the whole
   batch before the walk and memoise barcode resolution. A generation run hydrates the same
   items twice (build + catalog_report) — share the hydrated batch within one run.
4. Indexes (src/Database/Schema.php + src/Database/Migrator.php upgrade path), additive only:
   - price_history: created_at (default ORDER BY of the admin listing), item_type and
     correction_of (filter columns); consider (product_id, price_type, id) for latest_map.
   - special_sales: composite (status, start_date) for active_map().
   - publications: composite (location_id, format, status, id) for latest() and
     expired_candidates().
   Add an integration test asserting the indexes exist after upgrade and Migrator is idempotent.
5. COUNT(*) hotspots: LogRepository::count()/count_all() and PriceHistoryRepository::count()
   run unbounded counts on append-only tables on every admin render. Short-TTL the counts per
   filter set (or derive them from the fetched page) without breaking pagination accuracy.
6. Query hygiene: PublicationRepository::update() runs get($id) twice
   (`$existing = $this->get( $id ) ? $this->get( $id ) : array()`); CleanupJob::mark_expired()
   re-reads each row; Publisher's filename loop calls LocationRepository::next_sequence()
   (UPDATE + full SELECT) per attempt. One query each.
7. PriceHistoryRepository::timeline() sorts by COALESCE(valid_from, created_at), which can
   never use an index. Only act on this if measurement shows it matters; otherwise record it
   as measured-irrelevant and move on.

Constraints:
- Behaviour must be identical. If a test encodes a bug, fix the implementation, not the test —
  unless the test contradicts docs/legal-rules.md, in which case stop and report it.
- Never guess, backfill or rewrite historical price rows.
- PHP 7.4-8.4 compatibility (no PHP 8-only syntax in src/).
- Gates must pass: `composer test:unit`.

Done when: measured query counts/timings improved with numbers recorded, every item covered by
tests, fast unit suite passes green, legal rules untouched, branch pushed, and Pull Request opened for user review.
```
