<?php

class WCS_Subscription_Notifications_Processor_Test extends WP_UnitTestCase {

	/**
	 * Test processor queued when notifications are enabled.
	 */
	public function test_processor_queued() {
		$batch_processor = WCS_Batch_Processing_Controller::instance();

		// Test initial state.
		$this->assertTrue( $batch_processor->is_enqueued( WCS_Notifications_Batch_Processor::class ) );
		// Run.
		do_action( 'wcs_run_batch_process', WCS_Notifications_Batch_Processor::class );

		// Check that is dequeued.
		$this->assertFalse( $batch_processor->is_enqueued( WCS_Notifications_Batch_Processor::class ) );

		// Add some subscriptions.
		$this->notification_subscription_data_provider();

		// Change it.
		$this->enable_notifications_globally(
			[
				'number' => '4',
				'unit'   => 'days',
			],
		);
		$this->assertTrue( $batch_processor->is_enqueued( WCS_Notifications_Batch_Processor::class ) );

		// Run.
		do_action( 'wcs_run_batch_process', WCS_Notifications_Batch_Processor::class );

		// Check that is dequeued.
		$this->assertFalse( $batch_processor->is_enqueued( WCS_Notifications_Batch_Processor::class ) );
	}

	/**
	 * Test getting the total pending count and the processor flow.
	 */
	public function test_get_total_pending_count_and_flow() {
		$this->notification_subscription_data_provider();
		$processor     = new WCS_Notifications_Batch_Processor();
		$pending_count = $processor->get_total_pending_count();
		$this->assertEquals( 0, $pending_count );

		// Change it.
		// Give some time to differenciate the creation timestamp from the setting timestamp.
		sleep( 1 );
		$this->enable_notifications_globally(
			[
				'number' => '4',
				'unit'   => 'days',
			],
		);

		// Test again.
		$pending_count = $processor->get_total_pending_count();
		$this->assertEquals( 3, $pending_count );

		// Process the batch.
		$batch = $processor->get_next_batch_to_process( 2 );
		$processor->process_batch( $batch );

		// Test again.
		$pending_count = $processor->get_total_pending_count();
		$this->assertEquals( 1, $pending_count );

		// Process the batch.
		$batch = $processor->get_next_batch_to_process( 1 );
		$processor->process_batch( $batch );

		// Test again.
		$pending_count = $processor->get_total_pending_count();
		$this->assertEquals( 0, $pending_count );

		// Test that batch is empty.
		$batch = $processor->get_next_batch_to_process( 1 );
		$this->assertEmpty( $batch );
	}

	/**
	 * Test processing batch notifications.
	 */
	public function test_process_batch_notifications() {
		$batches   = $this->notification_subscription_data_provider();
		$processor = new WCS_Notifications_Debug_Tool_Processor();

		// Test that the notifications are scheduled.
		foreach ( $batches as $batch ) {
			$subscription = $batch['subscription'];
			$action_name  = $batch['action_name'];
			$action_args  = \WC_Subscriptions_Core_Plugin::instance()->notifications_scheduler::get_action_args( $subscription );

			// First iteration doesn't have the notification scheduled, since the feature was disabled during the creation.
			$has_notification = false !== as_next_scheduled_action( $action_name, $action_args, 'wcs_customer_notifications' );
			$this->assertTrue( $has_notification );

			// Unschedule the notification.
			as_unschedule_action( $action_name, $action_args, 'wcs_customer_notifications' );

			$processor->process_batch( [ $subscription->get_id() ] );

			$has_notification = false !== as_next_scheduled_action( $action_name, $action_args, 'wcs_customer_notifications' );
			$this->assertTrue( $has_notification );
		}

		$this->disable_notifications_globally();

		// Test now the actions are getting unscheduled.
		foreach ( $batches as $batch ) {
			$subscription = $batch['subscription'];
			$action_name  = $batch['action_name'];
			$action_args  = [ 'subscription_id' => $subscription->get_id() ];

			// No processor this time. It's a bulk update while disabling the feature.
			$has_notification = false !== as_next_scheduled_action( $action_name, $action_args, 'wcs_customer_notifications' );
			$this->assertFalse( $has_notification );
		}
	}

	/**
	 * Data provider for the "test_process_batch_notifications()" method.
	 *
	 * @return array
	 */
	protected function notification_subscription_data_provider() {

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

		$simple_subscription->update_dates(
			[
				'next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
			]
		);

		/*
		 * Create a free trial subscription.
		 */
		$free_trial_subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'     => 'active',
				'start_date' => gmdate( 'Y-m-d H:i:s' ),
			]
		);

		$free_trial_subscription->update_dates(
			[
				'trial_end' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
			]
		);

		/**
		 * Create an expiry subscription.
		 */
		$expiry_subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'     => 'active',
				'start_date' => gmdate( 'Y-m-d H:i:s' ),
			]
		);

		$expiry_subscription->update_dates(
			[
				'end' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
			]
		);

		return [
			[
				'subscription' => $simple_subscription,
				'action_name'  => 'woocommerce_scheduled_subscription_customer_notification_renewal',
			],
			[
				'subscription' => $free_trial_subscription,
				'action_name'  => 'woocommerce_scheduled_subscription_customer_notification_trial_expiration',
			],
			[
				'subscription' => $expiry_subscription,
				'action_name'  => 'woocommerce_scheduled_subscription_customer_notification_expiration',
			],
		];
	}

	/**
	 * Helper to enable notifications globally.
	 * TODO: We should create global helpers?
	 *
	 * @return void
	 */
	protected function enable_notifications_globally(
		$default_value = [
			'number' => '3',
			'unit'   => 'days',
		]
	) {
		update_option( WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$switch_setting_string, 'yes' );
		update_option(
			WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$offset_setting_string,
			$default_value
		);
	}

	/**
	 * Helper to enable notifications globally.
	 *
	 * @return void
	 */
	protected function disable_notifications_globally() {
		update_option( WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$switch_setting_string, 'no' );
	}
}
