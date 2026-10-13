import { test as base, expect, type Page } from '@playwright/test';
import { activateTheme, getActiveTheme, newAdminPage } from './helpers/admin';
import {
	addProductToCart,
	customer,
	expectOrderReceived,
	expectPhoneRejected,
	fillClassicCheckout,
	INVALID_PL_PHONE,
	placeOrder,
	VALID_PL_PHONE,
	VALID_PL_PHONE_E164,
} from './helpers/checkout';

// Coverage for #39: on a classic theme WooCommerce renders classic-checkout
// notices with its classic templates, one `ul.woocommerce-error li[data-id]`
// per error, the first markup checkout-validation.js handles. wp-env's default
// theme is a block theme, so this file switches to Twenty Twenty-One (installed
// by seed.sh) for each test. Each test asserts the classic markup, and the
// absence of the block banner, before the highlight.
const CLASSIC_THEME = 'twentytwentyone';
const ERROR_LIST = 'ul.woocommerce-error';
const BLOCK_BANNER = '.wc-block-components-notice-banner';

/**
 * Activates the classic theme for the test and puts the previous theme back in
 * the fixture teardown, which runs after a pass, a failure or a timeout (see
 * block-checkout-polish-messages.spec.ts). seed.sh reactivates the default
 * theme as well, the last safety net if this fails too. The admin works in its
 * own browser context, so the shopper stays a guest.
 */
const test = base.extend< { classicTheme: void } >( {
	classicTheme: [
		async ( { browser }, use ) => {
			const admin = await newAdminPage( browser );
			try {
				const previous = await getActiveTheme( admin );
				// Restoring "previous" would keep the leak for every later spec.
				if ( previous === CLASSIC_THEME ) {
					throw new Error(
						`The store was left on ${ CLASSIC_THEME }: run \`npm run env:seed\` to restore the default theme.`
					);
				}
				try {
					await activateTheme( admin, CLASSIC_THEME );
					await use();
				} finally {
					await activateTheme( admin, previous );
				}
			} finally {
				await admin.context().close();
			}
		},
		{ auto: true },
	],
} );

async function openClassicCheckout( page: Page, phone: string ): Promise< void > {
	await addProductToCart( page );
	await fillClassicCheckout( page, phone );
	await expect(
		page.locator( 'script#shift64-phone-checkout-validation-js' )
	).toHaveCount( 1 );
}

async function clearBillingFirstName( page: Page ): Promise< void > {
	await page
		.locator( '.woocommerce-billing-fields' )
		.getByLabel( /^First name/ )
		.fill( '' );
}

// Precondition for every test: the theme renders the classic notices, not the
// block banner. Fails loudly if WooCommerce or the theme switches to block
// notices instead of quietly testing the block markup again.
async function expectClassicNotice( page: Page, fieldId: string ): Promise< void > {
	await expect(
		page.locator( `${ ERROR_LIST } li[data-id="${ fieldId }"]` )
	).toBeVisible();
	await expect( page.locator( BLOCK_BANNER ) ).toHaveCount( 0 );
}

// WooCommerce's own client-side phone check accepts INVALID_PL_PHONE (digits
// and spaces) and marks the row `woocommerce-validated`; only the plugin's
// script swaps that for `woocommerce-invalid woocommerce-invalid-phone`.
async function expectPhoneHighlighted( page: Page ): Promise< void > {
	const row = page.locator( '#billing_phone_field' );
	await expect( row ).toContainClass(
		'woocommerce-invalid woocommerce-invalid-phone'
	);
	await expect( row ).not.toContainClass( 'woocommerce-validated' );
	await expect( page.locator( '#billing_phone' ) ).toHaveAttribute(
		'aria-invalid',
		'true'
	);
}

test( 'classic theme: an invalid phone as the only error highlights the field', async ( {
	page,
} ) => {
	await openClassicCheckout( page, INVALID_PL_PHONE );
	await placeOrder( page );

	await expectClassicNotice( page, 'billing_phone' );
	await expectPhoneHighlighted( page );
	await expectPhoneRejected( page );
} );

test( 'classic theme: an invalid phone among several errors highlights the field', async ( {
	page,
} ) => {
	await openClassicCheckout( page, INVALID_PL_PHONE );
	await clearBillingFirstName( page );
	await placeOrder( page );

	await expectClassicNotice( page, 'billing_phone' );
	await expect(
		page.locator( `${ ERROR_LIST } li[data-id="billing_first_name"]` )
	).toBeVisible();
	await expectPhoneHighlighted( page );
	await expectPhoneRejected( page );
} );

test( 'classic theme: a valid phone is not highlighted by another error and the order goes through', async ( {
	page,
} ) => {
	await openClassicCheckout( page, VALID_PL_PHONE );
	await clearBillingFirstName( page );
	await placeOrder( page );

	// The script has run by the time the notice is in the DOM: WooCommerce
	// fires checkout_error right after inserting it, in the same task.
	await expectClassicNotice( page, 'billing_first_name' );
	const row = page.locator( '#billing_phone_field' );
	await expect( row ).not.toContainClass( 'woocommerce-invalid' );
	await expect( row ).not.toContainClass( 'woocommerce-invalid-phone' );
	await expect( page.locator( '#billing_phone' ) ).not.toHaveAttribute(
		'aria-invalid',
		'true'
	);

	await page
		.locator( '.woocommerce-billing-fields' )
		.getByLabel( /^First name/ )
		.fill( customer.firstName );
	await placeOrder( page );
	await expectOrderReceived( page, VALID_PL_PHONE_E164 );
} );
