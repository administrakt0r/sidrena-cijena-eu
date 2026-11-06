# Publishing TitanWorks Sidrena Bazna Cijena on WordPress.org

The WordPress.org account at `profiles.wordpress.org/titanworks/` has username
`@titanworks` and display name `administrakt0r`. These can differ. The
`readme.txt` `Contributors:` field must use the case-sensitive account username
(`titanworks`); the plugin header can keep `Author: administrakt0r` as its
visible author name. Use the `titanworks` account to submit and maintain the
plugin.

## Before submission

1. The selected display name is **TitanWorks Sidrena Bazna Cijena**. The exact-name search
   returned no current plugin listing. The release uses the matching
   `titanworks-sidrena-bazna-cijena` text domain and package folder. WordPress.org derives
   the slug from the plugin name; confirm the assigned slug in the submission
   flow because availability is decided by the directory review team.
2. Check the current [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/),
   [common review issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/),
   and [readme format](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/).
   Keep the GPL-compatible license, readable source, clear data handling,
   sanitization, escaping, capability checks, and nonce checks. The plugin
   description must not promise legal certification.
3. Run the repository's unit, lint, PHPStan, JS lint, translation, integration,
   and browser checks. Review the generated ZIP as a clean install with and
   without WooCommerce. The ZIP must contain the plugin folder and runtime
   files only; keep `vendor`, `node_modules`, tests and internal documentation
   out of the distribution.
4. Verify the version in the plugin header, runtime constant, `readme.txt`
   stable tag and changelog, and `package.json`. Run `unzip -t` on the archive.
   Check that `Contributors: titanworks` links to the correct profile.
5. Prepare directory artwork and screenshots for the later SVN `/assets/`
   directory. These are distinct from this plugin's runtime `assets/` folder.

## Submission and release

1. Sign in as `titanworks` at [Submit a plugin](https://wordpress.org/plugins/developers/add/)
   with an email address you monitor. Upload the complete installable ZIP and
   provide a concise description. Watch for mail from `plugins@wordpress.org`.
2. Respond to review findings and upload a revised ZIP if asked. Approval
   grants a WordPress.org SVN repository; GitHub alone does not publish
   updates to the directory.
3. Put the approved runtime files in SVN `trunk/`, directory artwork in
   `assets/`, and tag the released version under `tags/<version>/`. Keep the
   `Stable tag:` and plugin header version aligned with the release. Review
   the listing and install from the directory after publication.
4. For later releases, update `trunk/`, add a new immutable SVN tag, and
   verify the directory package and upgrade path. Monitor support and security
   reports through the WordPress.org account.

Official workflow: [Planning, Submitting, and Maintaining Plugins](https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/)
and [Using Subversion](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/).
