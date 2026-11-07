# AGENTARDS — AI agent prompt pack for `sidrena-cijena`

Copy-paste prompts you can run with any AI coding agent (Antigravity, OpenCode, Claude, etc.) to
audit, cross-check, verify, improve and release this plugin. Run them **in order**; each prompt
is self-contained, completely up to date, and states its own done-criteria.

| # | File | Purpose | Needs Docker? | Status |
|---|------|---------|---------------|--------|
| 1 | [01-audit-and-fix.md](01-audit-and-fix.md) | Static audit: PHPCS, PHPStan, ESLint, unit tests, security sweep | No | ✅ v2.0.11 |
| 2 | [02-compliance-cross-check.md](02-compliance-cross-check.md) | Legal and specification cross-check against `docs/`, fix deviations | No | ✅ v2.0.22 |
| 3 | [03-wp-env-verification.md](03-wp-env-verification.md) | Full wp-env run: integration + E2E (port 8889) + 7-point checklist + block theme + label preset checks | **Yes** | ✅ v2.0.0 |
| 4 | [04-improve.md](04-improve.md) | General improvement pass: robustness, compatibility, docs sync | No | ✅ v2.0.17 |
| 5 | [05-finish-the-plugin.md](05-finish-the-plugin.md) | Finish remaining work: open backlog items, readme.txt Stable tag, WP.org readiness | Optional | ✅ v2.0.0 |
| 6 | [06-performance-db-queries.md](06-performance-db-queries.md) | Performance 1: query tuning, N+1 removal, indexes, COUNT hotspots | Optional (measurements) | ✅ v2.0.0 |
| 7 | [07-performance-caching-runtime.md](07-performance-caching-runtime.md) | Performance 2: caching, per-request work, asset loading | Optional (measurements) | ✅ v2.0.0 |
| 8 | [08-performance-export-cron.md](08-performance-export-cron.md) | Performance 3: generation pipeline, memory, cron/cleanup jobs | Optional (measurements) | ✅ v2.0.0 |
| 9 | [09-ui-ux-improvements.md](09-ui-ux-improvements.md) | UI/UX: admin screens, public price list, accessibility, i18n consistency, tooltip, label presets | No (E2E optional) | ✅ v2.0.14 |
| 10 | [10-prompt-drift-check.md](10-prompt-drift-check.md) | Prompt-pack drift audit: keep every prompt file in sync with the codebase | No | ✅ v2.0.0 |
| 11 | [11-production-deployment.md](11-production-deployment.md) | Deploy to https://wordpress.sidrenacijena.eu/: upload, verify Croatian headers, generate price list, purge cache | SSH optional | 🆕 v2.0.20 |
| 12 | [12-label-presets-display-guide.md](12-label-presets-display-guide.md) | Label presets, display styles, dynamic date tokens, tooltip debugging guide | No | 🆕 v2.0.20 |
| 13 | [13-cron-and-generation-debugging.md](13-cron-and-generation-debugging.md) | Cron, daily price list generation, archive page, static feeds 404, compliance findings | Yes (local testing) | 🆕 v2.0.20 |

Prompts 6–8 are the performance track (measure first, record the numbers), prompt 9 is the
UI/UX track; prompt 4 covers everything else. Performance work is logged as `PERF-xx` and
UI/UX work as `UX-xx` items in `docs/improvement-backlog.md` (existing conventions: `FIX-`,
`IMP-`, `REL-`). Prompt 10 audits the pack itself — run it any time (especially after a
session that changes the codebase) to keep every prompt honest. Prompt 11 is the production
deployment checklist. Prompt 12 is the label customisation reference. Prompt 13 is the
generation/cron debugging guide.

## Current green baseline (v2.0.20)

Verify against this before claiming a regression; if a gate is red when you start, fix that first.
`tools/verify.sh` runs the whole host gate set in one command (add `--with-integration --with-e2e`
on Docker hosts); see `docs/testing.md`.

- Unit suite: **535 tests / 1916 assertions** (`composer test:unit` — ~6 s).
- Integration suite: 96 tests / 454 assertions (`composer test:integration` in wp-env).
- E2E: 42 Playwright specs against port 8889 (`npm run test:e2e`).
- PHPStan level 6: 0 errors · PHPCS clean · ESLint clean · `./local.sh` exits 0.
- Croatian catalog complete: **809 strings, 0 untranslated** (`php tools/i18n/make-pot.php --locale=hr_HR`).
- Local demo site: http://localhost:8888 (admin / password, started via `./local.sh`).
- Local test site: http://localhost:8889 (Playwright E2E target only, started via `npx wp-env start`).

