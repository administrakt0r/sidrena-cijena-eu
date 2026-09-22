# AGENTARD — AI agent prompt pack for `sidrena-cijena`

Copy-paste prompts you can run with any AI coding agent (opencode, etc.) to
audit, fix, verify and improve this plugin. Run them **in order**; each prompt
is self-contained and states its own done-criteria.

| # | File | Purpose | Needs Docker? |
|---|------|---------|---------------|
| 1 | [01-audit-and-fix.md](01-audit-and-fix.md) | Static audit: lint, types, unit tests, fix loop | No |
| 2 | [02-compliance-cross-check.md](02-compliance-cross-check.md) | Spec/contract cross-check, fix deviations | No |
| 3 | [03-wp-env-verification.md](03-wp-env-verification.md) | Full wp-env run: integration + E2E + acceptance | **Yes** |
| 4 | [04-improve.md](04-improve.md) | Improvement pass: perf, UX, docs | No |

General rules for every prompt below:

- Work in the plugin root (`sidrena-cijena.php` lives here).
- Never modify WordPress core, theme or WooCommerce files.
- Never invent historical prices; history tables are append-only.
- Commit after each logical change with conventional messages
  (`feat:`, `fix:`, `test:`, `docs:`, `chore:`).
- Stop and report if a test fails and the fix is not obvious.
