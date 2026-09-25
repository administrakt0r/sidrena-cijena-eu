/**
 * Playwright end-to-end test configuration.
 *
 * The tests create and delete data, so they run against the wp-env test
 * environment (`npm run wp-env:test -- start`, port 8889) and talk to it
 * through the `tests-cli` service. Point `WP_BASE_URL` at another site only
 * when you accept that the specs will modify it. Credentials are
 * development-only and must never be used in production.
 */
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
	testDir: './tests/e2e',
	globalSetup: require.resolve('./tests/e2e/global-setup.js'),
	timeout: 60 * 1000,
	expect: { timeout: 10 * 1000 },
	fullyParallel: false,
	// One worker: every spec shares one WordPress database, and two specs
	// writing products, locations or settings at once made the suite flaky
	// (one worker's plugin activation ran dbDelta while another logged in).
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
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
