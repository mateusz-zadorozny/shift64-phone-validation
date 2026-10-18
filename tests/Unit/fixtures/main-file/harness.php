<?php
/**
 * Loads the main plugin file outside WordPress, with stubs that record what it
 * registers, runs its plugins_loaded callbacks in priority order and prints the
 * result as JSON. Used by MainFileWiringTest in a separate PHP process, because
 * the main file defines the plugin constants that the unit bootstrap defines too.
 *
 * Usage: php harness.php <core WordPress version> <WooCommerce active: 1|0>
 *
 * @package Shift64\SmartPhoneValidation\Tests
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

$shift64_harness = array(
	'core_version' => (string) ( $argv[1] ?? '' ),
	'actions'      => array(),
	'textdomain'   => array(),
	'activation'   => array(),
);

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['shift64_harness']['actions'][] = array( $hook, $callback, $priority );
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_action( $hook, $callback, $priority, $accepted_args );
}

function register_activation_hook( $file, $callback ) {
	$GLOBALS['shift64_harness']['activation'][] = array( basename( $file ), is_array( $callback ) ? implode( '::', $callback ) : 'closure' );
}

function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
	$GLOBALS['shift64_harness']['textdomain'][] = array( $domain, $deprecated, $plugin_rel_path );
	return true;
}

// Version the WordPress core files report; the plugin must use this, not $wp_version.
function wp_get_wp_version() {
	return $GLOBALS['shift64_harness']['core_version'];
}

// Same logic as WordPress 7.1 core (wp-includes/functions.php), minus the core-tests override.
function is_wp_version_compatible( $required ) {
	list( $version ) = explode( '-', wp_get_wp_version() );
	return empty( $required ) || version_compare( $version, $required, '>=' );
}

function is_multisite() {
	return false;
}

if ( '1' === ( $argv[2] ?? '0' ) ) {
	eval( 'class WooCommerce {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test harness only.
}

require dirname( __DIR__, 4 ) . '/shift64-phone-validation.php';

$shift64_loaded = array_values(
	array_filter(
		$shift64_harness['actions'],
		static fn( $action ) => 'plugins_loaded' === $action[0]
	)
);
usort( $shift64_loaded, static fn( $a, $b ) => $a[2] <=> $b[2] );

$shift64_result = array(
	'plugins_loaded_priorities' => array_map( static fn( $action ) => $action[2], $shift64_loaded ),
	'textdomain_after_priority' => array(),
	'admin_notices'             => array(),
	'booted_hooks'              => array(),
	'activation_hooks'          => $shift64_harness['activation'],
);

// Everything registered from here on comes from the plugins_loaded callbacks.
$shift64_registered_before_boot = count( $shift64_harness['actions'] );

foreach ( $shift64_loaded as $shift64_action ) {
	call_user_func( $shift64_action[1] );
	$shift64_result['textdomain_after_priority'][ (string) $shift64_action[2] ] = $shift64_harness['textdomain'];
}

foreach ( array_slice( $shift64_harness['actions'], $shift64_registered_before_boot ) as $shift64_action ) {
	if ( 'admin_notices' === $shift64_action[0] ) {
		$shift64_result['admin_notices'][] = is_array( $shift64_action[1] ) ? implode( '::', $shift64_action[1] ) : 'closure';
	} else {
		$shift64_result['booted_hooks'][] = $shift64_action[0];
	}
}

echo json_encode( $shift64_result );
