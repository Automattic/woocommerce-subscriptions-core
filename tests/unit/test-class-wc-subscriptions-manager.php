<?php

/**
 * Class WC_Subscriptions_Manager_Test
 */
class WC_Subscriptions_Manager_Test extends WP_UnitTestCase {
	/**
	 * Test for `failed_subscription_sign_ups_for_order` method.
	 *
	 * @param string $dispute_meta Dispute meta.
	 * @param string $expected_status Expected subscription status.
	 * @return void
	 * @dataProvider provide_test_failed_subscription_sign_ups_for_order
	 */
	public function test_failed_subscription_sign_ups_for_order( $dispute_meta, $expected_status ) {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'failed' );
		$order->update_meta_data( '_dispute_closed_status', $dispute_meta );
		$order->save();

		$subscription = WCS_Helper_Subscription::create_subscription(
			[
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
				'dispute meta'    => 'won',
				'expected status' => 'on-hold',
			],
			'order failed, dispute lost' => [
				'dispute meta'    => 'lost',
				'expected status' => 'cancelled',
			],
		];
	}
}
