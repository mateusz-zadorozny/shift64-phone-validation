<?php
/**
 * Plugin Name: SHIFT64 e2e – reworded error messages
 * Description: Test fixture for block-checkout-reworded-message-highlight.spec.ts and block-checkout-identical-reworded-message-highlight.spec.ts. Mounted into wp-content/mu-plugins by .wp-env.json; never shipped.
 *
 * Rewords two non-default error codes through shift64_phone_validation_error_message,
 * the way a theme would, but only for requests carrying the cookie below, so every
 * other spec keeps seeing the plugin's own wording. Like many themes it ignores
 * $field, so billing and shipping get the same text (#52).
 *
 * @package Shift64\SmartPhoneValidation\Tests
 */

add_filter(
	'shift64_phone_validation_error_message',
	function ( $message, $code ) {
		if ( empty( $_COOKIE['shift64_e2e_reworded_messages'] ) ) {
			return $message;
		}

		$reworded = array(
			'too_long'             => 'That number has too many digits.',
			'invalid_country_code' => 'Unknown country prefix.',
		);

		return $reworded[ $code ] ?? $message;
	},
	10,
	2
);
