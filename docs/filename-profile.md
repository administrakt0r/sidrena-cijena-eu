# Filename profile decision — 25 September 2026

Decision: keep the existing normalized filename contract. No filename setting,
default, published URL or retained archive entry changes in this release.

The repository contract at commit `a271c6a` is preserved in Git at
`specs/001-sidrena-cijena-compliance/contracts/filename.md`. Its runtime pattern is:

`{FACILITY_FORM}_{ADDRESS}_{FACILITY_ID}_{SEQUENCE}_{DDMMYYYY}_{HHMM}.{EXT}`

| Component | Current example | Supplied ministry example |
|-----------|-----------------|---------------------------|
| Facility | PRODAVAONICA | prodavaonica |
| Street and city | VLASKA68_ZAGREB | Ilica 150 Zagreb |
| Facility identifier | P1 | P-01 |
| Storage number | 01 | 104 |
| Date | 25092026 | 01.10.2026 |
| Time | 0101 | 07:45 |
| Format | CSV | xml |

Our per-location sequence **is** the storage number; it is not a missing field.
The differences are normalization, punctuation and case. The existing generator
transliterates Croatian diacritics, strips spaces and identifier punctuation,
and supports uppercase/lowercase and underscore/hyphen separators. A location
may override separator/case. It requires an address and an identifier and rejects
invalid timestamps and unsupported extensions. Published files remain immutable.

Keeping this contract avoids changing existing download names and integrations.
The provided example alone does not establish whether its punctuation is mandatory.
Therefore we do not describe the normalized format as legally equivalent to that
example. A selectable ministry profile is deferred until the merchant's review of
the actual guidance establishes the exact required syntax, including escaping
and whether colons must occur in the on-disk name or only the download name.

Review this decision and CSV/XML field layouts against NN 101/2016 (as requested
in the handoff), the applicable 2026 decisions and ministry guidance. The historical
repository contract cites NN 101/2026-1213; resolve the year/reference discrepancy
with the authoritative texts. This document is an engineering decision, not legal
advice or a certification of the filename.

Verification: existing FilenameGenerator unit tests and CSV/XML integration and
E2E publication/download checks passed with release 1.0.0. This decision changes
documentation only; no filename code changed.
