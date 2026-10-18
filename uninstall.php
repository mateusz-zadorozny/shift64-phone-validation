<?php
/**
 * Uninstall handler: removes everything the plugin stored in the database.
 *
 * Runs only when the plugin is deleted from WP Admin, not on deactivation.
 * Phone numbers already saved on orders are order data and are left untouched.
 *
 * @package Shift64\SmartPhoneValidation
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$shift64_phone_validation_options = array(
	'shift64_phone_validation_enabled',
	'shift64_phone_validation_default_country',
	'shift64_phone_validation_validation_mode',
	'shift64_phone_validation_output_format',
	'shift64_phone_validation_format_on_save',
);

foreach ( $shift64_phone_validation_options as $shift64_phone_validation_option ) {
	delete_option( $shift64_phone_validation_option );
}

// Set on activation and normally consumed within seconds.
delete_transient( 'shift64_phone_validation_activation_redirect' );

// Cleans up after the GitHub updater removed in 1.5.0.
delete_transient( 'shift64_phone_validation_github_release' );
