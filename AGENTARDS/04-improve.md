# Prompt 4 — General improvement pass (no Docker needed)

> **Status (v2.0.12):** COMPLETED. Product editor save workflows, nonce safety, form ergonomics, and regression coverage shipped.

```
You are improving the WordPress plugin in the current directory (sidrena-cijena.php is
the bootstrap). Pick a small number of high-value items (2-3 per session, not more unless
trivial), implement them well, cover every change with tests, and commit atomically
(feat:/fix:/test:/docs:). Do not change legal semantics without reviewing
`docs/legal-rules.md` first. Work only inside this plugin; never touch WordPress core,
themes or WooCommerce; price-history tables are append-only legal evidence.

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
   - Actively inspect and reproduce admin save workflows (do not limit the pass to known
     backlog items), especially WooCommerce product editing: trace the editor form, plugin
     controls, submit buttons, nonces, and POST handlers. Fix discovered defects and catch invalid
     nested forms, duplicate/shared `_wpnonce` fields, plugin `action` values that override the
     normal Update action, and browser `required` fields that block unrelated saves. Check that
     ordinary product edits still save and return to the editor, while dedicated plugin actions
     still reach their own handler; add a regression test for each discovered failure.
   - Check setup, store/location, product, and sale forms for fields whose expected value is
     unclear. Add concise, translated helper text with a format/example or source-of-truth hint;
     do not invent legal guidance.
   - Verify transient/option cleanup on deactivation and uninstall (Support\Deactivator,
     Support\Uninstaller) still leaves the anchor/lowest_30_day evidence intact (IMP-09).
2. Compatibility:
   - Strict PHP 7.4 to 8.4 compatibility: no PHP 8-only syntax in src/ (static scan).
   - Clean operation both with and without WooCommerce (standalone catalog provider).
   - When WooCommerce is active, verify the anchor-price and special-sale controls fit the
     native Product data editor (not a cramped Gutenberg block or competing meta box), and
     that the product editor's normal Update action remains independent of plugin evidence
     actions. Prefer a focused Playwright regression test when wp-env is available.
   - Classic and block themes render the anchor/30-day lines (PriceDisplay,
     BuilderCompat, render_block path).
3. Consistency with docs:
   - Keep `docs/` in sync with behaviour you touch (legal-rules, feature-acceptance,
     operations, testing, troubleshooting as applicable).
   - Log completed items in `docs/improvement-backlog.md` (FIX-xx/IMP-xx/REL-xx) and add
     verification notes to `docs/release-readiness.md`.
4. Quality gates & release:
   - Run all four static gates:
     `composer test:unit && composer lint && composer analyse && npm run lint:js`
   - If translation strings were updated, regenerate catalogs:
     `php tools/i18n/make-pot.php --locale=hr_HR` (705 translated strings / 0 untranslated in languages/hr_HR.po).
   - Follow the release procedure in AGENTARDS/README.md:
     1. Bump version in sidrena-cijena.php, readme.txt, and package.json.
     2. Run `tools/build-zip.sh <version>`.
     3. Verify with `unzip -t versions/<version>/sidrena-cijena-eu.zip`.
     4. Append release notes to docs/release-readiness.md and update docs/improvement-backlog.md.
     5. Commit all changes including versions/<version>/ and push to origin master.

Done when: improvements and tests are committed, all gates pass cleanly, translations are
complete if strings changed, and a new version archive is built, verified, and pushed.
```