## Mandatory Workflow & Pull Request (PR) Policy

> [!IMPORTANT]
> **NEVER commit directly to `master`. Always open a Pull Request on a new branch.**
> All agents must adhere to the following workflow for every prompt run so the user can review edits before merging:

1. **Create a new branch**:
   Before making any edits, checkout a new branch describing the task:
   ```bash
   git checkout -b <type>/<short-description>
   # Examples: feat/add-anchor-preset, fix/tooltip-flip, perf/query-optimization
   ```
2. **Make atomic commits**:
   Commit each logical step with conventional commit prefixes (`feat:`, `fix:`, `perf:`, `test:`, `docs:`, `chore:`).
3. **Run fast local sanity checks**:
   ```bash
   composer test:unit                         # Must pass 535/1916 or more
   php tools/i18n/make-pot.php --locale=hr_HR # Must report 0 untranslated
   ```
4. **Push the branch to origin**:
   ```bash
   git push -u origin <branch-name>
   ```
5. **Open a GitHub Pull Request via `gh` CLI**:
   ```bash
   gh pr create --base master --head <branch-name> \
     --title "<type>: <concise description>" \
     --body "<summary of changes, test coverage, and verification results>"
   ```
6. **Handoff for review**:
   Do **NOT** merge the pull request. Output the PR link to the user for human review and approval.
7. **Automated release zip**:
   When the user merges the PR into `master`, the automated `.git/hooks/post-merge` hook (or GitHub Action) builds the release zip in `versions/`.

## Hard rules for every prompt

- **Heavy CPU Offload to CircleCI (Keep Host PC Quiet):** Do NOT run full heavy CPU/RAM commands locally (`composer lint` over 184 files, full `composer analyse`, local `wp-env` integration or Playwright E2E suites). Run only fast local sanity checks (`php -l`, `composer test:unit -- --filter ...`, `composer test:unit`, `php tools/i18n/make-pot.php --locale=hr_HR`). Heavy gates (`verify` and `integration_and_e2e`) run in the cloud via CircleCI pipelines on push. Check status using `circleci run list` and `circleci run get <id>`.
- **Plugin boundaries:** Work **only** inside this plugin (`src/`, `assets/`, `tests/`, `languages/`, `docs/`, `tools/`, root config files). Never touch WordPress core, theme or WooCommerce files.
- **Legal semantics:** Never change legal behavior silently: reference dates (general **2026-09-10**, legacy **2025-05-02**), the 30-day lowest-price window (`start-30` → `start-1`, start excluded), filename pattern (`{FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT}`), 30+ day archive, enforcement deadline (**17 November 2026**, NN 110/2026), and blocking publication gate are governed by `docs/legal-rules.md`.
- **Permitted label wording:** Anchor label presets must use wording legally permitted by the Ministry of Economy clarification (22 Sep 2026): "Sidrena cijena", "Bazna cijena", "Referentna cijena", "Dodatna cijena", "Cijena na dan [datum]", "Cijena na [datum]", "Najniža redovna cijena ([datum])". Dynamic tokens {datum}/{date}/{datum_iso} must resolve to the category-appropriate reference date (2025-05-02 for legacy, 2026-09-10 for general).
- **Tooltip accuracy:** The anchor tooltip must accurately describe the anchor as the "redovna maloprodajna cijena na propisani referentni datum" — it is NOT the 30-day lowest price. Settings::all() auto-migrates old incorrect tooltip text.
- **Append-only history:** Never invent or guess historical prices; history tables (`wp_sidrena_price_history`) are append-only legal evidence.
- **Standalone catalog:** Keep the plugin functional **with and without WooCommerce**. Never fatal when WooCommerce is absent.
- **CRITICAL - Do NOT run `wp plugin install --force`:** The host repository is directly bind-mounted into Docker containers. Running `wp plugin install <zip> --force` triggers WordPress's `Plugin_Upgrader` which deletes the directory contents before extracting!
- **Local WordPress environments:**
  - **Demo shop:** `./local.sh` starts wp-env at `http://localhost:8888` (`admin` / `password`), sets EUR/Europe-Zagreb, seeds demo items, anchors, a sale, and generates price lists. Stop with `./stoplocal.sh`.
  - **Test shop:** `npx wp-env start` starts the isolated test site at `http://localhost:8889`. Playwright E2E tests (`npm run test:e2e`) run **exclusively** against port 8889.
  - Wrap docker commands in `sg docker -c '…'` if the running shell does not inherit docker group permissions.
  - Before integration tests, clean test DB: `npx wp-env clean tests`.
