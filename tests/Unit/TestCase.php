<?php
/**
 * Base test case: resets stubbed options/translations between tests.
 *
 * @package Shift64\SmartPhoneValidation\Tests
 */

namespace Shift64\SmartPhoneValidation\Tests\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	/**
	 * Subset of languages/shift64-phone-validation-pl_PL.po used to prove
	 * that logic does not depend on the active language.
	 */
	const POLISH = array(
		'Phone number must include international prefix (+).' => 'Numer telefonu musi zawierać międzynarodowy prefiks (+).',
		'The phone number is not valid.'                      => 'Numer telefonu jest nieprawidłowy.',
		'Phone number must contain country prefix.'           => 'Numer telefonu musi zawierać prefiks kraju.',
		'Please enter a valid phone number.'                  => 'Proszę podać prawidłowy numer telefonu.',
		'Shipping phone number must contain country prefix.'  => 'Numer telefonu do wysyłki musi zawierać prefiks kraju.',
		'Please enter a valid shipping phone number.'         => 'Proszę podać prawidłowy numer telefonu do wysyłki.',
		'Billing phone'                                       => 'Telefon do płatności',
		'Shipping phone'                                      => 'Telefon do wysyłki',
		'%s must contain country prefix.'                     => '%s musi zawierać prefiks kraju.',
		'%s is not a valid phone number.'                     => '%s nie jest prawidłowym numerem telefonu.',
	);

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['shift64_test_options']          = array();
		$GLOBALS['shift64_test_translations']     = array();
		$GLOBALS['shift64_test_filters']          = array();
		$GLOBALS['shift64_test_wp_version']       = '7.1.2';
		$GLOBALS['shift64_test_bloginfo_version'] = null;
		$GLOBALS['shift64_test_can']              = array( 'activate_plugins' );
		$GLOBALS['shift64_test_actions']          = array();
		$GLOBALS['shift64_test_multisite']        = false;
		$GLOBALS['shift64_test_network_admin']    = false;
		$GLOBALS['shift64_test_locale']           = array(
			'current'  => 'en_US',
			'switches' => array(),
			'calls'    => array(),
		);
		$GLOBALS['shift64_test_pages']            = array(
			'posts'    => array(),
			'queried'  => 0,
			'checkout' => 0,
		);
		$GLOBALS['shift64_test_scripts']          = array();
		$GLOBALS['shift64_test_available_languages'] = array( 'pl_PL', 'de_DE' );
	}

	protected function set_option( string $key, string $value ): void {
		$GLOBALS['shift64_test_options'][ 'shift64_phone_validation_' . $key ] = $value;
	}

	protected function add_filter( string $hook, callable $callback ): void {
		$GLOBALS['shift64_test_filters'][ $hook ] = $callback;
	}

	protected function use_polish(): void {
		$GLOBALS['shift64_test_translations'] = self::POLISH;
	}
}
