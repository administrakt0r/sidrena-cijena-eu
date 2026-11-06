/**
 * E2E: anchor price display on product pages, archives and shortcodes.
 */
const { test, expect } = require( '@playwright/test' );
const { wpCli, wpEval, login } = require( './helpers' );

const ANCHOR_PRICE = '25.00';
const CURRENT_PRICE = '20.00';

test.describe( 'Anchor price display', () => {
	let productId;
	let upsellId;

	test.beforeAll( () => {
		wpCli( [ 'plugin', 'activate', 'titanworks-bazna-cijena' ] );

		const created = wpEval(
			'$p = new WC_Product_Simple();' +
				"$p->set_name('E2E sidrena proizvod');" +
				"$p->set_regular_price('" +
				CURRENT_PRICE +
				"');" +
				"$p->set_sku('E2E-1');" +
				'$id = $p->save(); echo $id;'
		);

		productId = parseInt( created.trim(), 10 );

		wpEval(
			"$item = \\SidrenaCijena\\Plugin::instance()->catalog()->get_item(" +
				productId +
				');' +
				"\\SidrenaCijena\\Plugin::instance()->anchors()->record($item, '" +
				ANCHOR_PRICE +
				"', array('source' => 'manual'));"
		);

		const upsell = wpEval(
			"$p = new WC_Product_Simple(); $p->set_name('E2E sidrena povezani proizvod'); $p->set_regular_price('30.00'); $p->set_sku('E2E-RELATED-1'); echo $p->save();"
		);
		upsellId = parseInt( upsell.trim(), 10 );

		wpEval(
			"$plugin = \\SidrenaCijena\\Plugin::instance(); $item = $plugin->catalog()->get_item(" +
				upsellId +
				"); $plugin->anchors()->record($item, '35.00', array('source' => 'manual')); update_post_meta(" +
				upsellId +
				", '_sidrena_cijena_show_in_cards', '1'); $parent = wc_get_product(" +
				productId +
				"); $parent->set_upsell_ids(array(" +
				upsellId +
				")); $parent->save();"
		);

		wpEval(
			"update_option('sidrena_cijena_settings', array_merge(SidrenaCijena\\Support\\Settings::defaults(), array('display_enabled' => true, 'display_archives' => true, 'display_location' => 'price_html')));"
		);
	} );

	test.afterAll( () => {
		if ( upsellId ) {
			wpEval( 'wp_delete_post(' + upsellId + ', true);' );
		}

		if ( productId ) {
			wpEval( 'wp_delete_post(' + productId + ', true);' );
		}
	} );

	test( 'related upsell cards show the related product reference price', async ( { page } ) => {
		await page.goto( '/?p=' + productId );

		const card = page.locator( '.up-sells .product' ).filter( { hasText: 'E2E sidrena povezani proizvod' } );
		const reference = card.locator( '.sidrena-cijena[data-product-id="' + upsellId + '"]' );

		await expect( reference ).toBeVisible();
		await expect( reference.locator( '.sidrena-cijena__anchor-value' ) ).toContainText( '35,00' );
		await expect( card.locator( '.sidrena-cijena[data-product-id="' + productId + '"]' ) ).toHaveCount( 0 );
	} );

	test( 'shows the anchor price on the single product page', async ( { page } ) => {
		await page.goto( '/?p=' + productId );

		// The page may also show related products, so the anchor has to be the
		// one that belongs to this product: a block context bug once attached
		// one product's anchor to another product's price.
		const block = page.locator( '.sidrena-cijena[data-product-id="' + productId + '"]' );

		await expect( block.locator( '.sidrena-cijena__anchor-value' ) ).toContainText( '25,00' );
		await expect( block.locator( '.sidrena-cijena__label' ) ).toContainText( 'Sidrena cijena' );
		await expect( page.locator( '.sidrena-cijena[data-product-id="' + productId + '"]' ) ).toHaveCount( 1 );
	} );

	test( 'hides the anchor price in product cards by default', async ( { page } ) => {
		await page.goto( '/?post_type=product' );

		await expect( page.locator( '.sidrena-cijena[data-product-id="' + productId + '"]' ) ).toHaveCount( 0 );
	} );

	test( 'product editor can show its anchor on cards without changing the product page', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/post.php?post=' + productId + '&action=edit' );
		await page.locator( '#woocommerce-product-data .sidrena_cijena_options a' ).click();
		const choice = page.locator( '#sidrena-cijena-show-in-cards' );
		await expect( choice ).not.toBeChecked();
		await choice.check();
		await page.locator( '#publish' ).click();
		await expect.poll( () => wpEval( "echo get_post_meta(" + productId + ", '_sidrena_cijena_show_in_cards', true);" ).trim() ).toBe( '1' );

		await page.goto( '/?post_type=product' );
		await expect( page.locator( '.sidrena-cijena[data-product-id="' + productId + '"]' ) ).toBeVisible();
		await page.goto( '/?p=' + productId );
		await expect( page.locator( '.sidrena-cijena[data-product-id="' + productId + '"]' ) ).toBeVisible();

		await page.goto( '/wp-admin/post.php?post=' + productId + '&action=edit' );
		await page.locator( '#woocommerce-product-data .sidrena_cijena_options a' ).click();
		await choice.uncheck();
		await page.locator( '#publish' ).click();
		await expect.poll( () => wpEval( "echo get_post_meta(" + productId + ", '_sidrena_cijena_show_in_cards', true);" ).trim() ).toBe( '0' );
	} );

	test( 'hides the anchor price when display is disabled', async ( { page } ) => {
		wpEval(
			"$s = SidrenaCijena\\Support\\Settings::all(); $s['display_enabled'] = false; update_option('sidrena_cijena_settings', $s);"
		);

		await page.goto( '/?p=' + productId );

		await expect( page.locator( '.sidrena-cijena' ) ).toHaveCount( 0 );

		wpEval(
			"$s = SidrenaCijena\\Support\\Settings::all(); $s['display_enabled'] = true; update_option('sidrena_cijena_settings', $s);"
		);
	} );

	test( 'renders through the shortcode', async ( { page } ) => {
		const pageId = parseInt(
			wpCli( [
				'post',
				'create',
				'--post_type=page',
				'--post_status=publish',
				'--post_title=E2E sidrena shortcode',
				'--post_content=[sidrena_cijena product_id=' + productId + ']',
				'--porcelain',
			] ).trim(),
			10
		);

		await page.goto( '/?page_id=' + pageId );

		await expect( page.locator( '.sidrena-cijena__anchor-value' ) ).toContainText( '25,00' );
		await expect( page.locator( '.sidrena-cijena__current-value' ) ).toContainText( '20,00' );

		wpEval( 'wp_delete_post(' + pageId + ', true);' );
	} );
} );
