<?php

/**
 * Test suite for the WCS_Retry_Manager class
 */
class WCS_Retry_Manager_Test extends WCS_Unit_Test_Case {

	static $setting_id;

	protected static $retry_data;

	public static function setUpBeforeClass() {
		self::$setting_id = WC_Subscriptions_Admin::$option_prefix . '_enable_retry';

		self::$retry_data = array(
			'id'       => 0,
			'order_id' => 1235,
			'status'   => 'pending',
			'date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
			'rule_raw' => array(
				'retry_after_interval'            => DAY_IN_SECONDS / 2,
				'email_template_customer'         => 'WCS_Unit_Test_Email_Customer',
				'email_template_admin'            => 'WCS_Unit_Test_Email_Admin',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
		);
	}

	/**
	 * Make sure the stored 'woocommerce_subscriptions_enable_retry' setting enables/disables the retry system
	 */
	public function test_is_retry_enabled_default() {

		// remove the filter added to make sure the retry system was loaded
		remove_filter( 'wcs_is_retry_enabled', '__return_true' );

		$values_to_test = array(
			'no'  => false,
			'yes' => true,
			42    => false,
			'123' => false,
		);

		foreach ( $values_to_test as $value_to_store => $expected_result ) {
			update_option( self::$setting_id, $value_to_store );
			$this->assertEquals( $expected_result, WCS_Retry_Manager::is_retry_enabled() );
		}
	}

	/**
	 * Make sure the 'wcs_is_retry_enabled' can be used to enable/disable the retry system
	 */
	public function test_is_retry_enabled_filtered() {

		add_filter( 'wcs_is_retry_enabled', '__return_true', 10 );
		$this->assertTrue( WCS_Retry_Manager::is_retry_enabled() );
		remove_filter( 'wcs_is_retry_enabled', '__return_true', 10 );

		add_filter( 'wcs_is_retry_enabled', '__return_false', 10 );
		$this->assertFalse( WCS_Retry_Manager::is_retry_enabled() );
		remove_filter( 'wcs_is_retry_enabled', '__return_false', 10 );

		// Now make sure we reinstate the filter
		add_filter( 'wcs_is_retry_enabled', '__return_true', 10 );
	}

	/**
	 * Make sure calling WCS_Retry_Manager::add_retry_date_type() correctly adds the 'payment_retry' date type to an array.
	 */
	public function test_add_retry_date_type() {

		$subscription_date_types = array(
			'first_payment' => 'First Payment Date',
			'next_payment'  => 'Next Payment Date',
			'last_payment'  => 'Last Order Date',
		);

		$subscription_date_types = WCS_Retry_Manager::add_retry_date_type( $subscription_date_types );

		$this->assertArrayHasKey( 'payment_retry', $subscription_date_types );
		$this->assertEquals( 'Renewal Payment Retry', $subscription_date_types['payment_retry'] );

		// Now check to make sure the entry was inserted in the right place
		$next_payment_array_position  = array_search( 'next_payment', array_keys( $subscription_date_types ) );
		$payment_retry_array_position = array_search( 'payment_retry', array_keys( $subscription_date_types ) );

		$this->assertEquals( $next_payment_array_position + 1, $payment_retry_array_position );
	}

	/**
	 * Make sure calling WCS_Retry_Manager::maybe_cancel_retry() cancels the subscription's retry and deletes the 'payment_retry' date
	 * only for appropriate status changes.
	 */
	public function test_maybe_cancel_retry() {

		$old_status = 'on-hold';

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'   => 'on-hold',
		) );

		$order = WCS_Helper_Subscription::create_renewal_order( $subscription );

		$retry_data = self::$retry_data;
		$retry_data['order_id'] = wcs_get_objects_property( $order, 'id' );

		foreach ( array( 'active', 'pending-cancel', 'cancelled', 'switched', 'expired' ) as $new_status ) {

			// Make sure we have a retry and retry date set on the subscription
			$retry_id = WCS_Retry_Manager::store()->save( new WCS_Retry( $retry_data ) );
			$subscription->update_dates( array( 'payment_retry' => $retry_data['date_gmt'] ) );

			WCS_Retry_Manager::maybe_cancel_retry( $subscription, $new_status, $old_status );

			$this->assertEquals( 'cancelled', WCS_Retry_Manager::store()->get_retry( $retry_id )->get_status() );
			$this->assertEquals( 0, wcs_get_subscription( $subscription->get_id() )->get_date( 'payment_retry' ) );
		}

