<?php

use PHPUnit\Framework\TestCase;

class WCS_Subscription_Notifications_Debug_Tool_Test extends WP_UnitTestCase {

	/**
	 * Test the "WCS_Notifications_Debug_Tool_Processor::process_batch()" method.
	 *
	 * @covers WCS_Notifications_Debug_Tool_Processor::process_batch
	 *
	 * @param array $data
	 * @return bool
	 */
	public function test_process_batch_notifications() {

		// Enable feature.
		update_option( WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$switch_setting_string, 'yes' );
		update_option(
			WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$offset_setting_string,
			[
				'number' => '3',
				'unit'   => 'days',
			]
		);

		$batches = $this->notification_subscription_provider();

		foreach ( $batches as $batch ) {
			$subscription = $batch['subscription'];
			$action_name  = $batch['action_name'];
			$action_args  = [ 'subscription_id' => $subscription->get_id() ];

			$has_notification = false !== as_next_scheduled_action( $action_name, $action_args, 'wcs_customer_notifications' );
			$this->assertTrue( $has_notification );

			// Remove.
			as_unschedule_action( $action_name, $action_args );

			$has_notification = false !== as_next_scheduled_action( $action_name, $action_args, 'wcs_customer_notifications' );
			$this->assertFalse( $has_notification );

			// Run the debug processor.
			$processor = new WCS_Notifications_Debug_Tool_Processor();
			$processor->process_batch( [ $subscription->get_id() ] );

			$has_notification = false !== as_next_scheduled_action( $action_name, $action_args, 'wcs_customer_notifications' );
			$this->assertTrue( $has_notification );
		}
	}

	/**
	 * Data provider for the "test_process_batch_notifications()" method.
	 *
	 * @return array
	 */
	protected function notification_subscription_provider() {

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
	 * Test the WCS_Notifications_Debug_Tool_Processor tool state.
	 */
	public function test_tool_state() {

		$processor = new WCS_Notifications_Debug_Tool_Processor();

		// Get a reflection of private get_tool_state() method.
		$reflection = new ReflectionClass( $processor );
		$get_method = $reflection->getMethod( 'get_tool_state' );
		$get_method->setAccessible( true );

		// Get the update_tool_state() method.
		$update_method = $reflection->getMethod( 'update_tool_state' );
		$update_method->setAccessible( true );

		// Get the delete_tool_state() method.
		$delete_method = $reflection->getMethod( 'delete_tool_state' );
		$delete_method->setAccessible( true );

		// Test initial state of empty array.
		$tool_state = $get_method->invoke( $processor );
		$this->assertIsArray( $tool_state );
		$this->assertFalse( isset( $tool_state['last_offset'] ) );

		// Update the tool state.
		$update_method->invoke( $processor, [ 'last_offset' => 10 ] );

		// Test updated state.
		$tool_state = $get_method->invoke( $processor );
		$this->assertIsArray( $tool_state );
		$this->assertTrue( isset( $tool_state['last_offset'] ) );

		// Delete the tool state.
		$delete_method->invoke( $processor );

		// Test initial state of empty array.
		$tool_state = $get_method->invoke( $processor );
		$this->assertIsArray( $tool_state );
		$this->assertFalse( isset( $tool_state['last_offset'] ) );
	}

	/**
	 * Test the WCS_Notifications_Debug_Tool_Processor tool state.
	 *
	 * Hint: This tests is reusing the same subscriptions created in the previous test.
	 *
	 * @covers WCS_Notifications_Debug_Tool_Processor::get_next_batch_to_process
	 *
	 * @return void
	 */
	public function test_tool_state_while_processing() {

		$this->notification_subscription_provider();
		$processor = new WCS_Notifications_Debug_Tool_Processor();

		// Get a reflection of private get_tool_state() method.
		$reflection = new ReflectionClass( $processor );
		$get_method = $reflection->getMethod( 'get_tool_state' );
		$get_method->setAccessible( true );

		$batch_1 = $processor->get_next_batch_to_process( 2 );

		// Check the initial state.
		$tool_state = $get_method->invoke( $processor );
		$this->assertFalse( isset( $tool_state['last_offset'] ) );

		// Process.
		$processor->process_batch( $batch_1 );

		// Check the state after processing.
		$tool_state = $get_method->invoke( $processor );
		$this->assertTrue( isset( $tool_state['last_offset'] ) );
		$this->assertEquals( 2, $tool_state['last_offset'] );

		$batch_2 = $processor->get_next_batch_to_process( 1 );
		$processor->process_batch( $batch_2 );

		// Check the state after processing.
		$tool_state = $get_method->invoke( $processor );
		$this->assertTrue( isset( $tool_state['last_offset'] ) );
		$this->assertEquals( 3, $tool_state['last_offset'] );

		$batch_3 = $processor->get_next_batch_to_process( 1 );
		$this->assertEmpty( $batch_3 );

		// Check the state after processing.
		$tool_state = $get_method->invoke( $processor );
		$this->assertIsArray( $tool_state );
		$this->assertFalse( isset( $tool_state['last_offset'] ) );
	}
}
