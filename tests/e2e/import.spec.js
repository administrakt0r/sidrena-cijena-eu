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
    await wpCli(['plugin', 'activate', 'sidrena-cijena']);
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

    await expect(page.locator('h1')).toContainText('Import');
    await expect(page.getByText('Before you import')).toBeVisible();

    await page.setInputFiles('input[name="sidrena_import_file"]', filePath);
    await page.getByRole('button', { name: 'Upload and preview' }).click();

    await expect(page.locator('.sidrena-cijena-notice')).toContainText('Preview ready');
    await expect(page.locator('table.sidrena-cijena-import-preview')).toBeVisible();
    await expect(page.locator('table.sidrena-cijena-import-preview')).toContainText('E2E-IMPORT-1');
    await expect(page.locator('table.sidrena-cijena-import-preview')).toContainText('No catalog item matches this SKU or product ID.');
  });

  test('dry run reports changes without writing them', async ({ page }) => {
    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-import');

    await page.setInputFiles('input[name="sidrena_import_file"]', filePath);
    await page.getByRole('button', { name: 'Upload and preview' }).click();

    await page.locator('input[name="dry_run"]').last().check();
    await page.getByRole('button', { name: 'Commit import' }).click();

    await expect(page.locator('.sidrena-cijena-notice')).toContainText('Dry run finished');
    await expect(page.locator('.sidrena-cijena-notice')).toContainText('nothing was written');
  });

  test('commit stores valid rows and reports invalid ones', async ({ page }) => {
    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-import');

    await page.setInputFiles('input[name="sidrena_import_file"]', filePath);
    await page.getByRole('button', { name: 'Upload and preview' }).click();
    await page.getByRole('button', { name: 'Commit import' }).click();

    await expect(page.locator('.sidrena-cijena-notice')).toContainText('Import finished');
    await expect(page.locator('.sidrena-cijena-notice')).toContainText('1 inserted');

    await expect(page.getByRole('link', { name: 'Download error report (CSV)' })).toBeVisible();
  });
});
