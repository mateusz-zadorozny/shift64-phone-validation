<?php
/**
 * Plugin Name:     SHIFT64 Phone Validation for WooCommerce
 * Plugin URI:      https://github.com/mateusz-zadorozny/shift64-phone-validation
 * Description:     Validates and formats WooCommerce checkout phone numbers with Google's libphonenumber library.
 * Author:          Mateusz Zadorożny (SHIFT64)
 * Author URI:      https://shift64.com
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     shift64-phone-validation
 * Domain Path:     /languages
 * Version:         2.0.7
 * Requires PHP:    8.3
 * Requires at least: 7.1
 * Requires Plugins: woocommerce
 * WC requires at least: 7.2
 * WC tested up to: 11.1
 *
 * @package Shift64\SmartPhoneValidation
 */

declare(strict_types=1);

namespace Shift64\SmartPhoneValidation;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'SHIFT64_PHONE_VALIDATION_VERSION', '2.0.7' );
define( 'SHIFT64_PHONE_VALIDATION_FILE', __FILE__ );
define( 'SHIFT64_PHONE_VALIDATION_PATH', plugin_dir_path( __FILE__ ) );
define( 'SHIFT64_PHONE_VALIDATION_URL', plugin_dir_url( __FILE__ ) );
// Keep equal to "Requires at least" above and in readme.txt, and to minimum_wp_version in .phpcs.xml.dist.
define( 'SHIFT64_PHONE_VALIDATION_MIN_WP', '7.1' );

// Declare compatibility with WooCommerce features. Orders are only accessed through
// WC_Order CRUD methods, so both order storages work.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

// Load the bundled Composer autoloader (libphonenumber and the plugin classes).
$shift64_autoloader = SHIFT64_PHONE_VALIDATION_PATH . 'vendor/autoload.php';

if ( file_exists( $shift64_autoloader ) ) {
	require_once $shift64_autoloader;

	// Public helpers such as format_phone(). Required here instead of through Composer's
	// "files" autoload: their ABSPATH guard would otherwise stop dev tools that include
	// vendor/autoload.php outside WordPress (PHPUnit, WP-CLI) before they start.
	require_once SHIFT64_PHONE_VALIDATION_PATH . 'src/functions.php';
} else {
	// The vendor/ folder ships inside every release package; it is only missing from an
	// incomplete upload or a source checkout. Tell the people who can fix it, and stop.
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-error"><p>';
			echo esc_html__(
				'SHIFT64 Phone Validation for WooCommerce cannot run because its bundled vendor folder is missing. Reinstall the plugin from WordPress.org or from the release ZIP on GitHub.',
				'shift64-phone-validation'
			);
			echo '</p></div>';
		}
	);
	return;
}

// Open the settings tab once after activation (see Admin\PluginScreen for when it does not).
register_activation_hook( __FILE__, array( Admin\PluginScreen::class, 'on_activation' ) );

// Register the bundled languages/ folder. Since WordPress 6.7 this loads no file; translations
// are still loaded just in time, and a language pack from translate.wordpress.org in
// wp-content/languages/plugins still takes precedence. WordPress 7.1 registers the Domain
// Path header itself only for plugins activated per site, so a network-activated install
// needs this call. Plugin Check reports it as a warning that does not fail the check (accepted).
// Priority 5, ahead of the boot below, so the folder is registered before any string of this
// domain is translated.
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'shift64-phone-validation', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	5
);

// Check the WordPress version and WooCommerce, then boot.
add_action(
	'plugins_loaded',
	function () {
		// WordPress refuses to activate, install or update the plugin below "Requires at least",
		// but some copies skip that check: files copied outside WordPress (FTP, a deploy script,
		// Composer), and updates through the GitHub updater of 1.4.x, which offers the latest
		// GitHub release to any WordPress version (its offer declares "requires" 5.0).
		// Stay inert on an unsupported version instead, and tell the administrator why.
		if ( ! Admin\DependencyChecker::is_wordpress_supported() ) {
			Admin\DependencyChecker::display_wordpress_unsupported_notice();
			return;
		}

		if ( ! Admin\DependencyChecker::is_woocommerce_active() ) {
			Admin\DependencyChecker::display_woocommerce_missing_notice();
			return;
		}

		// Initialize plugin settings.
		Admin\Settings::init();

		// Settings link on the Plugins screen and the redirect after activation.
		Admin\PluginScreen::init();

		// Collapse Unicode whitespace in posted phone numbers before WooCommerce's own
		// phone check rejects them (classic checkout and Store API).
		Checkout\WhitespaceFilter::init();

		// Initialize checkout phone validation.
		Checkout\BillingPhoneValidator::init();
		Checkout\ShippingPhoneValidator::init();

		// Initialize block checkout validation (Store API).
		Checkout\BlockCheckoutValidator::init();

		// Initialize checkout assets (JS for field highlighting).
		Checkout\Assets::init();
	}
);
