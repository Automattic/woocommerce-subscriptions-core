<?php

use PHPUnit\Framework\TestCase;

class WCS_Subscription_Notification_Test extends WP_UnitTestCase {
	// Mock functions and helpers.

	/**
	 * Mock function to check if an email was sent
	 *
	 * @return bool
	 */
	protected function was_email_sent() {
		return true;
	}

	protected function create_free_trial_subscription( $args = [] ) {
		// Mock function to create a subscription object
		return new WC_Subscription( $args );
	}

	protected function create_renewing_subscription( $args = [] ) {
		// Mock function to create a subscription object
		return new WC_Subscription( $args );
	}

	protected function create_expiring_subscription( $args = [] ) {
		// Mock function to create a subscription object
		return new WC_Subscription( $args );
	}

	// Test cases.

	// I suppose it depends on the type of subscription and the payment method whether
	// the notification will be an expiry, free trial expiry or renewal notification?

	public function notification_type_data_provider() {
		return array();
	}

	/**
	 * Check that notification gets created correctly when subscription is created:
	 *  - free trial: free trial expiry notification
	 *  - renewal notification: paid subscription with automatic renewals
	 *  - expiry notification: paid subscription with manual renewals
	 *
	 * @dataProvider notification_type_data_provider
	 *
	 * @return void
	 */
	public function test_notification_created_when_subscription_created( $subscription, $notification_type, $expected ) {

	}

	/**
	 * Check that notification gets updated correctly when subscription is automatically renewed.
	 *
	 * @return void
	 */
	public function test_notification_updated_when_subscription_auto_renewed() {

	}

	/**
	 * Check that notification gets updated correctly when subscription is manually renewed.
	 *
	 * @return void
	 */
	public function test_notification_updated_when_subscription_manually_renewed() {

	}

	/**
	 * Check that notification gets updated correctly when subscription is up- or downgraded.
	 *
	 * @return void
	 */
	public function test_notification_updated_when_subscription_up_downgraded() {

	}

	/**
	 * Check that free-trial -> paid subscription correctly created a notification.
	 *
	 * @return void
	 */
	public function test_notification_updated_when_subscription_converted_to_paid() {

	}

	/**
	 * Check that notification gets removed when subscription gets cancelled (or do we keep it?).
	 *
	 * @return void
	 */
	public function test_notification_removed_when_subscription_cancelled() {

	}

	/**
	 * Check that notification can be triggered manually.
	 *
	 * @return void
	 */
	public function test_manually_trigger_notification() {

	}

	public function test_auto_notification_adds_order_note() {

	}

	public function test_manual_notification_adds_order_note() {

	}

	/**
	 * Check that store manager can set notification period.
	 *
	 * @return void
	 */
	public function test_set_notification_period() {

	}

	/**
	 * Check that store manager can change notification period and notifications are updated.
	 *
	 * @return void
	 */
	public function test_change_notification_period() {

	}

	/**
	 * Check that notification gets created for all existing subscriptions.
	 *
	 * @return void
	 */
	public function test_notifications_created_for_all_existing_subscriptions() {

	}

	/**
	 * Check that developers can filter notifications.
	 *
	 * @return void
	 */
	public function test_filter_notification() {

	}

	/**
	 * Check that developers can customize email notification.
	 *
	 * @return void
	 */
	public function test_customize_email_content() {

	}

	/**
	 * Check that enabling and disabling of notifications works.
	 *
	 * @return void
	 */
	public function test_enable_disable_notifications() {

	}

	/**
	 * Check that subscription notifications are disabled on staging/non-live sites.
	 *
	 * @return void
	 */
	public function test_disable_notifications_in_staging() {

	}


}

