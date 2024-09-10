<?php

use PHPUnit\Framework\TestCase;

class WCS_Subscription_Notifications_Debug_Tool_Test extends WP_UnitTestCase {

	/**
	 * Test the "WCS_Notifications_Debug_Tool_Processor::process_batch()" method.
	 *
	 * @dataProvider process_batch_notifications_provider
	 *
	 * @param array $data
	 * @return bool
	 */
	public function test_process_batch_notifications( $data ) {

		// Hint: This should be required here (?)
		// update_option(
		//  WC_Subscriptions_Admin::$option_prefix . '_customer_notifications',
		//  array(
		//      'number' => '3',
		//      'unit'   => 'days',
		//  )
		// );

		$subscription = $data['subscription'];
		$action_name  = $data['action_name'];
		$action_args  = [ 'subscription_id' => $subscription->get_id() ];

		$has_notification = false !== as_next_scheduled_action( $action_name, $action_args );
		$this->assertTrue( $has_notification );

		// Remove.
		as_unschedule_action( $action_name, $action_args );

		$has_notification = false !== as_next_scheduled_action( $action_name, $action_args );
		$this->assertFalse( $has_notification );

		// Run the debug processor.
		$processor = new WCS_Notifications_Debug_Tool_Processor();
		$processor->process_batch( [ $subscription->get_id() ] );

		$has_notification = false !== as_next_scheduled_action( $action_name, $action_args );
		$this->assertTrue( $has_notification );
	}

	/**
	 * Data provider for the "test_process_batch_notifications()" method.
	 *
	 * @return array
	 */
	public function process_batch_notifications_provider() {

		/*
		 * Create a simple subscription.
		 */
		$simple_subscription = WCS_Helper_Subscription::create_subscription(
			[
				'billing_period'   => 'month',
				'billing_interval' => 1,
			]
		);

		$simple_subscription->update_status( 'active' );
		$simple_subscription->save();

		/*
		 * Create a free trial subscription.
		 */
		$free_trial_subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'       => 'active',
				'start_date'   => '2024-09-10 08:08:08',
				'date_created' => '2024-09-10 08:08:08',
			]
		);

		$free_trial_subscription->update_dates(
			[
				'trial_end' => '2024-09-20 08:08:08',
				'end'       => '2024-09-20 08:08:08',
			]
		);

		/**
		 * Create an expiry subscription.
		 */
		$expiry_subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'       => 'active',
				'start_date'   => '2024-09-10 08:08:08',
				'date_created' => '2024-09-10 08:08:08',
			]
		);

		$expiry_subscription->update_dates(
			[
				'trial_end' => '2024-09-20 08:08:08',
				'end'       => '2024-09-20 08:08:08',
			]
		);

		return [
			[
				[
					'subscription' => $simple_subscription,
					'action_name'  => 'woocommerce_scheduled_subscription_customer_notification_renewal',
				],
			],
			[
				[
					'subscription' => $free_trial_subscription,
					'action_name'  => 'woocommerce_scheduled_subscription_customer_notification_trial_expiration',
				],
			],
			[
				[
					'subscription' => $expiry_subscription,
					'action_name'  => 'woocommerce_scheduled_subscription_customer_notification_expiration',
				],
			],
		];
	}
}
