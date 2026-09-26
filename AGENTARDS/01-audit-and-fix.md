# Prompt 1 — Audit and fix (no Docker needed)

```
You are maintaining the WordPress plugin in the current directory
(sidrena-cijena.php is the bootstrap). Do a full static audit and fix
everything you find. Rules: work only inside this plugin; do not touch
WordPress core, themes or WooCommerce; price-history tables are append-only;
commit each logical fix separately with conventional messages (fix:/test:/docs:/chore:).

Steps:
1. Run `composer install` (if vendor/ is missing) and `npm install` (if node_modules/ is missing),
   then in order run all four static gates:
   `composer test:unit`
   `composer lint`
   `composer analyse`
   `npm run lint:js`
2. Record every failure, warning and risky test. Fix them one by one:
   - Lint: WordPress Coding Standards via phpcs.xml.dist
     (PHPCompatibilityWP, testVersion 7.4-).
   - Static analysis: PHPStan level 6 via phpstan.neon.dist.
   - JS Lint: ESLint via .eslintrc.js (browser environment, ES6).
   - Unit tests must stay green (301+ tests / 1078+ assertions); if a test encodes a bug,
     fix the implementation, not the test - unless the test contradicts docs/legal-rules.md,
     in which case stop and report it.
3. Check translations:
   Run `php tools/i18n/make-pot.php`. If translatable strings were added or changed:
   update tools/i18n/en_US.php and tools/i18n/hr_HR.php, then run
   `php tools/i18n/make-pot.php --locale=hr_HR` to regenerate
   languages/sidrena-cijena.pot, languages/hr_HR.po, and languages/sidrena-cijena-hr.mo.
   Confirm 0 untranslated strings.
4. Security sweep:
   - Every admin action requires capability check (manage_sidrena_cijena) + valid nonce.
   - Every echo is properly escaped (esc_html, esc_attr, esc_url).
   - Every SQL query is prepared ($wpdb->prepare) or safely parameterized.
   - Uploaded/imported file paths are validated against allowed directories (CsvParser::is_allowed_path).
   - No customer personal data in logs (Logger::sanitize_context).
   - Post-activation redirect in WelcomeBanner::maybe_redirect() guards against bulk activation
     ($_GET['activate-multi']), network admin, cron, and REST requests.
   Add a regression test for each gap found.
5. Finish with clean static gates:
   `composer test:unit && composer lint && composer analyse && npm run lint:js`
6. Packaging (mandatory after every completed run per AGENTARDS/README.md):
   - Bump version in all 4 places: sidrena-cijena.php (header Version and SIDRENA_CIJENA_VERSION constant),
     readme.txt (Stable tag and = X.Y.Z = changelog), and package.json.
   - Run `tools/build-zip.sh <version>`.
   - Verify archive with `unzip -t versions/<version>/sidrena-cijena.zip`.
   - Append verification notes to docs/release-readiness.md and update docs/improvement-backlog.md.
   - Commit everything including versions/<version>/ and push to origin master.

Done when: unit suite passes (0 failures), lint exits 0, PHPStan reports no errors,
JS lint exits 0, translations are complete (0 untranslated), release archive is built and pushed.
```
