# Prompt 13 — Cron, price list generation and archive debugging

```
You are diagnosing and fixing issues with the daily price list generation pipeline in the
Sidrena Cijena EU plugin (sidrena-cijena.php is the bootstrap).

BEFORE YOU START — read AGENTARDS/README.md and docs/legal-rules.md. The daily price list
is a LEGAL OBLIGATION under NN 101/2026-1213. Failure to publish daily (before 08:00) is
subject to statutory fines. Never delete archive files, never skip a generation day, never
backfill price-history rows.

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

sg docker -c "npx wp-env run cli wp option get sidrena_cijena_settings" | python3 -m json.tool
# Check: generation_time (default '08:00'), location configured
```

**Fix:**
```bash
# Force a generation run:
sg docker -c "npx wp-env run cli wp sidrena-cijena generate --force"

# Then verify:
sg docker -c "npx wp-env run cli wp sidrena-cijena status"
# Should show: last_generation_at = now, files in uploads/sidrena-cijena/
```

**On production (SSH):**
```bash
wp sidrena-cijena generate --force --path=/path/to/wordpress
# Or via admin: Sidrena Cijena → Price Lists → "Generate now"
```

### Issue 2: Static feeds return 404 (/sidrena-cijena.csv, /sidrena-cijena.xml)

**Symptoms:** 404 on https://example.com/sidrena-cijena.csv

**Diagnosis:**
1. Generation has not run → fix Issue 1 first.
2. WordPress rewrite rules need flushing:
   ```bash
   wp rewrite flush
   # Or: visit wp-admin/options-permalink.php and click Save
   ```
3. The feed URL is registered as a custom rewrite rule in src/Plugin.php.
   Verify the rule exists: `wp rewrite list | grep sidrena`.

### Issue 3: Generation runs but produces wrong CSV/XML format

**Symptoms:** Ministry portal rejects uploaded file; wrong column order, wrong decimal separator.

**Reference:** docs/price-list-format.md, tests/fixtures/example-price-list.csv

**Diagnosis:**
```bash
# Generate and inspect:
sg docker -c "npx wp-env run cli wp sidrena-cijena generate --force"
# Find the file:
find /path/to/uploads/sidrena-cijena/ -name "*.CSV" | head -3
# Inspect:
head -5 <generated-file.CSV>
# Expected: semicolon delimiter, comma decimals, 12 columns, Croatian headers
```

**Run compliance tests:**
```bash
composer test:unit -- --filter "CsvWriter|XmlWriter|PriceListBuilder|FilenameGenerator"
```

### Issue 4: Cron misses runs (daily generation not happening automatically)

**Symptoms:** `wp sidrena-cijena status` shows last_generation_at from yesterday or earlier.

**Diagnosis:**
1. WP-Cron may not be firing (server doesn't get traffic, or DISABLE_WP_CRON = true).
2. Check if the cron job is registered:
   ```bash
   wp cron event list | grep sidrena
   # Should show: sidrena_cijena_daily_generation event at next scheduled time
   ```
3. Test fire cron manually:
   ```bash
   wp cron event run sidrena_cijena_daily_generation
   wp sidrena-cijena status
   ```
4. If DISABLE_WP_CRON is true in wp-config.php, set up a real server cron:
   ```cron
   0 7 * * * wget -q -O - https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   # Or use WP-CLI directly:
   0 7 * * * wp sidrena-cijena generate --path=/var/www/html --allow-root
   ```

### Issue 5: Generation blocked by compliance findings

**Symptoms:** `wp sidrena-cijena generate` exits with code 3 (blocking error).
Or: admin shows "Cannot generate — blocking compliance findings".

**Diagnosis:**
```bash
wp sidrena-cijena validate
# Shows all findings with codes (e.g. MISSING_ANCHOR, MISSING_LOCATION_ADDRESS)
```

**Fix by finding code:**
- `MISSING_ANCHOR`: product has no anchor price recorded. Go to WP Admin → product edit → Sidrena tab → record anchor.
- `MISSING_LOCATION_ADDRESS`: location has no address. Go to Sidrena Cijena → Locations → edit location → add address and city.
- `UNCLASSIFIED_PROMOTION`: product is on sale but no sale type is set. Edit product → Sidrena tab → set sale type.

**Allow generation despite findings (non-blocking mode):**
The plugin has `allow_generation_with_errors = true` by default, which lets daily generation
proceed and uses a safe fallback identifier 'P1' when the facility identifier is missing.
```bash
# Force generation ignoring all blocking errors:
wp sidrena-cijena generate --force
```

### Issue 6: Generation produces 0 items (empty CSV/XML)

**Symptoms:** File is generated but only has header rows.

**Diagnosis:**
1. No products are classified as catalog items:
   ```bash
   wp sidrena-cijena status
   # Look for: catalog_item_count
   ```
2. All products are out-of-stock and filtered:
   Check `docs/price-list-format.md` for availability rules.
3. No WooCommerce products / no anchor prices set:
   ```bash
   wp sidrena-cijena validate
   # Check for MISSING_ANCHOR on all products
   ```

## Testing generation locally (wp-env)

```bash
# Full generation test cycle:
cd /home/q/Desktop/wordpress/plugins/sidrena-cijena

# Start local demo environment (if not running):
./local.sh

# Check status:
sg docker -c "npx wp-env run cli wp sidrena-cijena status"

# Force generation:
sg docker -c "npx wp-env run cli wp sidrena-cijena generate --force"

# Verify files:
sg docker -c "npx wp-env run cli ls /var/www/html/wp-content/uploads/sidrena-cijena/"

# Check public pages:
curl -I http://localhost:8888/sidrena-cjenik/
curl -I http://localhost:8888/arhiva-sidrene-cijene/
curl -I http://localhost:8888/sidrena-cijena.csv

# Run unit tests for generation:
composer test:unit -- --filter "GenerationService|Publisher|PriceListBuilder"
```

## Quality gates

After any fix to the generation pipeline:
```bash
composer test:unit                           # 535/1916 must pass
composer test:unit -- --filter "Generation|Publisher|PriceList|Cron"
php tools/i18n/make-pot.php --locale=hr_HR   # 0 untranslated
```

Release procedure: follow AGENTARDS/README.md (bump version, build zip, verify, commit, push).

Done when: generation runs cleanly, produces valid CSV/XML matching docs/price-list-format.md,
archive page shows published files, static feeds return 200, and unit tests pass.
```
