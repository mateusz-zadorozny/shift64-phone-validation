import { test, expect, type Page } from '@playwright/test';
import {
	addProductToCart,
	customer,
	fillBlockCheckoutByFieldId,
	placeOrder,
	VALID_PL_PHONE,
} from './helpers/checkout';

// The notice selector block-checkout-validation.js watches.
const ERROR_BANNER =
	'.wc-block-components-notice-banner.is-error, .wc-block-store-notice.is-error';

const MESSAGE = 'That number has too many digits.';
const TOO_LONG_PHONE = '600 123 456 789 012 345';

/**
 * Regression test for #52. tests/e2e/mu-plugins/shift64-e2e-reworded-messages.php
 * rewords too_long without looking at $field, so billing and shipping get the same
 * text. The banner text alone cannot tell the fields apart: the highlighted field
 * must be the one the Store API error names.
 */
async function fillSeparateBillingAddress(
	page: Page,
	billingPhone: string,
	shippingPhone: string
): Promise< void > {
	await fillBlockCheckoutByFieldId( page, shippingPhone );
	await page.getByLabel( /use same address for billing/i ).uncheck();

	await page.locator( '#billing-first_name' ).fill( customer.firstName );
	await page.locator( '#billing-last_name' ).fill( customer.lastName );
	await page.locator( '#billing-address_1' ).fill( customer.address );
	await page.locator( '#billing-city' ).fill( customer.city );
	await page.locator( '#billing-postcode' ).fill( customer.postcode );
	await page.locator( '#billing-phone' ).fill( billingPhone );
}

const cases = [
	{
		field: 'shipping',
		other: 'billing',
		billingPhone: VALID_PL_PHONE,
		shippingPhone: TOO_LONG_PHONE,
	},
	{
		field: 'billing',
		other: 'shipping',
		billingPhone: TOO_LONG_PHONE,
		shippingPhone: VALID_PL_PHONE,
	},
];

for ( const { field, other, billingPhone, shippingPhone } of cases ) {
	test( `block checkout highlights only the ${ field } phone when the reworded message is the same for both fields`, async ( {
		page,
		context,
		baseURL,
	} ) => {
		await context.addCookies( [
			{
				name: 'shift64_e2e_reworded_messages',
				value: '1',
				url: baseURL ?? 'http://localhost:8888',
			},
		] );

		await addProductToCart( page );
		await fillSeparateBillingAddress( page, billingPhone, shippingPhone );
		await placeOrder( page );

		await expect(
			page.locator( ERROR_BANNER ).filter( { hasText: MESSAGE } ).first()
		).toBeVisible();

		const input = page.locator( `#${ field }-phone` );
		await expect( input ).toHaveAttribute(
			'data-shift64-phone-error',
			field
		);
		await expect( input ).toHaveAttribute( 'aria-invalid', 'true' );

		const otherInput = page.locator( `#${ other }-phone` );
		await expect( otherInput ).not.toHaveAttribute(
			'data-shift64-phone-error'
		);
		await expect( otherInput ).not.toHaveAttribute( 'aria-invalid', 'true' );
		await expect( page ).not.toHaveURL( /order-received/ );
	} );
}
