import { test as base, expect, type Page } from '@playwright/test';
import { newAdminPage, setSiteLanguage } from './helpers/admin';
import {
	addProductToCart,
	fillBlockCheckoutByFieldId,
	INVALID_PL_PHONE,
	placeOrder,
} from './helpers/checkout';

// From languages/shift64-phone-validation-pl_PL.po: "%s is not a valid phone
// number." with "Billing phone" as the field label.
const POLISH_INVALID_BILLING_PHONE =
	'Telefon do płatności nie jest prawidłowym numerem telefonu.';

// The notice selector block-checkout-validation.js watches.
const ERROR_BANNER =
	'.wc-block-components-notice-banner.is-error, .wc-block-store-notice.is-error';

// What Checkout\Assets prints for block-checkout-validation.js.
type ScriptWindow = {
	shift64PhoneValidation?: { messages?: Record< string, string[] > };
};

/**
 * A logged-in admin in its own browser context (the shopper stays a guest),
 * which also puts the store back in English once the test is over.
 *
 * Every other spec expects an English store. The switch back runs in the
 * fixture teardown, not in a `finally` in the test body: when the body times
 * out Playwright stops waiting for it, while fixture teardown still runs, with
 * a time budget of its own, after a pass, a failure or a timeout. It reuses
 * this session because it logged in while the store was still English; a
 * fresh login would meet a Polish wp-login.php that loginAsAdmin cannot read.
 * Selecting English again is harmless when the test never switched. seed.sh
 * switches the store back as well, the last safety net if this fails too.
 */
const test = base.extend< { admin: Page } >( {
	admin: async ( { browser }, use ) => {
		const admin = await newAdminPage( browser );
		await use( admin );
		try {
			await setSiteLanguage( admin, '' );
		} finally {
			await admin.context().close();
		}
	},
} );

/**
 * Regression test for #32. On WordPress 6.7+ the block checkout error came out
 * in English on a Polish store, and the phone field was not highlighted
 * because the script matches the banner against the Polish wording.
 *
 * WooCommerce's own wording is not asserted: the WooCommerce language pack
 * wp-env gets may not match the WooCommerce version (see seed.sh).
 */
test( 'block checkout on a Polish store shows the Polish error and highlights the phone field', async ( {
	page,
	admin,
} ) => {
	// The cart lives in the shopper's session: fill it while the store is
	// still English, so the product URL and button labels are the known ones.
	await addProductToCart( page );

	await setSiteLanguage( admin, 'pl_PL' );

	await fillBlockCheckoutByFieldId( page, INVALID_PL_PHONE );

	// Precondition: the page handed the script the Polish wording.
	const scriptMessages = await page.evaluate( () => {
		const config = ( window as unknown as ScriptWindow )
			.shift64PhoneValidation;
		return config?.messages?.billing ?? [];
	} );
	expect( scriptMessages ).toContain( POLISH_INVALID_BILLING_PHONE );

	await placeOrder( page );

	await expect(
		page
			.locator( ERROR_BANNER )
			.filter( { hasText: POLISH_INVALID_BILLING_PHONE } )
			.first()
	).toBeVisible();

	// With "Use same address for billing" the shipping phone input is the
	// billing phone, and the script marks it for the billing error.
	const phone = page.locator( '#shipping-phone' );
	await expect( phone ).toHaveAttribute(
		'data-shift64-phone-error',
		'billing'
	);
	await expect( phone ).toHaveAttribute( 'aria-invalid', 'true' );
	await expect(
		page
			.locator( '.wc-block-components-text-input' )
			.filter( { has: phone } )
	).toHaveClass( /(^|\s)has-error(\s|$)/ );

	await expect( page ).not.toHaveURL( /order-received/ );
} );
