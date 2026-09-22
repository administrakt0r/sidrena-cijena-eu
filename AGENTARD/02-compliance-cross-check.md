# Prompt 2 — Compliance cross-check (no Docker needed)

```
You are auditing the plugin in the current directory against its own
specification. Read specs/001-sidrena-cijena-compliance/spec.md,
contracts/ (csv-format, xml-format, filename, rest-api, cli,
wordpress-integration), data-model.md and quickstart.md first.

For each item below, verify the implementation and fix deviations
(tests first where behaviour changes). Commit each fix separately.

1. CSV contract: `;` delimiter, comma decimals, UTF-8 no BOM, LF endings,
   exact 12-column order, DA/NE flags, `dostupno`/`nedostupno`, products
   before services ordered by sifra/naziv/ID, effective retail price in
   `maloprodajna_cijena`. Generate a list via CsvWriter + PriceListBuilder
   in a unit test and diff it against tests/fixtures/example-price-list.csv
   structurally (header + row shapes).
2. XML contract: declaration, root <cjenik verzija pravilo generirano>,
   <prodajnoMjesto>, <stavka vrsta>, ASCII element names, empty elements
   omitted, point decimals, escaped entities. Validate
   tests/fixtures/example-price-list.xml with simplexml in a unit test.
3. Filename contract: {FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_
   {DDMMYYYY}_{HHMM}.{EXT}, transliteration (ČĆ→C, Đ→D, Š→S, Ž→Z), only
   separator/case configurable. Run FilenameGeneratorTest and add cases for
   diacritics, lowercase+dash config and per-location overrides.
4. 30-day engine: window = 30 calendar days before sale start, start
   excluded, day-30 included, site timezone; pure calculator; consecutive
   and overlapping sales; DST boundaries. Extend
   LowestPrice30DayCalculatorTest with any missing edge case.
5. Anchor vs 30-day separation: grep the codebase for any path where the
   anchor price is used as the 30-day price or vice versa; report or fix.
6. Validator coverage: every FR-058..FR-061 finding code must have a rule
   in src/Compliance/Rules/ and a case in ValidatorTest.
7. REST contract: routes, per_page clamps (500 / 200), ETag/Last-Modified,
   Cache-Control max-age=300, X-Robots-Tag, 304 handling, 429 throttle,
   capability on /status, price-data-only payloads.
8. CLI contract: exit codes 0/1/2/3 per contracts/cli.md for all seven
   subcommands; `seed-test-data` refuses without --yes/WP_ENV.

Done when: every contract clause maps to passing test coverage, deviations
are fixed and committed, and docs/price-list-format.md matches the code.
```
