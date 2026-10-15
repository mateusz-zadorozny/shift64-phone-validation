import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../helpers/admin';
import {
	INVALID_PL_PHONE,
	placeOrder,
	VALID_PL_PHONE,
	VALID_PL_PHONE_E164,
} from '../helpers/checkout';
import {
	addProductToCartOn,
	cartItemsCount,
	fillBlockCheckoutOn,
	sitePath,
} from './site';

/*
 * Smoke test of the plugin network-activated on a subdirectory multisite (the
 * demo network, npm run demo:start). Network activation is where #38 and the
 * textdomain registration in the main plugin file came from. Only the last
 * test depends on the demo landing page.
 */

// From languages/shift64-phone-validation-pl_PL.po.
const POLISH_INVALID_BILLING_PHONE =
	'Telefon do płatności nie jest prawidłowym numerem telefonu.';

const ERROR_BANNER =
	'.wc-block-components-notice-banner.is-error, .wc-block-store-notice.is-error';

test( '/pl/: an invalid phone is rejected with the Polish message', async ( {
	page,
} ) => {
	await addProductToCartOn( page, 'pl' );
	await fillBlockCheckoutOn( page, 'pl', INVALID_PL_PHONE );
	await placeOrder( page );

	await expect(
		page
			.locator( ERROR_BANNER )
			.filter( { hasText: POLISH_INVALID_BILLING_PHONE } )
			.first()
	).toBeVisible();
	await expect( page ).not.toHaveURL( /order-received/ );
} );

test( '/en/: a valid phone places the order, stored in E.164', async ( {
	page,
} ) => {
	await addProductToCartOn( page, 'en' );
	await fillBlockCheckoutOn( page, 'en', VALID_PL_PHONE );
	await placeOrder( page );

	await expect( page ).toHaveURL( /\/en\/checkout\/order-received\//, {
		timeout: 30_000,
	} );
	await expect( page.getByText( VALID_PL_PHONE_E164 ).first() ).toBeVisible();
} );

test( 'Network Admin: the plugin is network-active and shows no notice', async ( {
	page,
} ) => {
	// The network root: loginAsAdmin's /wp-login.php is the main site's.
	await loginAsAdmin( page );
	await page.goto( '/wp-admin/network/plugins.php' );

	const row = page.locator( '#the-list tr[data-plugin]' ).filter( {
		has: page.locator( '.plugin-title strong', {
			hasText: 'SHIFT64 Phone Validation',
		} ),
	} );
	await expect( row ).toHaveCount( 1 );
	await expect( row ).toHaveClass( /(^|\s)active(\s|$)/ );
	await expect( row.getByRole( 'link', { name: /Network Deactivate/ } ) ).toHaveCount( 1 );

	// DependencyChecker prints its notices here when something is missing (#38).
	await expect(
		page.locator( '.notice' ).filter( { hasText: /SHIFT64/ } )
	).toHaveCount( 0 );
} );

test( '/pl/ landing button: checkout with exactly one product, however often it is clicked', async ( {
	page,
} ) => {
	for ( let click = 0; click < 2; click++ ) {
		await page.goto( sitePath( 'pl' ) );
		await page
			.getByRole( 'link', { name: /Add to cart & go to checkout/ } )
			.click();
		await expect( page ).toHaveURL( new RegExp( `${ sitePath( 'pl', 'checkout/' ) }$` ) );
	}

	expect( await cartItemsCount( page, 'pl' ) ).toBe( 1 );
} );
