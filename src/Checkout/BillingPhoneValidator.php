<?php
/**
 * Billing phone validation for WooCommerce checkout.
 *
 * @package Shift64\SmartPhoneValidation\Checkout
 */

declare(strict_types=1);

namespace Shift64\SmartPhoneValidation\Checkout;

use Shift64\SmartPhoneValidation\Admin\Settings;
use Shift64\SmartPhoneValidation\Validation\PhoneValidator;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles billing phone validation and formatting during WooCommerce checkout.
 */
class BillingPhoneValidator {

	/**
	 * Initialize checkout validation hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Validate billing phone during checkout with field-specific errors.
		add_action( 'woocommerce_after_checkout_validation', array( self::class, 'validate_billing_phone' ), 10, 2 );

		// Format billing phone before saving order.
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'format_billing_phone_on_order' ), 10, 2 );
	}

	/**
	 * Validate billing phone number during checkout.
	 *
	 * @param array<string, mixed> $data   Checkout posted data.
	 * @param \WP_Error            $errors Validation errors.
	 * @return void
	 */
	public static function validate_billing_phone( array $data, \WP_Error $errors ): void {
		// Only validate if plugin is enabled.
		if ( ! Settings::is_validation_enabled() ) {
			return;
		}

		// Get billing phone from posted data.
		$billing_phone = isset( $data['billing_phone'] ) ? $data['billing_phone'] : '';

		// Skip validation if phone is empty (WooCommerce handles required field validation).
		if ( '' === $billing_phone ) {
			return;
		}

		// Get country for validation context.
		$billing_country = isset( $data['billing_country'] ) ? $data['billing_country'] : null;

		// Use billing country if provided, otherwise use default from settings.
		$country_code = ! empty( $billing_country ) ? $billing_country : null;

		if ( ! Hooks::should_validate( ErrorMessages::FIELD_BILLING, $data ) ) {
			return;
		}

		// Validate the phone number.
		$result = PhoneValidator::validate( $billing_phone, $country_code );

		if ( ! $result->is_valid() ) {
			$error_message = ErrorMessages::for_classic_checkout( $result->get_error_code(), ErrorMessages::FIELD_BILLING );
			$errors->add( 'billing_phone_validation', $error_message, array( 'id' => 'billing_phone' ) );
		}
	}

	/**
	 * Format billing phone number before saving to order.
	 *
	 * @param \WC_Order            $order    The order object.
	 * @param array<string, mixed> $data     The checkout data.
	 * @return void
	 */
	public static function format_billing_phone_on_order( \WC_Order $order, array $data ): void {
		// Only format if plugin is enabled and format on save is enabled.
		if ( ! Settings::is_validation_enabled() || ! Settings::is_format_on_save_enabled() ) {
			return;
		}

		$billing_phone = $order->get_billing_phone();

		// Skip if phone is empty.
		if ( '' === $billing_phone ) {
			return;
		}

		// Get billing country for validation context.
		$billing_country = $order->get_billing_country();
		$country_code    = ! empty( $billing_country ) ? $billing_country : null;

		if ( ! Hooks::should_validate( ErrorMessages::FIELD_BILLING, $data ) ) {
			return;
		}

		// Validate and format the phone number.
		$result = PhoneValidator::validate( $billing_phone, $country_code );

		if ( $result->is_valid() ) {
			$formatted = Hooks::format_for_order( $result->get_phone_number(), ErrorMessages::FIELD_BILLING, $order );
			$order->set_billing_phone( $formatted );
		}
	}
}
