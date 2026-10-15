<?php
/**
 * Demo network scenarios: the single source for tests/e2e/demo/bin/seed.sh
 * (which subsites exist), seed-site.php (how each one is configured) and the
 * landing page (what each one shows). Adding a scenario is one entry here.
 *
 * Keys:
 * - slug:        the subsite path (/<slug>/).
 * - title:       landing page heading and card title.
 * - description: one sentence, what to try.
 * - locale:      site language (en_US or an installed language).
 * - theme:       null for WordPress's default block theme, or a theme slug that
 *                seed.sh installs and network-enables.
 * - checkout:    'block' (the /checkout/ page) or 'classic' (/classic-checkout/).
 * - options:     shift64_phone_validation_<key> => value. Unlisted options get
 *                the plugin defaults (see seed-site.php).
 * - try:         example inputs for the landing page: phone, valid (bool),
 *                result (what the shopper sees or what is stored).
 *
 * @package Shift64\SmartPhoneValidation
 */

$shift64_demo_valid_e164 = array(
	'phone'  => '500 123 456',
	'valid'  => true,
	'result' => 'stored as +48500123456',
);

$shift64_demo_invalid_en = array(
	'phone'  => '100 000 000',
	'valid'  => false,
	'result' => '"Billing phone is not a valid phone number."',
);

return array(
	array(
		'slug'        => 'en',
		'title'       => 'Baseline: PL store, E.164',
		'description' => 'The plugin defaults: a Polish store, numbers checked against the address country and stored in E.164.',
		'locale'      => 'en_US',
		'theme'       => null,
		'checkout'    => 'block',
		'options'     => array(),
		'try'         => array( $shift64_demo_valid_e164, $shift64_demo_invalid_en ),
	),
	array(
		'slug'        => 'pl',
		'title'       => 'Polish error messages',
		'description' => 'Site language is Polish: try an invalid number and the checkout error comes back in Polish.',
		'locale'      => 'pl_PL',
		'theme'       => null,
		'checkout'    => 'block',
		'options'     => array(),
		'try'         => array(
			$shift64_demo_valid_e164,
			array(
				'phone'  => '100 000 000',
				'valid'  => false,
				'result' => '"Telefon do płatności nie jest prawidłowym numerem telefonu."',
			),
		),
	),
	array(
		'slug'        => 'international-only',
		'title'       => 'International numbers only',
		'description' => 'Numbers without a country prefix are rejected: only +48 … (or 0048 …) gets through.',
		'locale'      => 'en_US',
		'theme'       => null,
		'checkout'    => 'block',
		'options'     => array( 'validation_mode' => 'international_only' ),
		'try'         => array(
			array(
				'phone'  => '+48 500 123 456',
				'valid'  => true,
				'result' => 'stored as +48500123456',
			),
			array(
				'phone'  => '500 123 456',
				'valid'  => false,
				'result' => '"Billing phone must contain country prefix."',
			),
		),
	),
	array(
		'slug'        => 'format-international',
		'title'       => 'International output format',
		'description' => 'Valid numbers are stored in the international format, with spaces.',
		'locale'      => 'en_US',
		'theme'       => null,
		'checkout'    => 'block',
		'options'     => array( 'output_format' => 'INTERNATIONAL' ),
		'try'         => array(
			array(
				'phone'  => '500123456',
				'valid'  => true,
				'result' => 'stored as +48 500 123 456',
			),
			$shift64_demo_invalid_en,
		),
	),
	array(
		'slug'        => 'format-national',
		'title'       => 'National output format',
		'description' => 'Valid numbers are stored in the national format, without the country prefix.',
		'locale'      => 'en_US',
		'theme'       => null,
		'checkout'    => 'block',
		'options'     => array( 'output_format' => 'NATIONAL' ),
		'try'         => array(
			array(
				'phone'  => '+48500123456',
				'valid'  => true,
				'result' => 'stored as 500 123 456',
			),
			$shift64_demo_invalid_en,
		),
	),
	array(
		'slug'        => 'no-format',
		'title'       => 'Validation without formatting',
		'description' => 'Numbers are still validated, but a valid one is stored as typed.',
		'locale'      => 'en_US',
		'theme'       => null,
		'checkout'    => 'block',
		'options'     => array( 'format_on_save' => 'no' ),
		'try'         => array(
			array(
				'phone'  => '500-123-456',
				'valid'  => true,
				'result' => 'stored as 500-123-456',
			),
			$shift64_demo_invalid_en,
		),
	),
	array(
		'slug'        => 'classic',
		'title'       => 'Classic checkout and theme',
		'description' => 'The shortcode checkout in the classic Twenty Twenty-One theme: classic error notices and the phone field highlight.',
		'locale'      => 'en_US',
		'theme'       => 'twentytwentyone',
		'checkout'    => 'classic',
		'options'     => array(),
		'try'         => array(
			$shift64_demo_valid_e164,
			array(
				'phone'  => '100 000 000',
				'valid'  => false,
				'result' => '"Please enter a valid phone number."',
			),
		),
	),
	array(
		'slug'        => 'disabled',
		'title'       => 'Plugin disabled',
		'description' => 'Validation is switched off: only WooCommerce\'s own loose check runs, so a number that does not exist gets through.',
		'locale'      => 'en_US',
		'theme'       => null,
		'checkout'    => 'block',
		'options'     => array( 'enabled' => 'no' ),
		'try'         => array(
			array(
				'phone'  => '100 000 000',
				'valid'  => true,
				'result' => 'accepted, stored as typed',
			),
		),
	),
);
