<?php
/**
 * User-facing checkout error messages.
 *
 * @package Shift64\SmartPhoneValidation\Checkout
 */

declare(strict_types=1);

namespace Shift64\SmartPhoneValidation\Checkout;

use Shift64\SmartPhoneValidation\Validation\ValidationResult;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps validation error codes to messages shown to the customer.
 *
 * Single place for the code → message mapping, shared by classic and block checkout.
 * The mapping is keyed on ValidationResult::ERROR_* codes, so it works in every locale.
 */
class ErrorMessages {

	/**
	 * Billing phone field.
	 *
	 * @var string
	 */
	const FIELD_BILLING = 'billing';

	/**
	 * Shipping phone field.
	 *
	 * @var string
	 */
	const FIELD_SHIPPING = 'shipping';

	/**
	 * Classic (shortcode) checkout.
	 *
	 * @var string
	 */
	const CONTEXT_CLASSIC = 'classic';

	/**
	 * Block checkout (Store API).
	 *
	 * @var string
	 */
	const CONTEXT_BLOCK = 'block';

	/**
	 * Every code a failed validation can carry, so a theme may reword any of them.
	 *
	 * @var string[]
	 */
	private const ERROR_CODES = array(
		ValidationResult::ERROR_MISSING_INTERNATIONAL_PREFIX,
		ValidationResult::ERROR_INVALID_NUMBER,
		ValidationResult::ERROR_EMPTY,
		ValidationResult::ERROR_INVALID_COUNTRY_CODE,
		ValidationResult::ERROR_NOT_A_NUMBER,
		ValidationResult::ERROR_TOO_SHORT,
		ValidationResult::ERROR_TOO_LONG,
		ValidationResult::ERROR_PARSE_ERROR,
	);

	/**
	 * Get the message for classic checkout notices.
	 *
	 * @param string|null $error_code One of the ValidationResult::ERROR_* constants.
	 * @param string      $field      FIELD_BILLING or FIELD_SHIPPING.
	 * @return string The translated message.
	 */
	public static function for_classic_checkout( ?string $error_code, string $field ): string {
		$is_missing_prefix = ValidationResult::ERROR_MISSING_INTERNATIONAL_PREFIX === $error_code;

		if ( self::FIELD_SHIPPING === $field ) {
			$message = $is_missing_prefix
				? __( 'Shipping phone number must contain country prefix.', 'shift64-phone-validation' )
				: __( 'Please enter a valid shipping phone number.', 'shift64-phone-validation' );
		} else {
			$message = $is_missing_prefix
				? __( 'Phone number must contain country prefix.', 'shift64-phone-validation' )
				: __( 'Please enter a valid phone number.', 'shift64-phone-validation' );
		}

		return self::filter( $message, $error_code, $field, self::CONTEXT_CLASSIC );
	}

	/**
	 * All block checkout messages grouped by field, in the current locale.
	 *
	 * Handed to the block checkout script so it can recognise our notices without
	 * hardcoding any language: PHP stays the single source of the wording.
	 * Every error code is included, so a theme that rewords any of them keeps
	 * the field highlight (#35); identical messages are listed once.
	 *
	 * @return array<string, string[]> Messages keyed by FIELD_BILLING / FIELD_SHIPPING.
	 */
	public static function all_for_block_checkout(): array {
		$messages = array();

		foreach ( array( self::FIELD_BILLING, self::FIELD_SHIPPING ) as $field ) {
			$field_messages = array();

			foreach ( self::ERROR_CODES as $error_code ) {
				$field_messages[] = self::for_block_checkout( $error_code, $field );
			}

			$messages[ $field ] = array_values( array_unique( $field_messages ) );
		}

		return $messages;
	}

	/**
	 * Get the message for block checkout (Store API) responses.
	 *
	 * Block checkout shows errors in a generic banner, so the message names the field.
	 *
	 * @param string|null $error_code One of the ValidationResult::ERROR_* constants.
	 * @param string      $field      FIELD_BILLING or FIELD_SHIPPING.
	 * @return string The translated message.
	 */
	public static function for_block_checkout( ?string $error_code, string $field ): string {
		$field_label = self::FIELD_SHIPPING === $field
			? __( 'Shipping phone', 'shift64-phone-validation' )
			: __( 'Billing phone', 'shift64-phone-validation' );

		if ( ValidationResult::ERROR_MISSING_INTERNATIONAL_PREFIX === $error_code ) {
			/* translators: %s: field label (Billing phone or Shipping phone) */
			$message = sprintf( __( '%s must contain country prefix.', 'shift64-phone-validation' ), $field_label );
		} else {
			/* translators: %s: field label (Billing phone or Shipping phone) */
			$message = sprintf( __( '%s is not a valid phone number.', 'shift64-phone-validation' ), $field_label );
		}

		return self::filter( $message, $error_code, $field, self::CONTEXT_BLOCK );
	}

	/**
	 * Let themes reword a customer-facing message.
	 *
	 * @param string      $message    Default translated message.
	 * @param string|null $error_code One of the ValidationResult::ERROR_* constants.
	 * @param string      $field      FIELD_BILLING or FIELD_SHIPPING.
	 * @param string      $context    CONTEXT_CLASSIC or CONTEXT_BLOCK.
	 * @return string
	 */
	private static function filter( string $message, ?string $error_code, string $field, string $context ): string {
		/**
		 * Filters the validation message shown to the customer.
		 *
		 * @param string      $message    Default translated message.
		 * @param string|null $error_code Machine-readable reason, see ValidationResult::ERROR_*.
		 * @param string      $field      'billing' or 'shipping'.
		 * @param string      $context    'classic' or 'block'.
		 */
		$filtered = apply_filters( 'shift64_phone_validation_error_message', $message, $error_code, $field, $context );

		return is_string( $filtered ) && '' !== $filtered ? $filtered : $message;
	}
}
