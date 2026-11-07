# Prompt 10 — Prompt-pack drift audit: keep AGENTARDS/ in sync with the codebase (no Docker needed, full gates optional)

> **Status (v2.0.22):** COMPLETED. Full prompt-pack drift audit completed; all 13 prompts synchronized against codebase baseline (614 unit tests, 2335 assertions, 875 translation strings), main base branch, and actual file paths.

```
You are the maintainer of the AGENTARDS prompt pack shipped inside this WordPress
plugin (titanworks-bazna-cijena.php is the bootstrap). The files in AGENTARDS/ are executed
by AI coding agents against this repository; a stale prompt sends the next agent
after deleted files, renamed classes or invented test counts. Audit every prompt
file against the real state of the codebase and repair the drift so the pack keeps
the promise the README makes ("self-contained, completely up to date"). Work only
inside this plugin; never touch WordPress core, themes or WooCommerce. Do not
change legal semantics (docs/legal-rules.md), the CSV/XML contracts or the
filename pattern.

MANDATORY BRANCH & PULL REQUEST WORKFLOW:
1. Checkout a dedicated branch:
   `git checkout -b docs/prompt-drift-repair`
2. Commit changes atomically with `docs:` prefix.
3. Push branch and open a Pull Request for user review:
   `git push -u origin docs/prompt-drift-repair`
   `gh pr create --base main --title "docs: prompt-pack drift audit and synchronization" --body "..."`
   Do NOT push directly to main or merge the PR yourself.

Baseline: run `composer test:unit` first. Use the reported test/assertion counts as
the source of truth for every number. Never invent numbers; if a suite could not run,
say which one instead of guessing.

Work items:
1. Structural audit (every AGENTARDS/*.md). Each referenced path must exist
   (src/, tests/, docs/, tools/, templates/, root files). Each named class,
   constant or function must exist in the codebase.
2. Factual drift. Every stated baseline — unit/integration/E2E counts, assertion
   counts, translation string and untranslated counts, plugin version, ports —
   must match current `composer test:unit` output and `php tools/i18n/make-pot.php --locale=hr_HR`.
   All prompts must agree with each other and with the README.
3. Conventions and policy. The PR-based workflow (checkout branch, conventional commits,
   fast local checks, push branch, create PR via `gh pr create --base main`, human review
   before merge) must be stated in every prompt.
4. Work items versus reality. When a prompt's work items have shipped, the prompt
   must say so: cross-check each prompt against docs/improvement-backlog.md and
   docs/release-readiness.md, and annotate finished items.
5. The README table. Filenames, numbering, purposes and the "Needs Docker?"
   column must match the files actually present — all prompts (01-13) present and described.
6. Repository path. The correct local path is `/home/q/Desktop/wordpressprojects/plugins/titanworks-sidrena-bazna-cijena-dev`.
7. Legal deadline. The enforcement deadline is 17 November 2026 (NN 110/2026).
8. Repair, prove, open PR. Fix what you find, verify via fast local unit tests
   (`composer test:unit`), and after any string change regenerate catalogs
   (`php tools/i18n/make-pot.php --locale=hr_HR`). Push branch and open Pull Request.

Done when: every path, class, command and number referenced by AGENTARDS/*.md
resolves against the current tree and agrees with current test run output; shipped work
items are annotated; the README table matches all files; branch is pushed and PR is opened.
```
