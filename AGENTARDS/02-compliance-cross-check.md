# Prompt 2 — Compliance cross-check (no Docker needed)

> **Status (v2.0.0):** COMPLETED. Legal and specification cross-check verified and shipped.

```
You are auditing the plugin in the current directory against Croatian legal requirements
and its documentation. Read `docs/legal-rules.md`, `docs/feature-acceptance.md`,
`docs/price-list-format.md`, `docs/filename-profile.md`, `docs/price-history.md`,
`docs/operations.md`, and the known-findings record `docs/compliance-audit-2026-09-24.md`
first. Baseline: 535+ unit tests / 1916+ assertions green before you start.

MANDATORY BRANCH & PULL REQUEST WORKFLOW:
1. Checkout a dedicated branch before making changes:
   `git checkout -b compliance/legal-cross-check`
2. Commit each fix separately with conventional messages (fix:/test:/docs:).
3. When finished, push branch and open a Pull Request for user review:
   `git push -u origin compliance/legal-cross-check`
   `gh pr create --base master --title "compliance: legal rules and statutory format cross-check" --body "..."`
   Do NOT push directly to master or merge the PR yourself.

Legal context:
- Primary decrees: NN 101/2026-1212 (anchor/sidrena cijena display obligation) and
  NN 101/2026-1213 (daily machine-readable CSV/XML price list obligation).
- Amendment: NN 110/2026 postpones enforcement from 1 October 2026 to 17 November 2026
  (reference dates are UNCHANGED).
- General reference date: 2026-09-10 (all B2C retailers/services).
- Legacy reference date: 2025-05-02 (food, beverages, cosmetics, cleaning products,
  toiletries, household products — legacy categories from the 2025 regime).
- Ministry of Economy clarification (22 Sep 2026) permits alternative wording:
  "Bazna cijena", "Cijena na [datum]", "Referentna cijena", "Dodatna cijena", etc.

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
10. Label presets compliance: verify that all 7 anchor label presets returned by
    Settings::anchor_label_presets() use legally permitted wording per Ministry clarification
    (22 Sep 2026). Dynamic date tokens ({datum}, {date}, {datum_iso}) must resolve to the correct
    category-specific reference date (2025-05-02 for legacy, 2026-09-10 for general).
11. Tooltip text accuracy: default anchor_tooltip_text must accurately explain the anchor as the
    "redovna maloprodajna cijena na propisani referentni datum prema mjerama izravne kontrole cijena"
    (NOT the 30-day lowest price rule). Verify Settings::all() migration.

When deviations or gaps are fixed:
- Run fast local unit suite: `composer test:unit`.
- Check translation completeness: `php tools/i18n/make-pot.php --locale=hr_HR`.
- Push branch and create Pull Request via `gh pr create --base master`.
- Output PR link to user for review and approval.

Done when: every compliance rule is backed by passing test coverage, deviations
are fixed and committed, and a Pull Request is opened for user review.
```
