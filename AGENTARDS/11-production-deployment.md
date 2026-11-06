# Prompt 11 — Production deployment to wordpress.sidrenacijena.eu (needs SSH / WP-CLI remote access)

```
You are deploying the Titanworks Bazna Cijena plugin from the local development environment to the
production WordPress site at https://wordpress.sidrenacijena.eu/.

BEFORE YOU START — read the entire prompt, then read AGENTARDS/README.md.

## Environment context

- Local plugin repo:    /home/q/Desktop/wordpress/plugins/sidrena-cijena (branch: master)
- Public release repo:  https://github.com/administrakt0r/titanworks-bazna-cijena (tagged releases)
- Production site:      https://wordpress.sidrenacijena.eu/
- Production admin:     https://wordpress.sidrenacijena.eu/wp-admin/
- Local demo site:      http://localhost:8888 (admin / password, started via ./local.sh)
- Enforcement deadline: 17 November 2026 (NN 110/2026) — daily price lists MUST be published

## Pre-deployment checklist (run on local first)

1. Confirm local unit suite is green:
   ```bash
   cd /home/q/Desktop/wordpress/plugins/sidrena-cijena
   composer test:unit
   # Must report 535/1916 (or more) — 0 failures
   ```
2. Confirm translations are complete:
   ```bash
   php tools/i18n/make-pot.php --locale=hr_HR
   # Must report: 809 translated, 0 untranslated
   ```
3. Confirm readme.txt Stable tag matches titanworks-bazna-cijena.php Version:
   ```bash
   grep "Stable tag" readme.txt
   grep "Version:" titanworks-bazna-cijena.php | head -1
   # Both must match. If not: bump Stable tag, add changelog entry, rebuild and push.
   ```
4. Confirm the release zip exists and is valid:
   ```bash
   ls versions/$(grep '"version"' package.json | grep -o '[0-9.]*')/titanworks-bazna-cijena.zip
   unzip -t versions/*/titanworks-bazna-cijena.zip | tail -3
   ```
5. Confirm the latest version is tagged and released in titanworks-bazna-cijena public repo.

## Production deployment steps

### Method A: Upload via WordPress Admin (recommended — no SSH required)

1. Download the release zip from the titanworks-bazna-cijena public repo GitHub Releases page,
   OR use the local zip at `versions/<version>/titanworks-bazna-cijena.zip`.
2. Log into https://wordpress.sidrenacijena.eu/wp-admin/.
3. Go to Plugins → Add New → Upload Plugin.
4. Upload the zip and click "Replace current with uploaded".
5. Activate the plugin if it asks.
6. Verify the version shown in Plugins list matches the uploaded version.

### Method B: WP-CLI via SSH (fastest)

```bash
# Replace <host> and <path> with actual production server details
ssh <host> "wp plugin install https://github.com/administrakt0r/titanworks-bazna-cijena/releases/download/v<version>/titanworks-bazna-cijena.zip --activate --allow-root --path=<wp-path>"
```

NEVER use `wp plugin install --force` on a directory-mounted environment.

## Post-deployment verification checklist

After uploading and activating the new version:

1. **Plugin version check:**
   https://wordpress.sidrenacijena.eu/wp-admin/ → Plugins → confirm version shown.

2. **Price list table headers (Croatian):**
   Visit https://wordpress.sidrenacijena.eu/sidrena-cjenik/
   Verify table headers are in Croatian (Naziv, Šifra, Marka, etc.), NOT English.
   If still English: purge LiteSpeed Cache (admin → LiteSpeed Cache → Purge All).

3. **Archive page content:**
   Visit https://wordpress.sidrenacijena.eu/arhiva-sidrene-cijene/
   If the archive is empty, run generation via WP-CLI or admin:
   - Admin: Sidrena Cijena → Price Lists → "Generate now"
   - WP-CLI (SSH): `wp sidrena-cijena generate --force`

4. **Static feeds:**
   - https://wordpress.sidrenacijena.eu/sidrena-cijena.csv — must return 200 with CSV content
   - https://wordpress.sidrenacijena.eu/sidrena-cijena.xml — must return 200 with XML content
   - If 404: generation has not run yet. Run generate --force (item 3 above).

5. **REST API:**
   https://wordpress.sidrenacijena.eu/wp-json/sidrena-cijena/v1/prices
   Must return JSON with product data, no authentication required.

6. **Tooltip and storefront display:**
   Open any product page (e.g. https://wordpress.sidrenacijena.eu/?p=... or the shop).
   - Anchor/sidrena price label must appear below the price.
   - The ? tooltip button must be a circular icon (not raw text).
   - Hovering the ? must show the correct tooltip:
     "Sidrena (bazna) cijena je redovna maloprodajna cijena na propisani referentni datum
     prema mjerama izravne kontrole cijena."
   - The reference date shown must be appropriate for the product category:
     - Food/cleaning products: 2. 5. 2025.
     - General products: 10. 9. 2026.

7. **LiteSpeed Cache purge (mandatory after any plugin upgrade):**
   Admin → LiteSpeed Cache → Purge All (or WP-CLI: `wp lscache-purge all`)
   Then re-verify items 2 and 6.

8. **Daily cron check:**
   Admin → Sidrena Cijena → Dashboard → confirm last generation timestamp is today.
   If not, trigger manual generation.

## Known production issues (as of v2.0.20)

- English table headers on /sidrena-cjenik/ → fixed in v2.0.18 by I18N-02/I18N-03.
  After upgrade to v2.0.18+, purge LiteSpeed Cache to get Croatian headers.
- Empty /arhiva-sidrene-cijene/ → generation has never run or never succeeded.
  Fix: wp sidrena-cijena generate --force (SSH) or admin Generate now button.
- Static feeds 404 → depends on above. Fix: generate, then verify feeds.
- Tooltip showing wrong text (30-day rule instead of anchor definition) → fixed in v2.0.19.
  After upgrade, tooltip_text option is auto-migrated on first Settings::all() call.

## If anything breaks

1. Deactivate the new plugin version in WP Admin → Plugins.
2. Re-activate the previous version zip (keep the old zip in versions/).
3. Report the failure with the exact admin/CLI error message, PHP error log entries,
   and production WordPress/WooCommerce version.

Done when: production site shows the correct version, Croatian price list table headers,
non-empty archive (or generation has been triggered), static feeds return 200,
tooltip shows the correct statutory text, and LiteSpeed Cache has been purged.
```
