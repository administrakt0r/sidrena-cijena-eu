# Sidrena Cijena — finish the plugin (agent handoff prompt)

> **Status (v2.0.0):** COMPLETED. Release 2.0.0 (Sidrena Cijena EU) finalized, quality gates verified, and package created.

You are the coding agent for **Sidrena Cijena**, a WordPress plugin that implements the
Croatian "sidrena cijena" (anchor price) obligation, the daily machine-readable price-list
obligation and the 30-day lowest-price rule for classified sales.

* Repository: `/home/q/Desktop/wordpress/plugins/sidrena-cijena`
* Branch `master`, remote `origin https://github.com/administrakt0r/sidrena-cijena`
* Git author: configured repo-locally as `administrakt0r <markoes3@gmail.com>`
* Read `AGENTARDS/README.md` first — it defines the mandatory workflow and rules.

## Mandatory Branch & Pull Request (PR) Policy

> [!IMPORTANT]
> **NEVER push directly to `master`. ALWAYS open a Pull Request.**
> All changes must be made on a dedicated branch and submitted via `gh pr create` for human review before merging.

1. Checkout branch: `git checkout -b <type>/<description>`
2. Make atomic conventional commits (`feat:`, `fix:`, `test:`, `docs:`, `chore:`).
3. Run fast local sanity checks (`composer test:unit`, `php tools/i18n/make-pot.php --locale=hr_HR`).
4. Push branch: `git push -u origin <branch-name>`.
5. Open PR: `gh pr create --base master --title "..." --body "..."`.
6. STOP. Output PR link and wait for user review and merge.

## Non-negotiable rules

1. **Plugin boundaries:** Work only inside this plugin (`src/`, `assets/`, `tests/`, `languages/`, `docs/`, `tools/`, root config files). Never touch WordPress core, theme or WooCommerce files.
2. **Append-only history:** Never invent or guess historical prices; `wp_sidrena_price_history` rows are immutable legal evidence.
3. **Legal semantics:** Any change to reference dates (`2025-05-02` for legacy categories, `2026-09-10` for general), the 30-day window, the filename pattern, or the enforcement deadline (17 November 2026 per NN 110/2026) must be reviewed against `docs/legal-rules.md` and committed as a legal-change with explicit justification.
4. **Standalone catalog:** Keep the plugin functional with and without WooCommerce — no fatal when WooCommerce is absent.
5. **CRITICAL — Never `wp plugin install --force`:** The host directory is bind-mounted into Docker; `--force` deletes the directory contents!
6. **CPU policy (AGENTS.md):** Run only fast local checks (`php -l`, `composer test:unit`, `php tools/i18n/make-pot.php --locale=hr_HR`). Heavy gates (PHPCS, PHPStan, integration tests, Playwright) offload to CircleCI on push.
7. **Label presets:** Never invent label text without basis in the Ministry of Economy clarification (22 Sep 2026). Permitted labels include: "Sidrena cijena", "Bazna cijena", "Referentna cijena", "Dodatna cijena", "Cijena na dan [datum]", "Cijena na [datum]", "Najniža redovna cijena ([datum])". Dynamic tokens {datum}/{date}/{datum_iso} must resolve to the category-appropriate reference date.

## Current baseline (v2.0.20)

Confirm these are still green before starting any new work:
- Unit suite: **535 tests / 1916 assertions** (`composer test:unit` — ~6 s).
- Translations: **809 strings, 0 untranslated** (`php tools/i18n/make-pot.php --locale=hr_HR`).
- Integration suite (CircleCI): 96 tests / 454 assertions.
- E2E suite (CircleCI): 42 Playwright specs on port 8889.
- PHPStan level 6: 0 errors · PHPCS clean · ESLint clean.
- `./local.sh` exits 0, demo site at http://localhost:8888.

## Work in priority order

1. **Remaining open items in `docs/improvement-backlog.md`:**
   Read every row with status `todo` or `in progress`; pick the highest-priority item and implement it on a branch.
   Do not skip the docs update — log each change as a new row (FIX-xx/IMP-xx/PERF-xx/UX-xx).
2. **Production deployment alignment:**
   The production site at https://wordpress.sidrenacijena.eu/ runs an older version.
   For remote production deploys, see AGENTARDS/11-production-deployment.md.
3. **readme.txt Stable tag:**
   Confirm `readme.txt` Stable tag matches `titanworks-sidrena-bazna-cijena.php` Version.
4. **WooCommerce block theme / Product Collection archive:**
   PriceDisplay::filter_price_block accepts 3 args and reads $block_instance->context['postId']
   for Product Collection archive context.
5. **WordPress.org submission:**
   See `docs/wordpress-org-publishing.md`. The plugin is not yet submitted to WP.org; the
   public release repo is `administrakt0r/titanworks-sidrena-bazna-cijena` (tagged releases).

## Definition of done

* Fast local checks pass; CircleCI pipeline passes 100% green on the branch.
* Zero untranslated strings in Croatian catalog.
* Pull Request opened on GitHub via `gh pr create` with test evidence and description.
* Complete report summarizing completed work, gate results, and PR link for user review.
