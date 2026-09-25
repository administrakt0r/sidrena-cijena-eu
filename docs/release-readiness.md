# Release verification

## 1.0.4

Setup Guide status badges, activation redirect hardening, documentation/spec references cleanup, full AGENTARD prompts refresh, and translation catalogs:
- Unit: 175 tests, 525 assertions passed (`composer test:unit`).
- Static analysis & linting: PHPStan 106/106 files clean (0 errors), PHPCS 133/133 files clean, ESLint clean (`npm run lint:js`).
- Integration: 76 tests, 342 assertions passed on `wp-env` with MySQL (`composer test:integration`).
- Local environment: `./local.sh` exited 0; seeded 17 compliance test scenarios, forced generation, status healthy.
- Setup Guide checklist: Added dynamic status badges for all 5 steps: step 3 reports recorded anchor counts (`(done: %d recorded)` / `(none recorded)`), step 5 reports stored compliance report status (`(clean)`, `(errors found)`, or `(not run yet)`).
- Welcome banner hardening: Enhanced `WelcomeBanner::maybe_redirect()` to guard against redirects during bulk activation (`$_GET['activate-multi']`), network admin (`is_network_admin()`), cron runs, and REST requests.
- AGENTARD prompts refresh: Overhauled `AGENTARD/README.md` and prompts 01 through 05 with current quality gates (including `npm run lint:js`), strict isolation (E2E on port 8889), accurate documentation paths, and mandatory version release workflow.
- Cleaned up obsolete references to deleted `specs/001-sidrena-cijena-compliance/` and `contracts/` across `src/Database/Schema.php`, `src/Export/PriceListBuilder.php`, `src/Export/CsvWriter.php`, `src/Export/XmlWriter.php`, `docs/development.md`, and `docs/filename-profile.md`.
- Translations: Full Croatian translation (479/479 strings translated, 0 untranslated in `hr_HR.po` / `sidrena-cijena-hr.mo`).
- Release archive: Built via `tools/build-zip.sh 1.0.4`, checksum generated, `unzip -t` passed.

## 1.0.3

Setup guide onboarding, public page caching, resilient 08:00 daily generation (REL-06), CI pipeline (REL-08), translations and failure visibility:
- Unit: 173 tests, 517 assertions passed (`composer test:unit`).
- Static analysis & linting: PHPStan 106/106 files clean (0 errors), PHPCS 133/133 files clean, ESLint clean (`npm run lint:js`).
- Integration: 76 tests, 342 assertions passed on `wp-env` with MySQL (`composer test:integration`).
- Local environment: `./local.sh` exited 0; seeded 17 compliance test scenarios, forced generation, verified status reporting `Scheduler: healthy`.
- Deadline robustness: Re-anchored WP-Cron daily recurrence after runs to eliminate DST drift; timezone change listeners (`timezone_string`, `gmt_offset`); working-day missed run detection with catch-up generation throttled by transient lock on `init`; failure recording with dismissible admin notice, Publications/Dashboard status alerts, and WP-CLI `status` health diagnostics; covered by `SchedulerTest`.
- CI automation: `tools/ci/ci.yml` pipeline (ready for `.github/workflows/`) executing static quality gates and `wp-env` integration tests.
- First-run onboarding: `SetupGuidePage` (5-step progress checklist) and `WelcomeBanner` with author links and one-time activation redirect.
- Caching: Rendered HTML public price list caching in `PublicPage` with invalidation through `RestCache`.
- Translations: Full Croatian translation (474/474 strings translated, 0 untranslated in `hr_HR.po` / `sidrena-cijena-hr.mo`).
- Release archive: Built via `tools/build-zip.sh 1.0.3`, checksum generated, `unzip -t` passed.

## 1.0.2

Prospective first-offer capture and version archives: unit 119 tests/380 assertions,
integration 76/342, E2E 28 passed (4.2 minutes, port 8889), PHPStan, PHPCS and
ESLint clean; build script syntax checked; `./local.sh` exited 0. Integration
covers standalone products/services, zero prices, pre-baseline observations,
existing entries, copied eligibility and preserved anchors. Real WooCommerce
E2E covers draft/publication, edits, exact variation identity and imported
products including subsequent edits. Every bumped version is archived in a
numeric version directory with an installable ZIP/checksum; the builder refuses
to overwrite an existing ZIP.