		// Now make sure the date is not deleted when it shouldn't be, including when using a custom status for the subscription in the retry's rule
		$subscription = wcs_get_subscription( $subscription->get_id() );
		$subscription->update_dates( array( 'payment_retry' => $retry_data['date_gmt'] ) );

		foreach ( array( 'on-hold', 'fake-status', 'active' ) as $new_status ) {

			// Make sure we have a retry set on the subscription with this status defined in its rules
			$retry_data['rule_raw']['status_to_apply_to_subscription'] = $new_status;
			$retry_id = WCS_Retry_Manager::store()->save( new WCS_Retry( $retry_data ) );

			// Now test that it isn't deleted on an invalid status
			WCS_Retry_Manager::maybe_cancel_retry( $subscription, $new_status, $old_status );

			// Now make sure we still have a pending retry date
			$this->assertEquals( 'pending', WCS_Retry_Manager::store()->get_retry( $retry_id )->get_status() );
			$this->assertEquals( $retry_data['date_gmt'], $subscription->get_date( 'payment_retry' ) );
		}
	}

	/**
	 * Make sure calling WCS_Retry_Manager::maybe_delete_payment_retry_date() deletes a subscription's 'payment_retry' date
	 * only for appropriate retry status changes.
	 */
	public function test_maybe_delete_payment_retry_date() {

		$status     = 'on-hold';
		$retry_date = date( 'Y-m-d H:i:s', gmdate( 'U' ) + 16 * 60 * 60 );

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'   => 'on-hold',
		) );

		$order = WCS_Helper_Subscription::create_renewal_order( $subscription );

		$retry_data = self::$retry_data;
		$retry_data['order_id'] = wcs_get_objects_property( $order, 'id' );
		$retry_data['date_gmt'] = $retry_date;

		$retry_id = WCS_Retry_Manager::store()->save( new WCS_Retry( $retry_data ) );
		$retry    = WCS_Retry_Manager::store()->get_retry( $retry_id );

		// Make sure the retry date is deleted when the retry's status is changed
		foreach ( array( 'cancelled', 'completed', 'failed', 'custom-status' ) as $new_retry_status ) {

			$subscription->update_dates( array( 'payment_retry' => $retry_date ) );

			WCS_Retry_Manager::maybe_delete_payment_retry_date( $retry, $new_retry_status );

			$this->assertEquals( 0, wcs_get_subscription( $subscription->get_id() )->get_date( 'payment_retry' ) );
		}

		// Now make sure the date is not deleted when it shouldn't be
		$subscription = wcs_get_subscription( $subscription->get_id() );
		$subscription->update_dates( array( 'payment_retry' => $retry_date ) );

		WCS_Retry_Manager::maybe_delete_payment_retry_date( $retry, 'pending' );

		$this->assertEquals( $retry_date, $subscription->get_date( 'payment_retry' ) );

		// Make sure the date is deleted only when the last retry's status is updated, not when an older retry's status is updated by creating a newer retry
		$retry_id  = WCS_Retry_Manager::store()->save( new WCS_Retry( $retry_data ) );

		WCS_Retry_Manager::maybe_delete_payment_retry_date( $retry, 'another-custom-status' );

		$this->assertEquals( $retry_date, $subscription->get_date( 'payment_retry' ) );
	}

	/**
	 * Make sure WCS_Retry_Manager::maybe_apply_retry_rule() applies rules, and only for non-manual subscriptions
	 */
	public function test_maybe_apply_retry_rule() {
		$GLOBALS['wp_current_filter'][] = 'woocommerce_scheduled_subscription_payment';

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status' => 'on-hold',
		) );

		$order = WCS_Helper_Subscription::create_renewal_order( $subscription );

		// First assert a rule isn't applied when $subscription->is_manual().
		WCS_Retry_Manager::maybe_apply_retry_rule( $subscription, $order );
		$this->assertEquals( 0, WCS_Retry_Manager::store()->get_retry_count_for_order( wcs_get_objects_property( $order, 'id' ) ) );

		// Now make sure $subscription->payment_method_supports( 'subscription_date_changes' ) returns true so WCS_Retry_Manager::maybe_apply_retry_rule() applies the retry rule.
		$subscription->set_payment_method( $this->get_mock_payment_gateway() );

		add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );

		for ( $i = 0; $i < 4; ) {
			$i++;

			WCS_Retry_Manager::maybe_apply_retry_rule( $subscription, $order );
			$this->assertEquals( $i, WCS_Retry_Manager::store()->get_retry_count_for_order( wcs_get_objects_property( $order, 'id' ) ) );
		}

		// Retry rules shouldn apply when there's an scheduled payment retry.
		unset( $GLOBALS['wp_current_filter']['woocommerce_scheduled_subscription_payment'] );
		$GLOBALS['wp_current_filter'][] = 'woocommerce_scheduled_subscription_payment_retry';

		WCS_Retry_Manager::maybe_apply_retry_rule( $subscription, $order );
		$this->assertEquals( $i + 1, WCS_Retry_Manager::store()->get_retry_count_for_order( wcs_get_objects_property( $order, 'id' ) ) );

		// Shouldn't apply retry rules when renewals order is manual.
		unset( $GLOBALS['wp_current_filter']['woocommerce_scheduled_subscription_payment_retry'] );

		WCS_Retry_Manager::maybe_apply_retry_rule( $subscription, $order );
		WCS_Retry_Manager::maybe_apply_retry_rule( $subscription, $order );
		$this->assertEquals( $i + 1, WCS_Retry_Manager::store()->get_retry_count_for_order( wcs_get_objects_property( $order, 'id' ) ) );

		remove_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
	}

	/**
	 * Make sure calling WCS_Retry_Manager::maybe_retry_payment() successfully attempts to process a renewal payment
	 * and marks the retry as 'failed' if the payment fails and 'complete' if the payment succeeds.
	 */
	public function test_maybe_retry_payment_complete() {

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'   => 'on-hold',
			'requires_manual_renewal' => 'false',
		) );

		$order = WCS_Helper_Subscription::create_renewal_order( $subscription );

		$retry_data = self::$retry_data;
		$retry_data['order_id'] = wcs_get_objects_property( $order, 'id' );

		// Use a mock 'unit_test_gateway' payment method for handling renewal order payment and make sure the subscription is not seen as requiring manual renewal
		add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
		$subscription->set_payment_method( $this->get_mock_payment_gateway() );
		$order->set_payment_method( $this->get_mock_payment_gateway() ); // We need to pass the payment gateway instance to be compatible with WC < 3.0, only WC 3.0+ supports passing the string name

		// Save the subscription's payment method so processing the retry gets an updated payment method.
		$subscription->save();

		// Ensure a retry is set as 'failed' when the payment doesn't process
		$retry_id = WCS_Retry_Manager::store()->save( new WCS_Retry( $retry_data ) );
		add_action( 'woocommerce_scheduled_subscription_payment_unit_test_gateway', array( $this, 'process_payment_failure' ), 10, 2 );
		WCS_Retry_Manager::maybe_retry_payment( $order );
		$this->assertEquals( 'failed', WCS_Retry_Manager::store()->get_retry( $retry_id )->get_status() );
		remove_action( 'woocommerce_scheduled_subscription_payment_unit_test_gateway', array( $this, 'process_payment_failure' ), 10 );

		// Ensure a retry is set as 'complete' when the payment does process
		$order->update_status( 'pending' );
		$retry_id = WCS_Retry_Manager::store()->save( new WCS_Retry( $retry_data ) );
		add_action( 'woocommerce_scheduled_subscription_payment_unit_test_gateway', array( $this, 'process_payment_complete' ), 10, 2 );
		WCS_Retry_Manager::maybe_retry_payment( $order );
		$this->assertEquals( 'complete', WCS_Retry_Manager::store()->get_retry( $retry_id )->get_status() );
		remove_action( 'woocommerce_scheduled_subscription_payment_unit_test_gateway', array( $this, 'process_payment_complete' ), 10 );
	}

	/**
	 * Make sure calling WCS_Retry_Manager::maybe_retry_payment() when an order or subscriptions status has failed
	 * results in the retry being cancelled, not run.
	 */
	public function test_maybe_retry_payment_cancelled() {

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'   => 'on-hold',
			'order_id' => 813,
		) );

		$order = WCS_Helper_Subscription::create_renewal_order( $subscription );

		$retry_data = self::$retry_data;
		$retry_data['order_id'] = wcs_get_objects_property( $order, 'id' );

		// Ensure a retry is cancelled when the order's status is not the value of 'status_to_apply_to_order'
		$retry_data['rule_raw']['status_to_apply_to_order'] = 'unique_status';
		WCS_Retry_Manager::store()->save( new WCS_Retry( $retry_data ) );
		WCS_Retry_Manager::maybe_retry_payment( $order );
		$this->assertEquals( 'cancelled', WCS_Retry_Manager::store()->get_last_retry_for_order( wcs_get_objects_property( $order, 'id' ) )->get_status() );

		// Ensure a retry is cancelled when the subscription's status is not the value of 'status_to_apply_to_subscription'
		$retry_data['rule_raw']['status_to_apply_to_order'] = 'pending';
		$retry_data['rule_raw']['status_to_apply_to_subscription'] = 'unique_status';
		WCS_Retry_Manager::store()->save( new WCS_Retry( $retry_data ) );
		WCS_Retry_Manager::maybe_retry_payment( $order );
		$this->assertEquals( 'cancelled', WCS_Retry_Manager::store()->get_last_retry_for_order( wcs_get_objects_property( $order, 'id' ) )->get_status() );
	}

	/**
	 * Make sure the WCS_Retry_Manager::store() method returns a valid instance of WCS_Retry_Store
	 */
	public function test_store() {
		$this->assertInstanceOf( 'WCS_Retry_Store', WCS_Retry_Manager::store() );
	}

	/**
	 * Make sure the WCS_Retry_Manager::get_store_class() method returns the correct default class
	 */
	public function test_get_store_class_default() {
		$this->check_get_class( 'WCS_Retry_Database_Store', 'get_store_class' );
	}

	/**
	 * Make sure the 'wcs_retry_store_class' filter can be used to modify the class returned by the
	 * WCS_Retry_Manager::get_store_class() method
	 */
	public function test_get_store_class_filtered() {
		$this->check_get_class( 'WCS_Retry_Database_Store', 'get_store_class', 'wcs_retry_store_class' );
	}

	/**
	 * Make sure the WCS_Retry_Manager::rules() method returns a valid instance of WCS_Retry_Rules
	 */
	public function test_rules() {
		$this->assertInstanceOf( 'WCS_Retry_Rules', WCS_Retry_Manager::rules() );
	}

	/**
	 * Make sure the WCS_Retry_Manager::get_rules_class() method returns the correct default class
	 */
	public function test_get_rules_class_default() {
		$this->check_get_class( 'WCS_Retry_Rules', 'get_rules_class' );
	}

	/**
	 * Make sure the 'wcs_retry_rules_class' filter can be used to modify the class returned by the
	 * WCS_Retry_Manager::get_rules_class() method
	 */
	public function test_get_rules_class_filtered() {
		$this->check_get_class( 'WCS_Retry_Rules', 'get_rules_class', 'wcs_retry_rules_class' );
	}

	/**
	 * A helper method to check the return value of a protected WCS_Retry_Manager::get_*_class() method
	 */
	protected function check_get_class( $class_name, $method_name, $hook = '' ) {

		if ( ! method_exists( 'ReflectionMethod', 'setAccessible' ) ) { // only available in PHP 5.3.2+
			return;
		}

		$method = $this->get_accessible_protected_method( 'WCS_Retry_Manager', $method_name );

		if ( ! empty( $hook ) ) {
			tests_add_filter( $hook, array( $this, 'filter_class_name' ) );

			$class_name = $this->filter_class_name( $class_name );
		}

		$this->assertEquals( $class_name, $method->invoke( null ) );
	}

	/**
	 * Callback for testing filters that modifies the $current_class with expected output
	 */
	public function filter_class_name( $current_class ) {
		return $current_class . '_Test';
	}

	/**
	 * A method for mocking payment failing on an order.
	 */
	public function process_payment_failure( $order_total, $order ) {
		$order->update_status( 'failed' );
	}

	/**
	 * A method for mocking payment being processed on an order.
	 */
	public function process_payment_complete( $order_total, $order ) {
		$order->payment_complete();
	}
}
