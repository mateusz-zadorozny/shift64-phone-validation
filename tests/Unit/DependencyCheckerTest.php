<?php

namespace Shift64\SmartPhoneValidation\Tests\Unit;

use Shift64\SmartPhoneValidation\Admin\DependencyChecker;

class DependencyCheckerTest extends TestCase {

	/**
	 * @dataProvider wordpress_versions
	 */
	public function test_wordpress_version_is_compared_with_the_minimum( string $version, bool $supported ): void {
		$GLOBALS['shift64_test_wp_version'] = $version;

		$this->assertSame( $supported, DependencyChecker::is_wordpress_supported() );
	}

	public function wordpress_versions(): array {
		return array(
			'6.9.4, an older major'                => array( '6.9.4', false ),
			'7.0'                                  => array( '7.0', false ),
			'7.0.9, the last patch below'          => array( '7.0.9', false ),
			'7.0 release candidate'                => array( '7.0-RC2-60100', false ),
			'7.1, equal'                           => array( '7.1', true ),
			'7.1.0, equal with a zero patch'       => array( '7.1.0', true ),
			'7.1.2, a patch above'                 => array( '7.1.2', true ),
			'7.1 release candidate, as WordPress'  => array( '7.1-RC1-61234', true ),
			'7.1 beta, as WordPress'               => array( '7.1-beta2', true ),
			'7.2'                                  => array( '7.2', true ),
			'7.10, numerically above 7.1'          => array( '7.10', true ),
			'8.0'                                  => array( '8.0', true ),
			'empty, unknown version'               => array( '', false ),
		);
	}

	/**
	 * Some plugins blank or fake the $wp_version global (what get_bloginfo( 'version' )
	 * reports) to hide the version. The check reads the core version instead, like
	 * WordPress's own "Requires at least" check, so a supported site stays supported.
	 *
	 * @dataProvider faked_wp_version_globals
	 */
	public function test_a_blanked_or_faked_wp_version_global_does_not_change_the_result( string $core_version, string $bloginfo_version, bool $supported ): void {
		$GLOBALS['shift64_test_wp_version']       = $core_version;
		$GLOBALS['shift64_test_bloginfo_version'] = $bloginfo_version;

		$this->assertSame( $supported, DependencyChecker::is_wordpress_supported() );
	}

	public function faked_wp_version_globals(): array {
		return array(
			'core 7.1.2, global blanked'       => array( '7.1.2', '', true ),
			'core 7.1.2, global faked as 4.0'  => array( '7.1.2', '4.0', true ),
			'core 7.0.9, global faked as 99.0' => array( '7.0.9', '99.0', false ),
		);
	}

	/**
	 * The constant, the plugin header and the readme must name the same minimum:
	 * WordPress blocks activation by the header, the plugin itself by the constant.
	 */
	public function test_minimum_matches_the_plugin_header_and_the_readme(): void {
		$root        = dirname( __DIR__, 2 );
		$main_file   = (string) file_get_contents( $root . '/shift64-phone-validation.php' );
		$readme_file = (string) file_get_contents( $root . '/readme.txt' );

		$this->assertSame( 1, preg_match( "/define\\( 'SHIFT64_PHONE_VALIDATION_MIN_WP', '([^']+)' \\);/", $main_file, $constant ) );
		$this->assertSame( 1, preg_match( '/^ \* Requires at least:\s*(\S+)$/m', $main_file, $header ) );
		$this->assertSame( 1, preg_match( '/^Requires at least:\s*(\S+)$/m', $readme_file, $readme ) );

		$this->assertSame( SHIFT64_PHONE_VALIDATION_MIN_WP, $constant[1] );
		$this->assertSame( SHIFT64_PHONE_VALIDATION_MIN_WP, $header[1] );
		$this->assertSame( SHIFT64_PHONE_VALIDATION_MIN_WP, $readme[1] );
	}

	/**
	 * PHPCS measures deprecated WordPress functions against the same minimum.
	 */
	public function test_minimum_matches_the_phpcs_minimum_wp_version(): void {
		$ruleset = (string) file_get_contents( dirname( __DIR__, 2 ) . '/.phpcs.xml.dist' );

		$this->assertSame( 1, preg_match( '/<config name="minimum_wp_version" value="([^"]+)"\/>/', $ruleset, $config ) );
		$this->assertSame( SHIFT64_PHONE_VALIDATION_MIN_WP, $config[1] );
	}

	public function test_unsupported_wordpress_notice_is_registered_for_admin_notices(): void {
		DependencyChecker::display_wordpress_unsupported_notice();

		$this->assertSame(
			array( array( 'admin_notices', array( DependencyChecker::class, 'render_wordpress_notice' ) ) ),
			$GLOBALS['shift64_test_actions']
		);
	}

	public function test_unsupported_wordpress_notice_names_the_required_and_the_running_version(): void {
		$GLOBALS['shift64_test_wp_version'] = '7.0.9';

		ob_start();
		DependencyChecker::render_wordpress_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'class="notice notice-error"', $html );
		$this->assertStringContainsString(
			'SHIFT64 Phone Validation for WooCommerce requires WordPress 7.1 or later, and this site runs WordPress 7.0.9. Until WordPress is updated, phone numbers are not checked at checkout.',
			$html
		);
	}

	public function test_unsupported_wordpress_notice_names_the_core_version_not_a_faked_global(): void {
		$GLOBALS['shift64_test_wp_version']       = '7.0.9';
		$GLOBALS['shift64_test_bloginfo_version'] = '4.0';

		ob_start();
		DependencyChecker::render_wordpress_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'this site runs WordPress 7.0.9.', $html );
		$this->assertStringNotContainsString( 'WordPress 4.0', $html );
	}

	public function test_unsupported_wordpress_notice_is_translated(): void {
		$GLOBALS['shift64_test_wp_version']   = '7.0.9';
		$GLOBALS['shift64_test_translations'] = array(
			'SHIFT64 Phone Validation for WooCommerce requires WordPress %1$s or later, and this site runs WordPress %2$s. Until WordPress is updated, phone numbers are not checked at checkout.' => 'Wymaga %1$s, jest %2$s.',
		);

		ob_start();
		DependencyChecker::render_wordpress_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Wymaga 7.1, jest 7.0.9.', $html );
	}

	public function test_unsupported_wordpress_notice_is_hidden_from_users_who_cannot_manage_plugins(): void {
		$GLOBALS['shift64_test_wp_version'] = '7.0.9';
		$GLOBALS['shift64_test_can']        = false;

		ob_start();
		DependencyChecker::render_wordpress_notice();

		$this->assertSame( '', ob_get_clean() );
	}
}
