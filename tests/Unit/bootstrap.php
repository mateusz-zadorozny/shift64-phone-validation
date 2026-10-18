<?php
/**
 * Bootstrap for unit tests that run WITHOUT WordPress.
 *
 * Only the two WordPress functions the validation layer touches are stubbed:
 * - get_option(): backed by $GLOBALS['shift64_test_options'] (set per test),
 * - apply_filters(): backed by $GLOBALS['shift64_test_filters'] (hook => callable),
 * - __(): backed by $GLOBALS['shift64_test_translations'] (empty = English passthrough).
 *
 * @package Shift64\SmartPhoneValidation\Tests
 */

// Every file under src/ exits when ABSPATH is undefined (direct-access guard). Without
// this define the first src/ file loaded would end PHPUnit with exit code 0 and no tests
// run - a silent fake pass. Keep it above anything that loads plugin code.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Loaded by the main plugin file in WordPress, not by Composer (see composer.json).
require_once dirname( __DIR__, 2 ) . '/src/functions.php';

$GLOBALS['shift64_test_options']      = array();
$GLOBALS['shift64_test_translations'] = array();
$GLOBALS['shift64_test_filters']      = array();

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		if ( isset( $GLOBALS['shift64_test_filters'][ $hook ] ) ) {
			return $GLOBALS['shift64_test_filters'][ $hook ]( $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $GLOBALS['shift64_test_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $GLOBALS['shift64_test_translations'][ $text ] ?? $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return __( $text, $domain );
	}
}

// --- Minimum WordPress version, defined by the main plugin file in WordPress ----
// DependencyCheckerTest proves this value equals the main file's constant and both
// "Requires at least" headers.
if ( ! defined( 'SHIFT64_PHONE_VALIDATION_MIN_WP' ) ) {
	define( 'SHIFT64_PHONE_VALIDATION_MIN_WP', '7.1' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal stand-in: records what the checkout validators add.
	 */
	class WP_Error {
		public $errors     = array();
		public $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( '' !== $code ) {
				$this->add( $code, $message, $data );
			}
		}

		public function add( $code, $message, $data = '' ) {
			$this->errors[ $code ][]   = $message;
			$this->error_data[ $code ] = $data;
		}
	}
}

// --- Stubs used by the dependency checker tests ------------------------------
// wp_get_wp_version() reports $GLOBALS['shift64_test_wp_version'], the core version
// from wp-includes/version.php. get_bloginfo( 'version' ) reports the $wp_version
// global, which other plugins can blank or fake: $GLOBALS['shift64_test_bloginfo_version']
// when a test sets it, the core version otherwise. current_user_can() reports
// whether the capability is in $GLOBALS['shift64_test_can'], add_action() records
// each registration in $GLOBALS['shift64_test_actions'], and is_multisite() /
// is_network_admin() report $GLOBALS['shift64_test_multisite'] /
// $GLOBALS['shift64_test_network_admin'].
$GLOBALS['shift64_test_wp_version']       = '7.1.2';
$GLOBALS['shift64_test_bloginfo_version'] = null;
$GLOBALS['shift64_test_can']              = array( 'activate_plugins' );
$GLOBALS['shift64_test_actions']          = array();
$GLOBALS['shift64_test_multisite']        = false;
$GLOBALS['shift64_test_network_admin']    = false;

if ( ! function_exists( 'wp_get_wp_version' ) ) {
	function wp_get_wp_version() {
		return $GLOBALS['shift64_test_wp_version'];
	}
}

if ( ! function_exists( 'is_wp_version_compatible' ) ) {
	// Same logic as wp-includes/functions.php of WordPress 7.1, minus the override for
	// WordPress core's own test suite.
	function is_wp_version_compatible( $required ) {
		$wp_version = wp_get_wp_version();

		// Strip off any -alpha, -RC, -beta, -src suffixes.
		list( $version ) = explode( '-', $wp_version );

		if ( is_string( $required ) ) {
			$trimmed = trim( $required );

			if ( substr_count( $trimmed, '.' ) > 1 && str_ends_with( $trimmed, '.0' ) ) {
				$required = substr( $trimmed, 0, -2 );
			}
		}

		return empty( $required ) || version_compare( $version, $required, '>=' );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '', $filter = 'raw' ) {
		if ( 'version' !== $show ) {
			return '';
		}
		return $GLOBALS['shift64_test_bloginfo_version'] ?? $GLOBALS['shift64_test_wp_version'];
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return in_array( $capability, $GLOBALS['shift64_test_can'], true );
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() {
		return $GLOBALS['shift64_test_multisite'];
	}
}

if ( ! function_exists( 'is_network_admin' ) ) {
	function is_network_admin() {
		return $GLOBALS['shift64_test_network_admin'];
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['shift64_test_actions'][] = array( $hook, $callback );
		return true;
	}
}

// --- Stubs used by the block checkout tests ----------------------------------
// 'calls' logs the locale switches in order.
$GLOBALS['shift64_test_locale'] = array(
	'current'  => 'en_US',
	'switches' => array(),
	'calls'    => array(),
);

if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return $GLOBALS['shift64_test_locale']['current'];
	}
	function determine_locale() {
		return $GLOBALS['shift64_test_locale']['current'];
	}
	function switch_to_locale( $locale ) {
		$GLOBALS['shift64_test_locale']['calls'][]    = "switch_to_locale {$locale}";
		$GLOBALS['shift64_test_locale']['switches'][] = $GLOBALS['shift64_test_locale']['current'];
		$GLOBALS['shift64_test_locale']['current']    = $locale;
		return true;
	}
	function restore_previous_locale() {
		$GLOBALS['shift64_test_locale']['calls'][] = 'restore_previous_locale';
		$GLOBALS['shift64_test_locale']['current'] = array_pop( $GLOBALS['shift64_test_locale']['switches'] );
		return $GLOBALS['shift64_test_locale']['current'];
	}
	function get_available_languages() {
		return array( 'pl_PL', 'de_DE' );
	}
	function home_url( $path = '' ) {
		return 'https://shop.test' . $path;
	}
	function esc_html( $text ) {
		return $text;
	}
	function esc_url_raw( $url ) {
		return $url;
	}
	function wp_unslash( $value ) {
		return $value;
	}
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

// --- Stubs used by the checkout assets tests ---------------------------------
// $GLOBALS['shift64_test_pages'] holds the posts get_post() can find (id => WP_Post),
// 'queried' and 'checkout' are the IDs of the page being viewed and of the configured
// WooCommerce checkout page, and wp_enqueue_script() records each handle in
// $GLOBALS['shift64_test_scripts'] and its dependencies in
// $GLOBALS['shift64_test_script_deps'] (handle => deps).
$GLOBALS['shift64_test_pages']       = array(
	'posts'    => array(),
	'queried'  => 0,
	'checkout' => 0,
);
$GLOBALS['shift64_test_scripts']     = array();
$GLOBALS['shift64_test_script_deps'] = array();

if ( ! defined( 'SHIFT64_PHONE_VALIDATION_URL' ) ) {
	define( 'SHIFT64_PHONE_VALIDATION_URL', 'https://shop.test/wp-content/plugins/shift64-phone-validation/' );
}
if ( ! defined( 'SHIFT64_PHONE_VALIDATION_VERSION' ) ) {
	define( 'SHIFT64_PHONE_VALIDATION_VERSION', '0.0.0-test' );
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public $ID           = 0;
		public $post_content = '';

		public function __construct( $id, $content ) {
			$this->ID           = $id;
			$this->post_content = $content;
		}
	}
}

