import { expect, test } from '@playwright/test';
import { newAdminPage, setValidationMode } from './helpers/admin';
import {
	addProductToCart,
	expectOrderReceived,
	fillBlockCheckout,
	placeOrder,
	VALID_PL_PHONE,
	VALID_PL_PHONE_E164,
} from './helpers/checkout';

/**
 * Validation Mode "International only": a number without "+" is rejected with
 * its own message, even when it is valid for the address country, while the
 * same number with its prefix goes through.
 */
test( 'International only mode rejects a national number and accepts it with its prefix', async ( {
	page,
	browser,
} ) => {
	const admin = await newAdminPage( browser );
	try {
		await setValidationMode( admin, 'International only' );

		await addProductToCart( page );
		await fillBlockCheckout( page, VALID_PL_PHONE );
		await placeOrder( page );
		// Rendered in the banner and mirrored into an aria-live region.
		await expect(
			page.getByText( 'Billing phone must contain country prefix.' ).first()
		).toBeVisible();
		await expect( page ).not.toHaveURL( /order-received/ );

		await page.getByLabel( /^Phone/ ).fill( '+48 500 123 456' );
		await placeOrder( page );
		await expectOrderReceived( page, VALID_PL_PHONE_E164 );
	} finally {
		await setValidationMode( admin, 'Default country + international' );
		await admin.context().close();
	}
} );

/**
 * A leading 00 is the international prefix too. libphonenumber reads it that
 * way for a Polish address by itself, so only this mode, which looks for "+"
 * before parsing, shows whether Normalizer turned 00 into +.
 */
test( 'International only mode accepts a number typed with a leading 00', async ( {
	page,
	browser,
} ) => {
	const admin = await newAdminPage( browser );
	try {
		await setValidationMode( admin, 'International only' );

		await addProductToCart( page );
		await fillBlockCheckout( page, '0048 500 123 456' );
		await placeOrder( page );
		await expectOrderReceived( page, VALID_PL_PHONE_E164 );
	} finally {
		await setValidationMode( admin, 'Default country + international' );
		await admin.context().close();
	}
} );
