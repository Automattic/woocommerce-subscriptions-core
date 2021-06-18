<?php
/**
 *
 * @see WC_Unit_Test_Case::setUp()
 * @since 2.0
 */
class WCS_Unit_Test_Case extends WC_Unit_Test_Case {

	/* Susbcription product used for testing */
	public static $simple_subscription_product;

	/* User used throughout testing */
	public $user_id = 1;

	/**
	 * Setup the test case suite
	 *
	 * @since 2.0
	 */
	public function setUp() {
		parent::setUp();
		wp_set_current_user( $this->user_id );

		// We need the PayPal gateway for a lot of tests. In WC 3.3.0 PayPal is disabled by default, so we need to make sure it's enabled.
		if ( ! wcs_is_woocommerce_pre( '3.3.0' ) ) {
			$gateways = WC()->payment_gateways->payment_gateways();
			$gateways['paypal']->enabled = 'yes';
		}
	}

	/**
	 * Setup the test case suite
	 *
	 * @since 2.0
	 */
	public function tearDown() {
		parent::tearDown();

		// Delete line items between tests.
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_order_items" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_order_itemmeta" );

		// Clear some known singleton instances between tests.
		$this->clear_singleton_instance( 'WCS_Customer_Store' );

		wp_set_current_user( 0 );
	}

	/**
	 * Add default shipping to the subscription
	 *
	 * @since 2.0
	 */
	public function add_default_shipping_to( $subscription ) {
		if ( $subscription instanceof WC_Subscription ) {
			$default_shipping = array(
				'method_id' => 'free_shipping',
				'cost' => 0,
				'method_title' => 'Free Shipping',
			);

			$shipping_rate = new WC_Shipping_Rate( $default_shipping['method_id'], $default_shipping['method_title'], floatval( $default_shipping['cost'] ), array(), $default_shipping['method_id'] );

			$subscription->add_shipping( $shipping_rate );
		}
	}

	/**
	 * Add default fee to subscription.
	 *
	 * @since 2.0
	 */
	public function add_default_fee_to( $subscription ) {
		$subscription_fee = new stdClass();

		// setup default fee object
		$subscription_fee->name     = 'Default Subscription Fee';
		$subscription_fee->id       = sanitize_title( $subscription_fee->name );
		$subscription_fee->amount   = floatval( 5 );
		$subscription_fee->taxable  = false;
		$subscription_fee->tax      = 0;
		$subscription_fee->tax_data = array();

		$fee_id = $subscription->add_fee( $subscription_fee );
	}

	/**
	 * Add Default taxes to subscription
	 *
	 * @since 2.0
	 */
	public function add_default_tax_to( $subscription ) {
		$tax_rate = array(
			'tax_rate'          => '10.0000',
			'tax_rate_name'     => 'Default',
			'tax_rate_priority' => '1',
			'tax_rate_compound' => '0',
			'tax_rate_shipping' => '1',
			'tax_rate_order'    => '1',
			'tax_rate_class'    => ''
		);
		$tax_rate_id = WC_Tax::_insert_tax_rate( $tax_rate );

		$subscription_tax = $subscription->add_tax( $tax_rate_id, 10, 0 );
	}

	/**
	 * Force WC_Subscription::completed_payment_count() to return 10. This is to test almost every condition
	 * within WC_Subscription::can_date_be_updated();
	 *
	 * @since 2.0
	 */
	public function completed_payment_count_stub() {
		return 10;
	}

	/**
	 * Forces WC_Subscription::payment_method_supports( $feature ) to always return false. This is to
	 * help test more of the logic within WC_Subscription::can_be_updated_to().
	 *
	 * @since 2.0
	 */
	public function payment_method_supports_false() {
		return false;
	}

	public function assertDateTimeString( $actual, $message = '' ) {
		$this->assertRegExp( '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $actual, $message );
	}

	/**
	 * Return a mocked WC_Subscription that supports all necessary features to use with testing subscription features
	 *
	 * For handy docs on mocks, stubs and test doubles, see https://jtreminio.com/2013/03/unit-testing-tutorial-part-5-mock-methods-and-overriding-constructors/#the-four-pathways-of-getmockbuilder
	 *
	 * @param $subscription Instance of a WC_Subscription of WC_Subscription_Legacy object
	 * @param $set_methods null|array The methods to stub (i.e. return null) or mock (i.e. function as normal). Default null, mocks all methods. Use empty array() to stub all methods or array( 'method_name', 'function_name' ) to stub named methods and leave all others as mocks. Remember, only stubbed methods can be overridden.
	 * @since 2.2.0
	 */
	protected function get_mock_subscription( $subscription_id = 0, $set_methods = null ) {

		$subscription_class = wcs_is_woocommerce_pre( '3.0' ) ? 'WC_Subscription_Legacy' : 'WC_Subscription';

		$mock_subscription = $this->getMockBuilder( $subscription_class )->disableOriginalConstructor()->setMethods( $set_methods )->getMock();
		$mock_subscription->set_id( $subscription_id );

		return $mock_subscription;
	}

	/**
	 * Return a mocked payment gateway that supports all necessary features to use with testing subscription features
	 *
	 * @param $supports_map array Multidimensions array/map of the features the mock gateway should support. e.g. array( array( 'subscriptions', true ) ). If not passed in, it defaults to null, which will declare support for all features except for 'gateway_scheduled_payments'.
	 * @since 2.1
	 */
	protected function get_mock_payment_gateway( $supports_map = null ) {

		if ( null === $supports_map ) {
			$supports_map = array(
				array( 'subscriptions', true ),
				array( 'multiple_subscriptions', true ),
				array( 'subscription_suspension', true ),
				array( 'subscription_reactivation', true ),
				array( 'subscription_cancellation', true ),
				array( 'subscription_date_changes', true ),
				array( 'subscription_amount_changes', true ),
				array( 'subscription_payment_method_change_customer', true ),
				array( 'subscription_payment_method_change_admin', true ),
				array( 'gateway_scheduled_payments', false ),
			);
		}

		$mock_payment_gateway = $this->getMockBuilder( 'WC_Payment_Gateway' )->disableOriginalConstructor()->getMock();

		$mock_payment_gateway->id = 'unit_test_gateway';

		$mock_payment_gateway->expects( $this->any() )->method( 'supports' )->will( $this->returnValueMap( $supports_map ) );
		$mock_payment_gateway->expects( $this->any() )->method( 'is_available' )->will( $this->returnValue( true ) );
		$mock_payment_gateway->expects( $this->any() )->method( 'get_title' )->will( $this->returnValue( 'Unit Test Gateway' ) );

		// Make sure the gateway is added to WC's set of gateways as they are use for validation in an assortment of places
		WC()->payment_gateways->payment_gateways[ $mock_payment_gateway->id ] = $mock_payment_gateway;

		return $mock_payment_gateway;
	}

	/**
	 * A utility function to make certain methods public, useful for testing protected methods
	 * that affect public APIs, but are not public to avoid use due to potential confusion, like
	 * like @see WCS_Retry_Manager::get_store_class() & WCS_Retry_Manager::get_rules_class(), both
	 * of which are important to test to ensure that @see WCS_Retry_Manager::get_store() and @see
	 * WCS_Retry_Manager::get_rules() return correct custom classes when filtered, which can not
	 * be tested due to the use of static properties to store them.
	 *
	 * @return ReflectionMethod
	 */
	protected function get_accessible_protected_method( $object, $method_name ) {

		$reflected_object = new ReflectionClass( $object );
		$reflected_method = $reflected_object->getMethod( $method_name );

		$reflected_method->setAccessible( true );

		return $reflected_method;
	}

	/**
	 * A utility function to clear the $instance var in singleton classes.
	 *
	 * @param string $singleton_class The singleton class name.
	 * @since 2.3.4
	 */
	protected function clear_singleton_instance( $singleton_class ) {
		$reflected_singleton         = new ReflectionClass( $singleton_class );
		$reflected_instance_property = $reflected_singleton->getProperty( 'instance' );
		$reflected_instance_property->setAccessible( true );
		$reflected_instance_property->setValue( null, null );
	}

	/**
	 * if the $value is empty, it returns 0.
	 *
	 * @param mixed $value
	 *
	 * @return mixed|int
	 */
	public function return_0_if_empty( $value ) {
		if ( empty( $value ) ) {
			return 0;
		}

		return $value;
	}

	/**
	 * @param mixed $__
	 *
	 * @return Closure
	 */
	public function return__( $__ ) {
		return function () use ( $__ ) {
			return $__;
		};
	}
}
