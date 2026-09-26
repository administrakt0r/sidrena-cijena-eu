const { test, expect } = require('@playwright/test');
const { login, wpCli, wpEval } = require('./helpers');

let productId = 0;
const sku = `SIDRENA-EDIT-${Date.now()}`;

test.describe('WooCommerce product editor integration', () => {
	test.beforeAll(async () => {
		await wpCli(['plugin', 'activate', 'sidrena-cijena', 'woocommerce']);
		const output = await wpEval(`
			$product = new WC_Product_Simple();
			$product->set_name( 'Sidrena editor regression' );
			$product->set_sku( '${sku}' );
			$product->set_regular_price( '9.99' );
			$product->save();
			echo 'SIDRENA_PRODUCT_ID=' . $product->get_id();
		`);
		const match = output.match(/SIDRENA_PRODUCT_ID=(\d+)/);
		if (!match) {
			throw new Error(`Could not create product fixture: ${output}`);
		}
		productId = Number(match[1]);
	});

	test.afterAll(async () => {
		if (productId) {
			await wpEval(`wp_delete_post( ${productId}, true );`);
		}
	});

	test('native Sidrena tab is visible and normal product Update still saves', async ({ page }) => {
		await login(page);
		await page.goto(`wp-admin/post.php?post=${productId}&action=edit`);

		await expect(page.locator('#woocommerce-product-data')).toBeVisible();
		await expect(page.locator('#sidrena_cijena_product_data')).toBeAttached();
		await page.locator('#woocommerce-product-data .sidrena_cijena_options a').click();
		await expect(page.locator('#sidrena_cijena_product_data')).toBeVisible();
		await expect(page.locator('#sidrena_cijena_product_data h2')).toContainText(/Anchor price and special-sale evidence|Podaci o sidrenoj cijeni i posebnoj prodaji/);
		await expect(page.locator('#sidrena_cijena_product_data form')).toHaveCount(0);

		await page.fill('#title', 'Sidrena editor save confirmed');
		await page.locator('#publish').click();
		await expect(page).toHaveURL(/post\.php\?post=\d+&action=edit/);
		await expect.poll(async () => (await wpEval(`echo get_the_title( ${productId} );`)).includes('Sidrena editor save confirmed')).toBe(true);
	});

	test('dedicated anchor evidence action submits independently', async ({ page }) => {
		await login(page);
		await page.goto(`wp-admin/post.php?post=${productId}&action=edit`);
		await page.locator('#woocommerce-product-data .sidrena_cijena_options a').click();
		await page.locator('#sidrena-anchor-price').fill('1.23');
		await page.locator('button[name="action"][value="sidrena_cijena_record_anchor"]').click();
		await expect(page).toHaveURL(/sidrena_cijena_notice=success/);
		await expect(page.locator('.notice-success')).toBeVisible();
	});

	test('plugin has a dedicated anchor menu entry', async ({ page }) => {
		await login(page);
		await page.goto('wp-admin/index.php');
		const menu = page.locator('#toplevel_page_sidrena-cijena > a');
		await expect(menu).toBeVisible();
		await expect(menu).toContainText('Sidrena cijena');
		await expect(menu.locator('.dashicons-anchor')).toBeAttached();
	});
});
