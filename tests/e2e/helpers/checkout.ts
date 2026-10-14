import { expect, type Page } from '@playwright/test';

export const PRODUCT_PATH = '/product/e2e-test-product/';
export const BLOCK_CHECKOUT_PATH = '/checkout/';
export const CLASSIC_CHECKOUT_PATH = '/classic-checkout/';

export const VALID_PL_PHONE = '500 123 456';
// What the plugin stores after normalizing VALID_PL_PHONE to E.164.
export const VALID_PL_PHONE_E164 = '+48500123456';
// No Polish number starts with 10: libphonenumber rejects it while
// WooCommerce's own loose phone check (digits, spaces, dashes) lets it
// through. Beware: "123 456 789" is a real Kraków landline.
export const INVALID_PL_PHONE = '100 000 000';

export const customer = {
	email: 'e2e@example.com',
	firstName: 'Jan',
	lastName: 'Testowy',
	address: 'ul. Testowa 1',
	city: 'Warszawa',
	postcode: '00-001',
};

export async function addProductToCart( page: Page ): Promise< void > {
	await page.goto( PRODUCT_PATH );
	await page.getByRole( 'button', { name: /add to cart/i } ).click();
	await expect(
		page.getByText( /has been added to your cart/i )
	).toBeVisible();
}

export async function fillBlockCheckout(
	page: Page,
	phone: string
): Promise< void > {
	await page.goto( BLOCK_CHECKOUT_PATH );
	await page.getByLabel( /^Email address/ ).fill( customer.email );
	await page.getByLabel( /^First name/ ).fill( customer.firstName );
	await page.getByLabel( /^Last name/ ).fill( customer.lastName );
	await page.getByLabel( /^Address/ ).first().fill( customer.address );
	await page.getByLabel( /^City/ ).fill( customer.city );
	await page.getByLabel( /^Postal code/ ).fill( customer.postcode );
	await page.getByLabel( /^Phone/ ).fill( phone );
}

/**
 * Same as fillBlockCheckout(), through field ids instead of English labels, for
 * a store in another language. With "Use same address for billing" checked
 * (the default) only the shipping form is rendered: its phone is also the
 * billing phone.
 */
export async function fillBlockCheckoutByFieldId(
	page: Page,
	phone: string
): Promise< void > {
	await page.goto( BLOCK_CHECKOUT_PATH );
	await page.locator( '#email' ).fill( customer.email );
	await page.locator( '#shipping-first_name' ).fill( customer.firstName );
	await page.locator( '#shipping-last_name' ).fill( customer.lastName );
	await page.locator( '#shipping-address_1' ).fill( customer.address );
	await page.locator( '#shipping-city' ).fill( customer.city );
	await page.locator( '#shipping-postcode' ).fill( customer.postcode );
	await page.locator( '#shipping-phone' ).fill( phone );
}

export async function fillClassicCheckout(
	page: Page,
	phone: string
): Promise< void > {
	await page.goto( CLASSIC_CHECKOUT_PATH );
	// The classic form also renders the (hidden) shipping fields with the
	// same labels, so scope to the billing section.
	const billing = page.locator( '.woocommerce-billing-fields' );
	await billing.getByLabel( /^First name/ ).fill( customer.firstName );
	await billing.getByLabel( /^Last name/ ).fill( customer.lastName );
	await billing.getByLabel( /^Street address/ ).fill( customer.address );
	await billing.getByLabel( /^Postcode/ ).fill( customer.postcode );
	await billing.getByLabel( /^Town \/ City/ ).fill( customer.city );
	await billing.getByLabel( /^Phone/ ).fill( phone );
	await billing.getByLabel( /^Email address/ ).fill( customer.email );
}

/**
 * Click Place Order, in any store language (button found by class / id).
 *
 * The block checkout holds a typed address change for about 1.7 s before it
 * pushes it to the Store API (POST /wc/store/v1/batch), or pushes it at once
 * when the field loses focus. A Place Order click that lands while the update
 * runs is dropped: no checkout request, no notice. Clicking straight after the
 * last fill did exactly that now and then, because the click's own blur set
 * off the update. So: leave the field first, which starts any held update
 * synchronously, wait until the cart and checkout stores are no longer
 * updating, then click.
 */
export async function placeOrder( page: Page ): Promise< void > {
	const blockButton = page.locator(
		'.wc-block-components-checkout-place-order-button'
	);

	if ( ( await blockButton.count() ) === 0 ) {
		await page.locator( '#place_order' ).click();
		return;
	}

	await page.evaluate( () =>
		( document.activeElement as HTMLElement | null )?.blur()
	);
	await page.waitForFunction( () => {
		type Select = ( store: string ) => Record< string, () => boolean >;
		const select = ( window as unknown as { wp: { data: { select: Select } } } )
			.wp.data.select;
		return (
			! select( 'wc/store/cart' ).isCustomerDataUpdating() &&
			! select( 'wc/store/checkout' ).isCalculating()
		);
	} );
	await blockButton.click();
}

export async function expectOrderReceived(
	page: Page,
	storedPhone: string
): Promise< void > {
	await expect( page ).toHaveURL( /order-received/, { timeout: 30_000 } );
	await expect(
		page.getByText( /your order has been received/i )
	).toBeVisible();
	// The thank-you page prints the billing address as stored on the order,
	// so this proves the plugin normalized the phone, not just accepted it.
	// It appears twice (billing and the shipping copy), hence `.first()`.
	await expect( page.getByText( storedPhone ).first() ).toBeVisible();
}

export async function expectPhoneRejected( page: Page ): Promise< void > {
	// Block checkout: "Billing phone is not a valid phone number." (rendered in
	// the form and mirrored into an aria-live region, hence `.first()`).
	// Classic checkout: "Please enter a valid phone number."
	await expect(
		page.getByText( /(not a valid|enter a valid) phone number/i ).first()
	).toBeVisible();
	await expect( page ).not.toHaveURL( /order-received/ );
}
