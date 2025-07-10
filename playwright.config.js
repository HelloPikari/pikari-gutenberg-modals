/**
 * Playwright configuration for end-to-end tests
 *
 * @see https://playwright.dev/docs/test-configuration
 */

const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',

	// Maximum time one test can run
	timeout: 30 * 1000,

	// Run tests in parallel
	fullyParallel: true,

	// Fail the build on CI if you accidentally left test.only
	forbidOnly: !! process.env.CI,

	// Retry on CI only
	retries: process.env.CI ? 2 : 0,

	// Reporter to use
	reporter: 'html',

	// Shared settings for all tests
	use: {
		// Base URL for the site
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',

		// Collect trace when retrying the failed test
		trace: 'on-first-retry',

		// Screenshot only on failure
		screenshot: 'only-on-failure',

		// Video only on failure
		video: 'retain-on-failure',
	},

	// Configure projects for different browsers
	projects: [
		{
			name: 'chromium',
			use: { browserName: 'chromium' },
		},
		{
			name: 'firefox',
			use: { browserName: 'firefox' },
		},
		{
			name: 'webkit',
			use: { browserName: 'webkit' },
		},
	],

	// Run local development server before starting tests
	webServer: process.env.CI
		? undefined
		: {
				command: 'npm run start',
				port: 3000,
				reuseExistingServer: true,
		  },
} );
