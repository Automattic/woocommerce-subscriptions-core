<?php
class WCS_API_Subscriptions_Test extends WCS_API_Unit_Test_Case {

	/** @var \WC_API_Subscriptions instance */
	protected $endpoint;

	public function setUp() {

		if ( ! defined( 'WC_API_REQUEST_VERSION' ) ) {
			define( 'WC_API_REQUEST_VERSION', 3 );
		}

		parent::setUp();

		$this->endpoint = WC()->api->WC_API_Subscriptions;

		// PHP 7.1+ doesn't allow non-numeric values when using round() function, which is used by WC_Abstract_Order::calculate_totals
		// this filter calls make sure to return an int if the value is an empty string.
		add_filter( 'woocommerce_subscription_get_shipping_total', array( $this, 'return_0_if_empty' ) );
		add_filter( 'woocommerce_subscription_get_cart_tax', array( $this, 'return_0_if_empty' ) );
		add_filter( 'woocommerce_subscription_get_shipping_tax', array( $this, 'return_0_if_empty' ) );
	}

	/**
	 * Test wcs-api route registration
	 *
	 * @since 2.0
	 */
	public function test_registered_routes() {
		$routes = $this->endpoint->register_routes( array() );

		$this->assertArrayHasKey( '/subscriptions', $routes );
		$this->assertArrayHasKey( '/subscriptions/count', $routes );
		$this->assertArrayHasKey( '/subscriptions/statuses', $routes );
		$this->assertArrayHasKey( '/subscriptions/(?P<subscription_id>\d+)', $routes );
		$this->assertArrayHasKey( '/subscriptions/(?P<subscription_id>\d+)/notes', $routes );
		$this->assertArrayHasKey( '/subscriptions/(?P<subscription_id>\d+)/notes/(?P<id>\d+)', $routes );
		$this->assertArrayHasKey( '/subscriptions/(?P<subscription_id>\d+)/orders', $routes );
	}

	/**
	 * Test WC_API_Subscriptions::edit_subscription()
	 *
	 * @group api_tests
	 * @since 2.0
	 */
	public function test_wcs_api_edit_subscription() {
		$this->endpoint->register_routes( array() );

		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'pending' ) );

		// check the subscription is first pending
		$this->assertEquals( 'pending', $subscription->get_status() );

		// request data as if it were sent through API request
		$api_request_data = array(
			'subscription' => array(
				'status' => 'active',
			)
		);

		$response = $this->endpoint->edit_subscription( $subscription->get_id(), $api_request_data );

		if ( version_compare( phpversion(), '5.3', '>=' ) ) {
			$this->assertNotTrue( empty( $response['subscription']['id'] ) );
		}

		$edited_subscription = wcs_get_subscription( $response['subscription']['id'] );
		$this->assertEquals( 'active', $edited_subscription->get_status() );
	}

	/**
	 * Tests setting creating a manual subscription with WC_API_Subscriptions::create_subscription()
	 *
	 * @group api_tests
	 * @since 2.0
	 */
	public function test_wcs_api_create_subscription_manual() {
		$this->endpoint->register_routes( array() );

		$user_id = $this->factory->user->create( array( 'role' => 'shop_manager' ) );

		// no payment method
		$data = array(
			'subscription' => array(
				'status'           => 'active',
				'customer_id'      => $user_id,
				'billing_period'   => 'month',
				'billing_interval' => 1,
			)
		);

		$api_response = $this->endpoint->create_subscription( $data );
		$subscription = wcs_get_subscription( $api_response['creating_subscription']['subscription']['id'] );

		$this->assertEmpty( $api_response['creating_subscription']['subscription']['payment_details']['method_id'] );
		$this->assertTrue( $subscription->is_manual() );

		// manual payment method
		$data['subscription']['payment_details'] = array( 'method_id' => 'manual', 'method_title' => 'Manual' );

		$api_response = $this->endpoint->create_subscription( $data );
		$subscription = wcs_get_subscription( $api_response['creating_subscription']['subscription']['id'] );

		$this->assertEmpty( $api_response['creating_subscription']['subscription']['payment_details']['method_id'] );
		$this->assertTrue( $subscription->is_manual() );
	}

	/**
	 * Tests setting creating a subscription with WC_API_Subscriptions::create_subscription()
	 * and try to set the payment method to something that is not using the `woocommerce_subscription_payment_meta`
	 * filter.
	 *
	 * @group api_tests
	 * @since 2.0
	 */
	public function test_wcs_api_create_subscription_unsupported_payment_method() {
		$this->endpoint->register_routes( array() );

		$data = array(
			'subscription' => array(
				'status'           => 'active',
				'customer_id'      => $this->user_id,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'payment_details'  => array(
					'method_id'    => 'stripe',
					'method_title' => 'Credit Card (Stripe)',
					'post_meta'    => array(
						'_stripe_customer_id' => 'cus_post_stripe_id',
						'_stripe_card_id'     => 'card_post_stripe_card_token',
					),
				),
			)
		);

		$api_response = $this->endpoint->create_subscription( $data );
		$subscription = wcs_get_subscription( $api_response['creating_subscription']['subscription']['id'] );

		$this->assertTrue( ! is_wp_error( $subscription ) && $subscription->is_manual() );
		$this->assertEquals( '', $subscription->get_payment_method() );

		unset( $data['payment_details']['method_id'] );
		$api_response = $this->endpoint->create_subscription( $data );
		$subscription = wcs_get_subscription( $api_response['creating_subscription']['subscription']['id'] );

		$this->assertTrue( ! is_wp_error( $subscription ) && $subscription->is_manual() );
		$this->assertEquals( '', $subscription->get_payment_method() );
	}

	/**
	 * Test creating a subscription with a subscription that uses a payment method
	 * that uses the meta data hook.
	 * We will need a mock of WC_Payment_Gateway to test this functionality - continue manual tests.
	 *
	 * @group api_tests
	 * @since 2.0
	 */
	public function test_wcs_api_create_subscription_supported_payment_method() {
		$this->endpoint->register_routes( array() );

		$data = array(
			'subscription' => array(
				'status'           => 'active',
				'customer_id'      => $this->user_id,
				'billing_period'   => 'month',
				'billing_interval' => 1,
				'payment_details'  => array(
					'method_id'    => 'paypal',
					'method_title' => 'PayPal',
					'post_meta'    => array(),
				),
			)
		);

		$api_response = $this->endpoint->create_subscription( $data );
		$subscription = wcs_get_subscription( $api_response['creating_subscription']['subscription']['id'] );

		$this->assertTrue( $subscription->is_manual() );
		$this->assertEquals( 'paypal', get_post_meta( $subscription->get_id(), '_payment_method', true ) );
		$this->assertEquals( 'PayPal', get_post_meta( $subscription->get_id(), '_payment_method_title', true ) );
	}
}
