import { test } from '@playwright/test';
import {
	addProductToCart,
	expectOrderReceived,
	fillBlockCheckout,
	placeOrder,
	VALID_PL_PHONE_E164,
} from './helpers/checkout';

/**
 * VALID_PL_PHONE typed with brackets, dots, slashes and dashes is stored as
 * the same E.164 number. Normalizer strips these, but libphonenumber would
 * tolerate them as well, so this guards what the shopper gets, not Normalizer
 * itself. Its rules that only it covers have their own specs: the leading 00
 * in admin-international-only.spec.ts, non-breaking spaces in
 * block-checkout-unicode-whitespace-phone.spec.ts.
 */
test( 'block checkout stores a number typed with brackets, dots, slashes and dashes as E.164', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillBlockCheckout( page, '(500) 12-3/45.6' );
	await placeOrder( page );
	await expectOrderReceived( page, VALID_PL_PHONE_E164 );
} );
