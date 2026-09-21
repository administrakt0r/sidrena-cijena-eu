# Contract: Price-list CSV format

**Feature**: `001-sidrena-cijena-compliance` | **Version**: 1.0

This is the documented, deterministic format required by FR-032–FR-037 and the Odluka NN 101/2026-1213 field list. It is a public interface: external software parses it.

## File-level rules

| Property | Value |
|----------|-------|
| Encoding | UTF-8, **without BOM** |
| Delimiter | `;` (semicolon) |
| Decimal separator | `,` (comma) |
| Thousands separator | none |
| Line endings | LF (`\n`) |
| Quoting | RFC 4180-style: a field is quoted with `"` only when it contains the delimiter, a quote or a newline; embedded quotes are doubled |
| Header row | Always present, first row, exact column names and order below |
| HTML / serialization / hidden metadata | Forbidden |
| Empty values | Empty field (two consecutive delimiters), never `NULL` or `N/A` |
| Boolean fields | `DA` / `NE` (uppercase) |

## Columns (stable order)

| # | Column | Type | Products | Services | Notes |
|---|--------|------|----------|----------|-------|
| 1 | `vrsta` | enum | `proizvod` | `usluga` | Item type |
| 2 | `naziv` | text | required | required | Product/service name |
| 3 | `sifra` | text | required | optional | Code/SKU |
| 4 | `marka` | text | optional | — | Brand |
| 5 | `jedinica_mjere` | text | optional | — | Unit of measure, when applicable |
| 6 | `cijena_za_jedinicu_mjere` | decimal | optional | — | Unit price, when applicable |
| 7 | `maloprodajna_cijena` | decimal | required | required | Effective retail price at publication time (the promotional price when a special sale applies) |
| 8 | `poseban_oblik_prodaje` | enum | required | required | `DA` when a special form of sale applies, otherwise `NE` |
| 9 | `naziv_posebnog_oblika_prodaje` | text | required when #8 = DA | required when #8 = DA | Name of the special form of sale |
| 10 | `sidrena_cijena` | decimal | required | required | Anchor price; empty only when no anchor exists (a validation warning is emitted) |
| 11 | `barkod` | text | optional | — | Barcode (EAN/UPC as text, leading zeros preserved) |
| 12 | `dostupnost` | enum | required | optional | `dostupno` / `nedostupno` |

Product rows MUST leave service-only columns unused; service rows MUST leave product-only columns empty. The column set is the union required by the Odluka for products and services, published as one combined price list per location (documented decision in `research.md` R10).

## Decimal formatting

- Two decimal places, comma separator, no thousands separator: `1234,50`.
- Negative values are invalid and never exported; zero is exported as `0,00`.
- Rounding is half-up from the stored six-decimal value.

## Example

```csv
vrsta;naziv;sifra;marka;jedinica_mjere;cijena_za_jedinicu_mjere;maloprodajna_cijena;poseban_oblik_prodaje;naziv_posebnog_oblika_prodaje;sidrena_cijena;barkod;dostupnost
proizvod;Kava mljevena 250 g;KAV-250;Franck;kg;28,00;6,99;DA;akcija;7,49;3851234567890;dostupno
proizvod;Sok naranča 1 L;SOK-1L;Vindija;L;1,80;1,79;NE;;1,89;3851234567891;dostupno
usluga;Šišanje kratke kose;USL-01;;;;15,00;NE;;20,00;;dostupno
```

A canonical example lives at `tests/fixtures/example-price-list.csv`.

## Determinism

Row order: products first (ascending by `sifra`, then `naziv`, then item ID), then services (same ordering). Ties are broken by item ID. The same catalog state, rule version and location always produce byte-identical output (excluding the filename timestamp).
