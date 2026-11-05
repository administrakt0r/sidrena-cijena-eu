# Prompt 10 — Prompt-pack drift audit: keep AGENTARDS/ in sync with the codebase (no Docker needed, full gates optional)

> **Status (v2.0.0):** COMPLETED. Drift check executed and synchronized against v2.0.0 release.

```
You are the maintainer of the AGENTARDS prompt pack shipped inside this WordPress
plugin (sidrena-cijena.php is the bootstrap). The files in AGENTARDS/ are executed
by AI coding agents against this repository; a stale prompt sends the next agent
after deleted files, renamed classes or invented test counts. Audit every prompt
file against the real state of the codebase and repair the drift so the pack keeps
the promise the README makes ("self-contained, completely up to date"). Work only
inside this plugin; never touch WordPress core, themes or WooCommerce. Do not
change legal semantics (docs/legal-rules.md), the CSV/XML contracts or the
filename pattern. Prompt-pack maintenance needs no version bump and no release
archive; commit atomically (docs:) and push to origin master.

Baseline: run `composer test:unit` first. Use the reported test/assertion counts as
the source of truth for every number. Never invent numbers; if a suite could not run,
say which one instead of guessing.

Work items:
1. Structural audit (every AGENTARDS/*.md). Each referenced path must exist
   (src/, tests/, docs/, tools/, templates/, root files). Each named class,
   constant or function must exist in the codebase — a prompt citing a class that
   was renamed or merged away (e.g. `Support\HistoryLabels` after it became
   `Support\Labels`) is drift. Each command must be a real composer/npm script or
   shipped tool (`composer test:unit`, `composer test:integration`,
   `composer lint`, `composer analyse`, `npm run lint:js`, `npm run test:e2e`,
   `tools/build-zip.sh`, `tools/i18n/make-pot.php`, `tools/verify.sh`,
   `./local.sh`). Each reference to another prompt or doc file must resolve.
2. Factual drift. Every stated baseline — unit/integration/E2E counts, assertion
   counts, translation string and untranslated counts, plugin version, ports —
   must match current `composer test:unit` output and `php tools/i18n/make-pot.php --locale=hr_HR`.
   All prompts must agree with each other and with the README; update every occurrence
   to the same measured values.
3. Conventions and policy. The backlog conventions quoted in prompts (`FIX-`,
   `IMP-`, `REL-`, `PERF-`, `UX-xx`) must match docs/improvement-backlog.md. The
   release procedure must match AGENTS.md, AGENTARDS/README.md and tools/build-zip.sh:
   version in 4 places (`Version:` header + `SIDRENA_CIJENA_VERSION` in
   sidrena-cijena.php, `Stable tag:` + a changelog entry in readme.txt,
   `"version"` in package.json), then `tools/build-zip.sh <version>` locally, then
   `unzip -t versions/<version>/sidrena-cijena-eu.zip`, then docs updates
   (docs/improvement-backlog.md, docs/release-readiness.md), then push to origin
   master and verify CircleCI green. The hard rules (heavy CPU tasks offloaded to CircleCI,
   append-only price history, reference dates general 2026-09-10 / legacy 2025-05-02,
   30-day window, filename pattern, 30+ day archive, blocking publication gate, functional
   with and without WooCommerce, never `wp plugin install --force`, E2E only on port 8889 — never 8888,
   label presets must use Ministry-permitted wording) must read identically wherever they appear.
4. Work items versus reality. When a prompt's work items have shipped, the prompt
   must say so: cross-check each prompt against docs/improvement-backlog.md and
   docs/release-readiness.md, and annotate finished items with the backlog entry
   (e.g. "items 1–3 shipped as UX-01..UX-03") instead of letting completed work
   read as pending. Never delete work items; annotate them.
5. The README table. Filenames, numbering, purposes and the "Needs Docker?"
   column must match the files actually present — add a row for every new prompt
   (including prompts 11, 12, 13 that were added in v2.0.20) and keep the intro
   paragraph describing the current set.
6. repository path. The correct local path is `/home/q/Desktop/wordpress/plugins/sidrena-cijena`.
   Any prompt still referencing the old path `/home/q/Desktop/wordpressprojects/plugins/sidrena-cijena`
   must be corrected.
7. Legal deadline. The enforcement deadline is 17 November 2026 (NN 110/2026). Any
   prompt mentioning 1 October 2026 as an enforcement date must be corrected.
8. Repair, prove, ship. Fix what you find, verify via fast local unit tests
   (`composer test:unit`), and after any string change regenerate the catalogs
   (`php tools/i18n/make-pot.php --locale=hr_HR`) and confirm 809 translated strings / 0 untranslated in
   languages/hr_HR.po. Commit atomically (docs:) and push to origin master.

Done when: every path, class, command and number referenced by AGENTARDS/*.md
resolves against the current tree and agrees with current test run output; shipped work
items are annotated; the README table matches all files including 11, 12, 13; all gates
are green and the changes are pushed.
```
