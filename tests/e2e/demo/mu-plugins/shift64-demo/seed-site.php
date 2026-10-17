<?php
/**
 * Seed one demo site: the store baseline, then its scenario.
 *
 * Run by tests/e2e/demo/bin/seed.sh as
 *   wp --url=<site url> eval-file wp-content/mu-plugins/shift64-demo/seed-site.php <slug>
 * where <slug> is a scenario slug from scenarios.php, or `index` for the
 * network root (seeded like `en`). Idempotent: the product, pages and shipping
 * method are created only when missing; options, language and theme are
 * re-applied on every run, so hand-edited demo settings are reset.
 *
 * The store baseline mirrors tests/e2e/bin/seed.sh (default single-site env).
 * A change to one belongs in the other too.
 *
 * @package Shift64\SmartPhoneValidation
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! function_exists( 'WC' ) ) {
	WP_CLI::error( 'WooCommerce is not active on ' . home_url( '/' ) . ' - network-activate it first.' );
}

$shift64_demo_slug      = isset( $args[0] ) ? (string) $args[0] : '';
$shift64_demo_scenarios = require __DIR__ . '/scenarios.php';
$shift64_demo_lookup    = 'index' === $shift64_demo_slug ? 'en' : $shift64_demo_slug;
$shift64_demo_scenario  = null;
foreach ( $shift64_demo_scenarios as $shift64_demo_candidate ) {
	if ( $shift64_demo_candidate['slug'] === $shift64_demo_lookup ) {
		$shift64_demo_scenario = $shift64_demo_candidate;
	}
}
if ( null === $shift64_demo_scenario ) {
	WP_CLI::error( "Unknown scenario '$shift64_demo_slug'." );
}

$shift64_demo_log = static function ( string $message ) use ( $shift64_demo_slug ): void {
	WP_CLI::log( "seed: [$shift64_demo_slug] $message" );
};

/*
 * WooCommerce installs its tables and pages for a new subsite on first load;
 * make sure that happened before the pages are looked up.
 */
if ( ! get_option( 'woocommerce_version' ) || wc_get_page_id( 'checkout' ) < 1 ) {
	$shift64_demo_log( 'installing WooCommerce for this site' );
	WC_Install::install();
}

$shift64_demo_log( 'store settings' );
global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );
flush_rewrite_rules( false );
update_option( 'woocommerce_coming_soon', 'no' );
update_option(
	'woocommerce_onboarding_profile',
	array(
		'skipped'   => true,
		'completed' => true,
	)
);
update_option( 'woocommerce_store_address', 'ul. Testowa 1' );
update_option( 'woocommerce_store_city', 'Warszawa' );
update_option( 'woocommerce_store_postcode', '00-001' );
update_option( 'woocommerce_default_country', 'PL' );
update_option( 'woocommerce_currency', 'PLN' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_enable_checkout_login_reminder', 'no' );
update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );

$shift64_demo_log( 'payment (cash on delivery) and shipping (flat rate, rest of world)' );
$shift64_demo_gateways = WC()->payment_gateways()->payment_gateways();
if ( isset( $shift64_demo_gateways['cod'] ) ) {
	$shift64_demo_gateways['cod']->update_option( 'enabled', 'yes' );
}
$shift64_demo_zone = new WC_Shipping_Zone( 0 );
if ( ! $shift64_demo_zone->get_shipping_methods() ) {
	$shift64_demo_instance = $shift64_demo_zone->add_shipping_method( 'flat_rate' );
	update_option(
		"woocommerce_flat_rate_{$shift64_demo_instance}_settings",
		array(
			'title'      => 'Flat rate',
			'tax_status' => 'taxable',
			'cost'       => '10',
		)
	);
}

$shift64_demo_log( 'product' );
if ( ! get_page_by_path( 'e2e-test-product', OBJECT, 'product' ) ) {
	$shift64_demo_product = new WC_Product_Simple();
	$shift64_demo_product->set_name( 'E2E Test Product' );
	$shift64_demo_product->set_slug( 'e2e-test-product' );
	$shift64_demo_product->set_regular_price( '10' );
	$shift64_demo_product->set_status( 'publish' );
	$shift64_demo_product->save();
}

$shift64_demo_log( 'classic (shortcode) checkout page' );
$shift64_demo_classic = get_page_by_path( 'classic-checkout' );
if ( ! $shift64_demo_classic ) {
	$shift64_demo_classic = get_post(
		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Classic Checkout',
				'post_name'    => 'classic-checkout',
				'post_content' => '[woocommerce_checkout]',
			)
		)
	);
}

$shift64_demo_block = get_posts(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'name'        => 'checkout',
		'fields'      => 'ids',
	)
);
if ( 1 !== count( $shift64_demo_block ) ) {
	WP_CLI::error( "Expected exactly one published page with the slug 'checkout', got " . count( $shift64_demo_block ) . '.' );
}

$shift64_demo_checkout = 'classic' === $shift64_demo_scenario['checkout'] ? $shift64_demo_classic->ID : $shift64_demo_block[0];
$shift64_demo_log( 'WooCommerce checkout page = ' . get_permalink( $shift64_demo_checkout ) );
update_option( 'woocommerce_checkout_page_id', $shift64_demo_checkout );

$shift64_demo_log( 'language ' . $shift64_demo_scenario['locale'] );
update_option( 'WPLANG', 'en_US' === $shift64_demo_scenario['locale'] ? '' : $shift64_demo_scenario['locale'] );

$shift64_demo_theme = $shift64_demo_scenario['theme'] ?? WP_DEFAULT_THEME;
if ( get_stylesheet() !== $shift64_demo_theme ) {
	$shift64_demo_log( "theme $shift64_demo_theme" );
	switch_theme( $shift64_demo_theme );
}

$shift64_demo_log( 'plugin options' );
$shift64_demo_options = array_merge(
	array(
		'enabled'         => 'yes',
		'default_country' => 'PL',
		'validation_mode' => 'default_and_international',
		'output_format'   => 'E164',
		'format_on_save'  => 'yes',
	),
	$shift64_demo_scenario['options']
);
foreach ( $shift64_demo_options as $shift64_demo_key => $shift64_demo_value ) {
	update_option( 'shift64_phone_validation_' . $shift64_demo_key, $shift64_demo_value );
}

$shift64_demo_log( 'done' );