## 1.0.0

Release preparation started 25 September 2026. The plugin header, runtime
constant, npm manifest/lock and WordPress stable tag agree on 1.0.0.

The translation generator reads the plugin header. Both source catalogs contain
427 translated strings and zero untranslated strings. The Croatian MO compiler
now calculates offsets for its separate original/translation string blocks;
every dictionary entry was round-tripped through WordPress's MO reader.
The runtime catalog uses `sidrena-cijena-hr.mo`: WordPress's Croatian locale
identifier is `hr`, although the development dictionary is named `hr_HR.php`.

Verification so far:

- Unit: 119 tests, 380 assertions.
- Integration: 70 tests, 329 assertions after `sg docker -c 'npx wp-env clean tests'`.
  Existing E2E location/publication rows had contaminated the initial run.
- PHPStan, PHPCS and ESLint: clean.
- `sg docker -c './local.sh'`: exit 0.
- E2E: 27 passed (4.1 minutes), exclusively on port 8889.
- Clean ZIP installation: passed on fresh WordPress 7.1.2 / PHP 8.3 without
  WooCommerce. A disposable wp-env on ports 8890/8891 was destroyed and recreated.
  The final ZIP (source commit `7e98210`) installed and activated on its pristine
  test instance. Verification checked runtime version, every schema table,
  Croatian translation through `switch_to_locale('hr')` and `__()`, and absence
  of development dependencies. `unzip -t` also passed.

The integration bootstrap no longer predefines constants owned by the plugin,
eliminating duplicate-constant warnings during actual WordPress loading.

Build from a clean checkout of the release commit with
`tools/build-zip.sh 1.0.0`. An isolated checkout allows the developer's unrelated
working-tree changes to remain untouched. The archive contains runtime sources,
assets, templates, catalogs and documentation; development dependencies are excluded.

## Release 1.0.5 (2026-09-26)

- **Default Croatian Language**: Interface displays 100% in Croatian across all screens and sites by default.
- **Bulk Anchor Entry Tools**: Added shop-wide batch setup card to set anchor prices from current catalog prices in one click; added "Select all on this page", "Deselect all", and master header checkbox for instant table selection.
- **In-Dashboard Documentation**: Added rich Croatian reference screen (`sidrena-cijena-docs`) with direct links from Setup Guide, Welcome Banner, and Dashboard covering legal rules, 08:00 cron automation, CLI commands, developer hooks, and compliance gates.
- **Packaging Hardening**: Excluded developer notes, internal documentation, AI prompt files (`PROMPT.md`, `AGENTARD/`), and temporary files from the distribution release archive.
- **Quality Gates**: All static gates pass cleanly (PHPUnit 180 tests / 546 assertions, PHPStan 0 errors, PHPCS clean, ESLint clean).

## Release 1.0.6 (2026-09-26)

- **Opt-in Uninstaller Hardening**: Extended `Uninstaller::$options` with `sidrena_cijena_rest_generation`, `sidrena_cijena_public_feed_last_queue`, and `sidrena_cijena_terms_seeded` ensuring pristine cleanup while strictly preserving legal anchor and 30-day evidence rows.
- **XML Price-List Specification Alignment**: Documented `datumSidreneCijene` in `docs/price-list-format.md` conforming with the verified XML generator implementation.
- **Integration Test Isolation**: Added table reset to `ActivationTest::set_up()` and `Test_Cli::set_up()` ensuring idempotent test runs regardless of prior E2E execution state.
- **Unit Test Coverage**: Added `tests/unit/Support/UninstallerTest.php` covering evidence preservation, option lists, and opt-in execution gates (unit suite increased from 180 to 184 tests, 571 assertions).
- **All Quality Gates Green**: PHPUnit unit (184/571 passed), PHPStan (107/107 clean, 0 errors), PHPCS (137/137 clean), ESLint clean, integration (76/342 passed), Playwright E2E (28/28 passed), and `./local.sh` clean exit 0.
- **Translations**: Croatian translation catalogs fully compiled (567/567 strings translated, 0 untranslated in `hr_HR.po` / `sidrena-cijena-hr.mo`).


