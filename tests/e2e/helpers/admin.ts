import { expect, type Browser, type Page } from '@playwright/test';

export const SETTINGS_PATH =
	'/wp-admin/admin.php?page=wc-settings&tab=phone_validation';

export type OutputFormatLabel =
	| 'E.164 (e.g., +48123456789)'
	| 'International (e.g., +48 123 456 789)'
	| 'National (e.g., 123 456 789)';

/**
 * Admin credentials come from the environment, never from the test files:
 * `.ai/qa/test-env.env` (written by the test-env entrypoint and loaded by
 * playwright.config.ts) or the CI job's environment.
 */
function adminCredentials(): { user: string; password: string } {
	const user = process.env.TEST_ADMIN_USER ?? 'admin';
	const password = process.env.TEST_ADMIN_PASSWORD;
	if ( ! password ) {
		throw new Error(
			'TEST_ADMIN_PASSWORD is not set. Run `sh .ai/scripts/test-env-up.sh` (writes .ai/qa/test-env.env) or export it.'
		);
	}
	return { user, password };
}

export async function loginAsAdmin( page: Page ): Promise< void > {
	const { user, password } = adminCredentials();
	await page.goto( '/wp-login.php' );
	const userField = page.getByLabel( 'Username or Email Address' );
	const passwordField = page.getByLabel( 'Password', { exact: true } );
	// wp-login.php focuses and selects the username field 200 ms after the
	// page loads (wp_attempt_focus). When that lands in the middle of the
	// password fill, the password replaces the username and the form never
	// submits. The timer fires only once, so fill again until both stick.
	await expect( async () => {
		await userField.fill( user );
		await passwordField.fill( password );
		// Compared as booleans: in the race above the username field holds the
		// password, and a failure message must never print it.
		const filled =
			( await userField.inputValue() ) === user &&
			( await passwordField.inputValue() ) === password;
		expect( filled, 'both login fields hold what was typed' ).toBe( true );
	} ).toPass( { timeout: 10_000 } );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await expect( page ).toHaveURL( /wp-admin/ );
}

/** A separate browser context, so the shopper's session stays a guest. */
export async function newAdminPage( browser: Browser ): Promise< Page > {
	const context = await browser.newContext();
	const page = await context.newPage();
	await loginAsAdmin( page );
	return page;
}

export async function openPhoneValidationSettings( page: Page ): Promise< void > {
	await page.goto( SETTINGS_PATH );
	await expect(
		page.getByRole( 'heading', { name: 'Phone Validation Settings' } )
	).toBeVisible();
}

export async function saveSettings( page: Page ): Promise< void > {
	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	await expect(
		page.getByText( 'Your settings have been saved.' )
	).toBeVisible();
}

export async function setOutputFormat(
	page: Page,
	label: OutputFormatLabel
): Promise< void > {
	await openPhoneValidationSettings( page );
	// WooCommerce renders the select as a selectWoo combobox; the native
	// <select> stays in the DOM, so pick the option through it.
	const row = page.getByRole( 'row', { name: /Output Format/ } );
	await row.locator( 'select' ).selectOption( { label }, { force: true } );
	await saveSettings( page );
}

/**
 * Site Language values as WordPress stores them: English (United States) is
 * the empty string. Other languages must already be installed, see
 * tests/e2e/bin/seed.sh.
 */
export type SiteLanguage = '' | 'pl_PL';

/**
 * Settings > General > Site Language. Selectors use element ids and the
 * untranslated "English (United States)" label, because the admin itself
 * changes language with this setting.
 */
export async function setSiteLanguage(
	page: Page,
	language: SiteLanguage
): Promise< void > {
	await page.goto( '/wp-admin/options-general.php' );
	await page
		.locator( '#WPLANG' )
		.selectOption(
			language === '' ? { label: 'English (United States)' } : language
		);
	await page.locator( '#submit' ).click();
	await expect(
		page.locator( '#setting-error-settings_updated.notice-success' )
	).toBeVisible();
	await expect( page.locator( '#WPLANG' ) ).toHaveValue( language );
}

export async function setValidationEnabled(
	page: Page,
	enabled: boolean
): Promise< void > {
	await openPhoneValidationSettings( page );
	await page
		.getByRole( 'checkbox', {
			name: 'Enable phone number validation globally',
		} )
		.setChecked( enabled );
	await saveSettings( page );
}
