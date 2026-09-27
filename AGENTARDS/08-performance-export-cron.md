# Prompt 8 — Performance pass 3: export pipeline, memory and cron jobs (Docker optional, needed for measurements)

> **Status (v2.0.0):** COMPLETED. Generation pipeline, incremental hashing, and cron cleanup optimizations completed and verified.

```
You are optimising the generation pipeline of the WordPress plugin in the current
directory (sidrena-cijena.php is the bootstrap): daily price-list generation, file
publishing, checksums and cleanup. Goal: constant-ish memory on large catalogs, one
validation per generation, no redundant I/O — with byte-identical output.

Rules: work only inside this plugin; never touch WordPress core, themes or WooCommerce.
Legal semantics must not change (docs/legal-rules.md): the CSV/XML contracts
(docs/price-list-format.md), the filename pattern (docs/filename-profile.md), the
checksum/fingerprint reporting, the 30+ day archive retention (max(30, retention_days))
and the blocking publication gate (FR-007) all stay exactly as they are. Append-only
price history. PHP 7.4-8.4. Commit each item separately (perf: / fix:), with tests.

Baseline (v2.0.0): 361 unit tests / 1296 assertions green — keep green, grow the suite.

Measurement (first and last; Docker optional for the fixes):
- `./local.sh` → http://localhost:8888 (admin / password). Wrap docker/wp-env in
  `sg docker -c '…'` if needed. NEVER run `wp plugin install <zip> --force` (deletes the
  bind-mounted repository).
- Seed a large catalog (WP-CLI `wp sidrena-cijena seed-test-data --yes` or the import
  command with a generated fixture — inspect src/Cli/SeedTestDataCommand.php for the real
  flags) and record: `time wp sidrena-cijena generate --force`, memory_get_peak_usage()
  around the run, and the size of the generated files. Numbers go into
  docs/improvement-backlog.md (PERF-xx).

Work items — verify each against the real code first:
1. Validation runs twice per location per generation: GenerationService::generate() calls
   catalog_report() per output format (csv + xml), so the full catalog is validated twice
   (and hydrated twice). Compute the compliance report once per location/generation and
   share it between the writers. The publication gate must still block on ERROR findings
   exactly as today.
2. Whole-catalog-in-memory export: PriceListBuilder::build() accumulates all CatalogItem
   objects and all rows; fingerprint() copies the payload again, json-encodes and sha256s
   it per format; then the document is built as one big string (CsvWriter::to_string(),
   XmlWriter::to_string()) before Publisher::publish() writes it. Peak memory is ~3-4x the
   catalog. Streaming writers already exist but are unused (CsvWriter::write(),
   XmlWriter::write() with openURI). Stream to the target file, hash incrementally
   (hash_init/hash_update) while writing, and prove the win with the large catalog.
   Output must stay byte-identical: diff against tests/fixtures/example-price-list.csv and
   example-price-list.xml structurally in tests.
3. Publisher::publish() re-reads the file it just wrote with hash_file('sha256') to report
   the checksum. Compute the checksum during the write instead; the reported checksum
   value must not change.
4. Daily run churn: Cron/DailyGeneration::execute_generation() passes 'force' => true,
   which bypasses Publisher's unchanged-content skip. FIRST read docs/legal-rules.md and
   docs/price-list-format.md: if a fresh daily file (new timestamped filename, new
   sequence) is legally expected, KEEP publishing daily and only remove redundant work
   inside (shared validation per item 1, no double hashing per item 3). Never silently
   skip a day, and never change the filename pattern or sequence semantics.
5. LocationRepository::next_sequence() does UPDATE + full SELECT per call, and Publisher's
   filename-availability loop calls it up to 10 times per publication. Collapse to one
   query per publication.
6. CleanupJob (src/Cron/CleanupJob.php): mark_expired() re-reads each candidate row and
   PublicationRepository::update() runs get($id) twice per update. One read + one UPDATE
   per row (or one batched UPDATE), keeping the LIMIT 500 batching and the retention rule.
7. REST download endpoints (PriceListController::download()) re-serialise CSV/XML from the
   cached rows on every request. Cache the serialised document per published checksum, or
   serve the published file — the bytes must match the published file exactly.

Constraints:
- Behaviour identical; if a test encodes a bug, fix the implementation, not the test —
  unless it contradicts docs/legal-rules.md, in which case stop and report.
- Never guess or backfill price-history rows; never delete archive files early.
- Gates: `composer test:unit && composer lint && composer analyse && npm run lint:js`;
  with Docker also `npx wp-env clean tests && composer test:integration` and
  `npm run test:e2e` (port 8889 only).

Release (mandatory per AGENTARDS/README.md): bump the version in all 4 places, run
`tools/build-zip.sh <version>`, verify `unzip -t versions/<version>/sidrena-cijena-eu.zip`,
update docs/improvement-backlog.md (PERF-xx) and docs/release-readiness.md, commit and
push to origin master.

Done when: measured generation time and peak memory improved with numbers recorded,
generated files verified byte-identical against fixtures, all gates green, legal rules
untouched, release archive built and pushed.
```
