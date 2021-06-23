<?php

/**
 * Class: WCS_Webhooks
 */
class WCS_Webhooks_Test extends WCS_API_Unit_Test_Case {

	private $mock_api_subscription = array(
		// Unique key in order to check payload returned is correct one
		'zebra' => null,
	);

	/**
	 * Setup test class.
	 */
	public function setUp() {
		if ( version_compare( PHP_VERSION, '5.6.0', '<' ) ) {
			$this->markTestSkipped( 'The WC API is disabled unless running PHP 5.6+' );
		}
	}

	/**
	* Test add_topics()
	*
	*/
	public function test_add_topics() {

		// mock webhook
		$webhook = $this->getMockBuilder( 'WC_Webhook' )->disableOriginalConstructor()->getMock();

		$topic_hooks = array();

		$webhook->expects( $this->any() )->method( 'get_resource' )->will( $this->onConsecutiveCalls( 'subscription', 'notSubscription' ) );

		// if subscription, add subscription topics
		$topics = WCS_Webhooks::add_topics( $topic_hooks, $webhook );

		$this->assertArrayHasKey( 'subscription.created', $topics );
		$this->assertArrayHasKey( 'subscription.updated', $topics );
		$this->assertArrayHasKey( 'subscription.deleted', $topics );

		// if not subscription, don't add subscription topics
		$topics = WCS_Webhooks::add_topics( $topic_hooks, $webhook );

		$this->assertArrayNotHasKey( 'subscription.created', $topics );
		$this->assertArrayNotHasKey( 'subscription.updated', $topics );
		$this->assertArrayNotHasKey( 'subscription.deleted', $topics );
	}

	/**
	* Test add_topics_admin_menu()
	*/
	public function test_add_topics_admin_menu() {

		$topics = array();

		$expected_topics = WCS_Webhooks::add_topics_admin_menu( $topics );

		$this->assertArrayHasKey( 'subscription.created', $expected_topics );
		$this->assertArrayHasKey( 'subscription.updated', $expected_topics );
		$this->assertArrayHasKey( 'subscription.deleted', $expected_topics );
	}

	/**
	* Data provider for test_create_payload()
	*/
	public function provider_test_create_payload() {

		return array(
			// resource is a subscription, payload empty, resource_id is for a subscription
			array( array(), 'subscription', true, true ),
			// resource is not a subscription
			array( array(), 'notSubscription', true, false ),
			// payload not empty
			array( array( 'got something' ), 'subscription', true, false ),
			// resource_id not for a subscription
			array( array(), 'subscription', false, false ),
		);
	}

	/**
	* Test create_payload()
	* @param array $payload
	* @param string $resource String indicating if a subscription
	* @param object|int $resource_id WC_Subscription object | subscription id
	* @param int $id WC_Subscription id
	* @param bool $create whether payload should be created or not
	* @dataProvider provider_test_create_payload()
	*/
	public function test_create_payload( $payload, $resource, $mock_subscription, $create ) {

		if ( true === $mock_subscription ) {
			// create mock subscription
			$subscription = WCS_Helper_Subscription::create_subscription();
			$id = $subscription->get_id();
		} else {
			$subscription = null;
			$id = null;
		}

		// mock get_subscription() for webhook API legacy_v3
		$s_api = $this->getMockBuilder( 'WC_API_Subscriptions' )->disableOriginalConstructor()->getMock();
		WC()->api->WC_API_Subscriptions = $s_api;
		WC()->api->WC_API_Subscriptions->expects( $this->any() )->method( 'get_subscription' )->will( $this->returnValue( $this->mock_api_subscription ) );

		// mock get_subscription() for webhook API v1/v2.
		if ( $create ) {
			add_filter( 'woocommerce_rest_prepare_shop_subscription_object', array( $this, 'mock_api_subscription' ), 11, 3 );
		}

		// Bypass the user permission check when generating the payload.
		add_filter( 'woocommerce_rest_check_permissions', array( $this, 'bypass_permission_check' ), 10, 4 );
		$result = WCS_Webhooks::create_payload( $payload, $resource, $subscription ? $subscription->get_id() : $subscription, $id );
		remove_filter( 'woocommerce_rest_check_permissions', array( $this, 'bypass_permission_check' ) );

		if ( $create ) {
			$this->assertArrayHasKey( 'zebra', $result );
		} else {
			$this->assertArrayNotHasKey( 'zebra', $result );
		}
	}

	/*
	* Test add_resource()
	*/
	public function test_add_resource() {

		// for empty resources array
		$resources = array();

		$this->assertContains( 'subscription', WCS_Webhooks::add_resource( $resources ) );

		// for filled resources array
		$resources = array( 'has', 'some', 'elements', 'already', );

		$this->assertContains( 'subscription', WCS_Webhooks::add_resource( $resources ) );
	}

	/*
	* Mock the object returned while preparing the subscription during API requests.
	*/
	public function mock_api_subscription( $response, $object, $request ) {
		if ( wcs_is_subscription( $object ) ) {
			$response->data = $this->mock_api_subscription;
		}

		return $response;
	}

	/**
	 * Bypass the REST API permission check for the logged in user.
	 * @return bool
	 */
	public function bypass_permission_check( $permission, $context, $object_id, $post_type ) {
		if ( 'shop_subscription' === $post_type ) {
			$permission = true;
		}

		return $permission;
	}
}
