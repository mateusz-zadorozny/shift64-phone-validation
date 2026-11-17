import { expect, type Page } from '@playwright/test';
import { customer } from '../helpers/checkout';

/**
 * A path on one demo subsite, from the network root (the demo project's
 * baseURL): sitePath( 'pl', 'checkout/' ) → '/pl/checkout/'. The shared
 * helpers use absolute single-site paths, so the demo specs build their own.
 */
export function sitePath( slug: string, path = '' ): string {
	return `/${ slug }/${ path.replace( /^\/+/, '' ) }`;
}

/**
 * The number of items in this browser's cart on a subsite (Store API, same
 * cookies as the page).
 */
export async function cartItemsCount( page: Page, slug: string ): Promise< number > {
	const response = await page.request.get(
		sitePath( slug, 'wp-json/wc/store/v1/cart' )
	);
	expect( response.ok() ).toBe( true );
	return ( await response.json() ).items_count;
}

/**
 * Product page → Add to cart, in any store language. Waits for the cart rather
 * than for a notice, whose wording follows the store language.
 */
export async function addProductToCartOn(
	page: Page,
	slug: string
): Promise< void > {
	await page.goto( sitePath( slug, 'product/e2e-test-product/' ) );
	await page.locator( '.single_add_to_cart_button' ).click();
	await expect.poll( () => cartItemsCount( page, slug ) ).toBeGreaterThan( 0 );
}

/**
 * Open the block checkout of a subsite and fill it through field ids, which
 * do not change with the store language. With "Use same address for billing"
 * (the default) the shipping phone is also the billing phone.
 */
export async function fillBlockCheckoutOn(
	page: Page,
	slug: string,
	phone: string
): Promise< void > {
	await page.goto( sitePath( slug, 'checkout/' ) );
	await page.locator( '#email' ).fill( customer.email );
	await page.locator( '#shipping-first_name' ).fill( customer.firstName );
	await page.locator( '#shipping-last_name' ).fill( customer.lastName );
	await page.locator( '#shipping-address_1' ).fill( customer.address );
	await page.locator( '#shipping-city' ).fill( customer.city );
	await page.locator( '#shipping-postcode' ).fill( customer.postcode );
	await page.locator( '#shipping-phone' ).fill( phone );
}
