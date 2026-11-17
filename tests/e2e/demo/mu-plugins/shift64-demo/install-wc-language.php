<?php
/**
 * Install the WooCommerce language pack that matches the installed WooCommerce.
 *
 * Run by tests/e2e/demo/bin/seed.sh as
 *   wp eval-file wp-content/mu-plugins/shift64-demo/install-wc-language.php <locale>
 * after WooCommerce is network-activated.
 *
 * `wp language plugin install woocommerce <locale>` is not enough: wp-env
 * unpacks WooCommerce into woocommerce.latest-stable/, WP-CLI does not match
 * that folder to the woocommerce slug and installs a years-old pack. Its PHP
 * strings mostly still match, but its block-checkout JSON files are named after
 * script paths WooCommerce no longer has, so the block checkout stays half
 * English. This asks the translations API for the pack of WC_VERSION instead.
 * Idempotent: a pack as new as the API's is left alone.
 *
 * @package Shift64\SmartPhoneValidation
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! defined( 'WC_VERSION' ) ) {
	WP_CLI::error( 'WooCommerce is not active - network-activate it first.' );
}

$shift64_demo_locale = isset( $args[0] ) ? (string) $args[0] : '';
if ( '' === $shift64_demo_locale ) {
	WP_CLI::error( 'Usage: wp eval-file install-wc-language.php <locale>' );
}

require_once ABSPATH . 'wp-admin/includes/translation-install.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

$shift64_demo_api = translations_api(
	'plugins',
	array(
		'slug'    => 'woocommerce',
		'version' => WC_VERSION,
	)
);
if ( is_wp_error( $shift64_demo_api ) ) {
	WP_CLI::error( 'translations API: ' . $shift64_demo_api->get_error_message() );
}

$shift64_demo_pack = null;
foreach ( $shift64_demo_api['translations'] as $shift64_demo_translation ) {
	if ( $shift64_demo_translation['language'] === $shift64_demo_locale ) {
		$shift64_demo_pack = $shift64_demo_translation;
	}
}
if ( null === $shift64_demo_pack ) {
	WP_CLI::error( "No WooCommerce $shift64_demo_locale language pack for WooCommerce " . WC_VERSION . '.' );
}

$shift64_demo_installed = wp_get_installed_translations( 'plugins' );
$shift64_demo_revision  = $shift64_demo_installed['woocommerce'][ $shift64_demo_locale ]['PO-Revision-Date'] ?? '';
if ( '' !== $shift64_demo_revision && strtotime( $shift64_demo_revision ) >= strtotime( $shift64_demo_pack['updated'] ) ) {
	WP_CLI::log( "WooCommerce $shift64_demo_locale pack {$shift64_demo_pack['version']} is up to date." );
	return;
}

$shift64_demo_skin     = new Automatic_Upgrader_Skin();
$shift64_demo_upgrader = new Language_Pack_Upgrader( $shift64_demo_skin );
$shift64_demo_result   = $shift64_demo_upgrader->upgrade(
	(object) array(
		'type'     => 'plugin',
		'slug'     => 'woocommerce',
		'language' => $shift64_demo_locale,
		'version'  => $shift64_demo_pack['version'],
		'updated'  => $shift64_demo_pack['updated'],
		'package'  => $shift64_demo_pack['package'],
	)
);
// Success is the unpacked destination (an array), failure false or a WP_Error.
if ( ! $shift64_demo_result || is_wp_error( $shift64_demo_result ) ) {
	$shift64_demo_error = is_wp_error( $shift64_demo_result )
		? $shift64_demo_result->get_error_message()
		: implode( ' ', array_map( 'wp_strip_all_tags', $shift64_demo_skin->get_upgrade_messages() ) );
	WP_CLI::error( "WooCommerce $shift64_demo_locale pack {$shift64_demo_pack['version']} not installed: $shift64_demo_error" );
}
WP_CLI::log( "WooCommerce $shift64_demo_locale pack {$shift64_demo_pack['version']} installed." );
