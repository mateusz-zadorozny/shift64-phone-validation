<?php

namespace Shift64\SmartPhoneValidation\Tests\Unit;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Shift64\SmartPhoneValidation\Checkout\BlockCheckoutValidator;

class BlockCheckoutValidatorTest extends TestCase {

	public function test_invalid_billing_phone_rejects_the_place_order_request(): void {
		$order = new \WC_Order( array( 'billing_phone' => '1234' ) );

		try {
			BlockCheckoutValidator::validate_on_update_order( $order, new \WP_REST_Request( 'POST' ) );
			$this->fail( 'RouteException expected.' );
		} catch ( RouteException $e ) {
			$this->assertSame( 'invalid_billing_phone', $e->error_code );
			$this->assertSame( 400, $e->getCode() );
			$this->assertSame( 'Billing phone is not a valid phone number.', $e->getMessage() );
			$this->assertSame( array( 'field' => 'billing_phone', 'code' => 'invalid_number' ), $e->additional_data );
		}

		$this->assertSame( '1234', $order->data['billing_phone'] );
	}

	/**
	 * The hook also fires for PUT/PATCH while the customer is typing - a half-typed
	 * number must not be rejected (or reformatted under their fingers).
	 *
	 * @dataProvider non_post_methods
	 */
	public function test_form_interaction_requests_are_ignored( string $method ): void {
		$order = new \WC_Order( array( 'billing_phone' => '60' ) );

		BlockCheckoutValidator::validate_on_update_order( $order, new \WP_REST_Request( $method ) );

		$this->assertSame( '60', $order->data['billing_phone'] );
	}

	public function non_post_methods(): array {
		return array( array( 'PATCH' ), array( 'PUT' ) );
	}

	public function test_valid_phones_are_formatted_and_woocommerce_does_the_saving(): void {
		$order = new \WC_Order(
			array(
				'billing_phone'  => '600 100 200',
				'shipping_phone' => '0048 22 410 05 00',
			)
		);

		BlockCheckoutValidator::validate_on_update_order( $order, new \WP_REST_Request( 'POST' ) );

		$this->assertSame( '+48600100200', $order->data['billing_phone'] );
		$this->assertSame( '+48224100500', $order->data['shipping_phone'] );
		$this->assertSame( 0, $order->saves );
	}

	public function test_legacy_hook_saves_only_when_something_changed(): void {
		$changed = new \WC_Order( array( 'billing_phone' => '600 100 200' ) );
		BlockCheckoutValidator::validate_on_order_processed( $changed );
		$this->assertSame( 1, $changed->saves );

		$already_formatted = new \WC_Order( array( 'billing_phone' => '+48600100200' ) );
		BlockCheckoutValidator::validate_on_order_processed( $already_formatted );
		$this->assertSame( 0, $already_formatted->saves );

		$this->set_option( 'format_on_save', 'no' );
		$not_formatting = new \WC_Order( array( 'billing_phone' => '600 100 200' ) );
		BlockCheckoutValidator::validate_on_order_processed( $not_formatting );
		$this->assertSame( 0, $not_formatting->saves );
		$this->assertSame( '600 100 200', $not_formatting->data['billing_phone'] );
	}

	public function test_empty_phones_and_disabled_plugin_are_skipped(): void {
		$this->assertFalse( BlockCheckoutValidator::validate_phones( new \WC_Order() ) );

		$this->set_option( 'enabled', 'no' );
		$this->assertFalse( BlockCheckoutValidator::validate_phones( new \WC_Order( array( 'billing_phone' => '1234' ) ) ) );
	}

	public function test_invalid_shipping_phone_is_reported_for_the_shipping_field(): void {
		$order = new \WC_Order(
			array(
				'billing_phone'  => '600 100 200',
				'shipping_phone' => '1234',
			)
		);

		try {
			BlockCheckoutValidator::validate_phones( $order );
			$this->fail( 'RouteException expected.' );
		} catch ( RouteException $e ) {
			$this->assertSame( 'invalid_shipping_phone', $e->error_code );
			$this->assertSame( 'shipping_phone', $e->additional_data['field'] );
		}
	}

