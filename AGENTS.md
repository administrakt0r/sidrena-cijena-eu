# AGENTS.md — AI Agent Guidelines for `sidrena-cijena`

Welcome to `sidrena-cijena` (Croatian Anchor Price Compliance Plugin for WordPress and WooCommerce).
This document is the primary instruction set for AI coding agents (Antigravity, OpenCode, Claude, Cursor, Copilot, etc.) working in this repository.

---

## 1. Golden Rule: Offload Heavy CPU Tasks to CircleCI

> [!IMPORTANT]
> **Do NOT run heavy CPU/RAM tasks locally on the host PC.**
> The host machine must remain cool, quiet, and responsive. All heavy static analysis, coding standards enforcement, containerized integration testing, and Playwright browser testing are offloaded to **CircleCI pipelines**.

### ❌ What NOT to run locally:
- `composer lint` (PHPCS over 184 files takes ~5 minutes of continuous 100% CPU).
- Full `composer analyse` (PHPStan across all classes).
- `npx wp-env start` / `npm run test:e2e` (Playwright Chromium, Docker MariaDB, WordPress containers).
- Full wp-env integration suite runs on the host.

### ✅ What is allowed locally (fast, lightweight checks):
- PHP syntax checks on modified files:
  ```bash
  php -l <modified-file.php>
  ```
- Targeted single unit tests:
  ```bash
  composer test:unit -- --filter <SpecificTestName>
  ```
- Lightweight unit test suite:
  ```bash
  composer test:unit  # completes in ~6 seconds
  ```
- Translation catalog extraction/check:
  ```bash
  php tools/i18n/make-pot.php --locale=hr_HR
  ```

---

## 2. Using CircleCI for Heavy Verification

Every git push to `master` (or feature branch) triggers CircleCI to run full quality gates in parallel:

1. **`verify` job** (Docker `cimg/php:8.4-node`):
   - PHPUnit full unit test suite (456 tests, 1623 assertions)
   - PHPCS WordPress Coding Standards across all 184 files
   - PHPStan static analysis (Level 6, 0 errors)
   - ESLint for JavaScript assets
   - Croatian translation catalog completeness check (`0 untranslated`)

2. **`integration_and_e2e` job** (Ubuntu 24.04 VM):
   - Automated WordPress & MariaDB environment (`wp-env`)
   - Containerized PHPUnit integration test suite
   - Playwright end-to-end browser test suite against port 8889

### Inspecting CI Runs via CircleCI CLI:
You can check CircleCI pipeline status directly from the terminal without leaving the session:
```bash
# Check latest runs
circleci run list

# Inspect a specific run
circleci run get <run-id>

# If a job step failed, inspect step logs
circleci job output get <job-id> --step-num <step-num>
```

---

## 3. Release Packaging Protocol

> [!NOTE]
> Release zip building and publishing is done **locally on the host machine**, NOT on CircleCI. Release packages are published directly to the public release repository `sidrena-cijena-eu`.

When a feature, fix, or prompt pack pass is completed:

1. **Bump version in all 4 required locations**:
   - `sidrena-cijena.php` (`Version:` header and `SIDRENA_CIJENA_VERSION` constant)
   - `readme.txt` (`Stable tag:` and `= X.Y.Z =` changelog section)
   - `package.json` (`"version": "X.Y.Z"`)
2. **Sync documentation**:
   - `docs/improvement-backlog.md` (mark items closed)
   - `docs/release-readiness.md`
   - `AGENTARDS/README.md` and relevant prompt pack file
3. **Build the release package locally**:
   ```bash
   tools/build-zip.sh <version>
   ```
   This generates `versions/<version>/sidrena-cijena-eu.zip`, creates SHA256 checksums, validates the archive with `unzip -t`, and publishes/syncs to `sidrena-cijena-eu`.
4. **Commit and push to origin**:
   ```bash
   git add versions/<version>/
   git commit -m "chore: archive installable <version> release"
   git push origin master
   ```
5. **Verify on CircleCI**:
   Check `circleci run list` to confirm the CI pipeline passes green.

---

## 4. Legal & Architectural Boundaries

- **Croatian statutory rules**: Never violate rules documented in [`docs/legal-rules.md`](file:///home/q/Desktop/wordpressprojects/plugins/sidrena-cijena/docs/legal-rules.md):
  - Reference dates: general **2026-09-10**, legacy **2025-05-02**.
  - 30-day lowest-price window (`start-30` to `start-1`, start date excluded).
  - Filename format: `{FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT}`.
  - History tables (`wp_sidrena_price_history`) are append-only legal records.
- **Plugin boundaries**: Only modify files within this plugin (`src/`, `assets/`, `tests/`, `languages/`, `docs/`, `tools/`, and root configs). Never alter WordPress core, themes, or WooCommerce core.
- **Standalone compatibility**: Always ensure the plugin functions properly both when WooCommerce is active and when it is absent (standalone mode).
- **Never run `wp plugin install --force`**: The repository root is bind-mounted in Docker; forcing installation will delete the local project files.
