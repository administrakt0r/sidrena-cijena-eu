# Sidrena Cijena — finish the plugin (agent handoff prompt)

You are the coding agent for **Sidrena Cijena**, a WordPress plugin that implements the
Croatian "sidrena cijena" (anchor price) obligation, the daily machine-readable price-list
obligation and the 30-day lowest-price rule for classified sales.

* Repository: `/home/q/Desktop/wordpressprojects/plugins/sidrena-cijena`
* Branch `master`, remote `origin https://github.com/administrakt0r/sidrena-cijena`,
  last pushed commit `a271c6a`
* Git identity is configured repo-locally; `gh` is authenticated as `administrakt0r`
* Read `AGENTARD/README.md` first — it defines the mandatory workflow for any live
  WordPress work in this repository

## Non-negotiable rules

1. **One item at a time.** Close it, update `docs/improvement-backlog.md`
   (status + what was done + test), commit with a focused message, push.
2. **Never record a guessed anchor.** FR-093 forbids inferring the anchor from the current
   catalog price or the creation date. That is why the competitor's "fill empty" helpers
   are `declined`. A visible gap beats fabricated legal evidence.
3. **A wrong anchor is worse than a missing one.** Never attach one product's anchor to
   another product's price (see FIX-11).
4. **Gates must be green before every commit** (commands below).
5. **Treat uncommitted or unfamiliar changes as the user's work** — investigate, do not
   delete or overwrite them.

## Environment (already installed — do not reinstall)

* Docker 26 + docker-compose, PHP 8.4 + Composer 2.8, Node 24 + npm,
  Playwright Chromium Headless Shell.
* User `q` is in the `docker` group, but running shells do not inherit it:
  wrap every docker/wp-env command in `sg docker -c '…'`.
* **Dev shop:** `./local.sh` → <http://localhost:8888> (`admin` / `password`), demo
  products, anchors, a classified sale and published price lists under
  `wp-content/uploads/sidrena-cijena/`. Stop with `./stoplocal.sh`, reset with
  `npx wp-env destroy`.
* **Test shop:** `npm run wp-env:test -- start` → <http://localhost:8889> (the E2E target;
  the Playwright suite must never touch 8888).
* **Gates:**

  ```bash
  composer test:unit                       # host, no Docker
  composer analyse                         # PHPStan
  composer lint                            # PHPCS (WPCS + PHPCompatibilityWP)
  npm run lint:js                          # ESLint
  sg docker -c 'npx wp-env run tests-cli composer -d /var/www/html/wp-content/plugins/sidrena-cijena test:integration'
  sg docker -c 'npm run test:e2e'          # needs the 8889 env running
  ```

* **Gotchas:**
  * If `dockerd` restarts, containers exit 255 — bring them back with
    `sg docker -c 'docker start <mysql first, then wordpress/cli>'` or
    `npm run wp-env:test -- start`.
  * `wp-env run … wp eval` flattens the command line and mangles PHP quotes: put PHP in a
    file and use `wp eval-file` (see `tools/local-seed.php`, `tools/e2e-setup.php`).
  * `wp-env start` runs the `afterStart` lifecycle script from `.wp-env.json`.
  * E2E runs with one worker on purpose; a full run is ~3 minutes.
* **Last known green baseline:** unit 119 tests / 380 assertions, integration 70 / 329,
  E2E 27 passed, PHPStan + PHPCS + ESLint clean, `./local.sh` EXIT 0.

## Where the work is tracked

* `docs/improvement-backlog.md` — FIX-01…FIX-12 (defects) all `done`;
  IMP-01…IMP-12 (competitor gaps) `done`/`covered`, IMP-08 `declined` with a reason.
* Competitor reference: **MJDD Sidrena Cijena 1.24.3**, re-download from
  `https://downloads.wordpress.org/plugin/mjdd-sidrena-cijena.1.24.3.zip` into
  `/tmp/opencode/mjdd/` if you need to compare again.
* `docs/compliance-audit-2026-09-24.md` may still hold open observations.

## Work queue (highest value first)

1. **Release readiness for the first public version.** Bump `0.1.0` → `1.0.0` in
   `sidrena-cijena.php` (header *and* `SIDRENA_CIJENA_VERSION`), `package.json` and
   `readme.txt` (`Stable tag`, changelog entry), regenerate the translation files with
   `tools/i18n/make-pot.php`, then build with `tools/build-zip.sh 1.0.0` and verify the
   zip installs and activates on a clean wp-env (fresh `npx wp-env destroy` + install).
2. **Filename profile against the ministry example.** Ours is
   `PRODAVAONICA_VLASKA68_ZAGREB_P1_01_25092026_0101.CSV`; the example published by the
   ministry is `prodavaonica_Ilica 150 Zagreb_P-01_104_01.10.2026_07:45.xml` (spaces kept,
   dotted date, hyphen in the identifier, a storage number). Decide whether to keep the
   documented contract (`git show HEAD:specs/001-sidrena-cijena-compliance/contracts/filename.md`
   — the local `specs/` copy was deleted by the user) or to add a selectable "ministry"
   filename profile, and document the decision. Do not silently change the format.
3. **WordPress.org submission checklist.** Validate `readme.txt`, add plugin assets
   (icon/banner/screenshots), confirm licence, external-services and privacy statements,
   check the "tested up to" value, and dry-run the review checklist.
4. **Unit coverage for the classes added this session** — `src/Api/PublicFeed.php`,
   `src/Api/PublicPage.php`, `src/WooCommerce/Display/BuilderCompat.php`,
   `src/WooCommerce/PriceHistoryRecorder.php` (integration/E2E cover them today, unit
   tests do not).
5. **Public page performance.** `/sidrena-cjenik/` rebuilds the whole list on every
   request (the competitor's users already complained about a slow price-list page).
   Measure first, then cache with invalidation on publication/settings changes.
6. **Confirm the daily schedule meets the 08:00 deadline** (`OperationsSettingsPage`
   deadline + `src/Cron/Scheduler.php`): missed runs, timezone `Europe/Zagreb`,
   catch-up behaviour, and what the merchant sees when a scheduled run fails.
7. **Decide the pending `.specify/` and `specs/` deletions.** They are deleted in the
   working tree but were deliberately left out of commit `a271c6a`. Ask the user, then
   either restore them or commit the removal — do not guess.
8. **CI.** Add a GitHub Actions workflow that runs unit + PHPStan + PHPCS + ESLint, and
   the integration suite with a MySQL service (E2E can stay local or use `wp-env`).

## Definition of done

* Every queue item has a backlog row with a status and a note.
* All gates green; `./local.sh` EXIT 0; E2E at least 27 passed.
* Docs updated (`README.md`, `docs/…`); no temporary files (`tools/tmp-*`,
  `tests/e2e/tmp-*`, debug echoes) left behind.
* Commits pushed to `origin master`; the final summary states what changed, what was
  deliberately not done and why.
* Reminder in the summary: this is a technical compliance aid, not legal advice; the
  filename and field layout should still be reviewed against NN 101/2016 and the 2026
  ministry guidance.
