const { test, expect } = require('@playwright/test');
const { login, wpCli, wpEval } = require('./helpers');

let productId = 0;
let variationProductId = 0;
let variationIds = [];
const sku = `SIDRENA-EDIT-${Date.now()}`;
const variationSku = `SIDRENA-VARIATION-${Date.now()}`;

test.describe('WooCommerce product editor integration', () => {
	test.beforeAll(async () => {
		await wpCli(['plugin', 'activate', 'titanworks-bazna-cijena', 'woocommerce']);
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

		const variationOutput = await wpEval(`
			$parent = new WC_Product_Variable();
			$parent->set_name( 'Sidrena variation pairing regression' );
			$parent->set_status( 'publish' );
			$parent->save();
			$ids = array();
		foreach ( array( 'SMALL' => '12.00', 'LARGE' => '20.00' ) as $size => $price ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_status( 'publish' );
			$variation->set_sku( '${variationSku}-' . $size );
			$variation->set_regular_price( $price );
			$variation->save();
			$ids[] = $variation->get_id();
		}
		echo 'SIDRENA_VARIABLE_ID=' . $parent->get_id() . ' SIDRENA_VARIATION_IDS=' . implode( ',', $ids );
		`);
		const parentMatch = variationOutput.match(/SIDRENA_VARIABLE_ID=(\d+)/);
		const variationsMatch = variationOutput.match(/SIDRENA_VARIATION_IDS=([\d,]+)/);
		if (!parentMatch || !variationsMatch) {
			throw new Error(`Could not create variable product fixture: ${variationOutput}`);
		}
		variationProductId = Number(parentMatch[1]);
		variationIds = variationsMatch[1].split(',').map(Number);
	});

	test.afterAll(async () => {
		if (productId) {
			await wpEval(`wp_delete_post( ${productId}, true );`);
		}
		if (variationProductId) {
			await wpEval(`
				global $wpdb;
				$parent = wc_get_product( ${variationProductId} );
				if ( $parent ) {
					foreach ( $parent->get_children() as $variation_id ) {
						$variation = wc_get_product( $variation_id );
						if ( $variation ) {
							$variation->delete( true );
						}
					}
				}
				$wpdb->delete( $wpdb->prefix . 'sidrena_price_history', array( 'product_id' => ${variationProductId} ), array( '%d' ) );
				$wpdb->delete( $wpdb->prefix . 'sidrena_special_sales', array( 'product_id' => ${variationProductId} ), array( '%d' ) );
				wp_delete_post( ${variationProductId}, true );
			`);
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

	test('variation evidence fields and anchor history stay on the selected variation', async ({ page }) => {
		const selectedVariationId = variationIds[0];
		await login(page);
		await page.goto(`wp-admin/post.php?post=${variationProductId}&action=edit`);
		await page.locator('#woocommerce-product-data .sidrena_cijena_options a').click();

		const variationSections = page.locator('#sidrena_cijena_product_data .sidrena-cijena-variation-evidence');
		await expect(variationSections).toHaveCount(2);
		await expect(page.locator(`input[name="anchor_price[${selectedVariationId}]"]`)).toBeVisible();
		await expect(page.locator(`select[name="sale_type[${selectedVariationId}]"]`)).toBeVisible();
		await expect(page.locator('input[name="anchor_price"]')).toHaveCount(0);

		await page.locator(`input[name="anchor_price[${selectedVariationId}]"]`).fill('1.23');
		const selectedSection = variationSections.nth(0);
		await expect(selectedSection.locator('h3')).toContainText(`#${selectedVariationId}`);
		await selectedSection.locator('button[name="action"]').first().click();
		await expect(page).toHaveURL(/sidrena_cijena_notice=success/);

		const history = await wpEval(`
			$repository = new \\SidrenaCijena\\Database\\PriceHistoryRepository();
			$variation = $repository->latest( ${variationProductId}, ${selectedVariationId}, 'anchor' );
			$parent = $repository->latest( ${variationProductId}, 0, 'anchor' );
			echo 'VARIATION_ANCHOR=' . ( $variation ? $variation['price'] : 'none' );
			echo ' PARENT_ANCHOR=' . ( $parent ? $parent['price'] : 'none' );
		`);
		await expect(history).toContain('VARIATION_ANCHOR=1.230000');
		await expect(history).toContain('PARENT_ANCHOR=none');
	});

	test('special-sale entry is stored for the selected variation', async ({ page }) => {
		const selectedVariationId = variationIds[1];
		await login(page);
		await page.goto(`wp-admin/post.php?post=${variationProductId}&action=edit`);
		await page.locator('#woocommerce-product-data .sidrena_cijena_options a').click();

		const variationSection = page.locator('#sidrena_cijena_product_data .sidrena-cijena-variation-evidence').nth(1);
		await expect(variationSection.locator('h3')).toContainText(`#${selectedVariationId}`);
		const saleType = variationSection.locator(`select[name="sale_type[${selectedVariationId}]"]`);
		const saleTypeValue = await saleType.locator('option').evaluateAll((options) => options.map((option) => option.value).find((value) => value));
		if (!saleTypeValue) {
			throw new Error('No configured sale type is available for the variation form.');
		}
		await saleType.selectOption(saleTypeValue);
		await variationSection.locator(`input[name="sale_name[${selectedVariationId}]"]`).fill('Variation sale fixture');
		await variationSection.locator(`input[name="promo_price[${selectedVariationId}]"]`).fill('8.88');
		await variationSection.locator('button[name="action"]').last().click();
		await expect(page).toHaveURL(/sidrena_cijena_notice=success/);

		const saleIdentity = await wpEval(`
			$repository = new \\SidrenaCijena\\Database\\SpecialSaleRepository();
			$variation = $repository->for_item( ${variationProductId}, ${selectedVariationId} );
			$parent = $repository->for_item( ${variationProductId}, 0 );
			echo 'VARIATION_SALES=' . count( $variation );
			echo ' VARIATION_ID=' . ( isset( $variation[0] ) ? $variation[0]['variation_id'] : 'none' );
			echo ' PARENT_SALES=' . count( $parent );
		`);
		await expect(saleIdentity).toContain('VARIATION_SALES=1');
		await expect(saleIdentity).toContain(`VARIATION_ID=${selectedVariationId}`);
		await expect(saleIdentity).toContain('PARENT_SALES=0');
	});

	test('plugin has a dedicated anchor menu entry', async ({ page }) => {
		await login(page);
		await page.goto('wp-admin/index.php');
		const menu = page.locator('#toplevel_page_sidrena-cijena > a');
		await expect(menu).toBeVisible();
		await expect(menu).toContainText('Sidrena cijena');
		await expect(menu.locator('.wp-menu-image')).toHaveAttribute('style', /data:image\/svg\+xml;base64,/);
	});
});
