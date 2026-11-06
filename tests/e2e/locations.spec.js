const { test, expect } = require('@playwright/test');
const { login, wpCli, wpEval } = require('./helpers');

const IDENTIFIER = `E2E-${Date.now().toString().slice(-5)}`;

test.describe('Sales locations', () => {
  test.beforeAll(async () => {
    await wpCli(['plugin', 'activate', 'titanworks-sidrena-bazna-cijena']);
  });

  test.afterAll(async () => {
    await wpEval(`
      global $wpdb;
      $table = $wpdb->prefix . 'sidrena_locations';
      $wpdb->delete( $table, array( 'identifier' => '${IDENTIFIER}' ) );
      return 'ok';
    `);
  });

  test('a location can be added, listed and deleted', async ({ page }) => {
    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-locations');

    await expect(page.locator('h1')).toContainText(/Sales locations|Prodajna mjesta/i);

    await page.fill('#sidrena-location-name', 'E2E prodavaonica');
    await page.fill('#sidrena-location-address', 'Vlaška 68');
    await page.fill('#sidrena-location-city', 'Zagreb');
    await page.fill('#sidrena-location-identifier', IDENTIFIER);
    await page.locator('#sidrena-location-submit, input[type="submit"]').first().click();

    await expect(page.locator('.sidrena-cijena-notice.notice-success')).toBeVisible();
    await expect(page.locator('table.widefat')).toContainText(IDENTIFIER);

    await page.getByRole('link', { name: /Edit|Uredi/i }).last().click();
    await expect(page.locator('#sidrena-location-name')).toHaveValue('E2E prodavaonica');

    await page.goto('wp-admin/admin.php?page=sidrena-cijena-locations');
    page.on('dialog', (dialog) => dialog.accept());
    await page.getByRole('link', { name: /Delete|Obriši/i }).last().click();

    await expect(page.locator('.sidrena-cijena-notice.notice-success')).toBeVisible();
    await expect(page.locator('table.widefat')).not.toContainText(IDENTIFIER);
  });

  test('a duplicate identifier is rejected', async ({ page }) => {
    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-locations');

    await page.fill('#sidrena-location-name', 'Duplikat');
    await page.fill('#sidrena-location-identifier', IDENTIFIER);
    await page.locator('#sidrena-location-submit, input[type="submit"]').first().click();

    await expect(page.locator('.sidrena-cijena-notice.notice-success')).toBeVisible();

    await page.goto('wp-admin/admin.php?page=sidrena-cijena-locations');
    await page.fill('#sidrena-location-name', 'Duplikat 2');
    await page.fill('#sidrena-location-identifier', IDENTIFIER);
    await page.locator('#sidrena-location-submit, input[type="submit"]').first().click();

    await expect(page.locator('.sidrena-cijena-notice.notice-error')).toBeVisible();
  });
});
