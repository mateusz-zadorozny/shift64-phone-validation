import { expect, test } from '@playwright/test';
import { newAdminPage, setFormatOnSave } from './helpers/admin';
import {
	addProductToCart,
	expectOrderReceived,
	fillBlockCheckout,
	placeOrder,
	VALID_PL_PHONE_E164,
} from './helpers/checkout';

// Valid, but in none of the output formats: kept only if nothing reformats it.
const AS_TYPED = '500-123-456';

test( 'turning Formatting on Save off stores a valid phone as typed', async ( {
	page,
	browser,
} ) => {
	const admin = await newAdminPage( browser );
	try {
		await setFormatOnSave( admin, false );

		await addProductToCart( page );
		await fillBlockCheckout( page, AS_TYPED );
		await placeOrder( page );
		await expectOrderReceived( page, AS_TYPED );
		await expect( page.getByText( VALID_PL_PHONE_E164 ) ).toHaveCount( 0 );
	} finally {
		await setFormatOnSave( admin, true );
		await admin.context().close();
	}
} );
