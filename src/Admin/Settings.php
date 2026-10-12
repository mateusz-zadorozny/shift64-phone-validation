<?php
/**
 * WooCommerce Settings integration for phone validation.
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
 * Manages plugin settings within WooCommerce settings pages.
 */
class Settings {

	/**
	 * Settings tab ID.
	 *
	 * @var string
	 */
	const TAB_ID = 'phone_validation';

	/**
	 * Option prefix for all settings.
	 *
	 * @var string
	 */
	const OPTION_PREFIX = 'shift64_phone_validation_';

	/**
	 * Initialize settings hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( self::class, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_' . self::TAB_ID, array( self::class, 'render_settings_page' ) );
		add_action( 'woocommerce_update_options_' . self::TAB_ID, array( self::class, 'save_settings' ) );
	}

	/**
	 * Add settings tab to WooCommerce settings.
	 *
	 * @param array $tabs Existing WooCommerce settings tabs.
	 * @return array Modified tabs array.
	 */
	public static function add_settings_tab( array $tabs ): array {
		$tabs[ self::TAB_ID ] = __( 'Phone Validation', 'shift64-phone-validation' );
		return $tabs;
	}

	/**
	 * Render the settings page content.
	 *
	 * @return void
	 */
	public static function render_settings_page(): void {
		woocommerce_admin_fields( self::get_settings() );
	}

	/**
	 * Save settings to wp_options.
	 *
	 * @return void
	 */
	public static function save_settings(): void {
		woocommerce_update_options( self::get_settings() );
	}

