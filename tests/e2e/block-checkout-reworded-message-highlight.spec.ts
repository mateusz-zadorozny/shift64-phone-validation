import { test, expect } from '@playwright/test';
import { addProductToCart, fillBlockCheckout, placeOrder } from './helpers/checkout';

// The notice selector block-checkout-validation.js watches.
const ERROR_BANNER =
	'.wc-block-components-notice-banner.is-error, .wc-block-store-notice.is-error';

/**
 * Regression test for #35. A theme that rewords an error code other than
 * missing_international_prefix / invalid_number through
 * shift64_phone_validation_error_message lost the block checkout highlight:
 * the banner showed the reworded text, but the script was never handed it.
 *
 * The rewording comes from tests/e2e/mu-plugins/shift64-e2e-reworded-messages.php
 * and applies only to requests carrying its cookie, so no other spec sees it.
 */
const cases = [
	{
		code: 'too_long',
		phone: '600 123 456 789 012 345',
		message: 'That number has too many digits.',
	},
	{
		code: 'invalid_country_code',
		phone: '+999 600 123 456',
		message: 'Unknown country prefix.',
	},
];

for ( const { code, phone, message } of cases ) {
	test( `block checkout highlights the phone field for a reworded ${ code } message`, async ( {
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
		await fillBlockCheckout( page, phone );
		await placeOrder( page );

		await expect(
			page.locator( ERROR_BANNER ).filter( { hasText: message } ).first()
		).toBeVisible();

		// With "Use same address for billing" the shipping phone input is the
		// billing phone, and the script marks it for the billing error.
		const input = page.getByLabel( /^Phone/ );
		await expect( input ).toHaveAttribute(
			'data-shift64-phone-error',
			'billing'
		);
		await expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( page ).not.toHaveURL( /order-received/ );
	} );
}
