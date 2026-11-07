# Prompt 2 — Compliance cross-check (no Docker needed)

> **Status (v2.0.22):** COMPLETED. Statutory Croatian price control decree (NN 101/2026, NN 110/2026, NN 40/25) and Consumer Protection Act (NN 19/22, NN 59/26) cross-compliance verified, "Tri cijene" triple-price rules enforced, admin statutory guide panel integrated, and test suite green.

```
You are auditing the plugin in the current directory against Croatian statutory requirements
and its documentation. Read `docs/legal-rules.md`, `docs/feature-acceptance.md`,
`docs/price-list-format.md`, `docs/filename-profile.md`, `docs/price-history.md`,
`docs/operations.md`, and the known-findings record `docs/compliance-audit-2026-09-24.md`
first. Baseline: 615+ unit tests / 2333+ assertions green before you start.

MANDATORY BRANCH & PULL REQUEST WORKFLOW:
1. Checkout a dedicated branch before making changes:
   `git checkout -b compliance/legal-cross-check`
2. Commit each fix separately with conventional messages (fix:/test:/docs:).
3. When finished, push branch and open a Pull Request for user review:
   `git push -u origin compliance/legal-cross-check`
   `gh pr create --base main --title "compliance: legal rules and statutory format cross-check" --body "..."`
   Do NOT push directly to main or merge the PR yourself.

Statutory Legal Context:
- Primary legislation:
  * Zakon o iznimnim mjerama kontrole cijena (NN 40/25).
  * Zakon o zaštiti potrošača (NN 19/22, 59/26), Article 19 (posebni oblici prodaje).
- Primary price control decrees:
  * NN 101/2026-1212 — Odluka o isticanju dodatne cijene kao mjera izravne kontrole cijena
    (anchor/sidrena/bazna cijena display obligation at retail points of sale, e-commerce, and ads).
  * NN 101/2026-1213 — Odluka o objavi cjenika proizvoda i usluga kao mjera izravne kontrole cijena
    (daily machine-readable CSV/XML price list obligation for all businesses with websites).
- Amendment decree:
  * NN 110/2026-1309 & NN 110/2026 — Odluke o izmjenama Odluka (29 Sep 2026).
    Postpones statutory enforcement from 1 October 2026 to 17 November 2026
    (statutory reference dates remain STRICTLY UNCHANGED).
- Statutory reference dates:
  * General reference date: 10 September 2026 (2026-09-10) — all B2C retailers and service providers.
  * Legacy reference date: 2 May 2025 (2025-05-02) — food, beverages, cosmetics, cleaning products,
    toiletries, and household items carried over from the earlier 2025 decree (NN 75/25).
- Ministry of Economy (MINGO) clarification (22 Sep 2026):
  * Permitted wording: "Bazna cijena", "Sidrena cijena", "Cijena na dan [datum]", "Cijena na [datum]",
    "Referentna cijena", "Dodatna cijena", "Najniža redovna cijena ([datum])".
  * Scope: all businesses with websites (including presentation/informational sites); social media
    profiles alone do not qualify as websites.
  * Post-reference listings: items first offered after the statutory baseline must record verified
    first-offer retail price and date; publishing a blank anchor is strictly prohibited.

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
5. "Tri cijene" (Triple Price) cross-compliance mandate:
   When an item is subject to a special form of sale (akcija, sniženje, rasprodaja), verify that
   three distinct prices are maintained and displayed without conflation:
   (1) Effective promotional price.
   (2) Lowest price in the preceding 30 days (Zakon o zaštiti potrošača čl. 19).
   (3) Anchor / base price valid on the reference date (NN 101/2026-1212).
   Verify that anchor prices are never used as 30-day prices, and 30-day prices are never used as
   anchor prices. Verify that anchor history rows are excluded from 30-day calculation inputs.
   Verify that promotional price is strictly lower than the 30-day lowest price.
6. First-offer boundaries (docs/feature-acceptance.md): brand new products published with the
   plugin active record first offer; existing/imported items are NEVER backfilled; draft/pre-baseline
   publishes excluded; exact variation identity preserved. Blank anchor prices are prohibited.
7. Validator coverage & Remediation Proposals: missing anchors, missing location addresses,
   missing barcodes, and unclassified promotions produce findings with concrete actionable proposals
   ("Prijedlog izmjene") directing merchants exactly what to adjust before publication (FR-007, FR-058).
8. Admin Statutory Cross-Compliance Guide (CompliancePage):
   Verify that the central compliance screen (`admin.php?page=sidrena-cijena-compliance`) renders the
   Croatian statutory compliance framework panel detailing NN 101/2026, NN 110/2026, the 17 November 2026
   enforcement date, reference dates, triple price display rules, and machine-readable price list duties.
9. Daily publication before 08:00 (docs/legal-rules.md):
   Mandatory daily price list generation before 08:00 every working morning; services updated upon
   every price change. 30-day public archive retained at `/arhiva-sidrene-cijene/`.
10. Mandatory catalog attributes:
    Barcodes (EAN/GTIN) and unit prices (cijena za jedinicu mjere per kg/l) must be validated for
    applicable consumer goods.
11. REST and Public Feeds: `/sidrena-cijena.xml` and `/sidrena-cijena.csv` serve published files
    with checksum headers; HTML price list at `/sidrena-cjenik/` and archive at `/arhiva-sidrene-cijene/`.
12. CLI contract: exit codes 0 (clean), 1 (error), 2 (finding warnings), 3 (blocking error);
    `seed-test-data` refuses without `--yes`.
13. Label presets compliance: verify that all 7 anchor label presets returned by
    Settings::anchor_label_presets() use legally permitted wording per Ministry clarification
    (22 Sep 2026). Dynamic date tokens ({datum}, {date}, {datum_iso}) must resolve to the correct
    category-specific reference date (2025-05-02 for legacy, 2026-09-10 for general).
14. Tooltip text accuracy: default anchor_tooltip_text must accurately explain the anchor as the
    "redovna maloprodajna cijena na propisani referentni datum prema mjerama izravne kontrole cijena"
    (NOT the 30-day lowest price rule). Verify Settings::all() migration.

When deviations or gaps are fixed:
- Run fast local unit suite: `composer test:unit`.
- Check translation completeness: `php tools/i18n/make-pot.php --locale=hr_HR`.
- Push branch and create Pull Request via `gh pr create --base main`.
- Output PR link to user for review and approval.

Done when: every compliance rule is backed by passing test coverage, deviations
are fixed and committed, and a Pull Request is opened for user review.
```
