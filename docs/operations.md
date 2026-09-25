# Operations: scheduling, WP-CLI and retention

This plugin schedules daily list generation 15 minutes before the configured
deadline (default `08:00`, site time zone) and queues regeneration after a
service price change. WP-Cron is best-effort; the merchant/operator must
monitor publication logs and URLs. Three recurring events drive this:

| Hook | Schedule | Job |
| ---- | -------- | --- |
| `sidrena_cijena_daily_generation` | Daily 15 minutes before the deadline | `DailyGeneration::run_scheduled()` |
| `sidrena-cijena-service-regeneration` | Single events, queued on service price changes | `DailyGeneration::run_service_regeneration()` |
| `sidrena-cijena-daily-cleanup` | Daily at 03:30 | `CleanupJob::run()` |

## WP-Cron and real system cron

WP-Cron only runs when the site receives traffic. On low-traffic sites, or
for reliable timely execution, disable WP-Cron page-load triggering and call
it frequently from the server scheduler instead:

1. In `wp-config.php`: `define( 'DISABLE_WP_CRON', true );`
2. Add a server cron entry that runs every minute as the web user:

```cron
* * * * * cd /path/to/wordpress && wp cron event run --due-now --quiet
```

The plugin queues the recurring product-list event with a 15-minute buffer
before the configured deadline (07:45 for the default 08:00). A frequent,
healthy system scheduler, correct site timezone, and successful generated-file
checks are still necessary; software cannot guarantee timing when the host is
down or jobs are delayed.

Times are server-local; align them with the site time zone.

## Admin notice

When the daily generation event is missing or its next run is in the past,
administrators see a warning notice on every admin screen linking to the
price-lists screen, where **Generate now** republishes manually.

## WP-CLI

All commands live under `wp sidrena-cijena`. Exit codes: `0` success,
`1` validation/compliance errors, `2` operational failure, `3` invalid
arguments or unmet prerequisites.

```sh
wp sidrena-cijena status [--format=table|json]
wp sidrena-cijena validate [--format=csv|xml|both] [--location=<id>] [--severity=ERROR|WARNING|INFO] [--format-output=table|json]
wp sidrena-cijena generate [--format=csv|xml|both] [--location=<id>] [--force] [--dry-run]
wp sidrena-cijena history <product_id> [--variation=<id>] [--type=<type>] [--limit=<n>] [--format-output=table|json]
wp sidrena-cijena import <file> [--dry-run] [--confirm-overwrite] [--format-output=table|json]
wp sidrena-cijena cleanup [--dry-run]
wp sidrena-cijena seed-test-data [--scenario=<name>] [--yes]
```

`seed-test-data` refuses to run unless `--yes` is given or the `WP_ENV`
environment variable/constant is set. Valid scenario names: `normal`,
`sale`, `no-sku`, `barcode`, `no-barcode`, `variable`, `legacy-category`,
`introduced-after`, `frequently-repriced`, `consecutive-promotions`,
`out-of-stock`, `unit-pricing`, `missing-anchor`, `price-above`,
`price-equal`, `price-below`, `untyped-sale`, or `all` (default).

## Retention and uninstall

* Published price-list files stay available for at least 30 days
  (`retention_days`, minimum 30 enforced). Cleanup deletes files only at or
  after `expires_at`; publication rows are kept as an archive.
* Log entries are pruned after `log_retention_days` (default 180).
* Deactivation preserves everything and only unschedules cron events.
* Uninstall deletes plugin data **only** when the
  `sidrena_cijena_delete_data_on_uninstall` option is enabled: four custom
  tables (sales, locations, publications, logs), the plugin options, standalone
  catalog items and the publication files. WooCommerce, WordPress and any other
  data is never touched.
* The anchor prices and the 30-day lowest prices in
  `wp_sidrena_price_history` are the legal evidence of what the shop displayed,
  so they are kept even then — the ordinary `regular`/`sale`/`historical` rows
  are deleted and the table stays. Remove the table by hand if it really has to
  go. Without the opt-in, uninstall keeps everything.
