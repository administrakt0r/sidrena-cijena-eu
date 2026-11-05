# Prompt 8 — Performance pass 3: export pipeline, memory and cron jobs (Docker optional, needed for measurements)

> **Status (v2.0.0):** COMPLETED. Generation pipeline, incremental hashing, and cron cleanup optimizations completed and verified.

```
You are optimising the generation pipeline of the WordPress plugin in the current
directory (sidrena-cijena.php is the bootstrap): daily price-list generation, file
publishing, checksums and cleanup. Goal: constant-ish memory on large catalogs, one
validation per generation, no redundant I/O — with byte-identical output.

Rules: work only inside this plugin; never touch WordPress core, themes or WooCommerce.
Legal semantics must not change (docs/legal-rules.md): the CSV/XML contracts, filename
pattern, checksum reporting, 30+ day archive retention and the blocking publication gate
all stay exactly as they are. Append-only price history. PHP 7.4-8.4.

MANDATORY BRANCH & PULL REQUEST WORKFLOW:
1. Checkout a dedicated branch before making changes:
   `git checkout -b perf/export-pipeline-optimization`
2. Commit each item separately (perf: / fix:), with tests.
3. Push branch and open a Pull Request for user review:
   `git push -u origin perf/export-pipeline-optimization`
   `gh pr create --base master --title "perf: streaming export pipeline and cron optimization" --body "..."`
   Do NOT push directly to master or merge the PR yourself.

Baseline (v2.0.20): 535 unit tests / 1916 assertions green — keep green, grow the suite.

Measurement (first and last; Docker optional for the fixes):
- `./local.sh` → http://localhost:8888 (admin / password). Wrap docker/wp-env in
  `sg docker -c '…'` if needed. NEVER run `wp plugin install <zip> --force` (deletes the
  bind-mounted repository).
- Seed a large catalog (`wp sidrena-cijena seed-test-data --yes`) and record:
  `time wp sidrena-cijena generate --force`, memory_get_peak_usage(), and generated file size.
  Numbers go into docs/improvement-backlog.md (PERF-xx).

Work items — verify each against the real code first:
1. Validation runs twice per location per generation: GenerationService::generate() calls
   catalog_report() per output format (csv + xml), so the full catalog is validated twice.
   Compute the compliance report once per location/generation and share it between writers.
2. Whole-catalog-in-memory export: PriceListBuilder::build() accumulates all CatalogItem
   objects and all rows; fingerprint() copies the payload again, json-encodes and sha256s
   it per format; then the document is built as one big string before Publisher::publish()
   writes it. Peak memory is ~3-4x the catalog. Stream to target file, hash incrementally
   (hash_init/hash_update) while writing. Output must stay byte-identical: diff against
   tests/fixtures/example-price-list.csv and example-price-list.xml.
3. Publisher::publish() re-reads the file it just wrote with hash_file('sha256') to report
   the checksum. Compute the checksum during the write instead.
4. Daily run churn: Cron/DailyGeneration::execute_generation() passes 'force' => true,
   which bypasses Publisher's unchanged-content skip. Read docs/legal-rules.md: if a fresh
   daily file is legally expected, keep publishing daily and only remove redundant work.
5. LocationRepository::next_sequence() does UPDATE + full SELECT per call. Collapse to one
   query per publication.
6. CleanupJob (src/Cron/CleanupJob.php): mark_expired() re-reads each candidate row and
   PublicationRepository::update() runs get($id) twice. One read + one UPDATE per row.
7. REST download endpoints (PriceListController::download()) re-serialise CSV/XML from the
   cached rows on every request. Cache serialised document per published checksum or serve file.

Done when: measured generation time and peak memory improved with numbers recorded,
generated files verified byte-identical against fixtures, fast unit suite green,
legal rules untouched, branch pushed, and Pull Request opened for user review.
```
