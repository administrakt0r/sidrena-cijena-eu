// @ts-check
const { test, expect } = require('@playwright/test');
const { wpCli, wpEval, login } = require('./helpers');

const ITEM_SKU = 'E2E-DASH-1';

test.describe('Compliance dashboard', () => {
	test.beforeAll(async () => {
		wpCli(['plugin', 'activate', 'sidrena-cijena-eu', '--quiet']);

		wpEval(`
			$existing = get_posts( array( 'post_type' => 'product', 'meta_key' => '_sku', 'meta_value' => '${ITEM_SKU}', 'numberposts' => 1 ) );
			if ( empty( $existing ) ) {
				$product = new WC_Product_Simple();
				$product->set_name( 'E2E dashboard proizvod' );
				$product->set_sku( '${ITEM_SKU}' );
				$product->set_regular_price( '15.00' );
				$product->save();
			}
			$locations = Titanworks\\SidrenaCijena\\Plugin::instance()->locations()->all();
			$found = false;
			foreach ( $locations as $location ) {
				if ( 'E2E-P1' === $location['identifier'] ) { $found = true; }
			}
			if ( ! $found ) {
				Titanworks\\SidrenaCijena\\Plugin::instance()->locations()->insert( array(
					'name' => 'E2E prodavaonica',
					'type' => 'prodavaonica',
					'address' => 'Vlaška 68',
					'city' => 'Zagreb',
					'identifier' => 'E2E-P1',
					'active' => 1,
				) );
			}
		`);
	});

	test.afterAll(async () => {
		wpEval(`
			$posts = get_posts( array( 'post_type' => 'product', 'meta_key' => '_sku', 'meta_value' => '${ITEM_SKU}', 'numberposts' => -1 ) );
			foreach ( $posts as $post ) {
				wp_delete_post( $post->ID, true );
			}
		`);
	});

	test('dashboard shows anchor and validation cards', async ({ page }) => {
		await login(page);
		await page.goto('/wp-admin/admin.php?page=sidrena-cijena');

		await expect(page.locator('h1')).toContainText('Sidrena cijena');
		await expect(page.locator('.sidrena-cijena-card').first()).toBeVisible();
		await expect(page.getByText(/Anchors missing|Sidrene cijene nedostaju/i)).toBeVisible();
		await expect(page.getByText(/Validation errors|Greške provjere/i)).toBeVisible();
	});

	test('run compliance check produces a report with findings', async ({ page }) => {
		await login(page);
		await page.goto('/wp-admin/admin.php?page=sidrena-cijena-compliance');

		await expect(page.locator('h1')).toContainText(/Compliance check|Provjera usklađenosti/i);

		await page.locator('input[type="submit"], button[type="submit"]').first().click();
		await expect(page.locator('.sidrena-cijena-notice')).toBeVisible();

		await expect(page.getByText('missing_anchor_price').first()).toBeVisible();
		await expect(page.locator('a[href*="twbce_cijena_export_report"]').first()).toBeVisible();
	});

	test('compliance report exports as CSV', async ({ page }) => {
		await login(page);
		await page.goto('/wp-admin/admin.php?page=sidrena-cijena-compliance');

		const href = await page.locator('a[href*="twbce_cijena_export_report"]').first().getAttribute('href');
		expect(href).toContain('twbce_cijena_export_report');

		const response = await page.request.get(href);

		expect(response.status()).toBe(200);
		expect(response.headers()['content-type']).toContain('text/csv');

		const body = await response.text();

		expect(body).toContain('severity;code;entity_type;entity_id;message;rule_reference');
		expect(body).toContain('missing_anchor_price');
	});

	test('rest status route requires management capability', async ({ request }) => {
		const response = await request.get('/wp-json/sidrena-cijena/v1/status');

		expect([401, 403]).toContain(response.status());
	});
});
