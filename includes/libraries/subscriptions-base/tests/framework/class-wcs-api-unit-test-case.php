<?php
/**
 * Subscriptions Test Case Framework for wcs-api
 *
 * @package	 WooCommerce Subscriptions
 * @category Class
 * @author	 Prospress
 * @since	 2.0
 */
class WCS_API_Unit_Test_Case extends WCS_Unit_Test_Case {

	public function setUp() {

		//$this->load_resources();

		parent::setUp();

		WC()->api->includes();

		$_SERVER['REQUEST_METHOD'] = null;

		if ( method_exists( $this, 'createMock' ) ) { // PHPUnit 5.4+
			$this->mock_server = $this->createMock( 'WC_API_Server', array( 'header' ), array( '/' ) );
		} else { // PHPUnit < 5.4
			$this->mock_server = $this->getMock( 'WC_API_Server', array( 'header' ), array( '/' ) );
		}

		WC()->api->register_resources( $this->mock_server );

	}

	/**
	 * Load all the required WooCommerce and Subscriptions API Classes
	 *
	 * @since 2.0
	 */
	public function load_resources() {

		$bootstrap = WCS_Unit_Tests_Bootstrap::instance();

		require_once( $bootstrap->plugin_dir . '/includes/api/class-wc-api-subscriptions.php' );
		require_once( $bootstrap->modules_dir . '/woocommerce/includes/api/class-wc-api-orders.php' );
		require_once( $bootstrap->modules_dir . '/woocommerce/includes/api/class-wc-api-exception.php' );
		require_once( $bootstrap->modules_dir . '/woocommerce/includes/api/class-wc-api-server.php' );
		require_once( $bootstrap->modules_dir . '/woocommerce/includes/api/class-wc-api-resource.php' );


	}
}
