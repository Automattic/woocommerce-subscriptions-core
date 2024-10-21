<?php

class WCS_Subscription_Notifications_Emails_Test extends WP_UnitTestCase {

	/**
	 * Test should send notification.
	 */
	public function test_should_send_notification() {
		add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
		$should = WC_Subscriptions_Email_Notifications::should_send_notification();
		$this->assertTrue( $should );

		$this->disable_notifications_globally();

		$should = WC_Subscriptions_Email_Notifications::should_send_notification();
		$this->assertFalse( $should );
		remove_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
	}

	/**
	 * Test the update time sync.
	 */
	public function test_update_time_sync() {

		// Test installation provisioning.
		$notification_settings_update_timestamp = get_option( 'wcs_notification_settings_update_time' );
		$this->assertTrue( is_numeric( $notification_settings_update_timestamp ) );

		// Update the timestamp.
		$this->enable_notifications_globally(
			[
				'number' => '4',
				'unit'   => 'days',
			]
		);

		$notification_settings_update_timestamp = get_option( 'wcs_notification_settings_update_time' );
		$this->assertTrue( is_numeric( $notification_settings_update_timestamp ) );
		$this->assertLessThanOrEqual( time(), $notification_settings_update_timestamp );
	}

	/**
	 * Test subscription period too short.
	 */
	public function test_subscription_period_too_short() {
		$subscription1 = WCS_Helper_Subscription::create_subscription(
			[
				'status'           => 'active',
				'start_date'       => '2024-09-10 08:08:08',
				'billing_period'   => 'day',
				'billing_interval' => 1,
			]
		);

		$too_short1 = WCS_Action_Scheduler_Customer_Notifications::is_subscription_period_too_short( $subscription1 );
		$this->assertTrue( $too_short1 );

		$subscription2 = WCS_Helper_Subscription::create_subscription(
			[
				'billing_period'   => 'day',
				'billing_interval' => 2,
			]
		);

		$too_short2 = WCS_Action_Scheduler_Customer_Notifications::is_subscription_period_too_short( $subscription2 );
		$this->assertTrue( $too_short2 );

		$subscription3 = WCS_Helper_Subscription::create_subscription(
			[
				'billing_period'   => 'day',
				'billing_interval' => 3,
			]
		);

		$too_short3 = WCS_Action_Scheduler_Customer_Notifications::is_subscription_period_too_short( $subscription3 );
		$this->assertFalse( $too_short3 );
	}

	/**
	 * Test global subscription notification switch.
	 */
	public function test_notifications_globally_enabled() {
		$enabled = WC_Subscriptions_Email_Notifications::notifications_globally_enabled();
		$this->assertTrue( $enabled );

		$this->disable_notifications_globally();
		$enabled = WC_Subscriptions_Email_Notifications::notifications_globally_enabled();
		$this->assertFalse( $enabled );
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
