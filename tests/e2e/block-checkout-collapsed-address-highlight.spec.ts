import { test, expect, type Page } from '@playwright/test';
import {
	addProductToCart,
	customer,
	fillBlockCheckout,
	INVALID_PL_PHONE,
	placeOrder,
	VALID_PL_PHONE,
} from './helpers/checkout';

// The notice selector block-checkout-validation.js watches.
const ERROR_BANNER =
	'.wc-block-components-notice-banner.is-error, .wc-block-store-notice.is-error';

/**
 * Regression test for #54. A saved address is shown as a summary card with its
 * form hidden, so the phone the script marked could not be seen. After a phone
 * error the form holding the marked phone must be open.
 *
 * The address reaches the session while it is typed (cart/update-customer,
 * usually sent through the Store API batch route), so reloading the checkout
 * shows it as a card without placing an order first.
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

async function fillBillingForm( page: Page, phone: string ): Promise< void > {
	await page.locator( '#billing-first_name' ).fill( customer.firstName );
	await page.locator( '#billing-last_name' ).fill( customer.lastName );
	await page.locator( '#billing-address_1' ).fill( customer.address );
	await page.locator( '#billing-city' ).fill( customer.city );
	await page.locator( '#billing-postcode' ).fill( customer.postcode );
	await page.locator( '#billing-phone' ).fill( phone );
}

async function expectMarked(
	page: Page,
	inputId: string,
	type: 'billing' | 'shipping'
): Promise< void > {
	await expect(
		page.locator( ERROR_BANNER ).filter( { hasText: /phone/i } ).first()
	).toBeVisible();

	const input = page.locator( `#${ inputId }` );
	await expect( input ).toBeVisible();
	await expect( input ).toHaveAttribute( 'data-shift64-phone-error', type );
	await expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
	await expect(
		page.locator( '.wc-block-components-text-input', { has: input } )
	).toHaveClass( /has-error/ );
	await expect( page ).not.toHaveURL( /order-received/ );
}

test( 'block checkout opens a collapsed shipping address for a billing phone error (same address for billing)', async ( {
	page,
} ) => {
	await addProductToCart( page );
	const saved = savedPhone( page, INVALID_PL_PHONE );
	await fillBlockCheckout( page, INVALID_PL_PHONE );
	await saved;

	await page.reload();
	await expect( page.locator( '#shipping-phone' ) ).toBeHidden();

	await placeOrder( page );

	// The shipping phone doubles as the billing phone.
	await expectMarked( page, 'shipping-phone', 'billing' );
} );

test( 'block checkout opens a collapsed billing address for a billing phone error', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillBlockCheckout( page, VALID_PL_PHONE );
	await page.getByLabel( 'Use same address for billing' ).uncheck();
	const saved = savedPhone( page, INVALID_PL_PHONE );
	await fillBillingForm( page, INVALID_PL_PHONE );
	await saved;

	await page.reload();
	await expect( page.locator( '#billing-phone' ) ).toBeHidden();

	await placeOrder( page );

	await expectMarked( page, 'billing-phone', 'billing' );
} );

test( 'block checkout opens a collapsed shipping address for a shipping phone error', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillBlockCheckout( page, INVALID_PL_PHONE );
	await page.getByLabel( 'Use same address for billing' ).uncheck();
	const saved = savedPhone( page, VALID_PL_PHONE );
	await fillBillingForm( page, VALID_PL_PHONE );
	await saved;

	await page.reload();
	await expect( page.locator( '#shipping-phone' ) ).toBeHidden();

	await placeOrder( page );

	await expectMarked( page, 'shipping-phone', 'shipping' );
} );
