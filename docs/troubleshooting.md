# Troubleshooting

## Missing anchors

* Dashboard → "missing" lists every catalog item without an anchor.
* Bulk entry (`Sidrena cijena → Anchors`) records many at once; CSV
  import handles migrations (`Import` screen, preview first).
* For items first offered after the applicable fixed reference date, record
  the actual first-offer price/date from sales evidence; the product creation
  date is only a suggested value. The compliance validator blocks publication
  until an anchor is recorded.

## Price list is stale or missing

* Check `Price lists`: last published time, next scheduled run and the
  per-file validation summary.
* A warning notice appears on all admin screens when daily generation is
  unscheduled or overdue — open the price-lists screen and use
  **Generate now**.
* Low-traffic sites: configure a real system cron (see
  `docs/operations.md`); WP-Cron alone may miss the 08:00 deadline.
* Filename rejections (`invalid_filename`) mean a missing facility/address/
  identifier component, illegal characters or an invalid date/time — fix the
  location record. The generator never overwrites on collision; it increments
  the sequence.

## Cron issues

* `wp sidrena-cijena status` shows scheduler health and the next run.
* After changing the deadline, the daily event is rescheduled
  automatically; a stale event can be cleared with
  `wp cron event delete sidrena-cijena-daily-generation` (it is
  recreated on the next admin request/activation).

## Permissions

* Everything sits behind the single `manage_sidrena_cijena` capability
  (administrators by default, grantable to e.g. shop managers via
  `Admin → Capabilities` handling on activation).
* Subscribers hitting an admin screen get "You are not allowed…".
* REST `/prices*` is public by design (price data only);
  `/status` requires the capability.

## Common findings (compliance check)

| Code | Meaning |
| ---- | ------- |
| `missing_anchor` | No anchor recorded for the item |
| `invalid_anchor_date` | Anchor reference date differs from the applicable legal date |
| `missing_store_identifier` | Location has no identifier — blocks filename generation |
| `missing_location_address` | Location needs street address and city for the required filename |
| `missing_archived_price_list` | No retained publication for an expected format |
| `stale_price_list` | Latest publication is older than the deadline allows |
| `malformed_csv` / `malformed_xml` | A published file no longer parses |
| `missing_sale_type` / untyped sale | Promotional price without a classified special sale |

Run the check from `Dashboard → Run compliance check` or
`wp sidrena-cijena validate`; reports export as CSV/JSON.
