// @ts-check
const { devices } = require( '@playwright/test' );

/**
 * See https://playwright.dev/docs/test-configuration.
 */
const config = {
	testDir: './tests/e2e/specs/',
	/* Folder for test artifacts such as screenshots, videos, traces, etc. */
	outputDir: './tests/e2e/test-results/',

	/* Run your local dev server before starting the tests */
	webServer: {
		// command: 'npm run test:e2e-setup',
		port: 8881,
		reuseExistingServer: ! process.env.CI,
	},

	/* Maximum time one test can run for. */
	timeout: 30 * 1000,
	expect: {
		/**
		 * Maximum time expect() should wait for the condition to be met.
		 * For example in `await expect(locator).toHaveText();`
		 */
		timeout: 5000,
	},
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,
	/* Retry on CI only */
	retries: process.env.CI ? 2 : 0,
	/* Opt out of parallel tests on CI. */
	workers: process.env.CI ? 1 : undefined,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: 'list',

	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	globalSetup: require.resolve( './tests/e2e/global-setup' ),
	use: {
		/* Maximum time each action such as `click()` can take. Defaults to 0 (no limit). */
		actionTimeout: 0,
		/* Base URL to use in actions like `await page.goto('/')`. */
		baseURL: 'http://localhost:8881/',

		/* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
		trace: 'on-first-retry',

		// Tell all tests to load signed-in state from 'storageState.json'.
		storageState: './tests/e2e/storageState.json',
	},

	/* Configure projects for major browsers */
	projects: [
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
			},
		},

		{
			name: 'firefox',
			use: {
				...devices[ 'Desktop Firefox' ],
			},
		},

		{
			name: 'webkit',
			use: {
				...devices[ 'Desktop Safari' ],
			},
		},
	],
};

module.exports = config;