	/**
	 * Regression test for #32. Switching the locale is all it takes: WordPress loads the
	 * text domain for the switched locale just in time. Unloading it by hand blocked
	 * just-in-time loading and, since WordPress 6.7, left the message in English. There is
	 * no unload_textdomain(), load_textdomain() or load_plugin_textdomain() stub, so
	 * calling any of them again fails this test.
	 */
	public function test_message_is_built_in_the_switched_locale_and_the_locale_is_restored(): void {
		$_SERVER['HTTP_REFERER'] = 'https://shop.test/pl/zamowienie/';
		$this->use_polish();
		$built_in = $this->record_locale_of_messages();

		$this->assertSame( 'Telefon do płatności nie jest prawidłowym numerem telefonu.', $this->rejection_message() );
		$this->assertSame( array( 'pl_PL' ), $built_in->getArrayCopy() );
		$this->assertSame( array( 'switch_to_locale pl_PL', 'restore_previous_locale' ), $GLOBALS['shift64_test_locale']['calls'] );
		$this->assertSame( 'en_US', get_locale() );
		$this->assertSame( array(), $GLOBALS['shift64_test_locale']['switches'] );
	}

	/**
	 * Regression test for #32: on a site already in the customer's language (the plain
	 * pl_PL store) nothing is switched and the loaded translations are left alone.
	 */
	public function test_site_language_is_used_without_switching_or_reloading(): void {
		$GLOBALS['shift64_test_locale']['current'] = 'pl_PL';
		$this->use_polish();
		$built_in = $this->record_locale_of_messages();

		$this->assertSame( 'Telefon do płatności nie jest prawidłowym numerem telefonu.', $this->rejection_message() );
		$this->assertSame( array( 'pl_PL' ), $built_in->getArrayCopy() );
		$this->assertSame( array(), $GLOBALS['shift64_test_locale']['calls'] );
	}

	/**
	 * Message of the RouteException an invalid billing phone causes.
	 */
	private function rejection_message(): string {
		try {
			BlockCheckoutValidator::validate_phones( new \WC_Order( array( 'billing_phone' => '1234' ) ) );
		} catch ( RouteException $e ) {
			return $e->getMessage();
		} finally {
			unset( $_SERVER['HTTP_REFERER'] );
		}

		$this->fail( 'RouteException expected.' );
	}

	/**
	 * Record the active locale each time a message is built (the message filter runs
	 * inside ErrorMessages, right after translation).
	 */
	private function record_locale_of_messages(): \ArrayObject {
		$locales = new \ArrayObject();

		$this->add_filter(
			'shift64_phone_validation_error_message',
			static function ( $message ) use ( $locales ) {
				$locales[] = get_locale();
				return $message;
			}
		);

		return $locales;
	}

	/**
	 * @dataProvider referers
	 */
	public function test_locale_from_referer( string $referer, string $home, ?string $expected ): void {
		$this->assertSame( $expected, BlockCheckoutValidator::locale_from_referer( $referer, $home, array( 'pl_PL', 'de_DE' ) ) );
	}

	public function referers(): array {
		return array(
			'language directory'             => array( 'https://shop.test/pl/zamowienie/', 'https://shop.test/', 'pl_PL' ),
			'language directory only'        => array( 'https://shop.test/de', 'https://shop.test/', 'de_DE' ),
			'english needs no language pack' => array( 'https://shop.test/en/checkout/', 'https://shop.test/', 'en_US' ),
			'wordpress in a subdirectory'    => array( 'https://shop.test/sklep/pl/zamowienie/', 'https://shop.test/sklep/', 'pl_PL' ),
			'two letters deeper in the path' => array( 'https://shop.test/shop/it/checkout/', 'https://shop.test/', null ),
			'language not installed'         => array( 'https://shop.test/it/checkout/', 'https://shop.test/', null ),
			'no language directory'          => array( 'https://shop.test/zamowienie/', 'https://shop.test/', null ),
			'query string is not a path'     => array( 'https://shop.test/checkout/?lang=pl', 'https://shop.test/', null ),
			'empty referer'                  => array( '', 'https://shop.test/', null ),
		);
	}

	/**
	 * A store whose German is the formal variant gets formal messages on its /de/ pages.
	 *
	 * @dataProvider german_variant_referers
	 */
	public function test_locale_from_referer_picks_the_german_variant( array $installed, string $site_locale, ?string $expected ): void {
		$this->assertSame( $expected, BlockCheckoutValidator::locale_from_referer( 'https://shop.test/de/kasse/', 'https://shop.test/', $installed, $site_locale ) );
	}

