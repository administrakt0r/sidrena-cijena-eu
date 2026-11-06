const { test, expect } = require('@playwright/test');
const { login, wpCli, wpEval } = require('./helpers');

test.describe('Operations and administration', () => {
  test.beforeAll(async () => {
    await wpCli(['plugin', 'activate', 'titanworks-sidrena-bazna-cijena']);
  });

  test('operations settings can be changed and persist', async ({ page }) => {
    await login(page);
    await page.goto(
      'wp-admin/admin.php?page=sidrena-cijena-settings&tab=operations'
    );

    await expect(page.locator('h1')).toContainText(/settings|postavke/i);

    await page.fill('input[name="sidrena_cijena_settings[deadline]"]', '07:30');
    await page.fill(
      'input[name="sidrena_cijena_settings[retention_days]"]',
      '45'
    );
    await page.locator('#submit, input[type="submit"]').first().click();

    await expect(page.locator('#setting-error-settings_updated')).toBeVisible();

    await page.reload();
    await expect(
      page.locator('input[name="sidrena_cijena_settings[deadline]"]')
    ).toHaveValue('07:30');
    await expect(
      page.locator('input[name="sidrena_cijena_settings[retention_days]"]')
    ).toHaveValue('45');

    await wpEval(`
      update_option( 'sidrena_cijena_settings', array_merge( get_option( 'sidrena_cijena_settings', array() ), array( 'deadline' => '08:00', 'retention_days' => 30 ) ) );
      return 'ok';
    `);
  });

  test('retention below 30 days is raised back to 30', async ({ page }) => {
    await login(page);
    await page.goto(
      'wp-admin/admin.php?page=sidrena-cijena-settings&tab=operations'
    );

    await page.fill(
      'input[name="sidrena_cijena_settings[retention_days]"]',
      '7'
    );
    await page.locator('#submit, input[type="submit"]').first().click();
    await page.reload();

    await expect(
      page.locator('input[name="sidrena_cijena_settings[retention_days]"]')
    ).toHaveValue('30');
  });

  test('log entries are visible and filterable', async ({ page }) => {
    await wpEval(`
      ( new \\SidrenaCijena\\Database\\LogRepository() )->insert( 'info', 'import', 'E2E log entry', array(), 0 );
      return 'ok';
    `);

    await login(page);
    await page.goto('wp-admin/admin.php?page=sidrena-cijena-logs');

    await expect(page.locator('h1')).toContainText(/Logs|Zapisi/i);
    await expect(page.locator('table.widefat')).toContainText('E2E log entry');

    await page.selectOption('select[name="event"]', 'import');
    await page.locator('#post-query-submit, input[type="submit"]').first().click();

    await expect(page.locator('table.widefat')).toContainText('E2E log entry');
  });

  test('users without the capability are denied', async ({ page }) => {
    const user = `e2e_sub_${Date.now().toString().slice(-6)}`;

    await wpCli(['user', 'create', user, `${user}@example.com`, '--role=subscriber', '--user_pass=password']);

    try {
      await login(page, user, 'password');
      await page.goto('wp-admin/admin.php?page=sidrena-cijena-settings');

      await expect(page.locator('body')).toContainText(/not allowed|dopuštenje/i);
    } finally {
      await wpCli(['user', 'delete', user, '--yes']);
    }
  });
});
