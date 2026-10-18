<?php

namespace Shift64\SmartPhoneValidation\Tests\Unit;

use Shift64\SmartPhoneValidation\Admin\PluginScreen;

/**
 * The Settings link on the Plugins screen and the one-time redirect to the
 * settings tab after activation.
 */
class PluginScreenTest extends TestCase {

	private const SETTINGS_URL = 'https://shop.test/wp-admin/admin.php?page=wc-settings&tab=phone_validation';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['shift64_test_can'] = array( 'activate_plugins', 'manage_woocommerce' );
		// The page WordPress shows after a single activation: plugins.php?activate=true.
		$GLOBALS['pagenow'] = 'plugins.php';
		$_GET['activate']   = 'true';
	}

	protected function tearDown(): void {
		unset( $_GET['activate'], $_GET['activate-multi'], $GLOBALS['pagenow'] );
		parent::tearDown();
	}

	public function test_init_hooks_the_plugin_row_links_and_admin_init(): void {
		PluginScreen::init();

		$this->assertSame(
			array(
				// Named after the plugin folder, whatever it is called on this checkout.
				array( 'plugin_action_links_' . basename( dirname( __DIR__, 2 ) ) . '/shift64-phone-validation.php', array( PluginScreen::class, 'add_settings_link' ) ),
				array( 'admin_init', array( PluginScreen::class, 'maybe_redirect_after_activation' ) ),
			),
			array_map( null, array_column( $GLOBALS['shift64_test_actions'], 0 ), array_column( $GLOBALS['shift64_test_actions'], 1 ) )
		);
	}

	public function test_settings_url_points_to_the_plugin_tab(): void {
		$this->assertSame( self::SETTINGS_URL, PluginScreen::get_settings_url() );
	}

	public function test_settings_link_comes_first_and_keeps_the_other_links(): void {
		$links = PluginScreen::add_settings_link( array( 'deactivate' => '<a href="#">Deactivate</a>' ) );

		$this->assertSame( array( 'settings', 'deactivate' ), array_keys( $links ) );
		$this->assertSame(
			'<a href="https://shop.test/wp-admin/admin.php?page=wc-settings&#038;tab=phone_validation">Settings</a>',
			$links['settings']
		);
	}

	public function test_settings_link_is_translated(): void {
		$GLOBALS['shift64_test_translations'] = array( 'Settings' => 'Ustawienia' );

		$links = PluginScreen::add_settings_link( array() );

		$this->assertStringContainsString( '>Ustawienia</a>', $links['settings'] );
	}

	public function test_no_settings_link_for_users_who_cannot_open_woocommerce_settings(): void {
		$GLOBALS['shift64_test_can'] = array( 'activate_plugins' );

		$links = array( 'deactivate' => '<a href="#">Deactivate</a>' );

		$this->assertSame( $links, PluginScreen::add_settings_link( $links ) );
	}

	public function test_activation_redirects_the_activating_user_once(): void {
		PluginScreen::on_activation( false );

		$this->assertTrue( PluginScreen::should_redirect_after_activation() );
		$this->assertFalse( PluginScreen::should_redirect_after_activation() );
		$this->assertSame( array(), $GLOBALS['shift64_test_transients'] );
	}

	public function test_no_redirect_without_an_activation(): void {
		$this->assertFalse( PluginScreen::should_redirect_after_activation() );
	}

	public function test_network_activation_does_not_ask_for_a_redirect(): void {
		PluginScreen::on_activation( true );

		$this->assertSame( array(), $GLOBALS['shift64_test_transients'] );
		$this->assertFalse( PluginScreen::should_redirect_after_activation() );
	}

	public function test_bulk_activation_stays_on_the_plugins_screen(): void {
		PluginScreen::on_activation( false );
		// WordPress's redirect after a bulk activation: plugins.php?activate-multi=true.
		unset( $_GET['activate'] );
		$_GET['activate-multi'] = 'true';

		$this->assertFalse( PluginScreen::should_redirect_after_activation() );
	}

	public function test_other_admin_pages_are_never_redirected(): void {
		PluginScreen::on_activation( false );
		$GLOBALS['pagenow'] = 'admin-post.php';

		$this->assertFalse( PluginScreen::should_redirect_after_activation() );
		// Left for the Plugins screen, which still redirects.
		$GLOBALS['pagenow'] = 'plugins.php';
		$this->assertTrue( PluginScreen::should_redirect_after_activation() );
	}

	public function test_plugins_screen_without_the_activation_flag_is_not_redirected(): void {
		PluginScreen::on_activation( false );
		unset( $_GET['activate'] );

		$this->assertFalse( PluginScreen::should_redirect_after_activation() );
	}

	public function test_ajax_activation_does_not_ask_for_a_redirect(): void {
		$GLOBALS['shift64_test_doing_ajax'] = true;
		PluginScreen::on_activation( false );

		$this->assertSame( array(), $GLOBALS['shift64_test_transients'] );
	}

	public function test_rest_activation_does_not_ask_for_a_redirect(): void {
		$GLOBALS['shift64_test_rest_request'] = true;
		PluginScreen::on_activation( false );

		$this->assertSame( array(), $GLOBALS['shift64_test_transients'] );
	}

	public function test_no_redirect_on_ajax_requests(): void {
		PluginScreen::on_activation( false );
		$GLOBALS['shift64_test_doing_ajax'] = true;

		$this->assertFalse( PluginScreen::should_redirect_after_activation() );
	}

	public function test_no_redirect_in_the_network_admin(): void {
		PluginScreen::on_activation( false );
		$GLOBALS['shift64_test_network_admin'] = true;

		$this->assertFalse( PluginScreen::should_redirect_after_activation() );
	}

	public function test_no_redirect_for_another_user(): void {
		PluginScreen::on_activation( false );
		$GLOBALS['shift64_test_user_id'] = 2;

		$this->assertFalse( PluginScreen::should_redirect_after_activation() );
	}

	public function test_no_redirect_for_users_who_cannot_open_woocommerce_settings(): void {
		PluginScreen::on_activation( false );
		$GLOBALS['shift64_test_can'] = array( 'activate_plugins' );

		$this->assertFalse( PluginScreen::should_redirect_after_activation() );
	}

	public function test_uninstall_deletes_the_redirect_transient(): void {
		$uninstall = (string) file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );

		$this->assertStringContainsString( "delete_transient( '" . PluginScreen::REDIRECT_TRANSIENT . "' )", $uninstall );
	}
}
