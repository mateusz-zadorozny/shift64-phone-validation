<?php
/**
 * Phone number validator using libphonenumber.
 *
 * @package Shift64\SmartPhoneValidation\Validation
 */

declare(strict_types=1);

namespace Shift64\SmartPhoneValidation\Validation;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Shift64\SmartPhoneValidation\Admin\Settings;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates phone numbers using libphonenumber.
 */
class PhoneValidator {

	/**
	 * Validation mode constant for international only.
	 *
	 * @var string
	 */
	const MODE_INTERNATIONAL_ONLY = 'international_only';

	/**
	 * Validate a phone number.
	 *
	 * @param string      $phone_number The phone number to validate.
	 * @param string|null $country_code Optional ISO-2 country code override.
	 * @return ValidationResult The validation result.
	 */
	public static function validate( string $phone_number, ?string $country_code = null ): ValidationResult {
		// Normalize the input first.
		$normalized = Normalizer::normalize( $phone_number );

		// Check if empty after normalization.
		if ( '' === $normalized ) {
			return ValidationResult::failure(
				ValidationResult::ERROR_EMPTY,
				__( 'Phone number cannot be empty.', 'shift64-phone-validation' )
			);
		}

		// Check if number is international (starts with '+').
		$is_international = self::is_international_number( $normalized );

		// Get validation mode from settings.
		$validation_mode = Settings::get_validation_mode();

		// In 'International only' mode, reject numbers without '+' prefix.
		if ( self::MODE_INTERNATIONAL_ONLY === $validation_mode && ! $is_international ) {
			return ValidationResult::failure(
				ValidationResult::ERROR_MISSING_INTERNATIONAL_PREFIX,
				__( 'Phone number must include international prefix (+).', 'shift64-phone-validation' )
			);
		}

		// Determine the country code to use for parsing.
		$default_country = $country_code ?? Settings::get_default_country();

		// For international numbers, country code is not needed for parsing.
		$region_for_parsing = $is_international ? null : $default_country;

		// Parse and validate using libphonenumber.
		$util = PhoneNumberUtil::getInstance();

		try {
			$parsed_number = $util->parse( $normalized, $region_for_parsing );

			if ( ! $util->isValidNumber( $parsed_number ) ) {
				return ValidationResult::failure(
					ValidationResult::ERROR_INVALID_NUMBER,
					__( 'The phone number is not valid.', 'shift64-phone-validation' )
				);
			}

			return ValidationResult::success( $parsed_number );
		} catch ( NumberParseException $e ) {
			return self::handle_parse_exception( $e );
		}
	}

	/**
	 * Check if a phone number is international (starts with '+').
	 *
	 * @param string $phone_number The normalized phone number.
	 * @return bool True if the number starts with '+'.
	 */
	public static function is_international_number( string $phone_number ): bool {
		return 0 === strpos( $phone_number, '+' );
	}

	/**
	 * Handle NumberParseException and return appropriate error message.
	 *
	 * @param NumberParseException $exception The parse exception.
	 * @return ValidationResult The failure result with appropriate message.
	 */
	private static function handle_parse_exception( NumberParseException $exception ): ValidationResult {
		$error_type = $exception->getErrorType();

		switch ( $error_type ) {
			case NumberParseException::INVALID_COUNTRY_CODE:
				$code    = ValidationResult::ERROR_INVALID_COUNTRY_CODE;
				$message = __( 'Invalid country code.', 'shift64-phone-validation' );
				break;
			case NumberParseException::NOT_A_NUMBER:
				$code    = ValidationResult::ERROR_NOT_A_NUMBER;
				$message = __( 'The input does not appear to be a phone number.', 'shift64-phone-validation' );
				break;
			case NumberParseException::TOO_SHORT_AFTER_IDD:
				$code    = ValidationResult::ERROR_TOO_SHORT;
				$message = __( 'Phone number is too short after country code.', 'shift64-phone-validation' );
				break;
			case NumberParseException::TOO_SHORT_NSN:
				$code    = ValidationResult::ERROR_TOO_SHORT;
				$message = __( 'Phone number is too short.', 'shift64-phone-validation' );
				break;
			case NumberParseException::TOO_LONG:
				$code    = ValidationResult::ERROR_TOO_LONG;
				$message = __( 'Phone number is too long.', 'shift64-phone-validation' );
				break;
			default:
				$code    = ValidationResult::ERROR_PARSE_ERROR;
				$message = __( 'Unable to parse phone number.', 'shift64-phone-validation' );
				break;
		}

		return ValidationResult::failure( $code, $message );
	}
}
