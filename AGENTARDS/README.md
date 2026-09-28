# AGENTARDS — AI agent prompt pack for `sidrena-cijena`

Copy-paste prompts you can run with any AI coding agent (Antigravity, OpenCode, Claude, etc.) to
audit, cross-check, verify, improve and release this plugin. Run them **in order**; each prompt
is self-contained, completely up to date, and states its own done-criteria.

| # | File | Purpose | Needs Docker? | Status |
|---|------|---------|---------------|--------|
| 1 | [01-audit-and-fix.md](01-audit-and-fix.md) | Static audit: PHPCS, PHPStan, ESLint, unit tests, security sweep | No | ✅ v2.0.11 |
| 2 | [02-compliance-cross-check.md](02-compliance-cross-check.md) | Legal and specification cross-check against `docs/`, fix deviations | No | ✅ v2.0.0 |
| 3 | [03-wp-env-verification.md](03-wp-env-verification.md) | Full wp-env run: integration + E2E (port 8889) + 7-point checklist | **Yes** | ✅ v2.0.0 |
| 4 | [04-improve.md](04-improve.md) | General improvement pass: robustness, compatibility, docs sync | No | ✅ v2.0.17 |
| 5 | [05-finish-the-plugin.md](05-finish-the-plugin.md) | Finish remaining work: WordPress.org readiness, plugin audit & release | Optional | ✅ v2.0.0 |
| 6 | [06-performance-db-queries.md](06-performance-db-queries.md) | Performance 1: query tuning, N+1 removal, indexes, COUNT hotspots | Optional (measurements) | ✅ v2.0.0 |
| 7 | [07-performance-caching-runtime.md](07-performance-caching-runtime.md) | Performance 2: caching, per-request work, asset loading | Optional (measurements) | ✅ v2.0.0 |
| 8 | [08-performance-export-cron.md](08-performance-export-cron.md) | Performance 3: generation pipeline, memory, cron/cleanup jobs | Optional (measurements) | ✅ v2.0.0 |
| 9 | [09-ui-ux-improvements.md](09-ui-ux-improvements.md) | UI/UX: admin screens, public price list, accessibility, i18n consistency | No (E2E optional) | ✅ v2.0.14 |
| 10 | [10-prompt-drift-check.md](10-prompt-drift-check.md) | Prompt-pack drift audit: keep every prompt file in sync with the codebase | No | ✅ v2.0.0 |

Prompts 6–8 are the performance track (measure first, record the numbers), prompt 9 is the
UI/UX track; prompt 4 covers everything else. Performance work is logged as `PERF-xx` and
UI/UX work as `UX-xx` items in `docs/improvement-backlog.md` (existing conventions: `FIX-`,
`IMP-`, `REL-`). Prompt 10 audits the pack itself — run it any time (especially after a
session that changes the codebase) to keep every prompt honest.

## Current green baseline (v2.0.18)

Verify against this before claiming a regression; if a gate is red when you start, fix that first.
`tools/verify.sh` runs the whole host gate set in one command (add `--with-integration --with-e2e`
on Docker hosts); see `docs/testing.md`.

- Unit suite: 530 tests / 1896 assertions (`composer test:unit`).
- Integration suite: 96 tests / 454 assertions (`composer test:integration` in wp-env).
- E2E: 42 Playwright specs against port 8889 (`npm run test:e2e`).
- PHPStan level 6: 0 errors across 117 files · PHPCS clean · ESLint clean · `./local.sh` exits 0.
- Croatian catalog complete: 797 strings, 0 untranslated (`php tools/i18n/make-pot.php --locale=hr_HR`).

## Hard rules for every prompt

- **Heavy CPU Offload to CircleCI (Keep Host PC Quiet):** Do NOT run full heavy CPU/RAM commands locally (`composer lint` over 184 files, full `composer analyse`, local `wp-env` integration or Playwright E2E suites). Run only fast local sanity checks (`php -l`, `composer test:unit -- --filter ...`, `composer test:unit`, `php tools/i18n/make-pot.php --locale=hr_HR`). Heavy gates (`verify` and `integration_and_e2e`) run in the cloud via CircleCI pipelines on push. Check status using `circleci run list` and `circleci run get <id>`.
- **Local Release Packaging:** Release zip creation and publishing to the public repository `sidrena-cijena-eu` is handled locally via `tools/build-zip.sh <version>`. Never offload release packaging to CircleCI.
- **Plugin boundaries:** Work **only** inside this plugin (`src/`, `assets/`, `tests/`, `languages/`, `docs/`, `tools/`, root config files). Never touch WordPress core, theme or WooCommerce files.
- **Legal semantics:** Never change legal behavior silently: reference dates (general **2026-09-10**, legacy **2025-05-02**), the 30-day lowest-price window (`start-30` → `start-1`, start excluded), filename pattern (`{FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT}`), 30+ day archive, and blocking publication gate are governed by `docs/legal-rules.md`.
- **Append-only history:** Never invent or guess historical prices; history tables (`wp_sidrena_price_history`) are append-only legal evidence.
- **Standalone catalog:** Keep the plugin functional **with and without WooCommerce**. Never fatal when WooCommerce is absent.
- **CRITICAL - Do NOT run `wp plugin install --force`:** The host repository is directly bind-mounted into Docker containers. Running `wp plugin install <zip> --force` triggers WordPress's `Plugin_Upgrader` which deletes the directory contents before extracting!
- **Local WordPress environments:**
  - **Demo shop:** `./local.sh` starts wp-env at `http://localhost:8888` (`admin` / `password`), sets EUR/Europe-Zagreb, seeds demo items, anchors, a sale, and generates price lists. Stop with `./stoplocal.sh`.
  - **Test shop:** `npx wp-env start` starts the isolated test site at `http://localhost:8889`. Playwright E2E tests (`npm run test:e2e`) run **exclusively** against port 8889.
  - Wrap docker commands in `sg docker -c '…'` if the running shell does not inherit docker group permissions.
  - Before integration tests, clean test DB: `npx wp-env clean tests`.

## Release policy (mandatory at the end of every completed run)

Every completed run ships a new version archive following the repository convention:

1. **Bump the version in all 4 places** (they must agree exactly):
   - `sidrena-cijena.php` (header `Version:` and constant `SIDRENA_CIJENA_VERSION`)
   - `readme.txt` (`Stable tag:` and `= X.Y.Z =` changelog entry)
   - `package.json` (`"version": "X.Y.Z"`)
2. **Run fast local sanity checks**:
   ```bash
   composer test:unit          # PHPUnit fast unit suite (~6s)
   php tools/i18n/make-pot.php --locale=hr_HR # Verify 0 untranslated strings in languages/hr_HR.po
   ```
   *(Heavy static gates: PHPCS, PHPStan, integration tests, and Playwright E2E are verified automatically via CircleCI pipelines).*
3. **Build release archive and publish locally**:
   ```bash
   tools/build-zip.sh <version>
   # Builds package, computes SHA256 checksums, and syncs/publishes to public repo sidrena-cijena-eu
   unzip -t versions/<version>/sidrena-cijena-eu.zip
   ```
4. **Update documentation**:
   - Add verification notes to `docs/release-readiness.md`.
   - Update `docs/improvement-backlog.md` with closed items.
5. **Commit, push, and verify CircleCI**:
   Commit the release archive and push to `origin master`:
   ```bash
   git add versions/<version>/
   git commit -m "chore: archive installable <version> release"
   git push origin master
   circleci run list  # Ensure CircleCI pipeline runs and passes green
   ```

