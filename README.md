# Sidrena cijena - Croatian Anchor Price Compliance

A production-ready WordPress plugin that implements the Croatian additional
("sidrena") price obligation, the daily machine-readable price-list obligation
and the separate 30-day lowest-price rule for special forms of sale.

> **Disclaimer:** This plugin is a technical compliance aid, **not legal
> advice**. It does not guarantee legal compliance. Merchants must obtain
> professional legal and accounting advice for their specific situation.

## What it does

- **Anchor price records** - immutable historical records of the retail price
  valid on the applicable reference date (general: 10 September 2026; legacy
  categories: 2 May 2025), with source, currency, tax status, author and an
  append-only correction trail.
- **Frontend display** - current price and anchor price shown together on
  product pages, archives and merchant-controlled placements (shortcode and
  template functions), clearly labelled and configurable.
- **30-day lowest price** - a separate engine that calculates the lowest
  applicable price of the 30 calendar days before a classified special sale
  (akcija, sniženje, sezonsko sniženje, rasprodaja, custom). Never a substitute
  for the anchor price.
- **Daily price list** - deterministic CSV and XML price lists per sales
  location, with documented filenames, checksums, validation and a
  retention archive of at least 30 days.
- **Public machine-readable access** - unauthenticated JSON, CSV and XML
  endpoints under `/wp-json/sidrena-cijena/v1/`, plus stable public URLs
  `/sidrena-cijena.xml` and `/sidrena-cijena.csv`.
- **Public price list and archive pages** - `/sidrena-cjenik/` (searchable
  current list with the anchor price and its date) and
  `/arhiva-sidrene-cijene/` (published files with checksums), also available
  as the shortcodes `[sidrena-cjenik]` and `[sidrena-arhiva]`.
- **Compliance dashboard and validator** - anchor coverage, missing data,
  30-day warnings, export status and severity-based findings.
- **Safe onboarding** - CSV import with preview, validation, dry run,
  transactional commit/rollback and error report; bulk entry; migration
  detection.
- **Auditability** - price history per product/variation, correction records,
  rule-configuration audit and structured logs.
- **WP-CLI** - `status`, `validate`, `generate`, `history`, `import`,
  `cleanup`, `seed-test-data` with meaningful exit codes.
- **WooCommerce optional** - a dedicated adapter integrates through official
  hooks; without WooCommerce the standalone catalog keeps the full workflow
  available.

## Requirements

Release 1.0.0 includes complete Croatian and English source catalogs (427 strings).
Release verification and packaging evidence are tracked in
[`docs/release-readiness.md`](docs/release-readiness.md).
The retained filename profile and ministry-example differences are explained in
[`docs/filename-profile.md`](docs/filename-profile.md); legal format review remains necessary.

- WordPress 6.8+ (tested through 7.1)
- PHP 7.4+ (developed and tested on PHP 8.3)
- WooCommerce 8.x+ (optional)
- MySQL 5.7+ / MariaDB 10.4+

## Development quickstart

```bash
git clone <repository-url> sidrena-cijena
cd sidrena-cijena
npm install
composer install
npm run wp-env start
```

- Development site: <http://localhost:8888> (`admin` / `password` - development only)
- Test environment: <http://localhost:8889> via `npm run wp-env:test -- start`
- Activate: `npx wp-env run cli wp plugin activate sidrena-cijena`

### Tests and quality gates

```bash
composer test:unit          # pure domain unit tests
composer test:integration   # WordPress/WooCommerce integration tests (inside wp-env)
npm run wp-env:test -- start && npm run test:e2e   # Playwright, against the test site (8889)
composer lint               # PHPCS: WordPress standards + PHPCompatibilityWP
composer analyse            # PHPStan
npm run lint:js             # ESLint
```

## Documentation

| Document | Contents |
|----------|----------|
| [docs/development.md](docs/development.md) | Environment setup and tooling |
| [docs/legal-rules.md](docs/legal-rules.md) | Legal sources, rules and documented uncertainties |
| [docs/price-history.md](docs/price-history.md) | Price-history model, record types and corrections |
| [docs/price-list-format.md](docs/price-list-format.md) | CSV/XML formats and filename structure |
| [docs/woocommerce.md](docs/woocommerce.md) | WooCommerce integration and template API |
| [docs/testing.md](docs/testing.md) | Test suites, fixtures and scenarios |
| [docs/troubleshooting.md](docs/troubleshooting.md) | Common problems and resolutions |
| [docs/operations.md](docs/operations.md) | Scheduling, WP-CLI reference, retention, uninstall |

## Known limitations

- Integration and E2E suites require Docker (`npm run wp-env start`); they
  cannot run in environments without a Docker daemon.
- The public REST endpoint exposes price data only - no customer or order data.
- Block-theme product templates are covered through the standard
  `woocommerce_get_price_html` filter; no dedicated block is shipped.
- Barcode, brand and unit-of-measure values are read from common meta keys
  and taxonomies; exotic providers may need the documented filters.
- New first offers can receive an anchor from the price/date actually observed
  at publication. Existing catalog gaps and imports still need historical evidence.
  See [feature acceptance and setup requirements](docs/feature-acceptance.md).

## Legal assumptions (see docs/legal-rules.md)

- General anchor reference date: 10 September 2026; legacy categories:
  2 May 2025. Both are configurable, and changes are audited - history is
  never rewritten.
- The 30-day lowest price is a separate concept from the anchor price and is
  never substituted for it.
- XML schema and CSV conventions follow the plugin contract where the
  decisions leave the format unspecified; documented uncertainties are listed
  in `docs/legal-rules.md` rather than silently decided.

## Architecture

The compliance core (`src/Pricing`, `src/Compliance`, `src/Database`,
`src/Export`, `src/Import`) has no WooCommerce dependency. `src/Catalog/`
provides the catalog abstraction and the standalone provider; `src/WooCommerce/`
contains the adapter only. Admin, REST, CLI and cron layers depend on the core
through interfaces.

See `specs/001-sidrena-cijena-compliance/` for the full specification, plan,
data model, contracts and task list.

## License

GPL-2.0-or-later.
