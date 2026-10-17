<?php
/**
 * Block checkout phone validation for WooCommerce Store API.
 *
 * @package Shift64\SmartPhoneValidation\Checkout
 */

declare(strict_types=1);

namespace Shift64\SmartPhoneValidation\Checkout;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Shift64\SmartPhoneValidation\Admin\Settings;
use Shift64\SmartPhoneValidation\Validation\PhoneValidator;
use WC_Order;
use WP_REST_Request;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles phone validation and formatting for WooCommerce block checkout (Store API).
 */
class BlockCheckoutValidator {

	/**
	 * Hook fired while the Store API copies request data onto the order (WooCommerce 7.2+).
	 *
	 * @var string
	 */
	const HOOK_UPDATE_ORDER = 'woocommerce_store_api_checkout_update_order_from_request';

	/**
	 * Legacy hook fired after the order was fully processed. Used before WooCommerce 7.2.
	 *
	 * @var string
	 */
	const HOOK_ORDER_PROCESSED = 'woocommerce_store_api_checkout_order_processed';

	/**
	 * Two-letter language codes (as used in URLs by multilingual plugins) to locales.
	 *
	 * @var array<string, string>
	 */
	const LOCALE_MAP = array(
		'pl' => 'pl_PL',
		'en' => 'en_US',
		'de' => 'de_DE',
		'fr' => 'fr_FR',
		'es' => 'es_ES',
		'it' => 'it_IT',
		'nl' => 'nl_NL',
		'pt' => 'pt_PT',
		'ru' => 'ru_RU',
	);

	/**
	 * Initialize block checkout validation hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Only register if Store API is available.
		if ( ! class_exists( 'Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			return;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.2', '>=' ) ) {
			// Validate as early as possible: before customer data is synced and payment is attempted.
			add_action( self::HOOK_UPDATE_ORDER, array( self::class, 'validate_on_update_order' ), 10, 2 );
			return;
		}

		add_action( self::HOOK_ORDER_PROCESSED, array( self::class, 'validate_on_order_processed' ), 10, 1 );
	}

	/**
	 * Validate when the Store API updates the order from the request.
	 *
	 * The hook also fires for PUT/PATCH requests sent while the customer is still
	 * filling in the form. Only the place-order request (POST) is validated, otherwise
	 * a half-typed number would be rejected. WooCommerce saves the order itself.
	 *
	 * @param WC_Order        $order   The order object.
	 * @param WP_REST_Request $request The Store API request.
	 * @return void
	 * @throws RouteException When phone validation fails.
	 */
	public static function validate_on_update_order( $order, $request ): void {
		if ( ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
			return;
		}

		self::validate_phones( $order );
	}

	/**
	 * Validate on the legacy hook, where the order has to be saved by us.
	 *
	 * @param WC_Order $order The order object.
	 * @return void
	 * @throws RouteException When phone validation fails.
	 */
	public static function validate_on_order_processed( $order ): void {
		if ( self::validate_phones( $order ) ) {
			$order->save();
		}
	}

