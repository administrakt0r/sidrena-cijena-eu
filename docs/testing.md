# Testing

## Commands

```sh
composer test:unit          # PHPUnit unit suite — runs on the host, no WP needed
composer test:integration   # PHPUnit integration suite — needs wp-env running
npm run test:e2e            # Playwright E2E — needs wp-env running
composer lint               # PHPCS (must be clean)
composer analyse            # PHPStan level 6 (must report no errors)
```

Unit tests use `tests/unit/wp-stubs.php` (WordPress function stubs) and
never touch a database. Integration tests extend `WP_UnitTestCase`,
run inside transactions and cover activation, repositories, generation,
publication, REST, imports, locations, history, CLI exit codes and the
uninstall lifecycle. E2E specs drive a real wp-env site (Croatian locale,
`Europe/Zagreb`).

Docker is required for integration and E2E suites. Without a Docker
daemon they cannot run; unit tests, lint and static analysis still run on
the host.

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