if ( ! function_exists( 'is_checkout' ) ) {
	function is_checkout() {
		return true;
	}
}

if ( ! function_exists( 'get_queried_object' ) ) {
	function get_queried_object() {
		return get_post( $GLOBALS['shift64_test_pages']['queried'] );
	}
}

if ( ! function_exists( 'wc_get_page_id' ) ) {
	function wc_get_page_id( $page ) {
		return 'checkout' === $page ? $GLOBALS['shift64_test_pages']['checkout'] : -1;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return $GLOBALS['shift64_test_pages']['posts'][ $id ] ?? null;
	}
}

if ( ! function_exists( 'has_block' ) ) {
	function has_block( $block_name, $post = null ) {
		return 1 === preg_match( '#<!-- wp:' . preg_quote( $block_name, '#' ) . '[\s/]#', $post->post_content );
	}
}

if ( ! function_exists( 'has_shortcode' ) ) {
	function has_shortcode( $content, $tag ) {
		return str_contains( $content, '[' . $tag . ']' );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) {
		$GLOBALS['shift64_test_scripts'][]               = $handle;
		$GLOBALS['shift64_test_script_deps'][ $handle ] = $deps;
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		return true;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

// --- Stubs used by the Plugins screen tests -----------------------------------
// add_filter() records each registration in $GLOBALS['shift64_test_actions'] like
// add_action(), transients live in $GLOBALS['shift64_test_transients'] (name => value),
// get_current_user_id() reports $GLOBALS['shift64_test_user_id'], wp_doing_ajax()
// reports $GLOBALS['shift64_test_doing_ajax'] and wp_is_serving_rest_request()
// reports $GLOBALS['shift64_test_rest_request'].
$GLOBALS['shift64_test_transients']   = array();
$GLOBALS['shift64_test_rest_request'] = false;
$GLOBALS['shift64_test_user_id']      = 1;
$GLOBALS['shift64_test_doing_ajax']   = false;

if ( ! defined( 'SHIFT64_PHONE_VALIDATION_FILE' ) ) {
	define( 'SHIFT64_PHONE_VALIDATION_FILE', dirname( __DIR__, 2 ) . '/shift64-phone-validation.php' );
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_action( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://shop.test/wp-admin/' . $path;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return str_replace( '&', '&#038;', $url );
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		// Without an object cache WordPress stores transients as option rows and reads them back as strings.
		$GLOBALS['shift64_test_transients'][ $transient ] = (string) $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		return $GLOBALS['shift64_test_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		unset( $GLOBALS['shift64_test_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return $GLOBALS['shift64_test_user_id'];
	}
}

if ( ! function_exists( 'wp_is_serving_rest_request' ) ) {
	function wp_is_serving_rest_request() {
		return $GLOBALS['shift64_test_rest_request'];
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return $GLOBALS['shift64_test_doing_ajax'];
	}
}

require_once __DIR__ . '/stubs-woocommerce.php';
require_once __DIR__ . '/TestCase.php';
