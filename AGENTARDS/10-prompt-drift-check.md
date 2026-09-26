# Prompt 10 — Prompt-pack drift audit: keep AGENTARDS/ in sync with the codebase (no Docker needed, full gates optional)

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

Baseline (v1.0.13): 301 unit tests / 1078 assertions green — keep green, grow the
suite. Treat `tools/verify.sh` output as the source of truth for every number
(with Docker also `tools/verify.sh --with-integration --with-e2e`). Never invent
numbers; if a suite could not run, say which one instead of guessing.

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
   must match what `tools/verify.sh` reports and what AGENTARDS/README.md's
   "Current green baseline" section states. All prompts must agree with each
   other and with the README; update every occurrence to the same measured
   values.
3. Conventions and policy. The backlog conventions quoted in prompts (`FIX-`,
   `IMP-`, `REL-`, `PERF-`, `UX-xx`) must match docs/improvement-backlog.md. The
   release procedure must match AGENTARDS/README.md and tools/build-zip.sh:
   version in 4 places (`Version:` header + `SIDRENA_CIJENA_VERSION` in
   sidrena-cijena.php, `Stable tag:` + a changelog entry in readme.txt,
   `"version"` in package.json), then `tools/build-zip.sh <version>`, then
   `unzip -t versions/<version>/sidrena-cijena-eu.zip`, then docs updates
   (docs/improvement-backlog.md, docs/release-readiness.md), then push to origin
   master. The hard rules (append-only price history, reference dates general
   2026-09-10 / legacy 2025-05-02, 30-day window, filename pattern, 30+ day
   archive, blocking publication gate, functional with and without WooCommerce,
   never `wp plugin install --force`, E2E only on port 8889 — never 8888) must
   read identically wherever they appear.
4. Work items versus reality. When a prompt's work items have shipped, the prompt
   must say so: cross-check each prompt against docs/improvement-backlog.md and
   docs/release-readiness.md, and annotate finished items with the backlog entry
   (e.g. "items 1–3 shipped as UX-01..UX-03") instead of letting completed work
   read as pending. Never delete work items; annotate them.
5. The README table. Filenames, numbering, purposes and the "Needs Docker?"
   column must match the files actually present — add a row for every new prompt
   (including this one) and keep the intro paragraph describing the current set.
6. Repair, prove, ship. Fix what you find, re-run the host gates to prove the
   repository still matches what the prompts claim
   (`composer test:unit && composer lint && composer analyse && npm run lint:js`),
   and after any string change regenerate the catalogs
   (`php tools/i18n/make-pot.php --locale=hr_HR`) and confirm 0 untranslated in
   languages/hr_HR.po. Commit atomically (docs:) and push to origin master.

Done when: every path, class, command and number referenced by AGENTARDS/*.md
resolves against the current tree and agrees with `tools/verify.sh`; shipped work
items are annotated; the README table matches the files; all gates are green and
the changes are pushed.
```
