/**
 * Playwright end-to-end test configuration.
 *
 * The tests run against the wp-env development site. Credentials are
 * development-only and must never be used in production.
 */
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
	testDir: './tests/e2e',
	timeout: 60 * 1000,
	expect: { timeout: 10 * 1000 },
	fullyParallel: false,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		locale: 'hr-HR',
		timezoneId: 'Europe/Zagreb',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
});
