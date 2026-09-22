# Price-list format

Both formats are always produced per active location. Output is
deterministic: identical input yields byte-identical files (excluding the
filename timestamp and the XML generation timestamp).

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
| 10 | `sidrena_cijena` | Anchor price (empty only when no anchor is recorded) |
| 11 | `barkod` | Barcode (empty when unknown) |
| 12 | `dostupnost` | `dostupno` / `nedostupno` |

Example:

```csv
vrsta;naziv;sifra;marka;jedinica_mjere;cijena_za_jedinicu_mjere;maloprodajna_cijena;poseban_oblik_prodaje;naziv_posebnog_oblika_prodaje;sidrena_cijena;barkod;dostupnost
proizvod;Kava mljevena 250 g;KAV-250;Franck;kg;28,00;6,99;DA;akcija;7,49;3851234567890;dostupno
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
  nazivPosebnogOblikaProdaje, sidrenaCijena, barkod, dostupnost`.
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
