import { test, expect, type Page } from '@playwright/test';
import {
	addProductToCart,
	BLOCK_CHECKOUT_PATH,
	CLASSIC_CHECKOUT_PATH,
	customer,
	expectOrderReceived,
	expectPhoneRejected,
	placeOrder,
	VALID_PL_PHONE,
	VALID_PL_PHONE_E164,
} from './helpers/checkout';

/**
 * A number typed without "+" is read in the country of the address, not in
 * the plugin's Default Country (PL in seed.sh). Checked with a German address:
 * VALID_PL_PHONE is not a German number, a German mobile is.
 */

const GERMAN_ADDRESS = { country: 'DE', postcode: '10115', city: 'Berlin' };
const GERMAN_MOBILE = '0151 23456789';
const GERMAN_MOBILE_E164 = '+4915123456789';

async function fillGermanBlockCheckout( page: Page, phone: string ): Promise< void > {
	await page.goto( BLOCK_CHECKOUT_PATH );
	await page.getByLabel( /^Email address/ ).fill( customer.email );
	// Country first: it changes which postcode WooCommerce accepts.
	await page.getByLabel( 'Country/Region' ).selectOption( GERMAN_ADDRESS.country );
	await page.getByLabel( /^First name/ ).fill( customer.firstName );
	await page.getByLabel( /^Last name/ ).fill( customer.lastName );
	await page.getByLabel( /^Address/ ).first().fill( customer.address );
	await page.getByLabel( /^Postal code/ ).fill( GERMAN_ADDRESS.postcode );
	await page.getByLabel( /^City/ ).fill( GERMAN_ADDRESS.city );
	await page.getByLabel( /^Phone/ ).fill( phone );
}

async function fillGermanClassicCheckout( page: Page, phone: string ): Promise< void > {
	await page.goto( CLASSIC_CHECKOUT_PATH );
	const billing = page.locator( '.woocommerce-billing-fields' );
	// A selectWoo combobox over the native <select>.
	await billing
		.locator( '#billing_country' )
		.selectOption( GERMAN_ADDRESS.country, { force: true } );
	await billing.getByLabel( /^First name/ ).fill( customer.firstName );
	await billing.getByLabel( /^Last name/ ).fill( customer.lastName );
	await billing.getByLabel( /^Street address/ ).fill( customer.address );
	await billing.getByLabel( /^Postcode/ ).fill( GERMAN_ADDRESS.postcode );
	await billing.getByLabel( /^Town \/ City/ ).fill( GERMAN_ADDRESS.city );
	await billing.getByLabel( /^Phone/ ).fill( phone );
	await billing.getByLabel( /^Email address/ ).fill( customer.email );
}

test( 'block checkout reads a national number in the country of a German address', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillGermanBlockCheckout( page, GERMAN_MOBILE );
	await placeOrder( page );
	await expectOrderReceived( page, GERMAN_MOBILE_E164 );
} );

test( 'block checkout rejects a Polish national number for a German address', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillGermanBlockCheckout( page, VALID_PL_PHONE );
	await placeOrder( page );
	await expectPhoneRejected( page );
} );

test( 'block checkout accepts a Polish number with its prefix for a German address', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillGermanBlockCheckout( page, '+48 500 123 456' );
	await placeOrder( page );
	await expectOrderReceived( page, VALID_PL_PHONE_E164 );
} );

test( 'classic checkout reads a national number in the country of a German address', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillGermanClassicCheckout( page, GERMAN_MOBILE );
	await placeOrder( page );
	await expectOrderReceived( page, GERMAN_MOBILE_E164 );
} );

test( 'classic checkout rejects a Polish national number for a German address', async ( {
	page,
} ) => {
	await addProductToCart( page );
	await fillGermanClassicCheckout( page, VALID_PL_PHONE );
	await placeOrder( page );
	await expectPhoneRejected( page );
	await expect( page.locator( '#billing_phone_field' ) ).toContainClass(
		'woocommerce-invalid-phone'
	);
} );
