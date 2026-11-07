# Prompt 1 — Audit and fix (no Docker needed)

> **Status (v2.0.11):** COMPLETED. Full static audit pass completed, verified, and shipped.

```
You are maintaining the WordPress plugin in the current directory
(titanworks-bazna-cijena.php is the bootstrap). Do a full static audit and fix
everything you find. Rules: work only inside this plugin; do not touch
WordPress core, themes or WooCommerce; price-history tables are append-only.

MANDATORY BRANCH & PULL REQUEST WORKFLOW:
1. Checkout a dedicated branch before making changes:
   `git checkout -b fix/audit-and-fix`
2. Commit each logical fix separately with conventional messages (fix:/test:/docs:/chore:).
3. When finished, push the branch and open a Pull Request for user review:
   `git push -u origin fix/audit-and-fix`
   `gh pr create --base master --title "fix: static audit, lint and security remediation" --body "..."`
   Do NOT push directly to master or merge the PR yourself.

Steps:
1. Run `composer install` (if vendor/ is missing) and `npm install` (if node_modules/ is missing).
   Run fast local unit tests: `composer test:unit`. Heavy gates (full PHPCS across all files, PHPStan)
   can be offloaded to CircleCI pipelines to keep host PC quiet (per AGENTS.md).
2. Record every failure, warning and risky test. Fix them one by one:
   - Lint: WordPress Coding Standards via phpcs.xml.dist
     (PHPCompatibilityWP, testVersion 7.4-).
   - Static analysis: PHPStan level 6 via phpstan.neon.dist.
   - JS Lint: ESLint via .eslintrc.js (browser environment, ES6).
   - Unit tests must stay green (614+ tests / 2335+ assertions); if a test encodes a bug,
     fix the implementation, not the test - unless the test contradicts docs/legal-rules.md,
     in which case stop and report it.
3. Check translations:
   Run `php tools/i18n/make-pot.php`. If translatable strings were added or changed:
   update tools/i18n/en_US.php and tools/i18n/hr_HR.php, then run
   `php tools/i18n/make-pot.php --locale=hr_HR` to regenerate
   languages/titanworks-bazna-cijena.pot, languages/hr_HR.po, and languages/titanworks-bazna-cijena-hr.mo.
   Confirm 0 untranslated strings (baseline: 875 translated strings / 0 untranslated in languages/hr_HR.po).
4. Security sweep:
   - Every admin action requires capability check (manage_sidrena_cijena) + valid nonce.
   - Every echo is properly escaped (esc_html, esc_attr, esc_url).
   - Every SQL query is prepared ($wpdb->prepare) or safely parameterized.
   - Uploaded/imported file paths are validated against allowed directories (CsvParser::is_allowed_path).
   - No customer personal data in logs (Logger::sanitize_context).
   - Post-activation redirect in WelcomeBanner::maybe_redirect() guards against bulk activation
     ($_GET['activate-multi']), network admin, cron, and REST requests.
   Add a regression test for each gap found.
5. Quality verification & Pull Request:
   - Run fast unit suite (`composer test:unit`).
   - If version bump was needed, bump version in titanworks-bazna-cijena.php, readme.txt, package.json.
   - Append verification notes to docs/release-readiness.md and update docs/improvement-backlog.md.
   - Push branch and open PR via `gh pr create --base master`.
   - Output the PR link to the user and request their review.

Done when: unit suite passes (614 unit tests / 2335 assertions), lint exits 0, PHPStan reports no errors,
JS lint exits 0, translations are complete (875 translated strings / 0 untranslated in languages/hr_HR.po),
branch is pushed and Pull Request is opened for user review.
```
