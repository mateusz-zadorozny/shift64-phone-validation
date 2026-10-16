import { test as base, expect, type Page } from '@playwright/test';
import { newAdminPage } from './helpers/admin';
import {
	addProductToCart,
	BLOCK_CHECKOUT_PATH,
	customer,
	INVALID_PL_PHONE,
	placeOrder,
} from './helpers/checkout';

// The notice selector block-checkout-validation.js watches.
const ERROR_BANNER =
	'.wc-block-components-notice-banner.is-error, .wc-block-store-notice.is-error';

type Shopper = { username: string; password: string };

/**
 * A customer account whose saved addresses carry INVALID_PL_PHONE, created
 * through the WooCommerce REST API as the admin (in its own browser context)
 * and deleted again afterwards.
 *
 * That is how a store ends up with such an account: the phone was saved before
 * the plugin was active, or through My Account, which the plugin does not
 * validate. The password is generated per run and never leaves the test.
 */
const test = base.extend< { shopper: Shopper } >( {
	shopper: async ( { browser }, use ) => {
		const admin = await newAdminPage( browser );
		const nonce = await (
			await admin.request.get( '/wp-admin/admin-ajax.php?action=rest-nonce' )
		).text();
		const suffix = `${ Date.now() }-${ Math.floor( Math.random() * 1e6 ) }`;
		const shopper = {
			username: `e2e-customer-${ suffix }`,
			password: `e2e-${ suffix }-${ Math.random().toString( 36 ).slice( 2 ) }`,
		};
		const address = {
			first_name: customer.firstName,
			last_name: customer.lastName,
			address_1: customer.address,
			city: customer.city,
			postcode: customer.postcode,
			country: 'PL',
			phone: INVALID_PL_PHONE,
		};

		const created = await admin.request.post( '/wp-json/wc/v3/customers', {
			headers: { 'X-WP-Nonce': nonce },
			data: {
				email: `${ shopper.username }@example.com`,
				username: shopper.username,
				password: shopper.password,
				billing: { ...address, email: `${ shopper.username }@example.com` },
				shipping: address,
			},
		} );
		expect( created.status(), 'customer created' ).toBe( 201 );
		const { id } = ( await created.json() ) as { id: number };

		try {
			await use( shopper );
		} finally {
			await admin.request.delete( `/wp-json/wc/v3/customers/${ id }`, {
				headers: { 'X-WP-Nonce': nonce },
				params: { force: 'true' },
			} );
			await admin.context().close();
		}
	},
} );

async function logIn( page: Page, shopper: Shopper ): Promise< void > {
	await page.goto( '/my-account/' );
	await page.getByLabel( /^Username or email address/ ).fill( shopper.username );
	await page.getByLabel( /^Password/ ).fill( shopper.password );
	await page.getByRole( 'button', { name: 'Log in' } ).click();
	await expect(
		page.getByRole( 'link', { name: /log out/i } ).first()
	).toBeVisible();
}

/**
 * #54 as a returning customer meets it: the saved address is shown as a card,
 * so the phone the script marked sat in a hidden form. After the phone error
 * the form must be open and the phone marked.
 * block-checkout-collapsed-address-highlight.spec.ts covers the guest session.
 */
test( 'block checkout opens a returning customer’s saved address when its phone is invalid', async ( {
	page,
	shopper,
} ) => {
	await logIn( page, shopper );
	await addProductToCart( page );
	await page.goto( BLOCK_CHECKOUT_PATH );

	// Precondition: WooCommerce shows the saved address as a card.
	await expect( page.locator( '#shipping-phone' ) ).toBeHidden();

	await placeOrder( page );

	await expect(
		page.locator( ERROR_BANNER ).filter( { hasText: /phone/i } ).first()
	).toBeVisible();
	const input = page.locator( '#shipping-phone' );
	await expect( input ).toBeVisible();
	await expect( input ).toHaveAttribute( 'data-shift64-phone-error', 'billing' );
	await expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
	await expect(
		page.locator( '.wc-block-components-text-input', { has: input } )
	).toHaveClass( /has-error/ );
	await expect( page ).not.toHaveURL( /order-received/ );
} );
