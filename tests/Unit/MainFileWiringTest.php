<?php

namespace Shift64\SmartPhoneValidation\Tests\Unit;

use Shift64\SmartPhoneValidation\Admin\DependencyChecker;
use Shift64\SmartPhoneValidation\Admin\PluginScreen;

/**
 * The plugins_loaded wiring of the main plugin file: the translation folder is
 * registered before the boot, and the WordPress version gate stops the boot.
 *
 * The main file defines the plugin constants, which tests/Unit/bootstrap.php
 * defines as well, so it runs in a separate PHP process through
 * fixtures/main-file/harness.php.
 */
class MainFileWiringTest extends TestCase {

	/**
	 * WordPress 7.1 registers the Domain Path header itself only for plugins
	 * activated per site; without this call a network-activated install never
	 * finds languages/ (#32). Plugin Check warns about the call, so pin it.
	 */
	public function test_bundled_languages_folder_is_registered_before_the_boot(): void {
		$result = $this->run_main_file( '7.1.2', true );

		$this->assertSame( array( 5, 10 ), $result['plugins_loaded_priorities'] );
		$this->assertSame(
			array( array( 'shift64-phone-validation', false, basename( dirname( __DIR__, 2 ) ) . '/languages' ) ),
			$result['textdomain_after_priority']['5']
		);
		// Supported WordPress with WooCommerce: the plugin boots (settings tab, checkout hooks).
		$this->assertSame( array(), $result['admin_notices'] );
		$this->assertContains( 'woocommerce_settings_tabs_array', $result['booted_hooks'] );
		$this->assertContains( 'plugin_action_links_' . basename( dirname( __DIR__, 2 ) ) . '/shift64-phone-validation.php', $result['booted_hooks'] );
		$this->assertContains( 'admin_init', $result['booted_hooks'] );
	}

	public function test_activation_hook_asks_for_the_settings_redirect(): void {
		$result = $this->run_main_file( '7.1.2', true );

		$this->assertSame(
			array( array( 'shift64-phone-validation.php', PluginScreen::class . '::on_activation' ) ),
			$result['activation_hooks']
		);
	}

	public function test_unsupported_wordpress_boots_nothing_and_shows_only_the_version_notice(): void {
		$result = $this->run_main_file( '7.0.9', true );

		$this->assertSame(
			array( DependencyChecker::class . '::render_wordpress_notice' ),
			$result['admin_notices']
		);
		// Even with WooCommerce active, nothing else is hooked: no validation, no settings.
		$this->assertSame( array(), $result['booted_hooks'] );
	}

	public function test_supported_wordpress_passes_the_version_gate(): void {
		// Without WooCommerce the boot stops at the next check, which proves the
		// version gate let it through.
		$result = $this->run_main_file( '7.1-RC1-61234', false );

		$this->assertSame(
			array( DependencyChecker::class . '::render_woocommerce_notice' ),
			$result['admin_notices']
		);
		$this->assertSame( array(), $result['booted_hooks'] );
	}

	/**
	 * @return array{plugins_loaded_priorities: list<int>, textdomain_after_priority: array<string, list<array>>, admin_notices: list<string>, booted_hooks: list<string>, activation_hooks: list<array{string, string}>}
	 */
	private function run_main_file( string $core_version, bool $woocommerce_active ): array {
		$command = array( PHP_BINARY, __DIR__ . '/fixtures/main-file/harness.php', $core_version, $woocommerce_active ? '1' : '0' );
		$process = proc_open( $command, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		$this->assertIsResource( $process );

		$stdout = (string) stream_get_contents( $pipes[1] );
		$stderr = (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$this->assertSame( 0, proc_close( $process ), $stdout . $stderr );
		$result = json_decode( $stdout, true );
		$this->assertIsArray( $result, $stdout . $stderr );

		return $result;
	}
}
