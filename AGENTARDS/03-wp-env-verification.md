# Prompt 3 — Full wp-env verification (needs Docker)

```
You are verifying the plugin in the current directory inside a real WordPress environment.
Requires Docker, Node 18+, PHP + Composer. If the current shell cannot access the Docker socket,
wrap docker/wp-env commands in `sg docker -c '…'`.

CRITICAL WARNING: NEVER run `wp plugin install <zip> --force` inside wp-env. The host plugin
directory is directly bind-mounted into Docker containers; `--force` deletes the directory!

1. Local demo environment:
   Run `./local.sh` (starts wp-env on http://localhost:8888, activates WooCommerce + Sidrena Cijena,
   configures EUR and Europe/Zagreb timezone, seeds demo products, anchors, a sale, and seeds
   17 compliance scenarios). Stop with `./stoplocal.sh`.
2. Isolated testing environment:
   Start the automated test site on port 8889:
   `npm run wp-env:test -- start`
3. Integration testing:
   Clean the test database first to ensure clean state:
   `npx wp-env clean tests`
   Then run the integration suite:
   `composer test:integration`
   (or `npx wp-env run tests-cli -- wp-content/plugins/sidrena-cijena/vendor/bin/phpunit -c wp-content/plugins/sidrena-cijena/phpunit.xml.dist --testsuite=integration`)
   Must pass 100% (76+ tests, 342+ assertions).
4. End-to-End testing:
   Run Playwright tests exclusively against the port 8889 test instance:
   `npm run test:e2e`
   (Do NOT point Playwright at the 8888 demo shop).
5. 7-point live verification checklist:
   - 1. Install & Onboarding flow: Activation redirects to Setup Guide (wp-admin/admin.php?page=sidrena-cijena-setup).
        Welcome banner shows once with author project links and is dismissible. "Mark setup complete" dismisses it.
   - 2. Anchor price display: Open product page; reference date price and label render next to price
        (classic and block themes). Mapped legacy categories show 02.05.2025; general items show 10.09.2026.
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
6. Verify standalone operation without WooCommerce:
   Test deactivating WooCommerce (`npx wp-env run cli wp plugin deactivate woocommerce`).
   Plugin must continue operating without fatals using the standalone catalog. Re-activate WooCommerce after test.
7. If any fixes were made:
   - Run all static quality gates (`composer test:unit && composer lint && composer analyse && npm run lint:js`).
   - Package and release per AGENTARDS/README.md (bump version, build zip, verify with unzip -t, commit and push).

Done when: integration suite passes, E2E suite passes on port 8889, all 7 verification steps pass,
and any fixes are packaged, committed, and pushed.
```
