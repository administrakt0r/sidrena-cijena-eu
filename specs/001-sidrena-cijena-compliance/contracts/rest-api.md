# Contract: Public REST API

**Feature**: `001-sidrena-cijena-compliance` | **Version**: 1.0

Namespace: `sidrena-cijena/v1`. Public read routes require **no authentication** (FR-056). Management routes require the `manage_sidrena_cijena` capability. All responses are JSON unless the route name ends in `.csv` or `.xml`.

## Routes

### `GET /wp-json/sidrena-cijena/v1/prices`

Paginated current price data.

| Parameter | Type | Default | Notes |
|-----------|------|---------|-------|
| `page` | integer | 1 | ≥ 1 |
| `per_page` | integer | 100 | 1–500 |
| `type` | enum | all | `product` \| `service` |
| `location` | integer | all | Sales location ID; omitted = catalog-wide prices |
| `updated_since` | ISO 8601 | — | Only items changed after this timestamp |
| `sale_only` | boolean | false | Only items with an active special sale |

Response `200`:

```json
{
  "data": [
    {
      "id": 123,
      "variation_id": 0,
      "type": "product",
      "name": "Kava mljevena 250 g",
      "sku": "KAV-250",
      "brand": "Franck",
      "barcode": "3851234567890",
      "unit": "kg",
      "unit_price": 28.0,
      "currency": "EUR",
      "current_price": 6.99,
      "anchor_price": 7.49,
      "anchor_reference_date": "2026-09-10",
      "availability": "available",
      "special_sale": { "active": true, "type": "akcija", "name": "akcija", "start_date": "2026-10-01T00:00:00+02:00", "end_date": null, "lowest_30_day_price": 6.49 },
      "updated_at": "2026-10-01T07:50:00+02:00"
    }
  ],
  "meta": { "page": 1, "per_page": 100, "total": 10000, "total_pages": 100, "generated_at": "2026-10-01T07:50:00+02:00", "rule_version": "2026-10-01" }
}
```

- `current_price` is the effective retail price (the promotional price when a special sale is active).
- `anchor_price` is `null` when no anchor exists (never a substitute for the 30-day price).
- No customer or order data is ever included.

Headers: `ETag`, `Last-Modified`, `Cache-Control: public, max-age=300`, `X-Robots-Tag: noindex`. Conditional requests (`If-None-Match`, `If-Modified-Since`) return `304`.

Errors: `400` invalid parameters, `404` unknown location, `429` throttled.

### `GET /wp-json/sidrena-cijena/v1/prices.csv` and `.../prices.xml`

Current price list download. Parameters: `location` (required when more than one active location exists), otherwise the same filtering as `/prices`. Content-Type `text/csv; charset=utf-8` or `application/xml; charset=utf-8`; `Content-Disposition: attachment` with the generated filename. Content follows `contracts/csv-format.md` / `contracts/xml-format.md`.

### `GET /wp-json/sidrena-cijena/v1/publications`

Machine-readable index of retained published versions (FR-046, FR-055).

| Parameter | Type | Default | Notes |
|-----------|------|---------|-------|
| `location` | integer | all | Filter by location |
| `format` | enum | all | `csv` \| `xml` |
| `page` / `per_page` | integer | 1 / 50 | ≤ 200 |

Response `200`:

```json
{
  "data": [
    {
      "id": 501,
      "location_id": 1,
      "format": "csv",
      "filename": "PRODAVAONICA_VLASKA68_ZAGREB_P1_01_01102026_0750.CSV",
      "url": "https://example.test/wp-content/uploads/sidrena-cijena/p1/PRODAVAONICA_VLASKA68_ZAGREB_P1_01_01102026_0750.CSV",
      "checksum": "…sha256…",
      "rule_version": "2026-10-01",
      "status": "published",
      "item_count": 1024,
      "published_at": "2026-10-01T07:50:00+02:00",
      "expires_at": "2026-10-31T07:50:00+02:00"
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 42 }
}
```

Only retained publications are listed; expired ones are omitted from the public index but remain in the archive table for audit.

### `GET /wp-json/sidrena-cijena/v1/status` (management)

Requires `manage_sidrena_cijena`. Returns schema version, rule configuration summary, last/next generation per location, counts by severity, and cron health.

## Caching and throttling

- Responses are cached in the object cache/transients keyed by filters and invalidated on any price change, anchor change, sale change or settings change.
- `per_page` is capped at 500; requests above the cap are clamped.
- Basic per-IP throttling (for example 60 requests/minute) returns `429` with `Retry-After`; normal crawlers are unaffected.

## Security

- Every route declares explicit `permission_callback`.
- Public routes expose price data only.
- Input is validated and sanitized; unknown parameters are ignored.
- No route accepts file paths or arbitrary SQL.
