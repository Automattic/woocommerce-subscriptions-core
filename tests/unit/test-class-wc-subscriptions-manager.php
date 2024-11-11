<?php

/**
 * Class WC_Subscriptions_Manager_Test
 */
class WC_Subscriptions_Manager_Test extends WP_UnitTestCase {
	/**
	 * Test for `failed_subscription_sign_ups_for_order` method.
	 *
	 * @param string $initial_status Initial subscription status.
	 * @param string $expected_status Expected subscription status.
	 * @return void
	 * @dataProvider provide_test_failed_subscription_sign_ups_for_order
	 * @group test_failed_subscription_sign_ups_for_order
	 */
	public function test_failed_subscription_sign_ups_for_order( $initial_status, $expected_status ) {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'failed' );
		$order->save();

		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'   => $initial_status,
				'order_id' => $order->get_id(),
			]
		);

		WC_Subscriptions_Manager::failed_subscription_sign_ups_for_order( $order->get_id() );

		// Reload the subscription.
		$subscription = wcs_get_subscription( $subscription->get_id() );
		$this->assertTrue( $subscription->has_status( $expected_status ) );
	}

	/**
	 * Provider for `test_failed_subscription_sign_ups_for_order` method.
	 *
	 * @return array
	 */
	public function provide_test_failed_subscription_sign_ups_for_order() {
		return [
			'order failed, dispute won'  => [
				'initial status'  => 'active',
				'expected status' => 'on-hold',
			],
			'order failed, dispute lost' => [
				'initial status'  => 'pending-cancel',
				'expected status' => 'cancelled',
			],
		];
	}
}
