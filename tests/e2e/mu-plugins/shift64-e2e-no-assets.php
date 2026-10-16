<?php
/**
 * Plugin Name: SHIFT64 e2e – highlighting scripts switched off
 * Description: Test fixture for checkout-assets-opt-out.spec.ts. Mounted into wp-content/mu-plugins by .wp-env.json; never shipped.
 *
 * Returns false from shift64_phone_validation_enqueue_assets, the way a theme
 * with its own checkout error UX would, but only for requests carrying the
 * cookie below, so every other spec keeps the plugin's scripts.
 *
 * @package Shift64\SmartPhoneValidation\Tests
 */

add_filter(
	'shift64_phone_validation_enqueue_assets',
	function ( $enqueue ) {
		return empty( $_COOKIE['shift64_e2e_no_assets'] ) ? $enqueue : false;
	}
);
