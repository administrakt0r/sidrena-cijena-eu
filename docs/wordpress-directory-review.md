# WordPress.org directory review, 7 November 2026

Submission slug: `sidrena-cijena-eu`. Review ID: `R sidrena-cijena-eu/titanworks/28Sep26/T4 7Nov26/4.2 (P0TDX377039HGN)`.

Version 2.0.24 addresses the reported URL, translation-domain, escaping, naming and SQL findings. The optional Plugin URI and private repository download link are removed. Translation calls, the plugin header, bundled catalogs, autoloading, tests and package tooling use the directory slug or the new identifiers as appropriate.

Plugin PHP classes now live under `Titanworks\SidrenaCijena`. Plugin functions, constants, options, hooks, shortcodes, post types and private metadata use the `twbce_` / `TWBCE_` prefix. Existing installations migrate configuration, standalone catalog identifiers, metadata and capability grants (including direct user denials). Cron events are rescheduled. The physical `sidrena_*` database tables retain their names to preserve the existing statutory ledger; the migration never rewrites price-history records. The CSV field `sidrena_cijena` is part of the published interchange format and remains unchanged.

Custom integrations must update old PHP namespaces, functions, hooks and shortcode tags to their new names. In particular, existing `[sidrena_cijena]`, `[sidrena_cjenik]` and `[sidrena_arhiva]` content must use `[twbce_cijena]`, `[twbce_cjenik]` and `[twbce_arhiva]`. The migration deliberately does not rewrite merchant-authored page content. Development-build option backups remain under their old keys; new writes use only the new keys.

HTML is escaped at the output boundary, including form validation attributes. The public catalog uses an explicit allowlist that retains its GET search form. CSV/XML attachments use a byte-preserving response stream and their format-specific writers, with non-HTML content types, attachment disposition and `nosniff`; HTML escaping would corrupt those downloads. Compliance JSON uses `wp_send_json()`.

The reported history queries now have literal SQL predicates with bound filter values and `%i` column identifiers. Sort direction selects one of two literal queries. Tests cover injection-like filter/sort inputs, catalog upgrade retention, unchanged history records, escaping, and exact attachment bytes.

Local checks: PHP syntax for modified PHP files, the unit suite, and Croatian catalog extraction. Heavy PHPCS, PHPStan, integration tests, Playwright and submission-package Plugin Check run in CircleCI. The WordPress test environment has `WP_DEBUG` enabled. Do not claim clean-install checks passed until the corresponding CI results are available.

After human review and merge, build the submission ZIP using `tools/build-zip.sh 2.0.24 --no-upload`. It contains the `sidrena-cijena-eu/` directory and the existing bootstrap filename. Upload it through the WordPress.org submission page only after the final CI checks pass.

Suggested reply after uploading and verifying the corrected package:

> Hello, we have addressed the review findings and uploaded the corrected version. We tested it with WP_DEBUG enabled in standalone mode and with WooCommerce. The existing statutory history table names and published CSV field name are retained for data compatibility. CSV/XML attachments are streamed using their format-specific serialization rather than HTML escaping. Thank you for continuing the review.
