/**
 * ESLint configuration for the plugin's front-end scripts.
 *
 * `@wordpress/scripts` uses this file when the project provides one. The
 * bundled configuration pulls in `@typescript-eslint`, which fails to load
 * under Node 24, so the project supplies a small browser configuration
 * instead.
 */
module.exports = {
	env: {
		browser: true,
		es2022: true,
	},
	extends: [ 'eslint:recommended' ],
	parserOptions: {
		ecmaVersion: 2022,
		sourceType: 'script',
	},
};
