import { test, expect, type Page } from '@playwright/test';
import {
	addProductToCart,
	expectOrderReceived,
	fillBlockCheckout,
	INVALID_PL_PHONE,
	placeOrder,
	VALID_PL_PHONE,
	VALID_PL_PHONE_E164,
} from './helpers/checkout';

// The notice selector block-checkout-validation.js watches.
const ERROR_BANNER =
	'.wc-block-components-notice-banner.is-error, .wc-block-store-notice.is-error';
// WooCommerce Blocks' saved-address card and its form; .is-editing marks it open.
const ADDRESS_WRAPPER = '.wc-block-components-address-address-wrapper';

/**
 * What happens around the #54 fix, beyond the first error: the shopper corrects
 * the number in the form the script opened, places the order again with the
 * number still wrong, or never had a wrong number at all.
 * block-checkout-collapsed-address-highlight.spec.ts covers the first error.
 *
 * As there, the address reaches the session while it is typed, so reloading
 * the checkout shows it as a card without placing an order first.
 */

function savedPhone( page: Page, phone: string ) {
	return page.waitForResponse( ( response ) => {
		const body = response.request().postData() ?? '';

		return (
			response.url().includes( '/wc/store/' ) &&
			body.includes( 'cart/update-customer' ) &&
			body.includes( `"phone":"${ phone }"` ) &&
			response.ok()
		);
	} );
}

/** Guest checkout whose (single) shipping address shows as a collapsed card. */
async function openCheckoutWithCollapsedAddress(
	page: Page,
	phone: string
): Promise< void > {
	await addProductToCart( page );
	const saved = savedPhone( page, phone );
	await fillBlockCheckout( page, phone );
	await saved;

	await page.reload();
	await expect( page.locator( '#shipping-phone' ) ).toBeHidden();
}

function shippingAddressWrapper( page: Page ) {
	return page.locator( ADDRESS_WRAPPER, {
		has: page.locator( '#shipping-phone' ),
	} );
}

async function expectShippingPhoneMarked( page: Page ): Promise< void > {
	await expect(
		page.locator( ERROR_BANNER ).filter( { hasText: /phone/i } ).first()
	).toBeVisible();

	const input = page.locator( '#shipping-phone' );
	await expect( input ).toBeVisible();
	await expect( shippingAddressWrapper( page ) ).toHaveClass( /is-editing/ );
	// The shipping phone doubles as the billing phone.
	await expect( input ).toHaveAttribute( 'data-shift64-phone-error', 'billing' );
	await expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
	await expect(
		page.locator( '.wc-block-components-text-input', { has: input } )
	).toHaveClass( /has-error/ );
	await expect( page ).not.toHaveURL( /order-received/ );
}

test( 'block checkout: correcting the phone in the opened address clears the mark and the order goes through', async ( {
	page,
} ) => {
	await openCheckoutWithCollapsedAddress( page, INVALID_PL_PHONE );
	await placeOrder( page );
	await expectShippingPhoneMarked( page );

	const input = page.locator( '#shipping-phone' );
	await input.fill( VALID_PL_PHONE );

	await expect( input ).not.toHaveAttribute( 'data-shift64-phone-error' );
	await expect( input ).toHaveAttribute( 'aria-invalid', 'false' );
	await expect(
		page.locator( '.wc-block-components-text-input', { has: input } )
	).not.toHaveClass( /has-error/ );

	await placeOrder( page );
	await expectOrderReceived( page, VALID_PL_PHONE_E164 );
} );

test( 'block checkout: placing the order again with the phone still wrong keeps the address open and marked', async ( {
	page,
} ) => {
	await openCheckoutWithCollapsedAddress( page, INVALID_PL_PHONE );
	await placeOrder( page );
	await expectShippingPhoneMarked( page );

	// The form is open now: the script must leave it alone, not click the
	// card's Edit control again and fold it back up. The first notice is still
	// on screen, so wait for the second rejection before looking.
	const rejected = page.waitForResponse(
		( response ) =>
			response.request().method() === 'POST' &&
			/\/wc\/store(\/v\d+)?\/checkout/.test( response.url() ) &&
			response.status() === 400
	);
	await placeOrder( page );
	await rejected;
	await expectShippingPhoneMarked( page );
} );

test( 'block checkout: an order from a collapsed address with a valid phone goes through', async ( {
	page,
} ) => {
	await openCheckoutWithCollapsedAddress( page, VALID_PL_PHONE );
	await placeOrder( page );

	await expectOrderReceived( page, VALID_PL_PHONE_E164 );
} );
