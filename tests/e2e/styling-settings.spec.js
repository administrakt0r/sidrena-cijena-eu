const { test, expect } = require('@playwright/test');
const { login, wpCli, wpEval } = require('./helpers');

test('legal settings save and styling previews work in wp-admin', async ({ page }) => {
	await wpCli(['plugin', 'activate', 'sidrena-cijena']);
	await login(page);

	await page.goto('wp-admin/admin.php?page=sidrena-cijena-settings&tab=legal');
	await page.fill('input[name="sidrena_cijena_rule_config[rule_version]"]', '2026-10-01-preview');
	await page.locator('#submit').click();
	await expect(page.locator('#setting-error-settings_updated')).toBeVisible();
	await expect(page.locator('input[name="sidrena_cijena_rule_config[rule_version]"]')).toHaveValue('2026-10-01-preview');

	await page.goto('wp-admin/admin.php?page=sidrena-cijena-settings&tab=styling');
	await expect(page.locator('.sidrena-style-choice')).toHaveCount(9);
	await page.locator('input[value="pill"][type="radio"]').check();
	await page.locator('#sidrena-display-size').fill('80');
	await expect(page.locator('#sidrena-display-size-value')).toHaveText('80%');
	await page.locator('#submit').click();
	await expect(page.locator('#setting-error-settings_updated')).toBeVisible();
	await expect(page.locator('input[value="pill"][type="radio"]')).toBeChecked();
	await expect(page.locator('#sidrena-display-size')).toHaveValue('80');

	const icon = page.locator('#toplevel_page_sidrena-cijena .wp-menu-image');
	await expect(icon).toHaveAttribute('style', /data:image\/svg\+xml;base64,/);

	await wpEval(`
		$s = \\SidrenaCijena\\Support\\Settings::all();
		$s['product_display_style'] = 'plain';
		$s['display_size'] = 40;
		update_option( \\SidrenaCijena\\Support\\Settings::OPTION, $s );
		$r = \\SidrenaCijena\\Compliance\\RuleConfig::all();
		$r['rule_version'] = '2026-10-01';
		update_option( \\SidrenaCijena\\Compliance\\RuleConfig::OPTION, $r );
	`);
});
