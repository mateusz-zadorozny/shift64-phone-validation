import { test, expect, type Page } from '@playwright/test';
import {
	addProductToCart,
	customer,
	expectOrderReceived,
	fillClassicCheckout,
	INVALID_PL_PHONE,
	placeOrder,
	VALID_PL_PHONE,
	VALID_PL_PHONE_E164,
} from './helpers/checkout';

// A second valid Polish mobile, so the stored shipping phone cannot be
// mistaken for the billing one on the thank-you page.
const VALID_SHIPPING_PHONE = '600 700 800';
const VALID_SHIPPING_PHONE_E164 = '+48600700800';

// The notice WooCommerce renders for an error added with array( 'id' => ... ),
// in the classic and the block-theme markup (see checkout-validation.js).
function noticeFor( page: Page, fieldId: string ) {
	return page.locator(
		`.woocommerce-error li[data-id="${ fieldId }"], ` +
			`.wc-block-components-notice-banner.is-error[data-id="${ fieldId }"], ` +
			`.wc-block-components-notice-banner.is-error li[data-id="${ fieldId }"]`
	);
}

async function fillShippingAddress( page: Page, phone: string ): Promise< void > {
	await page.getByRole( 'checkbox', { name: 'Ship to a different address?' } ).check();
	const shipping = page.locator( '.woocommerce-shipping-fields' );
	await shipping.getByLabel( /^First name/ ).fill( customer.firstName );
	await shipping.getByLabel( /^Last name/ ).fill( customer.lastName );
	await shipping.getByLabel( /^Street address/ ).fill( customer.address );
	await shipping.getByLabel( /^Postcode/ ).fill( customer.postcode );
	await shipping.getByLabel( /^Town \/ City/ ).fill( customer.city );
	await shipping.getByLabel( /^Phone/ ).fill( phone );
}

test( 'classic checkout rejects an invalid shipping phone and highlights only that field', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillClassicCheckout( page, VALID_PL_PHONE );
	await fillShippingAddress( page, INVALID_PL_PHONE );
	await placeOrder( page );

	await expect( noticeFor( page, 'shipping_phone' ) ).toHaveCount( 1 );
	await expect( noticeFor( page, 'shipping_phone' ) ).toContainText(
		'Please enter a valid shipping phone number.'
	);
	await expect( noticeFor( page, 'billing_phone' ) ).toHaveCount( 0 );
	await expect( page.locator( '#shipping_phone_field' ) ).toContainClass(
		'woocommerce-invalid-phone'
	);
	await expect( page.locator( '#shipping_phone' ) ).toHaveAttribute(
		'aria-invalid',
		'true'
	);
	await expect( page.locator( '#billing_phone_field' ) ).not.toContainClass(
		'woocommerce-invalid-phone'
	);
	await expect( page ).not.toHaveURL( /order-received/ );
} );

test( 'classic checkout formats a valid shipping phone on the order', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillClassicCheckout( page, VALID_PL_PHONE );
	await fillShippingAddress( page, VALID_SHIPPING_PHONE );
	await placeOrder( page );

	await expectOrderReceived( page, VALID_SHIPPING_PHONE_E164 );
	await expect( page.getByText( VALID_PL_PHONE_E164 ).first() ).toBeVisible();
} );

test( 'classic checkout reports an invalid billing phone once when shipping to the same address', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillClassicCheckout( page, INVALID_PL_PHONE );
	await placeOrder( page );

	// WooCommerce copies the billing phone into shipping_phone; validating that
	// copy would add a second notice about a field the shopper never saw.
	await expect( noticeFor( page, 'billing_phone' ) ).toHaveCount( 1 );
	await expect( noticeFor( page, 'shipping_phone' ) ).toHaveCount( 0 );
	await expect(
		page.getByText( /valid (shipping )?phone number/i )
	).toHaveCount( 1 );
} );
