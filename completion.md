# Completion plan — Sidrena cijena

Current version: **1.0.4** (header, constant, `package.json`, readme stable tag agree).
Unit suite green (175/525). Everything below is what remains before the plugin
is fully finished.

## 1. Commit the open working tree

Uncommitted work: `WelcomeBanner`, `SetupGuidePage`, `PublicPage`/`RestCache`
changes, `admin.css`, `wp-stubs.php`, new unit tests, `readme.txt` author.

```sh
git status --short
git diff --stat
git add -A
git commit -m "feat: welcome banner, setup guide, author administrakt0r"
git push
```

## 2. Regenerate translations

The banner and setup guide added new `__()` strings; the `.pot`/`.po`/`.mo`
catalogs must be rebuilt so Croatian ships complete and counts stay at zero
untranslated.

```sh
ls tools/i18n
# rebuild POT + PO + sidrena-cijena-hr.mo per tools/i18n README, then verify:
# 0 untranslated strings, runtime loads sidrena-cijena-hr.mo under locale hr
```

## 3. Full gate suite green

Unit alone is not enough — the release contract requires all gates:

```sh
composer test:unit
composer lint          # PHPCS clean
composer analyse       # PHPStan, no errors
npm run lint:js
composer test:integration   # needs wp-env running (Docker; use sg docker if needed)
npm run wp-env:test -- start
npm run test:e2e            # port 8889 test env only, never the 8888 demo shop
./local.sh                  # must exit 0
```

## 4. Close the tracked backlog (`docs/improvement-backlog.md`)

| ID | Work | Notes |
|----|------|-------|
| REL-06 | Daily 08:00 deadline robustness | timezone, missed runs, catch-up, failure visibility — `done` |
| REL-08 | CI | static gates + integration with MySQL — `done` |
| REL-03 | WordPress.org submission | readme validation, `plugin-check`, actual submission — `in progress`, deliberately last |

## 5. Archive the release

Every shipped version keeps an installable ZIP + checksum in `versions/`
(convention from REL-09; `1.0.2` is the latest archived).

```sh
tools/build-zip.sh 1.0.3
unzip -t versions/1.0.3/*.zip
# fresh-install smoke: install + activate ZIP on pristine WP (with and
# without WooCommerce), check schema, hr translation, setup-guide redirect
```

## 6. Update the release record

Append the 1.0.3 section to `docs/release-readiness.md` (gate results,
ZIP source commit) and mark the backlog rows `done` with dates.

## 7. Manual acceptance (setup flow)

On a clean install verify: activation lands on the Setup Guide, the welcome
banner shows until dismissed, all five steps resolve (locations → legal
slugs → anchors → price list → compliance), and *Mark setup complete*
dismisses the banner. Covers the merchant obligations in
`docs/feature-acceptance.md` (legacy slug mapping, sale classification,
location addresses, cron).
