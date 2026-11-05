# Prompt 4 — General improvement pass (no Docker needed)

> **Status (v2.0.22):** COMPLETED. Admin form ergonomics, contextual legal reference date hints, translated settings descriptions (825 strings, 0 untranslated), and expanded unit test coverage across admin settings classes (574 unit tests, 2,134 assertions) shipped.

```
You are improving the WordPress plugin in the current directory (sidrena-cijena.php is
the bootstrap). Pick a small number of high-value items (2-3 per session, not more unless
trivial), implement them well, cover every change with tests, and commit atomically
(feat:/fix:/test:/docs:). Do not change legal semantics without reviewing
`docs/legal-rules.md` first. Work only inside this plugin; never touch WordPress core,
themes or WooCommerce; price-history tables are append-only legal evidence.

MANDATORY BRANCH & PULL REQUEST WORKFLOW:
1. Checkout a dedicated branch before making changes:
   `git checkout -b <type>/<improvement-name>`
   (e.g., `feat/admin-form-ergonomics`, `fix/variation-anchor-save`, `test/coverage-expansion`)
2. Commit each item separately with conventional messages.
3. When finished, push branch and open a Pull Request for user review:
   `git push -u origin <branch-name>`
   `gh pr create --base master --title "<type>: <brief description>" --body "..."`
   Do NOT push directly to master or merge the PR yourself.

Specialised passes exist — use them instead of improvising when the work matches:
- Performance: AGENTARDS/06-performance-db-queries.md (queries, N+1, indexes),
  AGENTARDS/07-performance-caching-runtime.md (caching, per-request work, assets),
  AGENTARDS/08-performance-export-cron.md (generation pipeline, memory, cron).
- UI/UX: AGENTARDS/09-ui-ux-improvements.md (admin screens, public price list, a11y, i18n).
If you touch those areas here, keep the change small and log the follow-up in
docs/improvement-backlog.md as a PERF-xx or UX-xx item.

This pass focuses on robustness and compatibility:
1. Robustness:
   - Add unit tests for uncovered branches in src/ (aim for real behavior coverage).
   - Sweep error paths: validation failures, empty catalogs, missing tables/options after
     migration, malformed CSV rows, corrupt published files, permission-denied admin posts.
   - Actively inspect and reproduce admin save workflows, especially WooCommerce product editing:
     trace the editor form, plugin controls, submit buttons, nonces, and POST handlers. Fix
     discovered defects and catch invalid nested forms, duplicate/shared _wpnonce fields, plugin
     action values overriding Update, and browser required fields.
   - Check setup, store/location, product, and sale forms for fields whose expected value is
     unclear. Add concise, translated helper text with a format/example or source-of-truth hint.
   - Verify transient/option cleanup on deactivation and uninstall (Support\Deactivator,
     Support\Uninstaller) leaves the anchor/lowest_30_day evidence intact.
   - Verify label preset resolution (Settings::anchor_label_presets()) and dynamic date token
     substitution (PriceDisplay::format_anchor_label()).
2. Compatibility:
   - Strict PHP 7.4 to 8.4 compatibility: no PHP 8-only syntax in src/ (static scan).
   - Clean operation both with and without WooCommerce (standalone catalog provider).
   - When WooCommerce is active, verify anchor-price and special-sale controls fit the
     native Product data editor tab without breaking regular product updates.
   - Classic and block themes render anchor/30-day lines (PriceDisplay, BuilderCompat).
     Block theme: filter_price_block accepts 3 args and reads $block_instance->context.
3. Consistency with docs:
   - Keep `docs/` in sync with behaviour you touch.
   - Log completed items in `docs/improvement-backlog.md` (FIX-xx/IMP-xx/REL-xx) and add
     verification notes to `docs/release-readiness.md`.
4. Quality gates & PR creation:
   - Run fast unit tests locally: `composer test:unit`.
   - Regenerate translation catalogs if strings changed:
     `php tools/i18n/make-pot.php --locale=hr_HR` (809 translated / 0 untranslated).
   - Push branch to origin: `git push -u origin <branch-name>`.
   - Create Pull Request: `gh pr create --base master --title "<type>: <description>" --body "<details>"`.
   - Output PR link to user and request their review.

Done when: improvements and tests are committed, fast local checks pass cleanly,
branch is pushed, and a Pull Request is opened for user review.
```
