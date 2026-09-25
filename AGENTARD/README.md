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
| 5 | [05-finish-the-plugin.md](05-finish-the-plugin.md) | Finish the plugin: remaining release queue and submission prep | No |

General rules for every prompt below:

- Work in the plugin root (`sidrena-cijena.php` lives here).
- Never modify WordPress core, theme or WooCommerce files.
- Never invent historical prices; history tables are append-only.
- For ANY live WordPress work (reproducing bugs, manual verification,
  screenshots, wp-env runs): use `./local.sh` to start the demo webshop
  and `./stoplocal.sh` to stop it. Do not hand-roll wp-env commands -
  `local.sh` already handles install, activation, EUR/Europe-Zagreb,
  demo products, anchors, a sale and price-list generation.
- Commit after each logical change with conventional messages
  (`feat:`, `fix:`, `test:`, `docs:`, `chore:`).
- After every edit batch: bump the version, rebuild the install zip,
  and follow the release policy below.

## Release policy (`tools/build-zip.sh`, `versions/README.md`)

1. Bump the version in `sidrena-cijena.php` (header + constant),
   `readme.txt` (Stable tag + changelog) and `package.json`.
2. Commit everything, then run `tools/build-zip.sh <version>`
   (produces `versions/<version>/sidrena-cijena.zip` + `SHA256SUMS.txt`).
3. Patch `0.0.x` → stays local (gitignored), never pushed.
   Minor `0.x.0` and major `x.0.0` → commit `versions/<version>/` and push.
