<?php

namespace Shift64\SmartPhoneValidation\Tests\Unit;

use libphonenumber\PhoneNumber;
use Shift64\SmartPhoneValidation\Checkout\ErrorMessages;
use Shift64\SmartPhoneValidation\Checkout\Hooks;
use Shift64\SmartPhoneValidation\Formatter\PhoneFormatter;
use Shift64\SmartPhoneValidation\Validation\PhoneValidator;
use Shift64\SmartPhoneValidation\Validation\ValidationResult;

use function Shift64\SmartPhoneValidation\format_phone;

class HooksTest extends TestCase {

	public function test_error_message_filter_receives_code_field_and_context(): void {
		$received = array();
		$this->add_filter(
			'shift64_phone_validation_error_message',
			function ( $message, $code, $field, $context ) use ( &$received ) {
				$received = array( $message, $code, $field, $context );
				return 'Podaj poprawny numer telefonu.';
			}
		);

		$message = ErrorMessages::for_classic_checkout( ValidationResult::ERROR_TOO_SHORT, ErrorMessages::FIELD_BILLING );

		$this->assertSame( 'Podaj poprawny numer telefonu.', $message );
		$this->assertSame( array( 'Please enter a valid phone number.', 'too_short', 'billing', 'classic' ), $received );
	}

	public function test_error_message_filter_cannot_blank_the_message(): void {
		$this->add_filter( 'shift64_phone_validation_error_message', __NAMESPACE__ . '\__return_empty_string_stub' );

		$this->assertSame(
			'Please enter a valid phone number.',
			ErrorMessages::for_classic_checkout( ValidationResult::ERROR_INVALID_NUMBER, ErrorMessages::FIELD_BILLING )
		);
	}

	/**
	 * The block script recognises notices by wording, so the list handed to JS
	 * must contain the reworded messages, not the defaults.
	 */
	public function test_messages_handed_to_block_script_are_filtered_too(): void {
		$this->add_filter(
			'shift64_phone_validation_error_message',
			function ( $message, $code, $field, $context ) {
				return "[$context/$field/$code]";
			}
		);

		$this->assertSame(
			array(
				'billing'  => array(
					'[block/billing/missing_international_prefix]',
					'[block/billing/invalid_number]',
					'[block/billing/empty]',
					'[block/billing/invalid_country_code]',
					'[block/billing/not_a_number]',
					'[block/billing/too_short]',
					'[block/billing/too_long]',
					'[block/billing/parse_error]',
				),
				'shipping' => array(
					'[block/shipping/missing_international_prefix]',
					'[block/shipping/invalid_number]',
					'[block/shipping/empty]',
					'[block/shipping/invalid_country_code]',
					'[block/shipping/not_a_number]',
					'[block/shipping/too_short]',
					'[block/shipping/too_long]',
					'[block/shipping/parse_error]',
				),
			),
			ErrorMessages::all_for_block_checkout()
		);
	}

	public function test_should_validate_defaults_to_true_and_can_be_switched_off_per_field(): void {
		$this->assertTrue( Hooks::should_validate( ErrorMessages::FIELD_BILLING, array() ) );

		$this->add_filter(
			'shift64_phone_validation_should_validate',
			function ( $validate, $field, $context ) {
				return 'shipping' !== $field && empty( $context['is_staff_order'] );
			}
		);

		$this->assertTrue( Hooks::should_validate( ErrorMessages::FIELD_BILLING, array() ) );
		$this->assertFalse( Hooks::should_validate( ErrorMessages::FIELD_SHIPPING, array() ) );
		$this->assertFalse( Hooks::should_validate( ErrorMessages::FIELD_BILLING, array( 'is_staff_order' => true ) ) );
	}

	public function test_formatted_phone_uses_setting_and_can_be_overridden(): void {
		$number = PhoneValidator::validate( '600 100 200', 'PL' )->get_phone_number();
		$order  = new \stdClass();

		$this->assertSame( '+48600100200', Hooks::format_for_order( $number, ErrorMessages::FIELD_BILLING, $order ) );

		$this->add_filter(
			'shift64_phone_validation_formatted_phone',
			function ( $formatted, PhoneNumber $parsed, $field, $passed_order ) use ( $order ) {
				$this->assertSame( $order, $passed_order );
				return PhoneFormatter::format_to( $parsed, PhoneFormatter::FORMAT_NATIONAL );
			}
		);

		$this->assertSame( '600 100 200', Hooks::format_for_order( $number, ErrorMessages::FIELD_BILLING, $order ) );
	}

	public function test_format_phone_helper(): void {
		$this->assertSame( '+48600100200', format_phone( '600 100 200', 'PL' ) );
		$this->assertSame( '600 100 200', format_phone( '+48600100200', null, PhoneFormatter::FORMAT_NATIONAL ) );
		$this->assertSame( '+48 600 100 200', format_phone( '0048 600-100-200', 'DE', PhoneFormatter::FORMAT_INTERNATIONAL ) );
		$this->assertNull( format_phone( '12345', 'PL' ) );
		$this->assertNull( format_phone( '', 'PL' ) );
	}
}

function __return_empty_string_stub() {
	return '';
}
