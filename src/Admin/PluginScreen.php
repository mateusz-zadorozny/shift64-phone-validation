<?php
/**
 * Plugins screen integration: the Settings action link and the redirect to the
 * settings tab after activation.
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
 * Gets the store owner to the settings tab: a Settings link under the plugin's
 * row on the Plugins screen, and one redirect to the tab right after activation.
 *
 * The redirect follows WordPress.org guideline 11 (no hijacking of the admin):
 * it happens once, only for the user who activated the plugin, and only from the
 * Plugins screen WordPress shows after a single activation. Bulk and network
 * activation, WP-CLI, AJAX (the Add Plugins cards) and REST activations never
 * redirect, and no other admin page is ever taken over.
 */
class PluginScreen {

	/**
	 * Transient set on activation and consumed by the next admin page load.
	 *
	 * @var string
	 */
	const REDIRECT_TRANSIENT = 'shift64_phone_validation_activation_redirect';

	/**
	 * Initialize Plugins screen hooks. Runs only once the WordPress version and
	 * WooCommerce checks have passed, so the settings tab exists.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'plugin_action_links_' . plugin_basename( SHIFT64_PHONE_VALIDATION_FILE ), array( self::class, 'add_settings_link' ) );
		add_action( 'admin_init', array( self::class, 'maybe_redirect_after_activation' ) );
	}

	/**
	 * Activation hook: ask the next admin page load to open the settings tab.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 * @return void
	 */
	public static function on_activation( $network_wide = false ): void {
		// Network activation covers many sites, WP-CLI has no browser to redirect, and an
		// AJAX or REST activation (the Add Plugins cards, installers) keeps the user where
		// they are.
		if ( $network_wide || ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_ajax() || wp_is_serving_rest_request() ) {
			return;
		}

		set_transient( self::REDIRECT_TRANSIENT, get_current_user_id(), 30 );
	}

	/**
	 * URL of the plugin's tab in WooCommerce settings.
	 *
	 * @return string
	 */
	public static function get_settings_url(): string {
		return admin_url( 'admin.php?page=wc-settings&tab=' . Settings::TAB_ID );
	}

	/**
	 * Put a Settings link first in the plugin's row on the Plugins screen.
	 *
	 * @param array<int|string, string> $links Existing action links.
	 * @return array<int|string, string> Action links with Settings first.
	 */
	public static function add_settings_link( $links ): array {
		$links = (array) $links;

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $links;
		}

		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::get_settings_url() ),
			esc_html__( 'Settings', 'shift64-phone-validation' )
		);

		return array( 'settings' => $settings ) + $links;
	}

	/**
	 * Whether this admin request should be redirected to the settings tab. Consumes
	 * the activation transient, so the redirect happens at most once.
	 *
	 * @return bool
	 */
	public static function should_redirect_after_activation(): bool {
		global $pagenow;

		// Only the page WordPress redirects to after a single activation
		// (plugins.php?activate=true); bulk activation adds activate-multi instead.
		// Any other admin page, form post or AJAX call is left alone.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only flags WordPress sets on its own redirect.
		if ( 'plugins.php' !== $pagenow || ! isset( $_GET['activate'] ) || isset( $_GET['activate-multi'] ) ) {
			return false;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( wp_doing_ajax() || is_network_admin() ) {
			return false;
		}

		$activated_by = get_transient( self::REDIRECT_TRANSIENT );

		if ( false === $activated_by ) {
			return false;
		}

		delete_transient( self::REDIRECT_TRANSIENT );

		return get_current_user_id() === (int) $activated_by && current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Redirect to the settings tab on the first admin page load after activation.
	 *
	 * @return void
	 */
	public static function maybe_redirect_after_activation(): void {
		if ( ! self::should_redirect_after_activation() ) {
			return;
		}

		wp_safe_redirect( self::get_settings_url() );
		exit;
	}
}
