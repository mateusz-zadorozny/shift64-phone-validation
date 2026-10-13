<?php
/**
 * Minimal WooCommerce / REST stand-ins for the block checkout tests.
 *
 * phpcs:ignoreFile
 */

namespace Automattic\WooCommerce\StoreApi\Exceptions {
	class RouteException extends \Exception {
		public $error_code;
		public $additional_data;

		public function __construct( $error_code, $message, $http_status_code = 400, $additional_data = array() ) {
			$this->error_code      = $error_code;
			$this->additional_data = $additional_data;
			parent::__construct( $message, $http_status_code );
		}
	}
}

namespace Automattic\WooCommerce\Blocks {
	// Assets loads the block checkout script only when WooCommerce Blocks is present.
	class Package {
	}
}

namespace {
	class WP_REST_Request {
		private $method;
		private $route;
		private $params;

		public function __construct( $method = 'POST', $route = '/wc/store/v1/checkout', array $params = array() ) {
			$this->method = $method;
			$this->route  = $route;
			$this->params = $params;
		}

		public function get_method() {
			return $this->method;
		}

		public function get_route() {
			return $this->route;
		}

		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		public function get_params() {
			return $this->params;
		}
	}

	class WC_Order {
		public $data;
		public $saves = 0;

		public function __construct( array $data = array() ) {
			$this->data = $data + array(
				'billing_phone'    => '',
				'billing_country'  => 'PL',
				'shipping_phone'   => '',
				'shipping_country' => 'PL',
			);
		}

		public function __call( $name, $args ) {
			if ( 0 === strpos( $name, 'get_' ) ) {
				return $this->data[ substr( $name, 4 ) ];
			}
			if ( 0 === strpos( $name, 'set_' ) ) {
				$this->data[ substr( $name, 4 ) ] = $args[0];
			}
		}

		public function save() {
			++$this->saves;
		}
	}
}
