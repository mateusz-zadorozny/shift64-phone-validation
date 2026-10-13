<?php

namespace Shift64\SmartPhoneValidation\Tests\Unit;

use Shift64\SmartPhoneValidation\Checkout\Assets;

/**
 * Regression for #34: the script follows the checkout page being viewed, not the
 * page configured as the WooCommerce checkout page.
 */
class AssetsTest extends TestCase {

	const BLOCK_PAGE          = 10;
	const CLASSIC_PAGE        = 20;
	const OTHER_PAGE          = 30;
	const EDITOR_CLASSIC_PAGE = 40;

	const CLASSIC_SCRIPT = 'shift64-phone-checkout-validation';
	const BLOCK_SCRIPT   = 'shift64-phone-block-checkout-validation';

	protected function setUp(): void {
		parent::setUp();
		$this->add_page( self::BLOCK_PAGE, "<!-- wp:woocommerce/checkout {\"align\":\"wide\"} -->\n<div class=\"wp-block-woocommerce-checkout\"></div>\n<!-- /wp:woocommerce/checkout -->" );
		$this->add_page( self::CLASSIC_PAGE, "<!-- wp:shortcode -->\n[woocommerce_checkout]\n<!-- /wp:shortcode -->" );
		$this->add_page( self::EDITOR_CLASSIC_PAGE, '<!-- wp:woocommerce/classic-shortcode {"shortcode":"checkout","align":"wide"} /-->' );
		$this->add_page( self::OTHER_PAGE, '<!-- wp:paragraph --><p>Checkout</p><!-- /wp:paragraph -->' );
	}

	public function test_classic_page_loads_the_classic_script_when_the_block_page_is_configured(): void {
		$this->view_page( self::CLASSIC_PAGE, self::BLOCK_PAGE );

		$this->assertSame( array( self::CLASSIC_SCRIPT ), $this->enqueued() );
	}

	public function test_block_page_loads_the_block_script_when_the_classic_page_is_configured(): void {
		$this->view_page( self::BLOCK_PAGE, self::CLASSIC_PAGE );

		$this->assertSame( array( self::BLOCK_SCRIPT ), $this->enqueued() );
	}

	public function test_editor_classic_checkout_block_loads_the_classic_script_when_the_block_page_is_configured(): void {
		$this->view_page( self::EDITOR_CLASSIC_PAGE, self::BLOCK_PAGE );

		$this->assertSame( array( self::CLASSIC_SCRIPT ), $this->enqueued() );
	}

	public function test_page_with_neither_falls_back_to_the_configured_page(): void {
		$this->view_page( self::OTHER_PAGE, self::BLOCK_PAGE );
		$this->assertSame( array( self::BLOCK_SCRIPT ), $this->enqueued() );

		$GLOBALS['shift64_test_scripts'] = array();
		$this->view_page( self::OTHER_PAGE, self::CLASSIC_PAGE );
		$this->assertSame( array( self::CLASSIC_SCRIPT ), $this->enqueued() );
	}

	public function test_no_queried_page_falls_back_to_the_configured_page(): void {
		$this->view_page( 0, self::BLOCK_PAGE );

		$this->assertSame( array( self::BLOCK_SCRIPT ), $this->enqueued() );
	}

	private function add_page( int $id, string $content ): void {
		$GLOBALS['shift64_test_pages']['posts'][ $id ] = new \WP_Post( $id, $content );
	}

	private function view_page( int $queried, int $configured ): void {
		$GLOBALS['shift64_test_pages']['queried']  = $queried;
		$GLOBALS['shift64_test_pages']['checkout'] = $configured;
	}

	private function enqueued(): array {
		Assets::enqueue_checkout_scripts();
		return $GLOBALS['shift64_test_scripts'];
	}
}
