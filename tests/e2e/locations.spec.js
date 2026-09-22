const { test, expect } = require('@playwright/test');
const { login, wpCli, wpEval } = require('./helpers');

const IDENTIFIER = `E2E-${Date.now().toString().slice(-5)}`;

test.describe('Sales locations', () => {
  test.beforeAll(async () => {
    await wpCli(['plugin', 'activate', 'sidrena-cijena']);
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

    await expect(page.locator('h1')).toContainText('Sales locations');

    await page.fill('#sidrena-location-name', 'E2E prodavaonica');
    await page.fill('#sidrena-location-address', 'Vlaška 68');
    await page.fill('#sidrena-location-city', 'Zagreb');
    await page.fill('#sidrena-location-identifier', IDENTIFIER);
    await page.getByRole('button', { name: 'Add location' }).click();

    await expect(page.locator('.notice-success')).toContainText('Location saved');
    await expect(page.locator('table.widefat')).toContainText(IDENTIFIER);

    await page.getByRole('link', { name: 'Edit', exact: true }).last().click();
    await expect(page.locator('#sidrena-location-name')).toHaveValue('E2E prodavaonica');

    await page.goto('wp-admin/admin.php?page=sidrena-cijena-locations');
    await page.getByRole('link', { name: 'Delete', exact: true }).last().click();

    await expect(page.locator('.notice-success')).toContainText('Location deleted');
    await expect(page.locator('table.widefat')).not.toContainText(IDENTIFIER);
  });

  test('a duplicate identifier is rejected', async ({ page }) => {
    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-locations');

    await page.fill('#sidrena-location-name', 'Duplikat');
    await page.fill('#sidrena-location-identifier', IDENTIFIER);
    await page.getByRole('button', { name: 'Add location' }).click();

    await expect(page.locator('.notice-success')).toContainText('Location saved');

    await page.goto('wp-admin/admin.php?page=sidrena-cijena-locations');
    await page.fill('#sidrena-location-name', 'Duplikat 2');
    await page.fill('#sidrena-location-identifier', IDENTIFIER);
    await page.getByRole('button', { name: 'Add location' }).click();

    await expect(page.locator('.notice-error')).toContainText('already uses this facility identifier');
  });
});
