# Development

## Instant demo: `./local.sh`

```sh
./local.sh     # start the demo webshop (WordPress + WooCommerce + demo data)
./stoplocal.sh # stop it (data is kept; full reset: npx wp-env destroy)
```

`local.sh` installs dependencies, starts wp-env on http://localhost:8888
(`admin` / `password` — **development only**), sets EUR + Europe/Zagreb,
creates demo products with anchors and an `akcija` sale
(`tools/local-seed.php`), seeds the 17 compliance scenarios, generates the
price lists and prints the URLs that matter (shop, admin, REST, price-list
files). AI agents working in this codebase must use `./local.sh` /
`./stoplocal.sh` for any live WordPress work — see `AGENTARD/README.md`.

## Prerequisites

* Docker (for `wp-env`)
* Node LTS (see `.nvmrc`) and npm
* PHP 7.4+ with Composer (the plugin supports PHP 7.4–8.5; CI tests 8.3)
* Git

## Setup

```sh
git clone <repo-url> sidrena-cijena
cd sidrena-cijena
npm install
composer install
npm run wp-env start
```

* Site: http://localhost:8888 (`admin` / `password` — **development only**)
* Test site: separate config `.wp-env.test.json` (port 8889)

Activate inside the environment if needed:

```sh
npx wp-env run cli wp plugin activate sidrena-cijena
```

## Daily commands

| Command | What it runs |
| ------- | ------------ |
| `composer test:unit` | PHPUnit unit suite (`tests/unit`) |
| `composer test:integration` | PHPUnit integration suite (needs wp-env + `WP_TESTS_DIR`) |
| `npm run test:e2e` | Playwright E2E (`tests/e2e`) |
| `composer lint` | PHPCS (WordPress + PHPCompatibilityWP) |
| `composer lint:fix` / `phpcbf` | Auto-fix coding-standard violations |
| `composer analyse` | PHPStan level 6 |

## wp-env lifecycle

```sh
npm run wp-env start    # start
npm run wp-env stop     # stop (keeps data)
npm run wp-env clean    # reset databases
npm run wp-env destroy  # remove containers and data
```

`.wp-env.json` maps the plugin and the latest stable WooCommerce into the
environment. Docker must be running; without it, integration and E2E
suites cannot execute (unit tests, lint and static analysis run on the
host).

## Architecture

```
sidrena-cijena.php          # bootstrap, constants, hooks
src/
  Admin/        # menu, settings, meta boxes, list screens
  Api/          # REST controller, cache, template functions, shortcode
  Catalog/      # CatalogProvider abstraction + standalone CPT provider
  Cli/          # WP-CLI commands (exit 0/1/2/3)
  Compliance/   # RuleConfig, ReferenceDateResolver, Validator + Rules/
  Cron/         # Scheduler, DailyGeneration, CleanupJob
  Database/     # Schema, Migrator, repositories (append-only)
  Export/       # PriceListBuilder, CsvWriter, XmlWriter, FilenameGenerator, Publisher, GenerationService
  Import/       # CsvParser, RowValidator, ImportService, MigrationDetector
  Logging/      # Logger facade (strips secrets), LogRepository
  Pricing/      # AnchorPriceService, AnchorCache, LowestPrice30DayCalculator, SpecialSaleService
  Support/      # Autoloader, Settings, Currency, Dates, Install, Deactivator, Uninstaller
  WooCommerce/  # Detector, CRUD-only catalog provider, display integration
templates/      # overridable price templates
```

Key principles: WooCommerce is optional and the plugin never fatals
without it; price history is append-only (corrections link to originals);
exports are deterministic (fixed columns, delimiters, filename rules);
the public REST endpoint exposes price data only, no customer or file-path
data. See `docs/legal-rules.md`, `docs/feature-acceptance.md` and `docs/price-list-format.md`
for legal rules, requirements, data model and price-list contracts.
