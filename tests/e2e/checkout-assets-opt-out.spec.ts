import { test, expect } from '@playwright/test';
import {
	addProductToCart,
	expectPhoneRejected,
	fillBlockCheckout,
	fillClassicCheckout,
	INVALID_PL_PHONE,
	placeOrder,
} from './helpers/checkout';

/**
 * The shift64_phone_validation_enqueue_assets escape hatch for themes with
 * their own checkout error UX: returning false drops the highlighting scripts,
 * and the server still rejects an invalid number.
 *
 * The filter comes from tests/e2e/mu-plugins/shift64-e2e-no-assets.php and
 * applies only to requests carrying its cookie, so no other spec sees it.
 */
test.beforeEach( async ( { context, baseURL } ) => {
	await context.addCookies( [
		{
			name: 'shift64_e2e_no_assets',
			value: '1',
			url: baseURL ?? 'http://localhost:8888',
		},
	] );
} );

test( 'block checkout without the plugin scripts still rejects an invalid phone', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillBlockCheckout( page, INVALID_PL_PHONE );
	await expect(
		page.locator( 'script#shift64-phone-block-checkout-validation-js' )
	).toHaveCount( 0 );

	await placeOrder( page );

	await expectPhoneRejected( page );
	await expect( page.getByLabel( /^Phone/ ) ).not.toHaveAttribute(
		'data-shift64-phone-error'
	);
} );

test( 'classic checkout without the plugin scripts still rejects an invalid phone', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillClassicCheckout( page, INVALID_PL_PHONE );
	await expect(
		page.locator( 'script#shift64-phone-checkout-validation-js' )
	).toHaveCount( 0 );

	await placeOrder( page );

	await expectPhoneRejected( page );
	await expect( page.locator( '#billing_phone_field' ) ).not.toContainClass(
		'woocommerce-invalid-phone'
	);
} );
