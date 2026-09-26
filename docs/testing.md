# Testing

## Commands

```sh
composer test:unit          # PHPUnit unit suite — runs on the host, no WP needed
composer test:integration   # PHPUnit integration suite — needs wp-env running
npm run test:e2e            # Playwright E2E — needs the test environment running
composer lint               # PHPCS (must be clean)
composer analyse            # PHPStan level 6 (must report no errors)
npm run lint:js             # ESLint over assets/js (must be clean)
php tools/i18n/make-pot.php --locale=hr_HR   # regenerate catalogs; must report 0 untranslated
```

## One-command verification

`tools/verify.sh` runs every gate above and prints a PASS/FAIL summary with the
test counts, so any AI agent can prove a change without rediscovering the
command list:

```sh
tools/verify.sh                     # unit + PHPCS + PHPStan + ESLint + i18n
tools/verify.sh --with-integration  # + PHPUnit integration suite (wp-env)
tools/verify.sh --with-e2e          # + Playwright E2E (test env, port 8889)
```

The i18n gate re-runs `tools/i18n/make-pot.php --locale=hr_HR` and fails unless
the Croatian catalogue reports **0 untranslated** strings. Timestamp-only churn
in `languages/` is restored automatically so the tree stays release-clean;
real catalogue changes are kept and flagged for commit. The E2E gate refuses to
run when `WP_BASE_URL` points at port 8888 (the demo shop).

## Current baselines (v1.0.13)

Compare against these when judging whether a change regressed anything:

* Unit: 301 tests / 1078 assertions · E2E: 28 specs · Integration: 77 tests / 349 assertions.
* `languages/hr_HR.po`: 632 strings, 0 untranslated.
* PHPStan level 6, PHPCS and ESLint: clean.

Unit tests use `tests/unit/wp-stubs.php` (WordPress function stubs) and
never touch a database. Integration tests extend `WP_UnitTestCase`,
run inside transactions and cover activation, repositories, generation,
publication, REST, imports, locations, history, CLI exit codes and the
uninstall lifecycle. E2E specs drive a real wp-env site (Croatian locale,
`Europe/Zagreb`).

### E2E runs against the test environment

```sh
npx wp-env start                # test site on http://localhost:8889
npm run test:e2e
```

(Plain `npx wp-env` — do not use `npm run wp-env:test`/`--config`: wp-env 10.39
silently no-ops every subcommand invoked with `--config`.)

The specs create and delete products, prices, pages and locations, so they run
against the **test** environment (`tests-cli`, port 8889) and never against the
demo shop on 8888. `tests/e2e/global-setup.js` gives the test site the same
baseline `./local.sh` gives the demo shop (permalinks, timezone, currency,
visible store) before the first spec runs. Override with `WP_BASE_URL` and
`WP_ENV_CLI` if you deliberately want another target.

Docker is required for integration and E2E suites. Without a Docker
daemon they cannot run; unit tests, lint and static analysis still run on
the host. If your shell cannot reach the Docker socket, wrap the command in
`sg docker -c '…'` (the Playwright specs shell out to `wp-env`). If
`wp-env start` silently does nothing or E2E reports `tests-cli` is not
running, the containers are stale — see `docs/troubleshooting.md`
("wp-env will not start") for the one-line recovery.

## Fixtures

* `tests/fixtures/anchor-import.csv` — import preview/dry-run/commit
  scenarios (valid rows, unknown SKU, duplicates, invalid dates).
* `tests/fixtures/example-price-list.csv` / `.xml` — canonical
  contract-conformant outputs (see `docs/price-list-format.md`); a unit
  test asserts they parse and match the contracts.

## Test-product scenarios

`wp sidrena-cijena seed-test-data --scenario=<name> --yes` (or `--scenario=all`)
creates the 17 required scenarios as `SEED-*` standalone items: `normal`,
`sale`, `no-sku`, `barcode`, `no-barcode`, `variable` (WooCommerce only),
`legacy-category`, `introduced-after`, `frequently-repriced`,
`consecutive-promotions`, `out-of-stock`, `unit-pricing`,
`missing-anchor`, `price-above`, `price-equal`, `price-below`,
`untyped-sale`. Seeding is idempotent per SKU and refuses to run without
`--yes` (or `WP_ENV`).

## Boundaries covered by unit tests

* 30-day window: exact 30-day boundary, start-date exclusion, DST
  transitions, consecutive/overlapping sales, sale-price changes,
  variations, stock irrelevance, empty history.
* Anchor dates: general vs legacy category resolution, disabled legacy
  mode, configuration changes, variation isolation.
* Filename: transliteration, separator/case options, sequence padding,
  rejection reasons.
* CSV/XML writers: contract example round-trip, quoting, escaping,
  omitted empties, decimal conventions.
* Import validation: resolution, normalization, duplicates, overwrite,
  unchanged detection, Croatian dates.

## Quickstart validation (quickstart.md)

V1 activation/migration · V2 anchors + display · V3 30-day engine ·
V4 generation · V5 archive/retention · V6 public access · V7 compliance
check · V8 import safety · V9 locations · V10 WooCommerce absence ·
V11 full suites · V12 lifecycle.
