const { test, expect } = require('@playwright/test');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { login, wpCli, wpEval } = require('./helpers');

const IMPORT_CSV = [
  'sku;reference_date;anchor_price;category',
  'E2E-IMPORT-1;2026-09-10;7,49;',
  'NEPOZNAT-E2E;2026-09-10;3,00;',
  '',
].join('\n');

test.describe('Anchor import', () => {
  let filePath;

  test.beforeAll(async () => {
    await wpCli(['plugin', 'activate', 'sidrena-cijena-eu']);
    await wpEval(`
      if ( ! function_exists( 'wc_get_product' ) || ! wc_get_product_id_by_sku( 'E2E-IMPORT-1' ) ) {
        $product = new WC_Product_Simple();
        $product->set_name( 'E2E uvoz proizvod' );
        $product->set_sku( 'E2E-IMPORT-1' );
        $product->set_regular_price( '9.99' );
        $product->set_status( 'publish' );
        $product->save();
      }
      return 'ok';
    `);

    filePath = path.join(os.tmpdir(), `sidrena-import-${Date.now()}.csv`);
    fs.writeFileSync(filePath, IMPORT_CSV, 'utf8');
  });

  test.afterAll(async () => {
    await wpEval(`
      $id = wc_get_product_id_by_sku( 'E2E-IMPORT-1' );
      if ( $id ) { wp_delete_post( $id, true ); }
      return 'ok';
    `);

    if (filePath && fs.existsSync(filePath)) {
      fs.unlinkSync(filePath);
    }
  });

  test('preview validates the file before anything is committed', async ({ page }) => {
    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-import');

    await expect(page.locator('h1')).toContainText(/Import|Uvoz/i);
    await expect(page.getByText(/Before you import|Prije uvoza/i)).toBeVisible();

    await page.setInputFiles('input[name="twbce_import_file"]', filePath);
    await page.locator('input[type="submit"], button[type="submit"]').first().click();

    await expect(page.locator('.sidrena-cijena-notice')).toBeVisible();
    await expect(page.locator('table.sidrena-cijena-import-preview')).toBeVisible();
    await expect(page.locator('table.sidrena-cijena-import-preview')).toContainText('E2E-IMPORT-1');
    await expect(page.locator('table.sidrena-cijena-import-preview')).toContainText(/matches this SKU|odgovara ovom/i);
  });

  test('dry run reports changes without writing them', async ({ page }) => {
    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-import');

    await page.setInputFiles('input[name="twbce_import_file"]', filePath);
    await page.locator('input[type="submit"], button[type="submit"]').first().click();

    await page.locator('input[name="dry_run"]').last().check();
    await page.locator('form input[type="submit"], form button[type="submit"]').last().click();

    await expect(page.locator('.sidrena-cijena-notice')).toBeVisible();
  });

  test('commit stores valid rows and reports invalid ones', async ({ page }) => {
    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-import');

    await page.setInputFiles('input[name="twbce_import_file"]', filePath);
    await page.locator('input[type="submit"], button[type="submit"]').first().click();
    await page.locator('form input[type="submit"], form button[type="submit"]').last().click();

    await expect(page.locator('.sidrena-cijena-notice')).toBeVisible();

    await expect(page.locator('a[href*="twbce_cijena_import_report"]').first()).toBeVisible();
  });
});
