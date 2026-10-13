<?php
/**
 * Shipping phone validation for WooCommerce checkout.
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
 * Handles shipping phone validation and formatting during WooCommerce checkout.
 */
class ShippingPhoneValidator {

	/**
	 * Initialize checkout validation hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Validate shipping phone during checkout with field-specific errors.
		add_action( 'woocommerce_after_checkout_validation', array( self::class, 'validate_shipping_phone' ), 10, 2 );

		// Format shipping phone before saving order.
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'format_shipping_phone_on_order' ), 10, 2 );
	}

	/**
	 * Validate shipping phone number during checkout.
	 *
	 * @param array<string, mixed> $data   Checkout posted data.
	 * @param \WP_Error            $errors Validation errors.
	 * @return void
	 */
	public static function validate_shipping_phone( array $data, \WP_Error $errors ): void {
		// Only validate if plugin is enabled.
		if ( ! Settings::is_validation_enabled() ) {
			return;
		}

		// Without "ship to a different address" WooCommerce fills shipping_* with copies
		// of billing_* - the customer never saw a shipping phone field, and the billing
		// validator already reports the number. Validating the copy doubles the notice.
		if ( empty( $data['ship_to_different_address'] ) ) {
			return;
		}

		// Get shipping phone from posted data.
		$shipping_phone = isset( $data['shipping_phone'] ) ? $data['shipping_phone'] : '';

		// Skip validation if phone is empty (shipping phone is optional).
		if ( '' === $shipping_phone ) {
			return;
		}

		// Get country for validation context.
		$shipping_country = isset( $data['shipping_country'] ) ? $data['shipping_country'] : null;

		// Use shipping country if provided, otherwise use default from settings.
		$country_code = ! empty( $shipping_country ) ? $shipping_country : null;

		if ( ! Hooks::should_validate( ErrorMessages::FIELD_SHIPPING, $data ) ) {
			return;
		}

		// Validate the phone number.
		$result = PhoneValidator::validate( $shipping_phone, $country_code );

		if ( ! $result->is_valid() ) {
			$error_message = ErrorMessages::for_classic_checkout( $result->get_error_code(), ErrorMessages::FIELD_SHIPPING );
			$errors->add( 'shipping_phone_validation', $error_message, array( 'id' => 'shipping_phone' ) );
		}
	}

	/**
	 * Format shipping phone number before saving to order.
	 *
	 * @param \WC_Order            $order    The order object.
	 * @param array<string, mixed> $data     The checkout data.
	 * @return void
	 */
	public static function format_shipping_phone_on_order( \WC_Order $order, array $data ): void {
		// Only format if plugin is enabled and format on save is enabled.
		if ( ! Settings::is_validation_enabled() || ! Settings::is_format_on_save_enabled() ) {
			return;
		}

		$shipping_phone = $order->get_shipping_phone();

		// Skip if phone is empty.
		if ( '' === $shipping_phone ) {
			return;
		}

		// Get shipping country for validation context.
		$shipping_country = $order->get_shipping_country();
		$country_code     = ! empty( $shipping_country ) ? $shipping_country : null;

		if ( ! Hooks::should_validate( ErrorMessages::FIELD_SHIPPING, $data ) ) {
			return;
		}

		// Validate and format the phone number.
		$result = PhoneValidator::validate( $shipping_phone, $country_code );

		if ( $result->is_valid() ) {
			$formatted = Hooks::format_for_order( $result->get_phone_number(), ErrorMessages::FIELD_SHIPPING, $order );
			$order->set_shipping_phone( $formatted );
		}
	}
}
