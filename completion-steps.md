# Sidrena cijena compliance remediation: completion steps

This plan turns the read-only audit into small, verifiable implementation tasks. It is a work queue, not a claim that every audit finding is still present. The repository has continued to change since the audit, and `docs/improvement-backlog.md` records later fixes (including price-history and variation work). **Recheck the current code and LocalWP behavior before implementing each item.** Close an item as already fixed only when fresh evidence supports that conclusion.

The audit’s legal baseline was the Croatian Consumer Protection Act, NN 19/2022, as amended by NN 59/2026, especially Article 19. Recheck the current official text and any later official guidance before changing legal behavior. Do not silently choose an interpretation for statutory exceptions, qualifying promotion types, services, or advertising scope; record **LEGAL INTERPRETATION / NEEDS HUMAN REVIEW** and keep the implementation configurable or blocked as appropriate.

## Working rules

- Work on **one numbered task per AI run**. Start with Task 1. Finish its implementation and verification, report it, and stop. Continue only when the user asks to proceed to the next task.
- Before editing, inspect `AGENTS.md` files and `AGENTARDS/README.md`, `docs/development.md`, `docs/testing.md`, and the relevant feature and legal documents. Respect their current local-environment instructions.
- Protect user work and local data. Inspect the working tree before editing. Never reset, clean, destroy, reseed, overwrite, or point destructive tests at a user’s LocalWP site. Use an isolated test site/database for integration or browser tests. Confirm the selected site and URL before browser actions.
- Keep production changes inside the active task. Add or update focused regression tests for changed behavior. Do not quietly roll fixes for newly discovered issues into the current task.
- Use the current official Croatian legal text and official authority guidance as evidence. Separate **REQUIRED**, **IMPLEMENTATION SAFETY**, **RECOMMENDATION**, and **LEGAL UNCERTAINTY**. Do not present engineering advice as law.
- Verify the changed behavior in layers: focused tests, relevant broader tests, then the configured local WordPress/WooCommerce runtime where applicable. Report commands, site/environment, outcomes, and anything not verified. Never claim a test passed without running it.
- If a task uncovers another defect, classify it as **ASAP** (can misstate a consumer price/reference price, expose a serious data-integrity issue, or block safe release) or **FUTURE** (important but not a current release blocker). Give evidence and add a proposed follow-up to the report; do not start that work in the same run.

## Completion status (2026-09-27)

All 10 tasks have been implemented, tested and verified green:

| Task | Status | Verified |
|------|--------|----------|
| 1 — Refresh the audit | ✅ Completed | Code review, no production changes |
| 2 — Make the historical price timeline reliable | ✅ Completed | Unit + integration tests |
| 3 — Connect WooCommerce promotional state | ✅ Completed | Unit + integration + E2E |
| 4 — Keep variation offers and reference prices paired | ✅ Completed | Unit + integration + E2E |
| 5 — Cover customer-visible price locations | ✅ Completed | Unit + E2E |
| 6 — Review plugin-generated feeds and promotional output | ✅ Completed | Unit + integration |
| 7 — Guarantee clear Croatian customer wording | ✅ Completed | Unit + integration + i18n |
| 8 — Verify time, cache, and sale-end behavior | ✅ Completed | Unit + integration |
| 9 — Make the calculated result explainable | ✅ Completed | Unit tests |
| 10 — Final focused compliance regression pass | ✅ Completed | All layers green |

Final baseline: 361 unit tests / 1296 assertions, 96 integration tests / 454 assertions,
42 Playwright E2E specs, PHPStan Level 6 (0 errors, 117 files), PHPCS clean (175 files),
ESLint clean, 705 translated strings / 0 untranslated.

## Ordered task list

### Task 1 — Refresh the audit against current code and legal sources

Recheck the current implementation, tests, working tree, `docs/improvement-backlog.md`, and relevant release notes against the original findings below. Verify the currently applicable text of Article 19 from Narodne novine and relevant official guidance. Make no production-code changes in this task. Produce a short evidence-backed list of findings that remain open, are already fixed, or need a legal/human decision. Identify the one next task that should be implemented first.

Original audit topics to revalidate:

- WooCommerce sale detection and its connection to calculated 30-day reference prices.
- Effective start/end times for regular and sale prices, including cleared and scheduled WooCommerce sales and multiple changes on one day.
- Variation-specific reference prices and any parent-to-variation fallback.
- Customer-facing WooCommerce display and plugin-generated promotional outputs such as public CSV/XML/API data.
- Croatian labels, caches, admin troubleshooting, and data retention.

### Task 2 — Make the historical price timeline reliable

After Task 1 confirms the interval problem remains, implement and test price-history intervals at the layer that owns price changes. Cover ordinary price edits, sale-price removal, scheduled sale start/end, timezone boundaries, repeated updates on one date, and successive reductions. Confirm imports, REST updates, bulk edits, and programmatic WooCommerce CRUD paths either record correctly or are explicitly detected/documented as unsupported. Do not derive history from the current regular price or latest row alone.

### Task 3 — Connect WooCommerce promotional state to the reference-price workflow

After reliable history exists, handle the lifecycle from normal price to promotion, continued or progressively reduced promotion, sale end, and a new nearby promotion. Ensure the value used is based on the applicable preceding period for the same offer. Avoid inventing classifications for legal exceptions; raise ambiguous cases for human review. Test that a discounted WooCommerce offer cannot silently publish without a usable reference value.