	/**
	 * Validate billing and shipping phone numbers and format the valid ones.
	 *
	 * @param WC_Order $order The order object.
	 * @return bool Whether a phone number on the order was changed.
	 * @throws RouteException When phone validation fails.
	 */
	public static function validate_phones( WC_Order $order ): bool {
		// Only validate if plugin is enabled.
		if ( ! Settings::is_validation_enabled() ) {
			return false;
		}

		$changed = false;

		foreach ( array( ErrorMessages::FIELD_BILLING, ErrorMessages::FIELD_SHIPPING ) as $field ) {
			$phone = (string) $order->{"get_{$field}_phone"}();

			// Both phones are validated only when filled in - required-ness is WooCommerce's job.
			if ( '' === $phone || ! Hooks::should_validate( $field, $order ) ) {
				continue;
			}

			$country = (string) $order->{"get_{$field}_country"}();
			$result  = PhoneValidator::validate( $phone, '' !== $country ? $country : null );

			if ( ! $result->is_valid() ) {
				throw new RouteException(
					esc_html( "invalid_{$field}_phone" ),
					esc_html( self::translated_message( $result->get_error_code(), $field ) ),
					400,
					array(
						'field' => esc_html( "{$field}_phone" ),
						'code'  => esc_html( (string) $result->get_error_code() ),
					)
				);
			}

			if ( ! Settings::is_format_on_save_enabled() ) {
				continue;
			}

			$formatted = Hooks::format_for_order( $result->get_phone_number(), $field, $order );

			if ( $formatted !== $phone ) {
				$order->{"set_{$field}_phone"}( $formatted );
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * Build the error message in the customer's language.
	 *
	 * Store API requests are REST requests: multilingual plugins often do not set the
	 * language there, so the locale is resolved explicitly and restored afterwards.
	 *
	 * Switching is all it takes: after switch_to_locale() and restore_previous_locale()
	 * WordPress loads this plugin's translations for the active locale just in time, from
	 * a language pack in wp-content/languages/plugins when one exists for that locale and
	 * from the bundled languages/ folder (the Domain Path header) otherwise. The text
	 * domain is never unloaded by hand: a plain unload_textdomain() blocks just-in-time
	 * loading for the rest of the request, which since WordPress 6.7 left every message
	 * in English (#32).
	 *
	 * @param string|null $error_code One of the ValidationResult::ERROR_* constants.
	 * @param string      $field      ErrorMessages::FIELD_BILLING or ErrorMessages::FIELD_SHIPPING.
	 * @return string The translated message.
	 */
	private static function translated_message( ?string $error_code, string $field ): string {
		$locale   = self::get_current_locale();
		$switched = get_locale() !== $locale && switch_to_locale( $locale );

		$message = ErrorMessages::for_block_checkout( $error_code, $field );

		if ( $switched ) {
			restore_previous_locale();
		}

		return $message;
	}

	/**
	 * Get current locale with multilingual plugin support.
	 *
	 * Order: what the multilingual plugin says, then the language directory of the
	 * page the request came from, then WordPress itself.
	 *
	 * @return string The current locale.
	 */
	private static function get_current_locale(): string {
		// Try Polylang.
		if ( function_exists( 'pll_current_language' ) ) {
			$pll_locale = pll_current_language( 'locale' );
			if ( $pll_locale ) {
				return (string) $pll_locale;
			}
		}

		// Try WPML.
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML hook.
			$wpml_lang = apply_filters( 'wpml_current_language', null );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML hook.
			$wpml_languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
			$wpml_locale    = self::locale_from_wpml( $wpml_lang, $wpml_languages, get_available_languages() );
			if ( null !== $wpml_locale ) {
				return $wpml_locale;
			}
		}

		// Multilingual plugins may not know the language in REST requests - look at the checkout page URL.
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$locale  = self::locale_from_referer( $referer, (string) home_url( '/' ), get_available_languages(), get_locale() );
		if ( null !== $locale ) {
			return $locale;
		}

		return determine_locale();
	}

	/**
	 * Locale of WPML's current language.
	 *
	 * WPML language codes say nothing about the variant, so the locale set for the
	 * language in WPML (e.g. de_DE_formal for "de") wins over LOCALE_MAP - when it is
	 * installed, since switch_to_locale() cannot switch to anything else.
	 *
	 * @param mixed    $code      Current language code from the wpml_current_language filter.
	 * @param mixed    $languages Active languages from the wpml_active_languages filter, keyed by code.
	 * @param string[] $installed Installed locales, e.g. array( 'pl_PL', 'de_DE_formal' ).
	 * @return string|null Locale or null when WPML gives no usable language.
	 */
	public static function locale_from_wpml( $code, $languages, array $installed ): ?string {
		if ( ! is_string( $code ) || '' === $code ) {
			return null;
		}

		$configured = is_array( $languages ) && isset( $languages[ $code ]['default_locale'] ) ? $languages[ $code ]['default_locale'] : '';
		if ( is_string( $configured ) && ( 'en_US' === $configured || in_array( $configured, $installed, true ) ) ) {
			return $configured;
		}

		return self::LOCALE_MAP[ $code ] ?? null;
	}

	/**
	 * Read the language directory (/pl/, /de/...) from the referring page URL.
	 *
	 * Only the first path segment after the site's home path counts, and only when that
	 * language is installed - so "/shop/it/" or a product slug never flips the language.
	 * A language with variants ("/de/": de_DE, de_DE_formal) resolves to the site's own
	 * locale when it is one of them, otherwise to the first one installed.
	 *
	 * @param string   $referer     Referring URL.
	 * @param string   $home_url    Site home URL.
	 * @param string[] $installed   Installed locales, e.g. array( 'pl_PL', 'de_DE' ).
	 * @param string   $site_locale The site's locale, e.g. de_DE_formal.
	 * @return string|null Locale or null when the URL carries no usable language.
	 */
	public static function locale_from_referer( string $referer, string $home_url, array $installed, string $site_locale = '' ): ?string {
		$path      = (string) wp_parse_url( $referer, PHP_URL_PATH );
		$home_path = rtrim( (string) wp_parse_url( $home_url, PHP_URL_PATH ), '/' );

		if ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		$segments = explode( '/', ltrim( $path, '/' ) );
		$code     = strtolower( $segments[0] );

		if ( ! isset( self::LOCALE_MAP[ $code ] ) ) {
			return null;
		}

		$locale = self::LOCALE_MAP[ $code ];

		// Variants are named after the base locale: de_DE_formal, nl_NL_formal...
		$is_variant = static fn ( string $candidate ): bool => $candidate === $locale || 0 === strpos( $candidate, $locale . '_' );

		if ( '' !== $site_locale && $is_variant( $site_locale ) ) {
			return $site_locale;
		}

		// en_US ships with WordPress and is never listed as installed.
		if ( 'en_US' === $locale || in_array( $locale, $installed, true ) ) {
			return $locale;
		}

		$variants = array_values( array_filter( $installed, $is_variant ) );

		return $variants[0] ?? null;
	}
}
