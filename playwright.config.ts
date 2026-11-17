import { defineConfig, devices } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';

// The e2e suite runs against the wp-env instance from .wp-env.json by default
// (port 8888, overridable with WP_ENV_PORT, the variable wp-env itself honors).
// Set WP_BASE_URL to attach to any other running WordPress instance.
const baseURL =
	process.env.WP_BASE_URL ??
	`http://localhost:${ process.env.WP_ENV_PORT ?? '8888' }`;

// The demo network (.wp-env.demo.json, npm run demo:start) on its own port.
// Its project exists only with WP_DEMO=1 (npm run test:e2e:demo), so a plain
// `playwright test` runs the single-site suite alone, as before.
const demoBaseURL =
	process.env.WP_DEMO_BASE_URL ??
	`http://localhost:${ process.env.WP_DEMO_PORT ?? '8890' }`;

// Admin credentials for the specs that log in (admin-*,
// block-checkout-polish-messages, block-checkout-saved-customer-address-highlight
// and classic-checkout-classic-theme-highlight):
// the test-env entrypoint writes them to this gitignored file; CI exports
// TEST_ADMIN_PASSWORD directly.
const credentialsFile = '.ai/qa/test-env.env';
if ( existsSync( credentialsFile ) ) {
	for ( const line of readFileSync( credentialsFile, 'utf8' ).split( '\n' ) ) {
		const match = /^([A-Z0-9_]+)=(.*)$/.exec( line.trim() );
		if ( match && process.env[ match[ 1 ] ] === undefined ) {
			process.env[ match[ 1 ] ] = match[ 2 ];
		}
	}
}

export default defineConfig( {
	testDir: './tests/e2e',
	testMatch: /.*\.spec\.ts$/,
	outputDir: 'test-results',
	timeout: 60_000,
	expect: { timeout: 10_000 },
	// One shared store: the admin-* specs change global plugin settings,
	// block-checkout-polish-messages.spec.ts the site language and
	// classic-checkout-classic-theme-highlight.spec.ts the active theme, so
	// files must not overlap in time.
	fullyParallel: false,
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI
		? [ [ 'github' ], [ 'html', { open: 'never' } ] ]
		: [ [ 'list' ], [ 'html', { open: 'never' } ] ],
	use: {
		baseURL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		...devices[ 'Desktop Chrome' ],
	},
	projects: [
		{
			name: 'default',
			testIgnore: /tests[\\/]e2e[\\/]demo[\\/]/,
		},
		...( process.env.WP_DEMO === '1'
			? [
					{
						name: 'demo',
						testDir: './tests/e2e/demo',
						use: { baseURL: demoBaseURL },
					},
			  ]
			: [] ),
	],
} );
