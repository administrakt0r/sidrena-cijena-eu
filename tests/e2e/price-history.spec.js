const { test, expect } = require('@playwright/test');
const { login, wpCli, wpEval } = require('./helpers');

test.describe('Price history and audit', () => {
  let productId = 0;

  test.beforeAll(async () => {
    await wpCli(['plugin', 'activate', 'sidrena-cijena']);

    const id = await wpEval(`
      $product = new WC_Product_Simple();
      $product->set_name( 'E2E povijest proizvod' );
      $product->set_sku( 'E2E-HIST-1' );
      $product->set_regular_price( '10.00' );
      $product->set_status( 'publish' );
      $product->save();
      echo $product->get_id();
    `);

    productId = parseInt(String(id).trim(), 10);

    await wpEval(`
      $catalog = SidrenaCijena\\Plugin::instance()->catalog();
      $item = $catalog->get_item( ${productId} );
      SidrenaCijena\\Plugin::instance()->anchors()->record( $item, 12.00, array( 'reference_date' => '2026-09-10' ) );
      return 'ok';
    `);
  });

  test.afterAll(async () => {
    await wpEval(`
      wp_delete_post( ${productId}, true );
      return 'ok';
    `);
  });

  test('history screen lists records with type and source', async ({ page }) => {
    await login(page);
    await page.goto(`wp-admin/admin.php?page=sidrena-cijena-history&product_id=${productId}`);

    await expect(page.locator('h1')).toContainText(/Price history|Povijest cijena/i);
    await expect(page.locator('table.widefat')).toContainText('E2E-HIST-1');
    await expect(page.locator('table.widefat')).toContainText(/Anchor price|Sidrena cijena/i);
    await expect(page.locator('table.widefat')).toContainText(/Manual entry|Ručni unos/i);
    await expect(page.locator('table.widefat')).toContainText('12,00');
  });

  test('correction preserves the original record', async ({ page }) => {
    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-history');

    await page.fill('#sidrena-history-product', String(productId));
    await page.selectOption('#sidrena-history-type', 'anchor');
    await page.fill('#sidrena-history-price', '11.50');
    await page.fill('#sidrena-history-reference', '2026-09-10');
    await page.fill('#sidrena-history-reason', 'E2E correction reason');
    await page.getByRole('button', { name: /Record correction|ispravak/i }).click();

    await expect(page.locator('.sidrena-cijena-notice.notice-success')).toBeVisible();
    await expect(page.locator('table.widefat')).toContainText(/correction of record|ispravak zapisa/i);
  });

  test('correction without a reason is rejected', async ({ page }) => {
    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-history');

    await page.fill('#sidrena-history-product', String(productId));
    await page.selectOption('#sidrena-history-type', 'anchor');
    await page.fill('#sidrena-history-price', '11.00');
    await page.fill('#sidrena-history-reference', '2026-09-10');

    // The field is `required` for the browser, so the server-side guard only
    // runs when that attribute is gone: a crafted request must be rejected too.
    await page.locator('#sidrena-history-reason').evaluate((el) => el.removeAttribute('required'));
    await page.getByRole('button', { name: /Record correction|ispravak/i }).click();

    await expect(page.locator('.sidrena-cijena-notice.notice-error')).toBeVisible();
  });

  test('audit screen shows the legal rule changes', async ({ page }) => {
    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-audit');

    await expect(page.locator('h1')).toContainText(/Audit trail|Revizijski trag/i);
    await expect(page.getByText(/Legal rule changes|Izmjene pravnih pravila/i)).toBeVisible();
    await expect(page.getByText(/Operational log|Operativni zapis/i)).toBeVisible();
  });
});
