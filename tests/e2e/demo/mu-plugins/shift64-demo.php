<?php
/**
 * Plugin Name: SHIFT64 demo network
 * Description: Demo-only (npm run demo:start). Renders the scenario landing page as the front page of every demo site and adds the ?shift64_demo=buy endpoint. Does nothing on sites that are not in scenarios.php. Never shipped.
 *
 * @package Shift64\SmartPhoneValidation
 */

/**
 * The scenarios from scenarios.php.
 *
 * @return array<int, array<string, mixed>>
 */
function shift64_demo_scenarios(): array {
	static $scenarios = null;
	if ( null === $scenarios ) {
		$scenarios = require __DIR__ . '/shift64-demo/scenarios.php';
	}
	return $scenarios;
}

/**
 * The current site's role in the demo: 'index' for the network root, a
 * scenario slug for a scenario subsite, null for any other site.
 */
function shift64_demo_current_slug(): ?string {
	if ( ! is_multisite() ) {
		return null;
	}
	$path = trim( get_site()->path, '/' );
	if ( '' === $path && is_main_site() ) {
		return 'index';
	}
	foreach ( shift64_demo_scenarios() as $scenario ) {
		if ( $scenario['slug'] === $path ) {
			return $path;
		}
	}
	return null;
}

/**
 * The seeded product, or null when WooCommerce or the product is missing.
 */
function shift64_demo_product_id(): ?int {
	if ( ! function_exists( 'WC' ) ) {
		return null;
	}
	$product = get_page_by_path( 'e2e-test-product', OBJECT, 'product' );
	return $product ? $product->ID : null;
}

// The front page of a demo site is the landing page (or the index).
add_filter(
	'template_include',
	static function ( $template ) {
		if ( is_front_page() && null !== shift64_demo_current_slug() ) {
			return __DIR__ . '/shift64-demo/landing.php';
		}
		return $template;
	},
	99
);

// ?shift64_demo=buy: empty the cart, add the product once, go to checkout.
add_action(
	'template_redirect',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- demo-only link, no privileged action.
		if ( ! isset( $_GET['shift64_demo'] ) || 'buy' !== $_GET['shift64_demo'] ) {
			return;
		}
		$slug = shift64_demo_current_slug();
		if ( null === $slug || 'index' === $slug ) {
			return;
		}
		$product_id = shift64_demo_product_id();
		if ( null === $product_id || null === WC()->cart ) {
			wp_safe_redirect( add_query_arg( 'shift64_demo', 'missing', home_url( '/' ) ) );
			exit;
		}
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product_id, 1 );
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}
);