### Task 4 — Keep variation offers and reference prices paired

Verify and, if necessary, fix simple products, variable parents, and each purchasable variation across admin entry, historical identity, calculation, selected-variation AJAX data, archive cards, and feeds. Do not show one variation’s reference price beside another variation’s price. Add focused tests for variation selection and parent fallback behavior.

### Task 5 — Cover customer-visible price locations controlled by the plugin

Inventory and verify single-product pages, shop/category loops, related/upsell products, blocks/builders, variation AJAX, quick-view paths, and any other plugin-supported cards. Inspect cart output and state clearly whether the plugin controls it and whether the applicable presentation requirement needs it. Verify that whenever the plugin displays a covered reduced price, the corresponding reference price and unambiguous wording appear. Test mobile layout at the local site where the environment supports it.

### Task 6 — Review plugin-generated feeds and promotional output

Trace CSV/XML price lists, public HTML/REST endpoints, shortcodes, widgets, schema, and any feed integration present in the current code. For each output, state whether it contains or can advertise a reduced price and whether the relevant reference price accompanies it. Do not add a feed field solely by assumption: first check current official rules and documented feed contracts, flagging legal ambiguity for review. Test exact serialized output when changes are made.

### Task 7 — Guarantee clear Croatian customer wording

Verify the actual rendered Croatian strings in the configured LocalWP site, including translation loading and configurable labels. Ensure the reference is unmistakably the lowest price over the relevant preceding period, not a normal/original/recommended price. Avoid changing the anchor-price wording as a substitute. Test the installed translation catalog as well as the English fallback behavior.

### Task 8 — Verify time, cache, and sale-end behavior end to end

Inspect the plugin’s own caches and invalidation plus LocalWP/page-cache behavior that can be exercised safely. Check transitions at scheduled start/end, site timezone and daylight-saving boundaries, object/transient/REST caches, and sale expiration. Do not claim control over an external CDN or theme cache without evidence. Verify the old sale display disappears when the sale ends while its historical records remain available for later calculations.

### Task 9 — Make the calculated result explainable to administrators

Check that an administrator can see the selected 30-day lowest price, calculation window, exact product/variation, source records, and missing-history condition. Add only the smallest auditability improvement needed. Test that explanations agree with the calculation for multiple reductions, short history, no history, imports, and a sale that ended recently.

### Task 10 — Final focused compliance regression pass

Only after the preceding tasks are complete, run the targeted pricing, WooCommerce, display, feed, import, and lifecycle tests; relevant integration/E2E checks; and a fresh LocalWP walkthrough of normal → sale → continued sale → sale end → nearby new sale. Review Croatian output and variation selection. Summarize remaining legal uncertainty and any external theme/cache/feed locations that could not be verified. Do not broaden into unrelated cleanup.

## Completion report format for each task

1. **Task and result:** task number; completed, already fixed, blocked by a concrete dependency, or incomplete.
2. **What changed:** files and observable behavior, or state that no files changed for Task 1.
3. **Evidence:** tests and commands run with results; LocalWP site/environment and paths exercised; screenshots or concise rendered-output evidence when useful.
4. **Legal classification:** REQUIRED / IMPLEMENTATION SAFETY / RECOMMENDATION / LEGAL UNCERTAINTY, with official source and provision for legal claims.
5. **Residual limits:** untested locations, external integrations, assumptions, or human-review questions.
6. **New findings:** list each as ASAP or FUTURE, include evidence and the proposed task; do not implement it in this run.
7. **Next step:** name the next numbered task and stop for the user’s instruction.

## Copy/paste prompt for the implementation AI

```text
Read `completion-steps.md` and the repository instructions before doing anything. Work on Task 1 only unless I explicitly name a different task. The task list is sequential: complete one task, report it using the required completion format, and stop. Do not start the next task until I ask.

This repository has changed since the read-only compliance audit. Treat its findings as hypotheses, inspect the current source/tests/backlog, and verify whether each issue remains before changing code. Follow AGENTS.md and AGENTARDS/README.md instructions. Use the configured local WordPress/WooCommerce environment safely: inspect the target URL/site first, preserve all local data, and run destructive or state-mutating browser/integration tests only against an isolated test site/database. Never reset or destroy my LocalWP site.

For an implementation task, trace the behavior to its owning layer, make the smallest coherent change for that task, add/update focused regression tests, then run those tests and the relevant broader checks. Verify customer-visible behavior in the local WordPress/WooCommerce site when applicable, including Croatian text and variation behavior for display tasks. Report exact commands and outcomes; distinguish implementation failures from environment limitations. Do not claim verification that you did not perform.

Use current official Croatian legal sources for legal claims. Classify findings as REQUIRED, IMPLEMENTATION SAFETY, RECOMMENDATION, or LEGAL UNCERTAINTY. Do not make legal assumptions about exceptions, sale classifications, or advertising scope; flag them as LEGAL INTERPRETATION / NEEDS HUMAN REVIEW. Keep legal compliance behavior separate from the plugin’s anchor-price feature.

If you discover other issues, classify each as ASAP or FUTURE, explain the evidence and proposed follow-up, and leave it untouched unless it is necessary to complete the current task. At the end, use the completion-report format in `completion-steps.md`, identify the next task, and stop for my instruction.

Start with Task 1: refresh the audit against the current implementation, tests, local environment, backlog, and current official legal sources. Make no production-code changes during Task 1.
```
