<?php
/**
 * Baseline for the Playwright test environment.
 *
 * Run with `wp eval-file` from `tests/e2e/global-setup.js`, the same way
 * `tools/local-seed.php` feeds the demo shop. It lives in a file because the
 * code is PHP, not because the specs need any data of their own: each spec
 * creates what it asserts on.
 *
 * @package SidrenaCijena
 */

defined( 'ABSPATH' ) || exit;

$plugin = \SidrenaCijena\Plugin::instance();

/*
 * A price-list filename is built from the facility street and city (FR-040),
 * and generation refuses to publish without them. The default webshop location
 * is created empty, so on a fresh test site every generation would fail for a
 * reason that has nothing to do with the spec.
 */
foreach ( $plugin->locations()->all() as $location ) {
	if ( '' !== $location['address'] && '' !== $location['city'] ) {
		continue;
	}

	$updated = $plugin->locations()->update(
		(int) $location['id'],
		array(
			'address' => 'Testna 1',
			'city'    => 'Zagreb',
		)
	);

	if ( $updated ) {
		printf(
			"Location #%d %s (%s): address set to %s, %s\n",
			$location['id'],
			$location['name'],
			$location['identifier'],
			$location['address'],
			$location['city']
		);
	}
}

echo "E2E baseline ready.\n";
