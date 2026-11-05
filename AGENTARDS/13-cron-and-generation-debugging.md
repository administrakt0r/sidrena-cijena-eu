# Prompt 13 — Cron, price list generation and archive debugging

```
You are diagnosing and fixing issues with the daily price list generation pipeline in the
Sidrena Cijena EU plugin (sidrena-cijena.php is the bootstrap).

BEFORE YOU START — read AGENTARDS/README.md and docs/legal-rules.md. The daily price list
is a LEGAL OBLIGATION under NN 101/2026-1213. Failure to publish daily (before 08:00) is
subject to statutory fines. Never delete archive files, never skip a generation day, never
backfill price-history rows.

MANDATORY BRANCH & PULL REQUEST WORKFLOW:
If fixing generation bugs in the codebase:
1. Checkout a dedicated branch:
   `git checkout -b fix/generation-pipeline-repair`
2. Commit each fix atomically (`fix:`, `test:`).
3. Push branch and open a Pull Request for user review:
   `git push -u origin fix/generation-pipeline-repair`
   `gh pr create --base master --title "fix: price list generation and cron pipeline" --body "..."`
   Do NOT push directly to master or merge the PR yourself.

## Architecture overview

```
Cron/Scheduler (init hook) → maybe_catchup() → DailyGeneration → GenerationService
  → PriceListBuilder::build() → CatalogProvider::hydrate()
  → Publisher::publish() → CSV/XML file on disk
  → PublicationRepository (DB row)
  → PublicPage::render_list() / render_archive() (front-end)
  → REST: /wp-json/sidrena-cijena/v1/prices (JSON)
  → Static feeds: /sidrena-cijena.csv, /sidrena-cijena.xml (via rewrite rules)
```

## Common generation issues and fixes

### Issue 1: Archive page (/arhiva-sidrene-cijene/) is empty

**Symptom:** Page loads but shows no published files.

**Diagnosis:**
```bash
# In local env (./local.sh running):
sg docker -c "npx wp-env run cli wp sidrena-cijena status"
# Look for: last_generation_at, last_generation_status, pending locations

sg docker -c "npx wp-env run cli wp post list --post_type=sidrena_publication --post_status=any"
# If 0 posts: generation has never run successfully
```

**Fix:**
```bash
# Force a generation run:
sg docker -c "npx wp-env run cli wp sidrena-cijena generate --force"
```

### Issue 2: Static feeds return 404 (/sidrena-cijena.csv, /sidrena-cijena.xml)

**Symptoms:** 404 on https://example.com/sidrena-cijena.csv

**Diagnosis:**
1. Generation has not run → fix Issue 1 first.
2. WordPress rewrite rules need flushing:
   ```bash
   wp rewrite flush
   ```

### Issue 3: Generation runs but produces wrong CSV/XML format

**Run compliance tests:**
```bash
composer test:unit -- --filter "CsvWriter|XmlWriter|PriceListBuilder|FilenameGenerator"
```

### Issue 4: Cron misses runs (daily generation not happening automatically)

```bash
wp cron event list | grep sidrena
wp cron event run sidrena_cijena_daily_generation
```

### Issue 5: Generation blocked by compliance findings

```bash
wp sidrena-cijena validate
# Shows all findings with codes (e.g. MISSING_ANCHOR, MISSING_LOCATION_ADDRESS)
# Force generation ignoring blocking errors:
wp sidrena-cijena generate --force
```

## Quality gates & Pull Request

After any fix to the generation pipeline:
```bash
composer test:unit                           # 535/1916 must pass
composer test:unit -- --filter "Generation|Publisher|PriceList|Cron"
php tools/i18n/make-pot.php --locale=hr_HR   # 0 untranslated
```

Push branch and open Pull Request:
```bash
git push -u origin fix/generation-pipeline-repair
gh pr create --base master --title "fix: price list generation and cron pipeline" --body "..."
```

Done when: generation runs cleanly, produces valid CSV/XML matching docs/price-list-format.md,
archive page shows published files, unit tests pass, branch is pushed, and PR is opened for review.
```
