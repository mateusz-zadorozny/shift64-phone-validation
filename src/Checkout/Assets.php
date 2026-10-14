<?php
/**
 * Checkout assets loader.
 *
 * @package Shift64\SmartPhoneValidation\Checkout
 */

declare(strict_types=1);

namespace Shift64\SmartPhoneValidation\Checkout;

use Shift64\SmartPhoneValidation\Admin\Settings;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles loading of checkout-related assets (JS/CSS).
 */
class Assets {

	/**
	 * Initialize assets hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_checkout_scripts' ) );
	}

	/**
	 * Enqueue checkout validation scripts.
	 *
	 * @return void
	 */
	public static function enqueue_checkout_scripts(): void {
		// Only load on checkout page when validation is enabled.
		if ( ! is_checkout() || ! Settings::is_validation_enabled() ) {
			return;
		}

		/**
		 * Filters whether the plugin loads its field-highlighting scripts.
		 *
		 * The scripts are presentation only. Return false when the theme has its own
		 * checkout error UX - server-side validation keeps working without them.
		 *
		 * @param bool $enqueue Whether to enqueue the scripts. Default true.
		 */
		if ( ! apply_filters( 'shift64_phone_validation_enqueue_assets', true ) ) {
			return;
		}

		// Classic checkout script (requires jQuery and wc-checkout).
		if ( ! self::is_block_checkout() ) {
			wp_enqueue_script(
				'shift64-phone-checkout-validation',
				SHIFT64_PHONE_VALIDATION_URL . 'assets/js/checkout-validation.js',
				array( 'jquery', 'wc-checkout' ),
				SHIFT64_PHONE_VALIDATION_VERSION,
				true
			);
		}

		// Block checkout script (vanilla JS). wp-api-fetch lets it read which field a
		// Store API error is about from the checkout response (#52).
		if ( self::is_block_checkout() ) {
			wp_enqueue_script(
				'shift64-phone-block-checkout-validation',
				SHIFT64_PHONE_VALIDATION_URL . 'assets/js/block-checkout-validation.js',
				array( 'wp-api-fetch' ),
				SHIFT64_PHONE_VALIDATION_VERSION,
				true
			);

			// The script recognises our notices by the exact server-side wording.
			wp_add_inline_script(
				'shift64-phone-block-checkout-validation',
				'window.shift64PhoneValidation = ' . wp_json_encode( array( 'messages' => ErrorMessages::all_for_block_checkout() ) ) . ';',
				'before'
			);
		}
	}

	/**
	 * Check if the checkout page being viewed uses block checkout.
	 *
	 * The page being viewed decides, so a store with both a block page and a
	 * [woocommerce_checkout] page loads the right script on each (#34). The
	 * configured checkout page is only the fallback, for a page that holds neither.
	 *
	 * @return bool True if using block checkout.
	 */
	private static function is_block_checkout(): bool {
		// Check if WooCommerce Blocks is available.
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
			return false;
		}

		$current_page = get_queried_object();
		if ( $current_page instanceof \WP_Post ) {
			if ( has_block( 'woocommerce/checkout', $current_page ) ) {
				return true;
			}
			if ( self::has_classic_checkout( $current_page->post_content ) ) {
				return false;
			}
		}

		// Get the checkout page.
		$checkout_page_id = wc_get_page_id( 'checkout' );
		if ( ! $checkout_page_id ) {
			return false;
		}

		$checkout_page = get_post( $checkout_page_id );
		if ( ! $checkout_page ) {
			return false;
		}

		// Check if page contains the checkout block.
		return has_block( 'woocommerce/checkout', $checkout_page );
	}

	/**
	 * Check if content holds the classic checkout: the [woocommerce_checkout]
	 * shortcode, or the editor's "Classic Checkout" block, which is saved as
	 * <!-- wp:woocommerce/classic-shortcode {"shortcode":"checkout"} /-->.
	 *
	 * @param string $content Post content.
	 * @return bool True if the content holds the classic checkout.
	 */
	private static function has_classic_checkout( string $content ): bool {
		if ( has_shortcode( $content, 'woocommerce_checkout' ) ) {
			return true;
		}

		return 1 === preg_match( '#<!--\s+wp:woocommerce/classic-shortcode\s+\{[^}]*"shortcode":"checkout"#', $content );
	}
}
