# Contract: WP-CLI commands

**Feature**: `001-sidrena-cijena-compliance` | **Version**: 1.0

Implements FR-088. Command root: `wp sidrena-cijena <subcommand>`. All commands require the `manage_sidrena_cijena` capability (WP-CLI runs as a user; use `--user` when needed).

## Exit codes

| Code | Meaning |
|------|---------|
| `0` | Success |
| `1` | Completed with validation/compliance errors (details printed) |
| `2` | Operational failure (I/O, database, exception) |
| `3` | Invalid arguments or missing prerequisites |

## Commands

### `wp sidrena-cijena status [--format=table|json]`

Prints schema version, rule version and reference dates, location summary, publication status, next scheduled run and cron health. Exit `0`.

### `wp sidrena-cijena validate [--format=csv|xml|both] [--location=<id>] [--severity=error|warning|info] [--format-output=table|json]`

Runs the compliance validator without publishing or modifying data (FR-061). Prints findings with severity and entity. Exit `0` when no ERROR findings, `1` when ERROR findings exist, `2` on failure.

### `wp sidrena-cijena generate [--format=csv|xml|both] [--location=<id>] [--force] [--dry-run]`

Generates, validates and publishes price lists. `--force` bypasses duplicate suppression; `--dry-run` writes nothing and prints what would be produced. Exit `0` success, `1` validation errors, `2` failure. Output includes filename, item count, checksum and URL per generated file.

### `wp sidrena-cijena history <product_id> [--variation=<id>] [--type=<price_type>] [--limit=<n>] [--format-output=table|json|csv]`

Prints the chronological price history (date, type, price, source, user/system, correction info). Exit `0`, or `1` when the product has no records.

### `wp sidrena-cijena import <file> [--dry-run] [--confirm-overwrite] [--format-output=summary|json]`

Imports anchor/historical data from a CSV file (columns documented in the import screen and `docs/price-history.md`). `--dry-run` validates and previews only. Overwriting existing records requires `--confirm-overwrite`; otherwise conflicting rows are reported and skipped. Exit `0` success, `1` row validation errors (nothing committed), `2` failure, `3` unreadable file or missing columns.

### `wp sidrena-cijena cleanup [--dry-run]`

Runs retention cleanup: removes publication files at or after `expires_at`, prunes logs older than the configured retention, and never removes archive rows or files still within retention (FR-047–FR-049). Exit `0`.

### `wp sidrena-cijena seed-test-data [--scenario=<name>] [--yes]`

Development/test utility that creates the catalog scenarios required by the specification (normal, sale, no SKU, barcode/no barcode, variable with variations, legacy category, introduced after reference date, frequently re-priced, consecutive promotions, out-of-stock, unit pricing, missing anchor, price above/equal/below anchor). Refuses to run when `WP_ENV` is not set and `--yes` is not passed. Exit `0`.

## Scheduling integration

The documented production pattern is a system cron entry, for example:

```cron
0 6 * * 1-5 cd /path/to/site && wp cron event run --due-now --quiet
```

so that the daily generation deadline (default 08:00 local time) is met even on low-traffic sites.
