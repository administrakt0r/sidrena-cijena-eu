# Sidrena Cijena — finish the plugin (agent handoff prompt)

> **Status (v2.0.0):** COMPLETED. Release 2.0.0 (Sidrena Cijena EU) finalized, quality gates verified, and package created.

You are the coding agent for **Sidrena Cijena**, a WordPress plugin that implements the
Croatian "sidrena cijena" (anchor price) obligation, the daily machine-readable price-list
obligation and the 30-day lowest-price rule for classified sales.

* Repository: `/home/q/Desktop/wordpress/plugins/sidrena-cijena`
* Branch `master`, remote `origin https://github.com/administrakt0r/sidrena-cijena`
* Git author: configured repo-locally as `administrakt0r <markoes3@gmail.com>`
* Read `AGENTARDS/README.md` first — it defines the mandatory workflow and rules.

## Non-negotiable rules

1. **Plugin boundaries:** Work only inside this plugin (`src/`, `assets/`, `tests/`, `languages/`, `docs/`, `tools/`, root config files). Never touch WordPress core, theme or WooCommerce files.
2. **Append-only history:** Never invent or guess historical prices; `wp_sidrena_price_history` rows are immutable legal evidence.
3. **Legal semantics:** Any change to reference dates (`2025-05-02` for legacy categories, `2026-09-10` for general), the 30-day window, the filename pattern, or the enforcement deadline (17 November 2026 per NN 110/2026) must be reviewed against `docs/legal-rules.md` and committed as a legal-change with explicit justification.
4. **Standalone catalog:** Keep the plugin functional with and without WooCommerce — no fatal when WooCommerce is absent.
5. **CRITICAL — Never `wp plugin install --force`:** The host directory is bind-mounted into Docker; `--force` deletes the directory contents!
6. **CPU policy (AGENTS.md):** Run only fast local checks (`php -l`, `composer test:unit`, `php tools/i18n/make-pot.php --locale=hr_HR`). Heavy gates (PHPCS, PHPStan, integration tests, Playwright) offload to CircleCI on push. Release packaging (`tools/build-zip.sh`) runs locally.
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
   Read every row with status `todo` or `in progress`; pick the highest-priority item and implement it.
   Do not skip the docs update — log each change as a new row (FIX-xx/IMP-xx/PERF-xx/UX-xx).
2. **Production deployment alignment:**
   The production site at https://wordpress.sidrenacijena.eu/ runs an older version.
   For remote production deploys, see AGENTARDS/11-production-deployment.md.
3. **readme.txt Stable tag:**
   Confirm `readme.txt` Stable tag matches `sidrena-cijena.php` Version. If they diverge
   (e.g., Stable tag: 2.0.19 vs Version: 2.0.20), bump readme.txt Stable tag and add changelog
   entry, then rebuild the zip and push.
4. **WooCommerce block theme / Product Collection archive:**
   PriceDisplay::filter_price_block accepts 3 args and reads $block_instance->context['postId']
   for Product Collection archive context. If you change PriceDisplay, run:
   `composer test:unit -- --filter PriceDisplayTest`
5. **WordPress.org submission:**
   See `docs/wordpress-org-publishing.md`. The plugin is not yet submitted to WP.org; the
   public release repo is `administrakt0r/sidrena-cijena-eu` (tagged releases).

## Quality gates & release (mandatory at the end of every completed run)

1. **Fast local checks:**
   ```bash
   composer test:unit                                    # Must pass 535/1916 (or more)
   php tools/i18n/make-pot.php --locale=hr_HR            # Must report 0 untranslated
   ```
2. **Bump the version in all 4 places** (they must agree exactly):
   - `sidrena-cijena.php` (header `Version:` and constant `SIDRENA_CIJENA_VERSION`)
   - `readme.txt` (`Stable tag:` and `= X.Y.Z =` changelog entry)
   - `package.json` (`"version": "X.Y.Z"`)
3. **Mandatory Release Procedure:**
   - Build archive locally: `tools/build-zip.sh <version>`.
   - Verify archive: `unzip -t versions/<version>/sidrena-cijena-eu.zip`.
   - Update `docs/release-readiness.md` and `docs/improvement-backlog.md`.
   - Commit all changes including `versions/<version>/` and push to `origin master`.
   - Verify CircleCI pipeline passes green (`circleci run list`).

## Definition of done

* Fast local checks pass; CircleCI pipeline passes 100% green.
* Zero untranslated strings in Croatian catalog.
* Installable ZIP archive built locally under `versions/<version>/`, verified, committed, pushed, and published to `sidrena-cijena-eu`.
* `readme.txt` Stable tag matches plugin Version exactly.
* Complete report summarizing completed work, gate results, release version, and confirmation of legal rule integrity.
