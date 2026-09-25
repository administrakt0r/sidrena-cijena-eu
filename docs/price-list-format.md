# Price-list format

Both formats are always produced per active location. Output is
deterministic: identical input yields byte-identical files (excluding the
filename timestamp and the XML generation timestamp).

## Public URL

The current price list is served on a stable, human-readable address, next to
the REST endpoints:

* <http://example.com/sidrena-cijena.xml>
* <http://example.com/sidrena-cijena.csv>

The URL always serves the **webshop** location (the first active location if
the shop has none). The response carries the generated filename in
`Content-Disposition` and the file checksum in `X-Sidrena-Cijena-Checksum`, so
a recipient can verify the download. If the published file is missing, the
answer is `503 Service Unavailable` with `Retry-After: 900` and one background
rebuild is queued (at most one per 15 minutes) — the visitor is told the list is
being regenerated instead of receiving a stale or empty document. The rewrite
rules are flushed on activation; after moving the site or changing permalinks,
run `wp rewrite flush`.

## Public pages

Two public HTML pages expose the same data to a human reader. Both always
describe the **webshop** location, the same one the machine-readable feed
serves, and neither requires a login:

* `/sidrena-cjenik/` — the current price list: one row per item with the
  retail price, the anchor price and its reference date, the unit price, the
  availability and the special form of sale. A search box (`?q=`) matches
  name, SKU, brand and barcode, and `?type=proizvod|usluga` narrows the list
  to products or services. The page links to the XML and CSV files.
* `/arhiva-sidrene-cijene/` — the published price lists of the location
  (published and superseded, newest first, up to 50): publication time,
  filename, format, item count, size, a short checksum and a download link.

Both pages are also available as shortcodes — `[sidrena-cjenik]` and
`[sidrena-arhiva]` — for merchants who want them inside their own layout.
The pages are rendered from the same builder and the same publication rows as
the exported files, so a page can never show a different price than the file
the ministry downloads.

## CSV

* UTF-8, **no BOM**, LF line endings, `;` delimiter.
* Comma decimals, two places, half-up from 6-decimal storage, no thousands
  separator (`28,00`, `6,99`, `0,00`). Negative prices are invalid.
* RFC4180 quoting: a field is quoted only when it contains `"`, `;` or a
  line break; embedded quotes are doubled.
* The header row is always first; empty values are empty fields (never
  `NULL` or `N/A`); booleans are `DA`/`NE`.
* Row order: products first, then services; each ordered by `sifra`,
  `naziv`, item ID.

| # | Column | Content |
| - | ------ | ------- |
| 1 | `vrsta` | `proizvod` or `usluga` |
| 2 | `naziv` | Product name / service name |
| 3 | `sifra` | SKU or service code |
| 4 | `marka` | Brand (empty for services without one) |
| 5 | `jedinica_mjere` | Unit of measure |
| 6 | `cijena_za_jedinicu_mjere` | Price per unit |
| 7 | `maloprodajna_cijena` | **Effective** retail price paid today (promotional price during a sale) |
| 8 | `poseban_oblik_prodaje` | `DA`/`NE` — special sale active |
| 9 | `naziv_posebnog_oblika_prodaje` | Sale type/name (required when 8 = `DA`) |
| 10 | `sidrena_cijena` | Anchor price (required for publication) |
| 11 | `datum_sidrene_cijene` | Date the anchor applies to; for a new item this is the verified first-offer date |
| 12 | `barkod` | Barcode (empty when unknown) |
| 13 | `dostupnost` | `dostupno` / `nedostupno` |

Example:

```csv
vrsta;naziv;sifra;marka;jedinica_mjere;cijena_za_jedinicu_mjere;maloprodajna_cijena;poseban_oblik_prodaje;naziv_posebnog_oblika_prodaje;sidrena_cijena;datum_sidrene_cijene;barkod;dostupnost
proizvod;Kava mljevena 250 g;KAV-250;Franck;kg;28,00;6,99;DA;akcija;7,49;2026-09-10;3851234567890;dostupno
```

## XML

* UTF-8, no BOM, declaration `<?xml version="1.0" encoding="UTF-8"?>`,
  2-space indent, LF endings, point decimals with two places.
* Root: `<cjenik verzija="1.0" pravilo="2026-10-01"
  generirano="2026-10-01T07:50:00+02:00">` (rule version + site-local
  ISO-8601 timestamp).
* `<prodajnoMjesto oznaka="P1" naziv="…" oblik="…" adresa="…"
  grad="…">` carries the location; empty attributes are omitted.
* One `<stavka vrsta="proizvod|usluga">` per row with children
  `naziv, sifra, marka, jedinicaMjere, cijenaZaJedinicuMjere,
  maloprodajnaCijena, posebanOblikProdaje (true/false),
  nazivPosebnogOblikaProdaje, sidrenaCijena, datumSidreneCijene, barkod, dostupnost`.
  Element names are ASCII (transliterated); empty elements are omitted;
  all content is XML-escaped. Same row order as CSV.

## Filename

`{FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT}`

* `FACILITY_FORM`: `PRODAVAONICA`, `WEBSHOP`, `POSLOVNICA`,
  `USLUZNO_MJESTO` (or a normalized custom type).
* `ADDRESS`: transliterated street + number + city, no spaces
  (`Č→C Ć→C Đ→D Š→S Ž→Z`): `VLASKA68_ZAGREB`.
* `FACILITY_ID`: required location identifier (`P1`); missing blocks
  generation. `SEQUENCE`: per-location counter, minimum two digits
  (`broj pohrane`), incremented on collision instead of overwriting.
* `DDMMYYYY`/`HHMM`: site-local zero-padded date/time. `EXT`: `CSV`/`XML`.
* Only the separator (`_`/`-`) and the letter case (upper/lower) are
  configurable, globally or per location.

Example: `PRODAVAONICA_VLASKA68_ZAGREB_P1_01_01102026_0750.CSV`
