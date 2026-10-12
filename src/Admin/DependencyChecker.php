<?php
/**
 * Dependency Checker for plugin requirements.
 *
 * @package Shift64\SmartPhoneValidation\Admin
 */

declare(strict_types=1);

namespace Shift64\SmartPhoneValidation\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks if required plugin dependencies are active.
 */
class DependencyChecker {

	/**
	 * Check that WordPress is at least SHIFT64_PHONE_VALIDATION_MIN_WP.
	 *
	 * Uses is_wp_version_compatible() (WordPress 5.2+), the check WordPress itself applies
	 * to "Requires at least", so the plugin never refuses to run where WordPress let it be
	 * activated. Since WordPress 6.7 it reads the core version from wp-includes/version.php
	 * through wp_get_wp_version(), not the $wp_version global behind
	 * get_bloginfo( 'version' ), which other plugins can blank or fake. It ignores a
	 * pre-release suffix, so "7.1-RC1-61234" counts as 7.1, and treats a required "x.y.0"
	 * as "x.y".
	 *
	 * @return bool True if the running WordPress version is supported, false otherwise.
	 */
	public static function is_wordpress_supported(): bool {
		return is_wp_version_compatible( SHIFT64_PHONE_VALIDATION_MIN_WP );
	}

	/**
	 * The WordPress version this site runs, for the admin notice.
	 *
	 * wp_get_wp_version() (WordPress 6.7+) when it exists, so the notice names the same
	 * version is_wp_version_compatible() compared; get_bloginfo( 'version' ) before that.
	 *
	 * @return string E.g. "7.0.9".
	 */
	private static function get_running_wordpress_version(): string {
		return function_exists( 'wp_get_wp_version' ) ? wp_get_wp_version() : (string) get_bloginfo( 'version' );
	}

	/**
	 * Display admin notice when the WordPress version is below the minimum.
	 *
	 * @return void
	 */
	public static function display_wordpress_unsupported_notice(): void {
		add_action( 'admin_notices', array( self::class, 'render_wordpress_notice' ) );
	}

	/**
	 * Render the unsupported WordPress version admin notice.
	 *
	 * @return void
	 */
	public static function render_wordpress_notice(): void {
		// Only people who can manage plugins can act on this notice.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: minimum WordPress version, e.g. 7.1, 2: WordPress version this site runs */
						__( 'SHIFT64 Phone Validation for WooCommerce requires WordPress %1$s or later, and this site runs WordPress %2$s. Until WordPress is updated, phone numbers are not checked at checkout.', 'shift64-phone-validation' ),
						SHIFT64_PHONE_VALIDATION_MIN_WP,
						self::get_running_wordpress_version()
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool True if WooCommerce is active, false otherwise.
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Display admin notice when WooCommerce is not active.
	 *
	 * @return void
	 */
	public static function display_woocommerce_missing_notice(): void {
		add_action( 'admin_notices', array( self::class, 'render_woocommerce_notice' ) );
	}

	/**
	 * Render the WooCommerce missing admin notice.
	 *
	 * @return void
	 */
	public static function render_woocommerce_notice(): void {
		// Only people who can install or activate WooCommerce can act on this notice.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo esc_html__(
					'SHIFT64 Phone Validation for WooCommerce requires WooCommerce to be installed and activated.',
					'shift64-phone-validation'
				);
				?>
			</p>
		</div>
		<?php
	}
}
