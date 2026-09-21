const { test, expect } = require('@playwright/test');
const { wpCli, wpEval, login } = require('./helpers');

test.describe('30-day lowest price display', () => {
  let productId = 0;

  test.beforeAll(async () => {
    wpCli(['plugin', 'activate', 'sidrena-cijena']);

    const id = wpEval(`
      $product = new WC_Product_Simple();
      $product->set_name('E2E 30-day proizvod');
      $product->set_regular_price('20.00');
      $product->set_sku('E2E-30D-1');
      $product->set_status('publish');
      $product->save();

      $plugin = \\SidrenaCijena\\Plugin::instance();
      $plugin->anchors()->record($plugin->catalog()->get_item($product->get_id()), 25.00, array('source' => 'manual'));

      $today = \\SidrenaCijena\\Support\\Dates::today();
      $plugin->history()->insert(array(
        'product_id' => $product->get_id(),
        'item_type' => 'product',
        'sku' => 'E2E-30D-1',
        'price_type' => 'historical',
        'price' => 9.00,
        'valid_from' => gmdate('Y-m-d', strtotime($today . ' -40 days')),
        'source' => 'manual',
      ));

      $plugin->special_sale_service()->create($plugin->catalog()->get_item($product->get_id()), array(
        'sale_type' => 'akcija',
        'sale_name' => 'E2E akcija',
        'start_date' => $today,
        'end_date' => gmdate('Y-m-d', strtotime($today . ' +5 days')),
        'promo_price' => 7.00,
      ));

      update_option('sidrena_cijena_settings', array_merge(\\SidrenaCijena\\Support\\Settings::all(), array(
        'display_enabled' => true,
        'display_location' => 'price_html',
      )), false);

      echo $product->get_id();
    `);

    productId = parseInt(id.trim(), 10);
  });

  test.afterAll(async () => {
    if (productId) {
      wpEval(`
        $product = wc_get_product(${productId});
        if ($product) { $product->delete(true); }
        echo 'ok';
      `);
    }
  });

  test('the 30-day price is displayed separately from the anchor price', async ({ page }) => {
    await page.goto(`/?p=${productId}`);

    const anchor = page.locator('.sidrena-cijena__anchor-value').first();
    await expect(anchor).toContainText('25,00');

    const block = page.locator('.sidrena-cijena--30-day').first();
    await expect(block).toBeVisible();
    await expect(block.locator('.sidrena-cijena__30-day-value')).toContainText('9,00');
    await expect(block.locator('.sidrena-cijena__label')).toContainText('Lowest price in the last 30 days');

    await expect(anchor).toContainText('25,00');
  });

  test('the shortcode renders both blocks distinctly', async ({ page }) => {
    const pageId = wpCli([
      'post',
      'create',
      '--post_type=page',
      '--post_title=E2E 30-day shortcode',
      `--post_content=[sidrena_cijena product_id=${productId} show_30_day=yes show_current=yes]`,
      '--post_status=publish',
      '--porcelain',
    ]).trim();

    await page.goto(`/?page_id=${pageId}`);

    await expect(page.locator('.sidrena-cijena__anchor-value').first()).toContainText('25,00');
    await expect(page.locator('.sidrena-cijena--30-day .sidrena-cijena__30-day-value').first()).toContainText('9,00');

    wpCli(['post', 'delete', pageId, '--force']);
  });
});
