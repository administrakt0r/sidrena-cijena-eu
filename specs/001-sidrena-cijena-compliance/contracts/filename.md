# Contract: Price-list filename generation

**Feature**: `001-sidrena-cijena-compliance` | **Version**: 1.0

Implements FR-038–FR-040 and the Odluka NN 101/2026-1213 requirement that filenames include the sales facility form, address, facility identifier, storage/sequence number and a submission timestamp.

## Pattern

```text
{FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT}
```

Example (illustrative, not hard-coded):

```text
PRODAVAONICA_VLASKA68_ZAGREB_P1_01_01102026_0750.CSV
```

(the address segment contains the normalized street, city and number, joined without spaces)

## Components

| Component | Source | Rules |
|-----------|--------|-------|
| `FACILITY_FORM` | Location type | Uppercase token: `PRODAVAONICA`, `WEBSHOP`, `POSLOVNICA`, `USLUZNO_MJESTO`, or a normalized custom type |
| `ADDRESS` | Location address + city | Diacritics transliterated (Č→C, Ć→C, Đ→D, Š→S, Ž→Z); spaces and non-alphanumeric characters removed; preserved order street+number+city |
| `FACILITY_ID` | Location identifier | Required; normalized alphanumeric; missing identifier blocks generation (validator ERROR) |
| `SEQUENCE` | Per-location counter | Two digits minimum (`01`, `02`, … `99`, `100`), incremented on every generated file for that location; the "broj pohrane" |
| `DDMMYYYY` | Submission date | Site local time, zero-padded |
| `HHMM` | Submission time | Site local time, 24-hour, zero-padded |
| `EXT` | Format | `CSV` or `XML` |

## Configurability

Per clarification Q5, the component set and order are fixed. Administrators may configure only:

- the separator between components (default `_`; allowed: `_`, `-`),
- letter case (default uppercase; allowed: uppercase, lowercase).

Both settings are validated; any other value falls back to the documented default. The active construction is shown in the admin settings and documented in `docs/price-list-format.md`.

## Validation

A filename is rejected before use when any of the following holds (validator code `invalid_filename`):

- a required component is empty,
- the normalized result contains characters outside `[A-Za-z0-9._-]`,
- the extension is not `CSV` or `XML`,
- the timestamp is missing or malformed,
- the name would collide with an existing retained file (the sequence is incremented instead).

No user-supplied path or filename is ever used directly; the generator is the only writer.
