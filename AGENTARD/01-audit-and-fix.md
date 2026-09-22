# Prompt 1 — Audit and fix (no Docker needed)

```
You are maintaining the WordPress plugin in the current directory
(sidrena-cijena.php is the bootstrap). Do a full static audit and fix
everything you find. Rules: do not touch WordPress core, themes or
WooCommerce; price-history tables are append-only; commit each logical fix
separately with conventional messages (fix:/test:/docs:/chore:).

Steps:
1. Run `composer install` (if vendor/ is missing), then in order:
   `composer test:unit`, `composer lint`, `composer analyse`.
2. Record every failure, warning and risky test. Fix them one by one:
   - Lint: WordPress Coding Standards via phpcs.xml.dist
     (PHPCompatibilityWP, testVersion 7.4-).
   - Static analysis: PHPStan level 6 via phpstan.neon.dist.
   - Unit tests must stay green; if a test encodes a bug, fix the
     implementation, not the test - unless the test contradicts
     specs/001-sidrena-cijena-compliance/spec.md, in which case report it.
3. Check that `php tools/i18n/make-pot.php` output matches the committed
   languages/sidrena-cijena.pot; if strings were added, regenerate the POT
   and update tools/i18n/hr_HR.php + languages/hr_HR.po|.mo
   (`php tools/i18n/make-pot.php --locale=hr_HR`) and the en_US catalog.
4. Security sweep: every admin action needs capability
   (manage_sidrena_cijena) + nonce; every echo escaped; every SQL query
   prepared; uploaded/imported file paths whitelisted
   (see CsvParser::is_allowed_path); no customer personal data in logs
   (see Logger::sanitize_context). Add a regression test for each gap found.
5. Finish with a clean `composer test:unit && composer lint &&
   composer analyse` run and report: files changed, commits made,
   remaining risks.
6. Packaging (mandatory after every edit batch): decide the bump -
   fixes without behaviour change bump the patch number (0.0.x, stays
   local); anything user-visible bumps minor. Update sidrena-cijena.php
   (header + SIDRENA_CIJENA_VERSION), readme.txt (Stable tag +
   changelog) and package.json, commit, then run
   `tools/build-zip.sh <version>`. Commit versions/<version>/ only for
   minor/major releases; patch zips stay local per .gitignore.

Done when: unit suite passes (0 failures), lint exits 0, PHPStan reports
no errors, translations are complete (0 untranslated), and git log shows
one atomic commit per fix.
```
