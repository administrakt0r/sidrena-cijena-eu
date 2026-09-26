# Prompt 2 — Compliance cross-check (no Docker needed)

```
You are auditing the plugin in the current directory against Croatian legal requirements
and its documentation. Read `docs/legal-rules.md`, `docs/feature-acceptance.md`,
`docs/price-list-format.md`, `docs/filename-profile.md`, `docs/price-history.md`,
`docs/operations.md`, and the known-findings record `docs/compliance-audit-2026-09-24.md`
first. Baseline: 268+ unit tests / 869+ assertions green before you start.

For each item below, verify the implementation and fix deviations (tests first
where behaviour changes). Commit each fix separately.

1. CSV contract (docs/price-list-format.md): `;` delimiter, comma decimals, UTF-8 no BOM,
   LF endings, exact 12-column order, DA/NE flags, `dostupno`/`nedostupno`, products
   before services ordered by sifra/naziv/ID, effective retail price in `maloprodajna_cijena`,
   and formula-injection neutralisation (`=`, `+`, `-`, `@` escaped). Diff generated output
   against tests/fixtures/example-price-list.csv structurally.
2. XML contract (docs/price-list-format.md): XML declaration, root <cjenik verzija pravilo generirano>,
   <prodajnoMjesto>, <stavka vrsta>, ASCII element names, empty elements omitted, point decimals,
   escaped entities. Validate tests/fixtures/example-price-list.xml with simplexml in a unit test.
3. Filename contract (docs/filename-profile.md):
   {FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT},
   transliteration (ČĆ→C, Đ→D, Š→S, Ž→Z), only separator/case configurable.
   Run FilenameGeneratorTest and verify edge cases.
4. 30-day engine (docs/legal-rules.md): window = 30 calendar days before sale start
   (`start-30` to `start-1`), start date excluded, day-30 included, site timezone; pure calculator;
   consecutive and overlapping sales; DST boundaries. Never use `lowest_30_day` row as its own input.
   Verify with LowestPrice30DayCalculatorTest.
5. Anchor vs 30-day separation: verify that anchor prices are never used as 30-day prices,
   and 30-day prices are never used as anchor prices.
6. First-offer boundaries (docs/feature-acceptance.md): brand new products published with the
   plugin active record first offer; existing/imported items are NEVER backfilled; draft/pre-baseline
   publishes excluded; exact variation identity preserved.
7. Validator coverage: missing anchors, missing location addresses, and unclassified promotions
   produce blocking ERROR findings that prevent price-list publication (FR-007).
8. REST and Public Feeds: `/sidrena-cijena.xml` and `/sidrena-cijena.csv` serve published files
   with checksum headers; HTML price list at `/sidrena-cjenik/` and archive at `/arhiva-sidrene-cijene/`.
9. CLI contract: exit codes 0 (clean), 1 (error), 2 (finding warnings), 3 (blocking error);
   `seed-test-data` refuses without `--yes`.

When deviations or gaps are fixed:
- Run all static gates: `composer test:unit && composer lint && composer analyse && npm run lint:js`.
- Follow the release procedure in AGENTARD/README.md: bump version, build zip with `tools/build-zip.sh <version>`,
  verify with `unzip -t`, update docs, commit and push to origin master.

Done when: every compliance rule is backed by passing test coverage, deviations
are fixed and committed, and a new version is packaged and pushed.
```
