# AGENTS.md — AI Agent Guidelines for `titanworks-bazna-cijena`

Welcome to `titanworks-bazna-cijena` (Titanworks Bazna Cijena — Croatian Anchor and Base Price Compliance Plugin for WordPress and WooCommerce).
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

Every git push of a feature branch or PR triggers CircleCI to run full quality gates in parallel:

1. **`verify` job** (Docker `cimg/php:8.4-node`):
   - PHPUnit full unit test suite (535+ tests, 1916+ assertions)
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
```

---

## 3. Mandatory Branch & Pull Request (PR) Policy

> [!IMPORTANT]
> **NEVER push directly to `main`. ALWAYS open a Pull Request.**
> All agent work must be performed on a dedicated branch and submitted as a GitHub Pull Request for human review before merging.

### Standard Agent Workflow:
1. **Create and switch to a feature/fix branch** before making changes:
   ```bash
   git checkout -b <type>/<short-description>
   # Examples: feat/add-label-preset, fix/tooltip-clamp, perf/query-index, ux/mobile-table
   ```
2. **Implement changes and atomic commits**:
   Use standard conventional commits (`feat:`, `fix:`, `perf:`, `test:`, `docs:`, `chore:`).
3. **Run fast local sanity checks**:
   ```bash
   composer test:unit                         # Must pass 100%
   php tools/i18n/make-pot.php --locale=hr_HR # 0 untranslated
   ```
4. **Push the branch to origin**:
   ```bash
   git push -u origin <branch-name>
   ```
5. **Open a GitHub Pull Request via `gh` CLI**:
   ```bash
   gh pr create --base main --head <branch-name> \
     --title "<type>: <brief title>" \
     --body "<summary of changes, test coverage, and verification results>"
   ```
6. **STOP and request review**:
   Do **NOT** merge the PR yourself unless explicitly authorized. Output the PR link to the user and request their review.
7. **Release Packaging**:
   Once the PR is approved and merged into `main`, the automated `.git/hooks/post-merge` hook or GitHub Action automatically generates the installable zip package.

---

## 4. Legal & Architectural Boundaries

- **Croatian statutory rules**: Never violate rules documented in [`docs/legal-rules.md`](file:///home/q/Desktop/wordpress/plugins/sidrena-cijena/docs/legal-rules.md):
  - Reference dates: general **2026-09-10**, legacy **2025-05-02**.
  - Statutory enforcement date: **17 November 2026** (NN 110/2026).
  - 30-day lowest-price window (`start-30` to `start-1`, start date excluded).
  - Filename format: `{FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT}`.
  - History tables (`wp_sidrena_price_history`) are append-only legal records.
- **Label Presets**: Only use wording legally permitted by the Ministry of Economy clarification (22 Sep 2026). Dynamic tokens `{datum}`, `{date}`, `{datum_iso}` resolve automatically to category reference dates.
- **Tooltip accuracy**: The tooltip must accurately describe the anchor price as the benchmark retail price under statutory direct price control measures, NOT the 30-day lowest-price rule.
- **Plugin boundaries**: Only modify files within this plugin (`src/`, `assets/`, `tests/`, `languages/`, `docs/`, `tools/`, and root configs). Never alter WordPress core, themes, or WooCommerce core.
- **Standalone compatibility**: Always ensure the plugin functions properly both when WooCommerce is active and when it is absent (standalone mode).
- **Never run `wp plugin install --force`**: The repository root is bind-mounted in Docker; forcing installation will delete the local project files.