	/**
	 * Get all settings fields configuration.
	 *
	 * @return array Settings fields array.
	 */
	public static function get_settings(): array {
		$settings = array(
			array(
				'title' => __( 'Phone Validation Settings', 'shift64-phone-validation' ),
				'type'  => 'title',
				'desc'  => __( 'Configure phone number validation and formatting behavior.', 'shift64-phone-validation' ),
				'id'    => self::OPTION_PREFIX . 'section_title',
			),
			array(
				'title'    => __( 'Enable Validation', 'shift64-phone-validation' ),
				'desc'     => __( 'Enable phone number validation globally', 'shift64-phone-validation' ),
				'id'       => self::OPTION_PREFIX . 'enabled',
				'default'  => 'yes',
				'type'     => 'checkbox',
				'desc_tip' => __( 'When enabled, phone numbers will be validated during checkout.', 'shift64-phone-validation' ),
			),
			array(
				'title'    => __( 'Default Country', 'shift64-phone-validation' ),
				'desc'     => __( 'Used for numbers entered without "+" only when the address has no country. Otherwise the number is read in the country of the billing / shipping address.', 'shift64-phone-validation' ),
				'id'       => self::OPTION_PREFIX . 'default_country',
				'default'  => 'PL',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'options'  => self::get_country_options(),
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Validation Mode', 'shift64-phone-validation' ),
				'desc'     => __( 'Choose how phone numbers should be validated.', 'shift64-phone-validation' ),
				'id'       => self::OPTION_PREFIX . 'validation_mode',
				'default'  => 'default_and_international',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'options'  => array(
					'default_and_international' => __( 'Default country + international', 'shift64-phone-validation' ),
					'international_only'        => __( 'International only', 'shift64-phone-validation' ),
				),
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Output Format', 'shift64-phone-validation' ),
				'desc'     => __( 'Select the format for storing phone numbers.', 'shift64-phone-validation' ),
				'id'       => self::OPTION_PREFIX . 'output_format',
				'default'  => 'E164',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'options'  => array(
					'E164'          => __( 'E.164 (e.g., +48123456789)', 'shift64-phone-validation' ),
					'INTERNATIONAL' => __( 'International (e.g., +48 123 456 789)', 'shift64-phone-validation' ),
					'NATIONAL'      => __( 'National (e.g., 123 456 789)', 'shift64-phone-validation' ),
				),
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Enable Formatting on Save', 'shift64-phone-validation' ),
				'desc'     => __( 'Automatically format phone numbers when saving', 'shift64-phone-validation' ),
				'id'       => self::OPTION_PREFIX . 'format_on_save',
				'default'  => 'yes',
				'type'     => 'checkbox',
				'desc_tip' => __( 'When enabled, phone numbers will be reformatted to the selected output format before saving.', 'shift64-phone-validation' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => self::OPTION_PREFIX . 'section_end',
			),
		);

		return $settings;
	}

	/**
	 * Get list of countries with ISO-2 codes.
	 *
	 * @return array Associative array of country codes and names.
	 */
	public static function get_country_options(): array {
		$countries = array(
			'AF' => __( 'Afghanistan', 'shift64-phone-validation' ),
			'AL' => __( 'Albania', 'shift64-phone-validation' ),
			'DZ' => __( 'Algeria', 'shift64-phone-validation' ),
			'AS' => __( 'American Samoa', 'shift64-phone-validation' ),
			'AD' => __( 'Andorra', 'shift64-phone-validation' ),
			'AO' => __( 'Angola', 'shift64-phone-validation' ),
			'AI' => __( 'Anguilla', 'shift64-phone-validation' ),
			'AQ' => __( 'Antarctica', 'shift64-phone-validation' ),
			'AG' => __( 'Antigua and Barbuda', 'shift64-phone-validation' ),
			'AR' => __( 'Argentina', 'shift64-phone-validation' ),
			'AM' => __( 'Armenia', 'shift64-phone-validation' ),
			'AW' => __( 'Aruba', 'shift64-phone-validation' ),
			'AU' => __( 'Australia', 'shift64-phone-validation' ),
			'AT' => __( 'Austria', 'shift64-phone-validation' ),
			'AZ' => __( 'Azerbaijan', 'shift64-phone-validation' ),
			'BS' => __( 'Bahamas', 'shift64-phone-validation' ),
			'BH' => __( 'Bahrain', 'shift64-phone-validation' ),
			'BD' => __( 'Bangladesh', 'shift64-phone-validation' ),
			'BB' => __( 'Barbados', 'shift64-phone-validation' ),
			'BY' => __( 'Belarus', 'shift64-phone-validation' ),
			'BE' => __( 'Belgium', 'shift64-phone-validation' ),
			'BZ' => __( 'Belize', 'shift64-phone-validation' ),
			'BJ' => __( 'Benin', 'shift64-phone-validation' ),
			'BM' => __( 'Bermuda', 'shift64-phone-validation' ),
			'BT' => __( 'Bhutan', 'shift64-phone-validation' ),
			'BO' => __( 'Bolivia', 'shift64-phone-validation' ),
			'BA' => __( 'Bosnia and Herzegovina', 'shift64-phone-validation' ),
			'BW' => __( 'Botswana', 'shift64-phone-validation' ),
			'BR' => __( 'Brazil', 'shift64-phone-validation' ),
			'BN' => __( 'Brunei', 'shift64-phone-validation' ),
			'BG' => __( 'Bulgaria', 'shift64-phone-validation' ),
			'BF' => __( 'Burkina Faso', 'shift64-phone-validation' ),
			'BI' => __( 'Burundi', 'shift64-phone-validation' ),
			'KH' => __( 'Cambodia', 'shift64-phone-validation' ),
			'CM' => __( 'Cameroon', 'shift64-phone-validation' ),
			'CA' => __( 'Canada', 'shift64-phone-validation' ),
			'CV' => __( 'Cape Verde', 'shift64-phone-validation' ),
			'KY' => __( 'Cayman Islands', 'shift64-phone-validation' ),
			'CF' => __( 'Central African Republic', 'shift64-phone-validation' ),
			'TD' => __( 'Chad', 'shift64-phone-validation' ),
			'CL' => __( 'Chile', 'shift64-phone-validation' ),
			'CN' => __( 'China', 'shift64-phone-validation' ),
			'CO' => __( 'Colombia', 'shift64-phone-validation' ),
			'KM' => __( 'Comoros', 'shift64-phone-validation' ),
			'CG' => __( 'Congo', 'shift64-phone-validation' ),
			'CD' => __( 'Congo, Democratic Republic', 'shift64-phone-validation' ),
			'CR' => __( 'Costa Rica', 'shift64-phone-validation' ),
			'HR' => __( 'Croatia', 'shift64-phone-validation' ),
			'CU' => __( 'Cuba', 'shift64-phone-validation' ),
			'CY' => __( 'Cyprus', 'shift64-phone-validation' ),
			'CZ' => __( 'Czech Republic', 'shift64-phone-validation' ),
			'DK' => __( 'Denmark', 'shift64-phone-validation' ),
			'DJ' => __( 'Djibouti', 'shift64-phone-validation' ),
			'DM' => __( 'Dominica', 'shift64-phone-validation' ),
			'DO' => __( 'Dominican Republic', 'shift64-phone-validation' ),
			'EC' => __( 'Ecuador', 'shift64-phone-validation' ),
			'EG' => __( 'Egypt', 'shift64-phone-validation' ),
			'SV' => __( 'El Salvador', 'shift64-phone-validation' ),
			'GQ' => __( 'Equatorial Guinea', 'shift64-phone-validation' ),
			'ER' => __( 'Eritrea', 'shift64-phone-validation' ),
			'EE' => __( 'Estonia', 'shift64-phone-validation' ),
			'ET' => __( 'Ethiopia', 'shift64-phone-validation' ),
			'FJ' => __( 'Fiji', 'shift64-phone-validation' ),
			'FI' => __( 'Finland', 'shift64-phone-validation' ),
			'FR' => __( 'France', 'shift64-phone-validation' ),
			'GA' => __( 'Gabon', 'shift64-phone-validation' ),
			'GM' => __( 'Gambia', 'shift64-phone-validation' ),
			'GE' => __( 'Georgia', 'shift64-phone-validation' ),
			'DE' => __( 'Germany', 'shift64-phone-validation' ),
			'GH' => __( 'Ghana', 'shift64-phone-validation' ),
			'GR' => __( 'Greece', 'shift64-phone-validation' ),
			'GL' => __( 'Greenland', 'shift64-phone-validation' ),
			'GD' => __( 'Grenada', 'shift64-phone-validation' ),
			'GU' => __( 'Guam', 'shift64-phone-validation' ),
			'GT' => __( 'Guatemala', 'shift64-phone-validation' ),
			'GN' => __( 'Guinea', 'shift64-phone-validation' ),
			'GW' => __( 'Guinea-Bissau', 'shift64-phone-validation' ),
			'GY' => __( 'Guyana', 'shift64-phone-validation' ),
			'HT' => __( 'Haiti', 'shift64-phone-validation' ),
			'HN' => __( 'Honduras', 'shift64-phone-validation' ),
			'HK' => __( 'Hong Kong', 'shift64-phone-validation' ),
			'HU' => __( 'Hungary', 'shift64-phone-validation' ),
			'IS' => __( 'Iceland', 'shift64-phone-validation' ),
			'IN' => __( 'India', 'shift64-phone-validation' ),
			'ID' => __( 'Indonesia', 'shift64-phone-validation' ),
			'IR' => __( 'Iran', 'shift64-phone-validation' ),
			'IQ' => __( 'Iraq', 'shift64-phone-validation' ),
			'IE' => __( 'Ireland', 'shift64-phone-validation' ),
			'IL' => __( 'Israel', 'shift64-phone-validation' ),
			'IT' => __( 'Italy', 'shift64-phone-validation' ),
			'CI' => __( 'Ivory Coast', 'shift64-phone-validation' ),
			'JM' => __( 'Jamaica', 'shift64-phone-validation' ),
			'JP' => __( 'Japan', 'shift64-phone-validation' ),
			'JO' => __( 'Jordan', 'shift64-phone-validation' ),
			'KZ' => __( 'Kazakhstan', 'shift64-phone-validation' ),
			'KE' => __( 'Kenya', 'shift64-phone-validation' ),
			'KI' => __( 'Kiribati', 'shift64-phone-validation' ),
			'KP' => __( 'Korea, North', 'shift64-phone-validation' ),
			'KR' => __( 'Korea, South', 'shift64-phone-validation' ),
			'KW' => __( 'Kuwait', 'shift64-phone-validation' ),
			'KG' => __( 'Kyrgyzstan', 'shift64-phone-validation' ),
			'LA' => __( 'Laos', 'shift64-phone-validation' ),
			'LV' => __( 'Latvia', 'shift64-phone-validation' ),
			'LB' => __( 'Lebanon', 'shift64-phone-validation' ),
			'LS' => __( 'Lesotho', 'shift64-phone-validation' ),
			'LR' => __( 'Liberia', 'shift64-phone-validation' ),
			'LY' => __( 'Libya', 'shift64-phone-validation' ),
			'LI' => __( 'Liechtenstein', 'shift64-phone-validation' ),
			'LT' => __( 'Lithuania', 'shift64-phone-validation' ),
			'LU' => __( 'Luxembourg', 'shift64-phone-validation' ),
			'MO' => __( 'Macao', 'shift64-phone-validation' ),
			'MK' => __( 'North Macedonia', 'shift64-phone-validation' ),
			'MG' => __( 'Madagascar', 'shift64-phone-validation' ),
			'MW' => __( 'Malawi', 'shift64-phone-validation' ),
			'MY' => __( 'Malaysia', 'shift64-phone-validation' ),
			'MV' => __( 'Maldives', 'shift64-phone-validation' ),
			'ML' => __( 'Mali', 'shift64-phone-validation' ),
			'MT' => __( 'Malta', 'shift64-phone-validation' ),
			'MH' => __( 'Marshall Islands', 'shift64-phone-validation' ),
			'MR' => __( 'Mauritania', 'shift64-phone-validation' ),
			'MU' => __( 'Mauritius', 'shift64-phone-validation' ),
			'MX' => __( 'Mexico', 'shift64-phone-validation' ),
			'FM' => __( 'Micronesia', 'shift64-phone-validation' ),
			'MD' => __( 'Moldova', 'shift64-phone-validation' ),
			'MC' => __( 'Monaco', 'shift64-phone-validation' ),
			'MN' => __( 'Mongolia', 'shift64-phone-validation' ),
			'ME' => __( 'Montenegro', 'shift64-phone-validation' ),
			'MA' => __( 'Morocco', 'shift64-phone-validation' ),
			'MZ' => __( 'Mozambique', 'shift64-phone-validation' ),
			'MM' => __( 'Myanmar', 'shift64-phone-validation' ),
			'NA' => __( 'Namibia', 'shift64-phone-validation' ),
			'NR' => __( 'Nauru', 'shift64-phone-validation' ),
			'NP' => __( 'Nepal', 'shift64-phone-validation' ),
			'NL' => __( 'Netherlands', 'shift64-phone-validation' ),
			'NZ' => __( 'New Zealand', 'shift64-phone-validation' ),
			'NI' => __( 'Nicaragua', 'shift64-phone-validation' ),
			'NE' => __( 'Niger', 'shift64-phone-validation' ),
			'NG' => __( 'Nigeria', 'shift64-phone-validation' ),
			'NO' => __( 'Norway', 'shift64-phone-validation' ),
			'OM' => __( 'Oman', 'shift64-phone-validation' ),
			'PK' => __( 'Pakistan', 'shift64-phone-validation' ),
			'PW' => __( 'Palau', 'shift64-phone-validation' ),
			'PS' => __( 'Palestine', 'shift64-phone-validation' ),
			'PA' => __( 'Panama', 'shift64-phone-validation' ),
			'PG' => __( 'Papua New Guinea', 'shift64-phone-validation' ),
			'PY' => __( 'Paraguay', 'shift64-phone-validation' ),
			'PE' => __( 'Peru', 'shift64-phone-validation' ),
			'PH' => __( 'Philippines', 'shift64-phone-validation' ),
			'PL' => __( 'Poland', 'shift64-phone-validation' ),
			'PT' => __( 'Portugal', 'shift64-phone-validation' ),
			'PR' => __( 'Puerto Rico', 'shift64-phone-validation' ),
			'QA' => __( 'Qatar', 'shift64-phone-validation' ),
			'RO' => __( 'Romania', 'shift64-phone-validation' ),
			'RU' => __( 'Russia', 'shift64-phone-validation' ),
			'RW' => __( 'Rwanda', 'shift64-phone-validation' ),
			'WS' => __( 'Samoa', 'shift64-phone-validation' ),
			'SM' => __( 'San Marino', 'shift64-phone-validation' ),
			'ST' => __( 'Sao Tome and Principe', 'shift64-phone-validation' ),
			'SA' => __( 'Saudi Arabia', 'shift64-phone-validation' ),
			'SN' => __( 'Senegal', 'shift64-phone-validation' ),
			'RS' => __( 'Serbia', 'shift64-phone-validation' ),
			'SC' => __( 'Seychelles', 'shift64-phone-validation' ),
			'SL' => __( 'Sierra Leone', 'shift64-phone-validation' ),
			'SG' => __( 'Singapore', 'shift64-phone-validation' ),
			'SK' => __( 'Slovakia', 'shift64-phone-validation' ),
			'SI' => __( 'Slovenia', 'shift64-phone-validation' ),
			'SB' => __( 'Solomon Islands', 'shift64-phone-validation' ),
			'SO' => __( 'Somalia', 'shift64-phone-validation' ),
			'ZA' => __( 'South Africa', 'shift64-phone-validation' ),
			'SS' => __( 'South Sudan', 'shift64-phone-validation' ),
			'ES' => __( 'Spain', 'shift64-phone-validation' ),
			'LK' => __( 'Sri Lanka', 'shift64-phone-validation' ),
			'SD' => __( 'Sudan', 'shift64-phone-validation' ),
			'SR' => __( 'Suriname', 'shift64-phone-validation' ),
			'SZ' => __( 'Eswatini', 'shift64-phone-validation' ),
			'SE' => __( 'Sweden', 'shift64-phone-validation' ),
			'CH' => __( 'Switzerland', 'shift64-phone-validation' ),
			'SY' => __( 'Syria', 'shift64-phone-validation' ),
			'TW' => __( 'Taiwan', 'shift64-phone-validation' ),
			'TJ' => __( 'Tajikistan', 'shift64-phone-validation' ),
			'TZ' => __( 'Tanzania', 'shift64-phone-validation' ),
			'TH' => __( 'Thailand', 'shift64-phone-validation' ),
			'TL' => __( 'Timor-Leste', 'shift64-phone-validation' ),
			'TG' => __( 'Togo', 'shift64-phone-validation' ),
			'TO' => __( 'Tonga', 'shift64-phone-validation' ),
			'TT' => __( 'Trinidad and Tobago', 'shift64-phone-validation' ),
			'TN' => __( 'Tunisia', 'shift64-phone-validation' ),
			'TR' => __( 'Turkey', 'shift64-phone-validation' ),
			'TM' => __( 'Turkmenistan', 'shift64-phone-validation' ),
			'TV' => __( 'Tuvalu', 'shift64-phone-validation' ),
			'UG' => __( 'Uganda', 'shift64-phone-validation' ),
			'UA' => __( 'Ukraine', 'shift64-phone-validation' ),
			'AE' => __( 'United Arab Emirates', 'shift64-phone-validation' ),
			'GB' => __( 'United Kingdom', 'shift64-phone-validation' ),
			'US' => __( 'United States', 'shift64-phone-validation' ),
			'UY' => __( 'Uruguay', 'shift64-phone-validation' ),
			'UZ' => __( 'Uzbekistan', 'shift64-phone-validation' ),
			'VU' => __( 'Vanuatu', 'shift64-phone-validation' ),
			'VA' => __( 'Vatican City', 'shift64-phone-validation' ),
			'VE' => __( 'Venezuela', 'shift64-phone-validation' ),
			'VN' => __( 'Vietnam', 'shift64-phone-validation' ),
			'YE' => __( 'Yemen', 'shift64-phone-validation' ),
			'ZM' => __( 'Zambia', 'shift64-phone-validation' ),
			'ZW' => __( 'Zimbabwe', 'shift64-phone-validation' ),
		);

		return $countries;
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key     Setting key (without prefix).
	 * @param mixed  $default Default value if setting is not found.
	 * @return mixed Setting value or default.
	 */
	public static function get( string $key, $default = null ) {
		return get_option( self::OPTION_PREFIX . $key, $default );
	}

	/**
	 * Check if phone validation is enabled.
	 *
	 * @return bool True if validation is enabled.
	 */
	public static function is_validation_enabled(): bool {
		return 'yes' === self::get( 'enabled', 'yes' );
	}

	/**
	 * Check if format on save is enabled.
	 *
	 * @return bool True if format on save is enabled.
	 */
	public static function is_format_on_save_enabled(): bool {
		return 'yes' === self::get( 'format_on_save', 'yes' );
	}

	/**
	 * Get the default country code.
	 *
	 * @return string ISO-2 country code.
	 */
	public static function get_default_country(): string {
		return (string) self::get( 'default_country', 'PL' );
	}

	/**
	 * Get the validation mode.
	 *
	 * @return string Validation mode key.
	 */
	public static function get_validation_mode(): string {
		return (string) self::get( 'validation_mode', 'default_and_international' );
	}

	/**
	 * Get the output format.
	 *
	 * @return string Output format key (E164, INTERNATIONAL, NATIONAL).
	 */
	public static function get_output_format(): string {
		return (string) self::get( 'output_format', 'E164' );
	}
}
