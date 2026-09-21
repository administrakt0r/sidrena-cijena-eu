# Quickstart: Validation Guide

**Feature**: `001-sidrena-cijena-compliance` | **Date**: 2026-09-21

This guide proves the feature works end-to-end. It is a validation/run guide; implementation details live in `tasks.md` and the implementation phase. Contracts referenced: [`contracts/`](./contracts/), data structures: [`data-model.md`](./data-model.md).

## Prerequisites

- Docker (Desktop or Engine) running
- Node.js LTS (18+) and npm
- PHP 8.3 (provided inside wp-env; a host PHP 7.4+ with Composer is needed for host-side linting only)
- Composer 2.x
- Git (the plugin root is not yet a Git repository; initialize it during implementation)

## Setup

```bash
git init                       # if not already a repository
npm install                    # wp-env, Playwright, scripts
composer install               # PHPUnit, PHPCS, PHPStan (dev only)
npm run wp-env start           # starts WordPress + WooCommerce with the plugin mounted
```

- Site: http://localhost:8888 — development credentials: `admin` / `password` (development-only; never use in production)
- Test site: http://localhost:8889 (via `.wp-env.test.json`)
- Activate: `npx wp-env run cli wp plugin activate sidrena-cijena`
- WooCommerce is installed and activated by the environment configuration.

Stop / reset:

```bash
npm run wp-env stop            # stop containers, keep data
npm run wp-env clean all       # reset databases
npm run wp-env destroy         # remove containers and volumes
```

## Test commands

```bash
composer test:unit             # pure domain unit tests (no WordPress bootstrap)
composer test:integration      # WordPress/WooCommerce integration tests
npm run test:e2e               # Playwright end-to-end tests
composer lint                  # PHPCS: WordPress standards + PHPCompatibilityWP
composer analyse               # PHPStan
npm run lint:js                # ESLint (admin/frontend scripts)
```

## Validation scenarios

Each scenario is runnable in the wp-env site and is covered by automated tests.

### V1. Activation and migration

1. Activate the plugin (`wp plugin activate sidrena-cijena`).
2. Run `npx wp-env run cli wp sidrena-cijena status`.
3. **Expected**: schema version present, five tables created, rule version `2026-10-01`, reference dates `2026-09-10` / `2025-05-02`, exit code `0`.
4. Deactivate and reactivate; **expected**: no data loss, no fatal errors.

### V2. Anchor prices and display

1. Seed scenarios: `npx wp-env run cli wp sidrena-cijena seed-test-data --yes`.
2. Import anchors: `npx wp-env run cli wp sidrena-cijena import tests/fixtures/anchor-import.csv --dry-run`, then without `--dry-run`.
3. Open a product page and an archive page.
4. **Expected**: current price and "Sidrena cijena" shown together, correct per variation; a product without an anchor shows an admin warning but the storefront is unaffected; disabling display removes all anchor markup.

### V3. 30-day lowest price

1. Start a classified special sale for a seeded product with a prior promotion.
2. **Expected**: the 30-day lowest price equals the lowest applicable price in the window (30 calendar days before the start, start date excluded), is labelled distinctly from the anchor price, and the window boundaries are stored.

### V4. Price-list generation

1. `npx wp-env run cli wp sidrena-cijena generate --format=both --force`
2. **Expected**: CSV and XML files per active location with validated filenames (facility form, address, identifier, sequence, timestamp), required fields per `contracts/csv-format.md` and `contracts/xml-format.md`, validation passed, archive rows created, exit `0`.

### V5. Archive and retention

1. Open `wp sidrena-cijena/v1/publications` and the human-readable publications page.
2. **Expected**: published files listed with checksums and expiry ≥ 30 days; `wp sidrena-cijena cleanup --dry-run` reports nothing to delete while retention holds.

### V6. Public machine-readable access

1. `curl 'http://localhost:8888/wp-json/sidrena-cijena/v1/prices?per_page=2'` without authentication.
2. Download `.../prices.csv?location=1` and `.../prices.xml?location=1`.
3. **Expected**: paginated JSON with current price, anchor price, identifiers, SKU, barcode, availability and sale status; no customer data; `ETag`/`Last-Modified` present; downloads validate against the format contracts.

### V7. Compliance check

1. `npx wp-env run cli wp sidrena-cijena validate --severity=info`
2. **Expected**: seeded problems reported with correct severities and item references; exit `1` when ERROR findings exist, `0` when clean.

### V8. Import safety

1. Import a fixture containing valid, invalid, duplicate and unknown rows with `--dry-run`.
2. **Expected**: preview and error report; nothing committed. Run without dry-run and confirm atomic commit; re-run and confirm overwrite requires `--confirm-overwrite`.

### V9. Multiple locations

1. Create a webshop plus two physical stores in `Prodajna mjesta`, one inactive.
2. Generate again.
3. **Expected**: three active files (webshop + active stores) with distinct facility metadata; no file for the inactive location.

### V10. WooCommerce absence regression

1. `npx wp-env run cli wp plugin deactivate woocommerce`
2. Browse homepage, a standalone catalog item, and run `wp sidrena-cijena status` and `generate`.
3. **Expected**: no fatal errors; compliance features operate on the standalone catalog; WooCommerce-native price fields untouched.
4. Reactivate WooCommerce; **expected**: WooCommerce display integration resumes.

### V11. Automated suites

```bash
composer test:unit
composer test:integration
npm run test:e2e
composer lint && composer analyse
```

**Expected**: all suites pass; PHPCS/PHPStan report no errors; PHP debug log and browser console are clean.

### V12. Lifecycle

1. Deactivate: all data remains.
2. Uninstall without the delete option: tables, options and publications remain; catalog data untouched.
3. Uninstall with deletion explicitly enabled and confirmed: only plugin-owned data is removed.
