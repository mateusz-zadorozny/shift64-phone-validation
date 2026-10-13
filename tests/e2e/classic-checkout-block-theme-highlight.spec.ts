import { expect, test, type Page } from '@playwright/test';
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

// Regression for #33: on a block theme WooCommerce renders classic-checkout
// notices with its block notice templates, so the field id sits on
// `.wc-block-components-notice-banner` (one error) or on `li[data-id]` inside
// it (several errors), not on `ul.woocommerce-error li`. The suite runs on
// wp-env's default theme, which is a block theme (Twenty Twenty-Five on
// current WordPress); each test asserts the block banner markup before the
// highlight, so a switch to a classic default theme fails loudly instead of
// quietly testing the classic markup.
const ERROR_BANNER = '.wc-block-components-notice-banner.is-error';

// Regression for #34: the seed configures the block /checkout/ page as the
// WooCommerce checkout page, and /classic-checkout/ must still load the classic
// script, picked from the page being viewed.
async function openClassicCheckout( page: Page, phone: string ): Promise< void > {
	await addProductToCart( page );
	await fillClassicCheckout( page, phone );
	// The script under test, not block-checkout-validation.js (#34).
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

test( 'block theme: an invalid phone as the only error highlights the field', async ( {
	page,
} ) => {
	await openClassicCheckout( page, INVALID_PL_PHONE );
	await placeOrder( page );

	// One error: the banner itself carries the data-id.
	await expect(
		page.locator( `${ ERROR_BANNER }[data-id="billing_phone"]` )
	).toBeVisible();
	await expectPhoneHighlighted( page );
	await expectPhoneRejected( page );
} );

test( 'block theme: an invalid phone among several errors highlights the field', async ( {
	page,
} ) => {
	await openClassicCheckout( page, INVALID_PL_PHONE );
	await clearBillingFirstName( page );
	await placeOrder( page );

	// Several errors: the banner has no data-id, its list items do.
	await expect(
		page.locator( `${ ERROR_BANNER } li[data-id="billing_phone"]` )
	).toBeVisible();
	await expect(
		page.locator( `${ ERROR_BANNER } li[data-id="billing_first_name"]` )
	).toBeVisible();
	await expectPhoneHighlighted( page );
	await expectPhoneRejected( page );
} );

test( 'block theme: a valid phone is not highlighted by another error and the order goes through', async ( {
	page,
} ) => {
	await openClassicCheckout( page, VALID_PL_PHONE );
	await clearBillingFirstName( page );
	await placeOrder( page );

	// The script has run by the time the banner is in the DOM: WooCommerce
	// fires checkout_error right after inserting it, in the same task.
	await expect(
		page.locator( `${ ERROR_BANNER }[data-id="billing_first_name"]` )
	).toBeVisible();
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
