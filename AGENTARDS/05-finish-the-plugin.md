# Sidrena Cijena — finish the plugin (agent handoff prompt)

> **Status (v2.0.0):** COMPLETED. Release 2.0.0 (Sidrena Cijena EU) finalized, quality gates verified, and package created.

You are the coding agent for **Sidrena Cijena**, a WordPress plugin that implements the
Croatian "sidrena cijena" (anchor price) obligation, the daily machine-readable price-list
obligation and the 30-day lowest-price rule for classified sales.

* Repository: `/home/q/Desktop/wordpressprojects/plugins/sidrena-cijena`
* Branch `master`, remote `origin https://github.com/administrakt0r/sidrena-cijena`
* Git author: configured repo-locally as `administrakt0r <markoes3@gmail.com>`
* Read `AGENTARDS/README.md` first — it defines the mandatory workflow and rules.

## Non-negotiable rules

1. **One logical item at a time.** Implement it, add unit/integration tests, update documentation,
   commit with a focused conventional message (`feat:`, `fix:`, `test:`, `docs:`, `chore:`).
2. **Never alter legal behavior silently:**
   - General reference date: **2026-09-10**; legacy category reference date: **2025-05-02**.
   - 30-day lowest-price window: `start - 30` to `start - 1` (start date excluded, day -30 included).
   - Filename pattern: `{FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT}`.
   - Archive retention: at least 30 days (`max(30, retention_days)`).
   - Mandatory publication gate blocks generation on missing anchors or unclassified promotions.
   - Price-history rows (`anchor`, `lowest_30_day`) are append-only legal evidence; never guess or backfill.
3. **Keep the plugin functional with and without WooCommerce.** Never fatal when WooCommerce is absent.
4. **All quality gates must be green before packaging and committing.**
5. **Release at the end of every completed run.** Every completed run ships a new version archive
   under `versions/<version>/`, committed and pushed to git.

## Environment & Gotchas

* Docker, PHP 8.4 + Composer, Node 24 + npm, Playwright Chromium.
* If user `q` lacks active docker permissions in the current subshell, wrap docker/wp-env commands in `sg docker -c '…'`.
* **Dev webshop:** `./local.sh` → <http://localhost:8888> (`admin` / `password`). Stop with `./stoplocal.sh`.
* **Test webshop:** `npx wp-env start` → <http://localhost:8889> (the exclusive E2E target; Playwright must never touch 8888).
* **CRITICAL:** NEVER run `wp plugin install <zip> --force` inside wp-env (it deletes the bind-mounted repository!).
* **Clean test DB:** Before running integration tests, run `npx wp-env clean tests`.
* **CI Workflow location:** CI workflow is maintained in `tools/ci/ci.yml` (ready to copy to `.github/workflows/` when pushing with a token possessing GitHub `workflow` scope).
* **Current green baseline:** 361+ unit tests / 1296+ assertions, 96+ integration tests / 454+ assertions, 42 E2E specs, PHPStan level 6 clean (0 errors, 117 files), PHPCS clean (175 files), ESLint clean, `./local.sh` exit 0.

## Quality Gates

```bash
composer test:unit          # PHPUnit unit suite
composer lint               # PHPCS WordPress Coding Standards
composer analyse            # PHPStan level 6
npm run lint:js             # ESLint
```

When Docker is available:
```bash
npx wp-env clean tests
composer test:integration   # PHPUnit integration suite in wp-env
npx wp-env start
npm run test:e2e            # Playwright E2E on port 8889
./local.sh                  # One-command local setup, must exit 0
```

## Where the work is tracked

* `docs/improvement-backlog.md` — tracks completed compliance fixes (FIX-01..12), competitor improvements (IMP-01..12), public release items (REL-01..14), performance items (PERF-xx) and UI/UX items (UX-xx).
* `docs/release-readiness.md` — records verification notes and gate results for every shipped version.
* `completion-steps.md` — compliance remediation plan and release status.

## Work queue

For focused passes use the dedicated prompts instead of improvising:
performance — [06](06-performance-db-queries.md) (queries/indexes), [07](07-performance-caching-runtime.md)
(caching/runtime/assets), [08](08-performance-export-cron.md) (generation/memory/cron);
UI/UX — [09](09-ui-ux-improvements.md). They carry the known hotspots and their own done-criteria.

1. **REL-03 — WordPress.org submission readiness:**
   - Review `readme.txt` against WordPress.org plugin directory requirements.
   - Confirm screenshots, banner, icon assets in `.wordpress-org/`.
   - Verify license (GPL-2.0-or-later) and external services/privacy declarations.
   - Ensure clean code standards (nonces, escaping, capability checks on all endpoints).
2. **Defect & Edge-Case Sweeps:**
   - Sweep `src/` against `docs/legal-rules.md`, `docs/feature-acceptance.md`, and `docs/price-list-format.md`.
   - Check edge cases: timezones, DST boundaries, first-offer boundaries, variation identity, service items, CSV formula neutralization, error reporting.
   - Fix real defects only (no speculative abstractions).
3. **Translations:**
   - If strings are added or edited, update `tools/i18n/en_US.php` and `tools/i18n/hr_HR.php`, then run:
     `php tools/i18n/make-pot.php --locale=hr_HR`
   - Confirm 0 untranslated strings in `languages/hr_HR.po` (baseline: 705 translated strings / 0 untranslated).
4. **Mandatory Release Procedure:**
   - Bump version in all 4 places:
     - `sidrena-cijena.php` (header `Version:` and `SIDRENA_CIJENA_VERSION`)
     - `readme.txt` (`Stable tag:` and changelog entry)
     - `package.json` (`"version": "X.Y.Z"`)
   - Ensure all quality gates pass.
   - Build archive: `tools/build-zip.sh <version>`.
   - Verify archive: `unzip -t versions/<version>/sidrena-cijena-eu.zip`.
   - Update `docs/release-readiness.md` and `docs/improvement-backlog.md`.
   - Commit all changes including `versions/<version>/` and push to `origin master`.

## Definition of done

* All gates green.
* Zero untranslated strings in Croatian catalog.
* Installable ZIP archive built under `versions/<version>/`, verified, committed, and pushed.
* Complete report summarizing completed work, gate results, release version, and confirmation of legal rule integrity.
