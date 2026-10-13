<?php
/**
 * Bootstrap for PHPStan only: never loaded at runtime or by PHPUnit.
 *
 * The plugin's constants are defined in shift64-phone-validation.php from WordPress
 * functions (plugin_dir_url() and friends), which PHPStan cannot evaluate, so it reports
 * them as "not found" where src/ uses them. Defining them here with values of the same
 * type lets the analysis resolve them. Keep the list in sync with the main plugin file.
 *
 * @package Shift64\SmartPhoneValidation\Tests
 */

define( 'SHIFT64_PHONE_VALIDATION_VERSION', '0.0.0' );
define( 'SHIFT64_PHONE_VALIDATION_FILE', dirname( __DIR__, 2 ) . '/shift64-phone-validation.php' );
define( 'SHIFT64_PHONE_VALIDATION_PATH', dirname( __DIR__, 2 ) . '/' );
define( 'SHIFT64_PHONE_VALIDATION_URL', 'https://example.test/wp-content/plugins/shift64-phone-validation/' );
define( 'SHIFT64_PHONE_VALIDATION_MIN_WP', '7.1' );