	public function german_variant_referers(): array {
		return array(
			'only formal installed'                 => array( array( 'pl_PL', 'de_DE_formal' ), 'pl_PL', 'de_DE_formal' ),
			'both installed, base locale wins'      => array( array( 'de_DE', 'de_DE_formal' ), 'en_US', 'de_DE' ),
			'both installed in any order'           => array( array( 'de_DE_formal', 'de_DE' ), 'en_US', 'de_DE' ),
			'both installed, site is formal'        => array( array( 'de_DE', 'de_DE_formal' ), 'de_DE_formal', 'de_DE_formal' ),
			'site is formal, nothing else'          => array( array(), 'de_DE_formal', 'de_DE_formal' ),
			'no german installed'                   => array( array( 'pl_PL' ), 'pl_PL', null ),
			'a lookalike locale is not a variant'   => array( array( 'de_DEX' ), 'en_US', null ),
		);
	}

	/**
	 * Through the referer the site locale is passed in: the formal store keeps "Sie".
	 */
	public function test_formal_site_keeps_formal_german_on_a_de_page(): void {
		$GLOBALS['shift64_test_locale']['current'] = 'de_DE_formal';
		$_SERVER['HTTP_REFERER']                   = 'https://shop.test/de/kasse/';
		$built_in                                  = $this->record_locale_of_messages();

		$this->rejection_message();

		$this->assertSame( array( 'de_DE_formal' ), $built_in->getArrayCopy() );
		$this->assertSame( array(), $GLOBALS['shift64_test_locale']['calls'] );
	}

	/**
	 * @dataProvider wpml_languages
	 */
	public function test_locale_from_wpml( $code, $languages, ?string $expected ): void {
		$this->assertSame( $expected, BlockCheckoutValidator::locale_from_wpml( $code, $languages, array( 'pl_PL', 'de_DE', 'de_DE_formal' ) ) );
	}

	/**
	 * WordPress cannot switch to a locale that is not installed: WPML's setting then
	 * gives way to the plain language, which is.
	 */
	public function test_wpml_locale_that_is_not_installed_falls_back_to_the_language(): void {
		$languages = array( 'de' => array( 'default_locale' => 'de_DE_formal' ) );

		$this->assertSame( 'de_DE', BlockCheckoutValidator::locale_from_wpml( 'de', $languages, array( 'de_DE' ) ) );
	}

	/**
	 * End to end through get_current_locale(): the WPML language list is asked for
	 * every language (no queried object in a Store API request) and its locale is used.
	 */
	public function test_wpml_formal_german_reaches_the_message(): void {
		$asked = new \ArrayObject();
		$this->add_filter( 'wpml_current_language', static fn () => 'de' );
		$this->add_filter(
			'wpml_active_languages',
			static function ( $value, $args ) use ( $asked ) {
				$asked[] = $args;
				return array( 'de' => array( 'default_locale' => 'de_DE_formal' ) );
			}
		);
		$GLOBALS['shift64_test_available_languages'] = array( 'de_DE', 'de_DE_formal' );
		$built_in                                    = $this->record_locale_of_messages();

		$this->rejection_message();

		$this->assertSame( array( 'de_DE_formal' ), $built_in->getArrayCopy() );
		$this->assertSame( array( array( 'skip_missing' => 0 ) ), $asked->getArrayCopy() );
	}

	public function wpml_languages(): array {
		$formal = array(
			'de' => array( 'code' => 'de', 'default_locale' => 'de_DE_formal' ),
			'pl' => array( 'code' => 'pl', 'default_locale' => 'pl_PL' ),
		);

		return array(
			'locale configured in wpml'   => array( 'de', $formal, 'de_DE_formal' ),
			'english needs no pack'       => array( 'en', array( 'en' => array( 'default_locale' => 'en_US' ) ), 'en_US' ),
			'other configured language'   => array( 'pl', $formal, 'pl_PL' ),
			'no active language list'     => array( 'de', null, 'de_DE' ),
			'language missing from list'  => array( 'fr', $formal, 'fr_FR' ),
			'empty locale falls back'     => array( 'de', array( 'de' => array( 'default_locale' => '' ) ), 'de_DE' ),
			'unknown code'                => array( 'xx', array(), null ),
			'no current language'         => array( null, $formal, null ),
		);
	}
}
