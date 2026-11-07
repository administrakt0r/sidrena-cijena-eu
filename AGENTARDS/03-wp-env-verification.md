# Prompt 3 — Full wp-env verification (needs Docker)

> **Status (v2.0.0):** COMPLETED. Integration suite, E2E suite, and 7-point checklist fully verified.

```
You are verifying the plugin in the current directory inside a real WordPress environment.
Requires Docker, Node 18+, PHP + Composer. If the current shell cannot access the Docker socket,
wrap docker/wp-env commands in `sg docker -c '…'`.

CRITICAL WARNING: NEVER run `wp plugin install <zip> --force` inside wp-env. The host plugin
directory is directly bind-mounted into Docker containers; `--force` deletes the directory!

MANDATORY BRANCH & PULL REQUEST WORKFLOW:
If any fixes are made during verification:
1. Create a dedicated branch: `git checkout -b fix/wp-env-integration-fixes`
2. Commit each fix atomically (fix:/test:).
3. Push branch and open a Pull Request for user review:
   `git push -u origin fix/wp-env-integration-fixes`
   `gh pr create --base master --title "fix: integration and live wp-env verification fixes" --body "..."`
   Do NOT push directly to master or merge the PR yourself.

1. Local demo environment:
   Run `./local.sh` (starts wp-env on http://localhost:8888, activates WooCommerce + Sidrena Cijena,
   configures EUR and Europe/Zagreb timezone, seeds demo products, anchors, a sale, and seeds
   17 compliance scenarios). Stop with `./stoplocal.sh`.
   Demo products seeded at localhost:8888: IDs ~10 (Kava mljevena), 11 (Maslinovo ulje),
   12 (Deterdžent), 13 (Majica pamučna), 14 (Kava u zrnu), 22 (Seed variable).
2. Isolated testing environment & CircleCI:
   Heavy integration and Playwright E2E suites can be run locally on port 8889 or offloaded
   to CircleCI (`integration_and_e2e` job) to spare the host PC CPU and RAM (see AGENTS.md).
   When running locally:
   Start the automated test site on port 8889:
   `npx wp-env start`
3. Integration testing:
   Clean the test database first to ensure clean state:
   `npx wp-env clean tests`
   Then run the integration suite:
   `composer test:integration`
   (or `npx wp-env run tests-cli -- wp-content/plugins/sidrena-cijena/vendor/bin/phpunit -c wp-content/plugins/sidrena-cijena/phpunit.xml.dist --testsuite=integration`)
   Must pass 100% (96+ tests, 454+ assertions).
4. End-to-End testing:
   Run Playwright tests exclusively against the port 8889 test instance (42 specs passing):
   `npm run test:e2e`
   (Do NOT point Playwright at the 8888 demo shop). CircleCI runs this automatically in headless Chromium.
5. 7-point live verification checklist:
   - 1. Install & Onboarding flow: Activation redirects to Setup Guide (wp-admin/admin.php?page=sidrena-cijena-setup).
        Welcome banner shows once with author project links and is dismissible. "Mark setup complete" dismisses it.
   - 2. Anchor price display: Open product page; reference date price and label render next to price
        (classic and block themes). Mapped legacy categories show 02.05.2025; general items show 10.09.2026.
        Verify that dynamic date tokens ({datum}) resolve in the label to the category-appropriate date.
        Verify that the tooltip shows the correct statutory text (NOT the 30-day rule text).
   - 3. 30-day lowest price: Item with classified promotion shows lowest price line; promotional start date
        is excluded; lowest_30_day rows are never used as self-input.
   - 4. Daily price list: Generate from admin or WP-CLI (`wp sidrena-cijena generate --force`);
        filename matches pattern, CSV/XML valid, public feeds work at `/sidrena-cijena.xml`,
        `/sidrena-cijena.csv`, `/sidrena-cjenik/`, and `/arhiva-sidrene-cijene/`.
   - 5. Catalog coverage: Products, variations, and standalone services (`_sidrena_item_type=service`)
        render correctly in price lists and queries (>200 items paginated).
   - 6. New product auto-anchor: Publishing a new product captures first offer; existing/imported items
        are NOT backfilled; variations record individually.
   - 7. Compliance gate: Missing anchor blocks publication with visible ERROR in Compliance check;
        resolving the finding allows generation.
6. Block theme (Product Collection block) verification:
   Verify PriceDisplay::filter_price_block passes 3 args and reads $block_instance->context to show
   archive prices in WooCommerce Product Collection blocks.
7. Label preset display verification:
   In Display Settings, verify preset pills, dynamic date preview, and tooltip styling.
8. Verify standalone operation without WooCommerce:
   Deactivate WooCommerce (`npx wp-env run cli wp plugin deactivate woocommerce`).
   Plugin must continue operating without fatals using the standalone catalog. Re-activate WooCommerce after test.
9. If any fixes were made:
   - Run fast unit tests: `composer test:unit`.
   - Push branch and create PR via `gh pr create --base master`.
   - Hand off PR link to user for review.

Done when: integration suite passes (96 tests / 454 assertions), E2E suite passes (42 Playwright specs on port 8889),
all 7+ verification steps pass, and any fixes are pushed on a branch with an open Pull Request.
```
