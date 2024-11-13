<?php

/**
 * Class: WC_Subscriptions_Get_Date_Test
 */
class WC_Subscriptions_Test extends WP_UnitTestCase {

	/** An array of basic subscriptions used to test against */
	private $subscriptions = [];

	/**
	 * Setup the suite for testing the WC_Subscription class
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function set_up() {
		parent::set_up();
		$this->subscriptions = WCS_Helper_Subscription::create_subscriptions();
	}

	/**
	 * Forces WC_Subscription::payment_method_supports( $feature ) to always return false. This is to
	 * help test more of the logic within WC_Subscription::can_be_updated_to().
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 * @return false
	 */
	public function payment_method_supports_false() {
		return false;
	}

	/**
	 * Force WC_Subscription::completed_payment_count() to return 10. This is to test almost every condition
	 * within WC_Subscription::can_date_be_updated();
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function completed_payment_count_stub() {
		return 10;
	}


	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-pending' );
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_be_updated_to_pending() {

		$expected_results = [
			'pending'        => false,
			'active'         => false,
			'on-hold'        => false,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_be_updated_to( 'pending' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to pending.' );

			$actual_result = $subscription->can_be_updated_to( 'wc-pending' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to wc-pending.' );
		}
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-active' );
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_be_updated_to_active() {

		$expected_results = [
			'pending'   => true,
			'active'    => false,
			'on-hold'   => true,
			'cancelled' => false,
			'expired'   => false,
			'switched'  => false,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {

			if ( ! isset( $expected_results[ $status ] ) ) {
				continue;
			}

			$subscription->update_dates( [ 'end' => gmdate( 'Y-m-d H:i:s', wcs_add_months( time(), 1 ) ) ] );

			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_be_updated_to( 'active' );

			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to active.' );

			$actual_result = $subscription->can_be_updated_to( 'wc-active' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to wc-active.' );
		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );

		$this->assertFalse( $this->subscriptions['on-hold']->can_be_updated_to( 'active' ), '[FAILED]: Should not be able to activate an on-hold subscription if the payment gateway does not support it.' );
		$this->assertTrue( $this->subscriptions['pending']->can_be_updated_to( 'active' ), '[FAILED]: Should be able to update pending status to active if the payment method does not support subscription reactivation.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );
	}

	/**
	 * Test for `can_be_updated_to` with the `active` status using different end dates.
	 * `pending-cancel` and `on-hold` subscriptions cannot be reactivated when the end date is in the past.
	 *
	 * @param $status string The subscription status to test
	 * @return void
	 * @dataProvider provide_test_can_be_updated_to_active_with_different_end_dates
	 */
	public function test_can_be_updated_to_active_with_different_end_dates( $status ) {
		$subscription = $this->subscriptions[ $status ];

		// End date in the future
		$subscription->update_dates( [ 'end' => gmdate( 'Y-m-d H:i:s', wcs_add_months( time(), 1 ) ) ] );
		$can_be_updated = $subscription->can_be_updated_to( 'active' );

		$this->assertTrue( $can_be_updated, '[FAILED]: ' . $status . ' to active.' );

		$can_be_updated = $subscription->can_be_updated_to( 'wc-active' );
		$this->assertTrue( $can_be_updated, '[FAILED]: ' . $status . ' to wc-active.' );

		// End date in the past
		$subscription->update_dates( [ 'end' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ] );
		$can_be_updated = $subscription->can_be_updated_to( 'active' );

		$this->assertFalse( $can_be_updated, '[FAILED]: ' . $status . ' to active.' );

		$can_be_updated = $subscription->can_be_updated_to( 'wc-active' );
		$this->assertFalse( $can_be_updated, '[FAILED]: ' . $status . ' to wc-active.' );
	}

	/**
	 * Provider for `test_can_be_updated_to_active_with_different_end_dates`.
	 *
	 * @return array
	 */
	public function provide_test_can_be_updated_to_active_with_different_end_dates() {
		return [
			[ 'pending-cancel' ],
			[ 'on-hold' ],
		];
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-on-hold' );
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_be_updated_to_onhold() {
		$expected_results = [
			'pending'        => true,
			'active'         => true,
			'on-hold'        => false,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_be_updated_to( 'on-hold' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to on-hold.' );

			$actual_result = $subscription->can_be_updated_to( 'wc-on-hold' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to wc-on-hold.' );

		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );

		$this->assertEquals( false, $this->subscriptions['active']->can_be_updated_to( 'on-hold' ), '[FAILED]: Should not be able to put subscription on-hold if the payment gateway does not support it.' );
		$this->assertEquals( false, $this->subscriptions['pending']->can_be_updated_to( 'on-hold' ), '[FAILED]: Should be able to update pending status on-hold if the payment method does not support subscription suspension.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-cancelled' );
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_be_updated_to_cancelled() {
		$expected_results = [
			'pending'        => true,
			'active'         => true,
			'on-hold'        => true,
			'cancelled'      => false,
			'pending-cancel' => true, // subscription has pending-cancel and has not yet ended
			'expired'        => false,
			'switched'       => false,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_be_updated_to( 'wc-cancelled' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to wc-cancelled.' );

			$actual_result = $subscription->can_be_updated_to( 'cancelled' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to cancelled.' );

		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );

		$this->assertEquals( false, $this->subscriptions['pending-cancel']->can_be_updated_to( 'cancelled' ) );
		$this->assertEquals( false, $this->subscriptions['active']->can_be_updated_to( 'cancelled' ) );
		$this->assertEquals( false, $this->subscriptions['pending']->can_be_updated_to( 'cancelled' ) );
		$this->assertEquals( false, $this->subscriptions['on-hold']->can_be_updated_to( 'cancelled' ) );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-switched' );
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_be_updated_to_switched() {
		$expected_results = [
			'pending'        => false,
			'active'         => false,
			'on-hold'        => false,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false, // should statuses be able to be updated to their previous status ?!
		];

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_be_updated_to( 'wc-switched' );

			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to wc-switched.' );
		}
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-expired' );
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_be_updated_to_expired() {
		$expected_results = [
			'pending'        => true,
			'active'         => true,
			'on-hold'        => true,
			'cancelled'      => false,
			'pending-cancel' => true,
			'expired'        => true,
			'switched'       => false,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_be_updated_to( 'wc-expired' );

			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Updating subscription (' . $status . ') to wc-expired.' );
		}
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-pending-cancel' );
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_be_updated_to_pending_cancellation() {
		$expected_results = [
			'pending'        => false,
			'active'         => true,
			'on-hold'        => true,
			'cancelled'      => true,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_be_updated_to( 'pending-cancel' );

			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to pending-cancel.' );
		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );

		$this->assertEquals( false, $this->subscriptions['active']->can_be_updated_to( 'pending-cancel' ), '[FAILED]: Active Subscription statuses cannot be updated to pending-cancel if the payment method does not support it.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'trash' );
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_be_updated_to_trash() {
		$expected_results = [
			'pending'        => true,
			'active'         => true,
			'on-hold'        => true,
			'cancelled'      => true,
			'pending-cancel' => true,
			'expired'        => true,
			'switched'       => true,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			// although wc-trash is not a legitimate status, it should still work
			$actual_result = $subscription->can_be_updated_to( 'wc-trash' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to trash.' );

		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );

		$this->assertEquals( false, $this->subscriptions['active']->can_be_updated_to( 'trash' ), '[FAILED]: Should not be able to  move active subscription to the trash if the payment method does not support it.' );
		$this->assertEquals( false, $this->subscriptions['pending']->can_be_updated_to( 'trash' ), '[FAILED]: Should not be able to move a Pending subscription with a payment method that does not support subscription cancellation to the trash.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'deleted' );
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_be_updated_to_deleted() {
		$expected_results = [
			'pending'        => false,
			'active'         => false,
			'on-hold'        => false,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {

			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_be_updated_to( 'deleted' );
			$this->assertEquals( $expected_result, $actual_result );

		}
	}

	/**
	 * Test case testing what happens when a unexpected status is entered.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_be_updated_to_other() {
		$expected_results = [
			'pending'        => false,
			'active'         => false,
			'on-hold'        => false,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_be_updated_to( 'fgsdyfg' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Should not be able to update subscription (' . $status . ') to fgsdyfg.' );

			$actual_result = $subscription->can_be_updated_to( 7783 );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Should not be be able to update subscription (' . $status . ') to 7783.' );
		}
	}

	/**
	 * Testing WC_Subscription::can_date_be_updated( 'date_created' )
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_start_date_be_updated() {
		$expected_results = [
			'pending'        => true,
			'active'         => false,
			'on-hold'        => false,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_date_be_updated( 'date_created' );

			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Should ' . ( ( $expected_results[ $status ] ) ? '' : 'not' ) . ' be able to update date (' . $status . ') to start.' );
		}

	}

	/**
	 * Testing WC_Subscription::can_date_be_updated( 'trial_end' )
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_date_be_updated() {
		$expected_results = [
			'pending'        => true,
			'active'         => true,
			'on-hold'        => true,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {

			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_date_be_updated( 'trial_end' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Updating trial end date of subscription (' . $status . ').' );

			// test subscriptions with a completed payment count over 1
			add_filter( 'woocommerce_subscription_renewal_payment_completed_count', [ $this, 'completed_payment_count_stub' ] );

			$this->assertEquals( false, $subscription->can_date_be_updated( 'trial_end' ), '[FAILED]: Should not be able to update a subscription ( ' . $status . ' ) trial_end date if the completed payments counts is over 1.' );

			remove_filter( 'woocommerce_subscription_renewal_payment_completed_count', [ $this, 'completed_payment_count_stub' ] );

		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );

		$this->assertEquals( true, $this->subscriptions['pending']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should able to update pending subscription even if the payment gateway does not support it.' );
		$this->assertEquals( false, $this->subscriptions['active']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should not be able to update an active subscription trial_end date if the payment gateway does not support it.' );
		$this->assertEquals( false, $this->subscriptions['on-hold']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should not be able to update an active subscription trial_end date if the payment gateway does not support it.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );
	}

	/**
	 * Testing WC_Subscription::can_date_be_updated( 'end' ) and
	 * WC_Subscription::can_date_be_updated( 'next_payment' )
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_can_end_and_next_payment_date_be_updated() {
		$expected_results = [
			'pending'        => true,
			'active'         => true,
			'on-hold'        => true,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		];

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_date_be_updated( 'next_payment' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Updating next_payment date of subscription (' . $status . ').' );

			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_date_be_updated( 'end' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Updating end date of subscription (' . $status . ').' );
		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );

		$this->assertEquals( true, $this->subscriptions['pending']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should able to update pending subscription even if the payment gateway does not support it.' );
		$this->assertEquals( false, $this->subscriptions['active']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should not be able to update an active subscription trial_end date if the payment gateway does not support it.' );
		$this->assertEquals( false, $this->subscriptions['on-hold']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should not be able to update an active subscription trial_end date if the payment gateway does not support it.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'payment_method_supports_false' ] );
	}

	/**
	 * Testing WC_Subscription::calculate_date() when given rubbish.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_calculate_date_rubbish() {

		$this->assertEmpty( $this->subscriptions['active']->calculate_date( 'dhfu' ) );
	}

	/**
	 * Test calculating next payment date
	 * Could possible remove this test as it's pretty redundant if we're also testing the function: WC_Subscription:calculate_next_payment_date()
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_calculate_next_payment_date() {

		$start_date = current_time( 'mysql' );

		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'     => 'active',
				'start_date' => $start_date,
			]
		);

		$expected_result = gmdate( 'Y-m-d H:i:s', wcs_add_months( strtotime( $start_date ), 1 ) );
		$actual_result   = $subscription->calculate_date( 'next_payment' );

		$this->assertEquals( $expected_result, $actual_result );
	}

	/**
	 * Test calculating next payment date
	 * Could possible remove this test as it's pretty redundant if we're also testing the function: WC_Subscription:calculate_next_payment_date()
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_calculate_next_payment_date_when_start_time_is_last_payment_time() {

		$start_date = current_time( 'mysql' );

		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'                  => 'active',
				'start_date'              => $start_date,
				'last_order_date_created' => $start_date,
				'last_order_date_paid'    => $start_date,
			]
		);

		$expected_result = gmdate( 'Y-m-d H:i:s', wcs_add_months( strtotime( $start_date ), 1 ) );
		$actual_result   = $subscription->calculate_date( 'next_payment' );

		$this->assertEquals( $expected_result, $actual_result );
	}


	/**
	 * Test calculating trial_end date.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_calculate_trial_end_date() {
		$now                  = time();
		$active_subscription  = WCS_Helper_Subscription::create_subscription( [ 'status' => 'active' ] );
		$pending_subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => 'pending' ] );

		$trial_end = gmdate( 'Y-m-d H:i:s', wcs_add_months( $now, 1 ) );
		$this->assertEquals( $trial_end, $active_subscription->calculate_date( 'trial_end' ) );

		// test subscriptions with a completed payment count over 1
		add_filter( 'woocommerce_subscription_renewal_payment_completed_count', [ $this, 'completed_payment_count_stub' ] );

		$this->assertEmpty( $active_subscription->calculate_date( 'trial_end' ), '[FAILED]: Should not be able to update a subscriptions trial_end date if the completed payments counts is over 1.' );
		$this->assertEmpty( $pending_subscription->calculate_date( 'trial_end' ), '[FAILED]: Should not be able to update a subscription trial_end date if the completed payments counts is over 1.' );

		remove_filter( 'woocommerce_subscription_renewal_payment_completed_count', [ $this, 'completed_payment_count_stub' ] );
	}

	/**
	 * Testing the logic around calculating the end of prepaid term dates
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_calculate_end_of_prepaid_term_date() {
		// Test with next payment being in the future. If there is a future payment that means the customer has paid up until that payment date.
		$now          = time();
		$start_date   = gmdate( 'Y-m-d H:i:s', $now );
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'           => 'active',
				'start_date'       => $start_date,
				'billing_period'   => 'month',
				'billing_interval' => 1,
			]
		);

		$this->assertEqualsWithDelta( strtotime( $start_date ), strtotime( $subscription->calculate_date( 'end_of_prepaid_term' ) ), 3 ); // delta set to 3 as a margin of error between the dates, shouldn't be more than 1 but just to be safe.

		$expected_date = gmdate( 'Y-m-d H:i:s', wcs_add_months( $now, 1 ) );

		$subscription->update_dates(
			[
				'next_payment' => $expected_date,
			]
		);

		$this->assertEqualsWithDelta( strtotime( $expected_date ), strtotime( $subscription->calculate_date( 'end_of_prepaid_term' ) ), 3 ); // delta set to 3 as a margin of error between the dates, shouldn't be more than 1 but just to be safe.

		$expected_date = gmdate( 'Y-m-d H:i:s', wcs_add_months( $now, 2 ) );

		$subscription->update_dates(
			[
				'start'        => gmdate( 'Y-m-d H:i:s', strtotime( '-2 weeks', $now ) ),
				'next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 week', $now ) ),
				'end'          => $expected_date,
			]
		);

		$this->assertEqualsWithDelta( strtotime( $expected_date ), strtotime( $subscription->calculate_date( 'end_of_prepaid_term' ) ), 3 ); // delta set to 3 as a margin of error between the dates, shouldn't be more than 1 but just to be safe.
	}

	/**
	 * Tests the WC_Subscription::get_date() also includes testing getting
	 * dates using the suffix. Fetching dates that already exists.
	 *
	 * @expectedDeprecated WC_Subscription::get_date
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_date_already_set() {

		$start_date      = '2013-12-12 08:08:08';
		$parent_order    = WCS_Helper_Subscription::create_order(
			[
				'post_date_gmt' => $start_date,
				'post_date'     => get_date_from_gmt( $start_date ),
			]
		);
		$parent_order_id = wcs_get_objects_property( $parent_order, 'id' );

		if ( is_callable( [ $parent_order, 'set_date_created' ] ) ) { // WC 3.0+
			$parent_order->set_date_created( wcs_date_to_time( $start_date ) );
			$parent_order->save();
		} else { // WC < 3.0
			wp_update_post(
				[
					'ID'            => $parent_order_id,
					'post_date_gmt' => $start_date,
					'post_date'     => get_date_from_gmt( $start_date ),
				]
			);
		}

		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'       => 'pending',
				'start_date'   => $start_date,
				'order_id'     => $parent_order_id,
				'date_created' => $start_date,
			]
		);

		$subscription->update_dates(
			[
				'trial_end' => '2014-01-12 08:08:08',
				'end'       => '2014-08-12 08:08:08',
			]
		);

		// get schedule
		$this->assertEquals( '2013-12-12 08:08:08', $subscription->get_date( 'start_date' ) );
		$this->assertEquals( '2013-12-12 08:08:08', $subscription->get_date( 'start' ) );
		$this->assertEquals( '2013-12-12 08:08:08', $subscription->get_date( 'date_created' ) );
		$this->assertEquals( '2013-12-12 08:08:08', $subscription->get_date( 'schedule_start' ) );
		// get scheduled trial end date
		$this->assertEquals( '2014-01-12 08:08:08', $subscription->get_date( 'trial_end_date' ) );
		$this->assertEquals( '2014-01-12 08:08:08', $subscription->get_date( 'trial_end' ) );
		$this->assertEquals( '2014-01-12 08:08:08', $subscription->get_date( 'schedule_trial_end' ) );
		// get scheduled end date
		$this->assertEquals( '2014-08-12 08:08:08', $subscription->get_date( 'end_date' ) );
		$this->assertEquals( '2014-08-12 08:08:08', $subscription->get_date( 'end' ) );
		// get scheduled last payment date
		$this->assertEquals( '2013-12-12 08:08:08', $subscription->get_date( 'last_payment_date' ) );
		$this->assertEquals( '2013-12-12 08:08:08', $subscription->get_date( 'last_payment' ) );
		$this->assertEquals( '2013-12-12 08:08:08', $subscription->get_date( 'last_order_date_created' ) );
		$this->assertEquals( '2013-12-12 08:08:08', $subscription->get_date( 'last_order_date_created' ) );
	}

	/**
	 * Test for random cases.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_date_other() {
		// set a date for the pending subscription to test against
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status' => 'pending',
			]
		);

		$this->setExpectedException( 'InvalidArgumentException', 'Invalid data. First parameter has a date that is not in the registered date types.' );

		$subscription->update_dates(
			[
				'rubbish' => '2013-12-12 08:08:08',
			]
		);
	}

	/**
	 * Test the get_date() function specifying a date that is not GMT.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_date_not_gmt() {
		$start_date = '2014-01-01 01:01:01';

		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'     => 'pending',
				'start_date' => $start_date,
			]
		);

		$this->assertEquals( get_date_from_gmt( $start_date ), $subscription->get_date( 'start', 'site' ) );
		$this->assertEquals( get_date_from_gmt( $start_date ), $subscription->get_date( 'start', 'other' ) );
	}

	/**
	 * Tests for WC_Subscription::get_date( $date, 'gmt' )
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_date_gmt() {
		$expected_result = '2014-01-01 01:01:01';

		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'     => 'pending',
				'start_date' => $expected_result,
			]
		);

		$this->assertEquals( $expected_result, $subscription->get_date( 'start', 'gmt' ) );
		$this->assertEquals( $expected_result, $subscription->get_date( 'start' ) );
	}

	/**
	 * Tests for WC_Subscription::calculate_next_payment_date() on active subscriptions.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_calculate_next_payment_date_active() {

		$start_time = time();
		$start_date = gmdate( 'Y-m-d H:i:s', $start_time );
		$trial_end  = gmdate( 'Y-m-d H:i:s', wcs_add_months( $start_time, 1 ) );

		// Create a mock of Subscription that has a public calculate_next_payment_date() function.
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'     => 'active',
				'start_date' => $start_date,
				'trial_end'  => $trial_end,
			]
		);

		$this->assertEquals( $trial_end, PHPUnit_Utils::call_method( $subscription, 'calculate_next_payment_date' ) );

		// no trial, last payment or end date
		$subscription->update_dates(
			[
				'date_created' => $start_date,
				'trial_end'    => 0,
			]
		);

		$this->assertEquals( wcs_add_months( $start_time, 1 ), strtotime( PHPUnit_Utils::call_method( $subscription, 'calculate_next_payment_date' ) ) );

		// If the subscription has an end date and the next billing period comes after that
		WCS_Helper_Subscription::create_renewal_order( $subscription );

		$last_payment_time = wcs_add_months( $start_time, 2 );
		$last_payment_date = gmdate( 'Y-m-d H:i:s', $last_payment_time );

		$subscription->update_dates(
			[
				'trial_end'               => 0,
				'last_order_date_created' => $last_payment_date,
				'end'                     => gmdate( 'Y-m-d H:i:s', wcs_add_time( 1, 'day', $last_payment_time ) ),
			]
		);

		$this->assertEquals( 0, PHPUnit_Utils::call_method( $subscription, 'calculate_next_payment_date' ) );

		$new_start_time = strtotime( '-1 month', $start_time );
		$new_start_date = gmdate( 'Y-m-d H:i:s', $new_start_time );

		// If the last payment date is later then the trial end date, calculate the next payment based on the last payment time
		$subscription->update_dates(
			[
				'start'                   => $new_start_date,
				'trial_end'               => gmdate( 'Y-m-d H:i:s', wcs_add_time( 1, 'week', strtotime( 'last month', $start_time ) ) ),
				'next_payment'            => 0,
				'last_order_date_created' => $last_payment_date,
				'end'                     => 0,
			]
		);
		$this->assertEquals( wcs_add_months( $last_payment_time, 1 ), strtotime( PHPUnit_Utils::call_method( $subscription, 'calculate_next_payment_date' ) ) );

		// trial end is greater than start time but it is not in the future, therefore we use the last payment
		$subscription->update_dates(
			[
				'start'                   => $new_start_date,
				'trial_end'               => gmdate( 'Y-m-d H:i:s', wcs_add_time( 1, 'week', $new_start_time ) ),
				'next_payment'            => 0,
				'last_order_date_created' => $last_payment_date,
				'end'                     => 0,
			]
		);

		$this->assertEquals( gmdate( 'Y-m-d H:i:s', wcs_add_months( $last_payment_time, 1 ) ), PHPUnit_Utils::call_method( $subscription, 'calculate_next_payment_date' ) );

		// make sure the payment is in the future, even if calculating it more than 10 years later
		$subscription->update_dates(
			[
				'start'                   => '2000-12-01 00:00:00',
				'trial_end'               => 0,
				'last_order_date_created' => 0,
				'end'                     => 0,
			]
		);

		$this->assertTrue( strtotime( PHPUnit_Utils::call_method( $subscription, 'calculate_next_payment_date' ) ) >= time() );
	}

	/**
	 * Tests for WC_Subscription::calculate_next_payment_date() on subscriptions with different statuses
	 * Overall this a pretty pointless test because there's no checks before calculating the next payment date for status
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_calculate_next_payment_date_per_status() {

		$start_date = current_time( 'mysql', true );
		$statuses   = [
			'pending',
			'cancelled',
			'on-hold',
			'switched',
			'expired',
		];

		$expected_next_payment_date = wcs_add_months( strtotime( $start_date ), 1 );

		foreach ( $statuses as $status ) {
			$subscription = WCS_Helper_Subscription::create_subscription(
				[
					'status'     => $status,
					'start_date' => $start_date,
				]
			);
			$this->assertEquals( $expected_next_payment_date, strtotime( PHPUnit_Utils::call_method( $subscription, 'calculate_next_payment_date' ) ) );
		}
	}

	/**
	 * Test WC_Subscripiton::delete_date() throws an exception when trying to delete start date.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_delete_start_date() {
		// make sure the start date doesn't exist
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => 'active' ] );

		$caught = false;
		try {
			$subscription->delete_date( 'start_date' );
		} catch ( Exception $e ) {
			$caught = 'Subscription #' . $subscription->get_id() . ': The start date of a subscription can not be deleted, only updated.' === $e->getMessage();
		}

		$this->assertTrue( $caught, '[FAILED]: Exception and the correct message should have been caught when trying to delete a subscriptions start date.' );
	}

	/**
	 * Test the exception is thrown when trying to delete the last payment date.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_delete_last_payment_date() {
		$caught = false;

		try {
			$this->subscriptions['active']->delete_date( 'last_order_date_created' );
		} catch ( Exception $e ) {
			$caught = 'Subscription #' . $this->subscriptions['active']->get_id() . ': The last_order_date_created date of a subscription can not be deleted. You must delete the order.' === $e->getMessage();
		}

		$this->assertTrue( $caught, '[FAILED]: Exception and the correct message should have been caught when trying to delete a subscriptions last payment date.' );
	}

	/**
	 * Delete a valid date value and check the post meta is updated correctly.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_delete_date_valid() {
		$old_date = $this->subscriptions['active']->get_date( 'end' );

		$this->subscriptions['active']->delete_date( 'end' );
		$this->assertEquals( 0, $this->subscriptions['active']->get_date( 'end' ) );
		$this->assertEmpty( $this->subscriptions['active']->get_meta( wcs_get_date_meta_key( 'end' ), true ) );

		update_post_meta( $this->subscriptions['active']->get_id(), wcs_get_date_meta_key( 'end' ), $old_date );
	}

	/**
	 * Try deleting a date that doesn't exist.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_delete_date_other() {
		$this->subscriptions['pending']->delete_date( 'wcs_rubbish' );
		$this->assertEquals( 0, $this->subscriptions['pending']->get_date( 'wcs_rubbish' ) );
		$this->assertEmpty( $this->subscriptions['pending']->get_meta( wcs_get_date_meta_key( 'wcs_rubbish' ), true ) );
	}

	/**
	 * Test completed payment count for subscription that has no renewal orders.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_completed_count_one() {
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => 'active' ] );
		$order        = WCS_Helper_Subscription::create_order();

		$order->payment_complete();

		WCS_Related_Order_Store::instance()->add_relation( $order, $subscription, 'renewal' );

		$completed_payments = $subscription->get_payment_count();
		$expected_count     = 1;

		$this->assertEquals( $expected_count, $completed_payments );
	}

	/**
	 * Test completed_payment_count() for subscription that have not yet been completed.
	 * Only tests valid cases.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_completed_count_none() {
		foreach ( [ 'active', 'on-hold', 'pending' ] as $status ) {
			$completed_payments = $this->subscriptions[ $status ]->get_payment_count();
			$this->assertEmpty( $completed_payments );
		}
	}

	/**
	 * Testing WC_Subscription::get_completed_count() where the subscription has many completed payments.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_completed_count_many() {
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => 'active' ] );

		$order_1 = WCS_Helper_Subscription::create_order();
		$order_1->payment_complete();
		$order_2 = WCS_Helper_Subscription::create_order();
		$order_2->payment_complete();

		WCS_Related_Order_Store::instance()->add_relation( $order_1, $subscription, 'renewal' );
		WCS_Related_Order_Store::instance()->add_relation( $order_2, $subscription, 'renewal' );

		$completed_payments = $subscription->get_payment_count();

		$this->assertEquals( 2, $completed_payments );
	}

	/**
	 * Testing WC_Subscription::get_completed_count() for those weird cases that we probably don't expect to happen, but potentially could.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_completed_count_invalid_cases() {
		// new WP_Post with subscription as parent
		$post_id = wp_insert_post(
			[
				'post_author' => 1,
				'post_name'   => 'example',
				'post_title'  => 'example_title',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		update_post_meta( $post_id, '_subscription_renewal', $this->subscriptions['active']->get_id() );

		$this->assertEmpty( $this->subscriptions['active']->get_payment_count() );
	}

	/**
	 * Run a few tests for susbcriptions that have one failed payment.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_failed_payment_count_one() {
		$order = WCS_Helper_Subscription::create_order();

		$order->set_status( 'wc-failed' );
		$order->save();

		foreach ( [ 'active', 'on-hold', 'pending' ] as $status ) {

			WCS_Related_Order_Store::instance()->add_relation( $order, $this->subscriptions[ $status ], 'renewal' );

			$failed_payments = $this->subscriptions[ $status ]->get_failed_payment_count();

			$expected_count = 1;

			$this->assertEquals( $expected_count, $failed_payments );
		}
	}

	/**
	 * Tests for WC_Subscription::get_failed_payment_count() for a subscription that has
	 * many failed payments.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_failed_payment_count_many() {
		$orders = [];

		for ( $i = 0; $i < 20; $i++ ) {

			$order = WCS_Helper_Subscription::create_order();
			$order->update_status( 'wc-failed' );
			$order->save();
			$orders[] = $order;
		}

		foreach ( [ 'active', 'on-hold', 'pending' ] as $status ) {

			$expected_count = 0;
			foreach ( $orders as $order ) {

				WCS_Related_Order_Store::instance()->add_relation( $order, $this->subscriptions[ $status ], 'renewal' );
				$expected_count++;

				$failed_payments = $this->subscriptions[ $status ]->get_failed_payment_count();

				$this->assertEquals( $expected_count, $failed_payments );
			}
		}
	}

	/**
	 * Test getting a single related order for a subscription.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_related_order() {
		// stub REMOTE_ADDR to run in test conditions @see wc_create_order():L104 - not sure if this value exists in travis so dont override if so.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$_SERVER['REMOTE_ADDR'] = ( isset( $_SERVER['REMOTE_ADDR'] ) ) ? $_SERVER['REMOTE_ADDR'] : '';

		// setup active subscription for testing
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => 'active' ] );
		$order        = WCS_Helper_Subscription::create_order();
		WCS_Related_Order_Store::instance()->add_relation( $order, $subscription, 'renewal' );

		$related_orders = $subscription->get_related_orders();

		$this->assertEquals( 1, count( $related_orders ) );
		$this->assertEquals( wcs_get_objects_property( $order, 'id' ), reset( $related_orders ) );

		$related_orders = $subscription->get_related_orders( 'all' );

		$this->assertEquals( 1, count( $related_orders ) );

		// For some reason or another, WC 3.0 changes the values in the `WC_Order_Data_Store_CPT->internal_meta_keys` property, meaning we can’t compare two of the same order and trust they'll be seen as the same by assertEquals() or assertSame() because the `WC_Order->data_store` property will have different values for `WC_Order_Data_Store_CPT->internal_meta_keys`, so instead we just have to check type
		foreach ( $related_orders as $related_order_id => $related_order ) {
			$this->assertInstanceOf( 'WC_Order', $related_order );
		}
	}

	/**
	 * Test WC_Subscription::get_related_orders() for more than one related order.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_related_orders() {

		$start_time = strtotime( '-1 month' );

		// setup fresh active subscription
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'     => 'active',
				'start_date' => gmdate( 'Y-m-d H:i:s', $start_time ),
			]
		);

		$orders = [];

		for ( $i = 0; $i < 3; $i++ ) {
			$order = WCS_Helper_Subscription::create_order();
			WCS_Related_Order_Store::instance()->add_relation( $order, $subscription, 'renewal' );
			wcs_set_objects_property( $order, 'date_created', wcs_add_time( $i, 'week', $start_time ) ); // so get_related_orders() query will order them nicely by date
			$orders[ $i ] = wc_get_order( wcs_get_objects_property( $order, 'id' ) );
		}

		// test no param
		$related_order_ids = $subscription->get_related_orders();
		$this->assertCount( 3, $related_order_ids );
		$this->assertEquals( wcs_get_objects_property( $orders[0], 'id' ), array_pop( $related_order_ids ) );

		// test with 'ids' param
		$related_order_ids = $subscription->get_related_orders( 'ids' );
		$this->assertCount( 3, $related_order_ids );
		$this->assertEquals( wcs_get_objects_property( $orders[0], 'id' ), array_pop( $related_order_ids ) );
		$this->assertEquals( wcs_get_objects_property( $orders[1], 'id' ), array_pop( $related_order_ids ) );
		$this->assertEquals( wcs_get_objects_property( $orders[2], 'id' ), array_pop( $related_order_ids ) );

		// Delete order to assert only valid WC_Order are returned.
		$orders[2]->delete( true );

		$related_orders = $subscription->get_related_orders( 'all' );

		$this->assertCount( 2, $related_orders );

		// For some reason or another, WC 3.0 changes the values in the `WC_Order_Data_Store_CPT->internal_meta_keys` property, meaning we can’t compare two of the same order and trust they'll be seen as the same by assertEquals() or assertSame() because the `WC_Order->data_store` property will have different values for `WC_Order_Data_Store_CPT->internal_meta_keys`, so instead we just have to check type
		$this->assertContainsOnly( 'WC_Order', $related_orders );
	}

	/**
	 * Test updating an active/cancelled subscription to pending cancellation.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_update_status_to_pending_canellation() {
		$expected_to_pass = [ 'active', 'on-hold', 'cancelled' ];

		foreach ( WCS_Helper_Subscription::create_subscriptions() as $status => $subscription ) {

			// nothing to check on pending cancellation subs.
			if ( 'pending-cancel' === $status ) {
				continue;
			}

			if ( in_array( $status, $expected_to_pass, true ) ) {

				try {
					$start_date = gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) );

					$subscription->update_dates( [ 'start' => $start_date ] );
					$subscription->update_status( 'pending-cancel' );

					$this->assertEqualsWithDelta( time(), $subscription->get_time( 'end' ), 3 ); // delta set to 3 as a margin of error between the dates, shouldn't be more than 1 but just to be safe.
				} catch ( Exception $e ) {
					$this->fail( $e->getMessage() );
				}
			} else {
				$exception_caught = false;

				try {
					$subscription->update_status( 'pending-cancel' );
				} catch ( Exception $e ) {
					$exception_caught = 'Unable to change subscription status to "pending-cancel".' === $e->getMessage();
				}

				$this->assertTrue( $exception_caught, '[FAILED]: Expected exception was not caught when updating ' . $status . ' to pending cancellation.' );
			}
		}
	}

	/**
	 * Test updating a subscription status to active.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_update_status_to_active() {

		// list of subscription that will not throw a "cannot update status" exception
		$expected_to_pass = [ 'pending', 'pending-cancel', 'on-hold', 'active' ];

		foreach ( WCS_Helper_Subscription::create_subscriptions() as $status => $subscription ) {

			if ( in_array( $status, $expected_to_pass, true ) ) {

				if ( 'pending-cancel' === $status || 'on-hold' === $status ) {
					$subscription->update_dates( [ 'end' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ) ] );
				}

				$subscription->update_status( 'active' );

				// check the user has the default subscriber role
				$user_data = get_userdata( $subscription->get_user_id() );
				$roles     = $user_data->roles;

				$this->assertFalse( in_array( 'administrator', $roles, true ) );
				$this->assertTrue( in_array( 'subscriber', $roles, true ) );

			} else {

				$exception_caught = false;

				try {
					$subscription->update_status( 'active' );
				} catch ( Exception $e ) {
					$exception_caught = 'Unable to change subscription status to "active".' === $e->getMessage();
				}

				$this->assertTrue( $exception_caught, '[FAILED]: Expected exception was not caught when updating ' . $status . ' to active.' );

			}
		}
	}

	/**
	 * Testing WC_Subscription::set_suspension_count function
	 *
	 * @group test_set_suspension_count
	 */
	public function test_set_suspension_count() {
		$subscription         = WCS_Helper_Subscription::create_subscription();
		$expected_suspensions = 10;

		$this->assertNotEquals( $expected_suspensions, $subscription->get_suspension_count() );
		if ( ! wcs_is_custom_order_tables_usage_enabled() ) {
			// Keeping usage of `get_post_meta` here due legacy meta key retrieval (those should have getters/setters instead).
			$this->assertNotEquals( $expected_suspensions, get_post_meta( $subscription->get_id(), '_suspension_count', true ) );
		}

		$subscription->set_suspension_count( $expected_suspensions );
		$subscription->save();
		$this->assertEquals( $expected_suspensions, $subscription->get_suspension_count() );
		if ( ! wcs_is_custom_order_tables_usage_enabled() ) {
			// Keeping usage of `get_post_meta` here due legacy meta key retrieval (those should have getters/setters instead).
			$this->assertEquals( $expected_suspensions, get_post_meta( $subscription->get_id(), '_suspension_count', true ) );
		}
	}

	/**
	 * Test updating a subscription status to on-hold. This test does not check if the user's
	 * role has been updated to inactive, this is because the same user is used throughout testing
	 * and will almost always have an active subscription.
	 *
	 * Checks the suspension count on the subscription is updated correctly.
	 *
	 * @depends test_set_suspension_count
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_update_status_to_onhold() {
		$expected_to_pass = [ 'pending', 'active' ];
		$subscriptions    = WCS_Helper_Subscription::create_subscriptions();

		foreach ( $subscriptions as $status => $subscription ) {
			// skip over subscriptions with the status on-hold, we don't need to check the suspension count
			if ( 'on-hold' === $status ) {
				continue;
			}

			if ( in_array( $status, $expected_to_pass, true ) ) {

				// set the suspension count to 0 to make sure it is correctly being incrememented
				$subscription->set_suspension_count( 0 );

				$subscription->update_status( 'on-hold' );

				$this->assertEquals( 1, $subscription->get_suspension_count() );

			} else {

				// expecting an exception to be thrown
				$exception_caught = false;

				try {
					$subscription->update_status( 'on-hold' );
				} catch ( Exception $e ) {
					$exception_caught = 'Unable to change subscription status to "on-hold".' === $e->getMessage();
				}

				$this->assertTrue( $exception_caught, '[FAILED]: Expected exception was not caught when updating ' . $status . ' to on-hold.' );

			}
		}
	}

	/**
	 * Test updating the status of a subscription to expired and making sure the
	 * correct end date is set correctly.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_update_status_to_expired() {
		$expected_to_pass = [ 'active', 'pending', 'pending-cancel', 'on-hold' ];
		$now              = time();

		$subscriptions = WCS_Helper_Subscription::create_subscriptions(
			[
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 month', $now ) ),
			]
		);

		foreach ( $subscriptions as $status => $subscription ) {

			// skip over subscriptions with the status expired or switched, we don't need to check the end date for them.
			if ( 'expired' === $status || 'switched' === $status ) {
				// skip switched until bug is fixed - PR for the fix has been made.
				continue;
			}

			if ( in_array( $status, $expected_to_pass, true ) ) {

				try {
					$subscription->update_status( 'expired' );
					// end date should be set to the current time
					$this->assertEqualsWithDelta( $now, $subscription->get_time( 'end' ), 3 ); // delta set to 3 as a margin of error between the dates, shouldn't be more than 1 but just to be safe.
				} catch ( Exception $e ) {
					$this->fail( $e->getMessage() );
				}
			} else {

				// expecting an exception to be thrown
				$exception_caught = false;

				try {
					$subscription->update_status( 'expired' );
				} catch ( Exception $e ) {
					$exception_caught = 'Unable to change subscription status to "expired".' === $e->getMessage();
				}

				$this->assertTrue( $exception_caught, '[FAILED]: Expected exception was not caught when updating ' . $status . ' to expired.' );

			}
		}
	}

	/**
	 * Test updating a subscription status to cancelled. Potentially look at combining the test function
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_update_status_to_cancelled() {
		$expected_to_pass = [ 'active', 'pending', 'pending-cancel', 'on-hold' ];
		$now              = time();
		$subscriptions    = WCS_Helper_Subscription::create_subscriptions(
			[
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 month', $now ) ),
			]
		);

		foreach ( $subscriptions as $status => $subscription ) {
			// skip over subscriptions with the status cancelled as we don't need to check the end date
			if ( 'cancelled' === $status ) {
				continue;
			}

			if ( in_array( $status, $expected_to_pass, true ) ) {

				try {
					$subscription->update_status( 'cancelled' );

					// end date should be set to the current time
					$this->assertEqualsWithDelta( $now, $subscription->get_time( 'end' ), 3 ); // delta set to 3 as a margin of error between the dates, shouldn't be more than 1 but just to be safe.
				} catch ( Exception $e ) {
					$this->fail( $e->getMessage() );
				}
			} else {

				$exception_caught = false;

				try {
					$subscription->update_status( 'cancelled' );
				} catch ( Exception $e ) {
					$exception_caught = 'Unable to change subscription status to "cancelled".' === $e->getMessage();
				}

				$this->assertTrue( $exception_caught, '[FAILED]: Expected exception was not caught when updating ' . $status . ' to cancelled.' );

			}
		}
	}

	/**
	 * Test updating a subscription to either expired, cancelled or switched.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_user_inactive_update_status_to_cancelled() {
		// create a new user with no active subscriptions
		$user_id      = wp_create_user( 'susan', 'testuser', 'susan@example.com' );
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'      => 'pending',
				'start_date'  => '2015-07-14 00:00:00',
				'customer_id' => $user_id,
			]
		);

		try {
			$subscription->update_status( 'cancelled' );
		} catch ( Exception $e ) {
			$this->fail( $e->getMessage() );
		}

		// check the user has the default inactive role
		$user_data = get_userdata( $subscription->get_user_id() );
		$roles     = $user_data->roles;

		$this->assertContains( 'customer', $roles );

		// create a new user with 1 currently active subscription
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'      => 'active',
				'start_date'  => '2015-07-14 00:00:00',
				'customer_id' => $user_id,
			]
		);

		try {
			$subscription->update_status( 'cancelled' );
		} catch ( Exception $e ) {
			$this->fail( $e->getMessage() );
		}

		$user_data = get_userdata( $subscription->get_user_id() );
		$roles     = $user_data->roles;

		$this->assertContains( 'customer', $roles );
	}

	/**
	 * Test to make sure that a users role is set to inactive when updating an active
	 * or pending subscription to expired.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_user_inactive_update_status_to_expired() {
		// create a new user with no active subscriptions
		$user_id      = wp_create_user( 'susan', 'testuser', 'susan@example.com' );
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'      => 'pending',
				'start_date'  => '2015-07-14 00:00:00',
				'customer_id' => $user_id,
			]
		);

		try {
			$subscription->update_status( 'expired' );
		} catch ( Exception $e ) {
			$this->fail( $e->getMessage() );
		}

		// check the user has the default inactive role
		$user_data = get_userdata( $subscription->get_user_id() );
		$roles     = $user_data->roles;
		$this->assertContains( 'customer', $roles );

		// create a new user with 1 currently active subscription
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'      => 'active',
				'start_date'  => '2015-07-14 00:00:00',
				'customer_id' => $user_id,
			]
		);

		try {
			$subscription->update_status( 'cancelled' );
		} catch ( Exception $e ) {
			$this->fail( $e->getMessage() );
		}

		$user_data = get_userdata( $subscription->get_user_id() );
		$roles     = $user_data->roles;
		$this->assertContains( 'customer', $roles );
	}

	/**
	 * Check exceptions are thrown correctly when trying to update status from active to pending.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_update_status_exception_thrown_one() {
		$this->setExpectedException( 'Exception', 'Unable to change subscription status to "pending".' );
		$this->subscriptions['active']->update_status( 'pending' );
	}

	/**
	 * Check exceptions are thrown correctly when trying to update status from pending to pending-cancel.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_update_status_exception_thrown_two() {
		$this->setExpectedException( 'Exception', 'Unable to change subscription status to "pending-cancel".' );
		$this->subscriptions['pending']->update_status( 'pending-cancel' );
	}

	/**
	 * Test $subscription->set_parent_id()
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_set_parent_id_valid() {

		foreach ( WCS_Helper_Subscription::create_subscriptions() as $subscription ) {
			$parent_order = WCS_Helper_Subscription::create_order();
			$new_order    = WCS_Helper_Subscription::create_order();

			$parent_order_id = wcs_get_objects_property( $parent_order, 'id' );
			$new_order_id    = wcs_get_objects_property( $new_order, 'id' );

			$subscription->set_parent_id( 0 );
			$this->assertEquals( 0, $subscription->get_parent_id() );

			$subscription->set_parent_id( $parent_order_id );
			$this->assertEquals( $parent_order_id, $subscription->get_parent_id() );

			$subscription->set_parent_id( $new_order_id );
			$this->assertEquals( $new_order_id, $subscription->get_parent_id() );
		}
	}

	/**
	 * Test $subscription->set_parent_id()
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_set_parent_valid() {

		foreach ( WCS_Helper_Subscription::create_subscriptions() as $subscription ) {
			$parent_order = WCS_Helper_Subscription::create_order();
			$new_order    = WCS_Helper_Subscription::create_order();

			$this->assertNotEquals( $parent_order, $subscription->get_parent() );

			$parent_order_id = wcs_get_objects_property( $parent_order, 'id' );
			$new_order_id    = wcs_get_objects_property( $new_order, 'id' );

			$subscription->set_parent_id( $parent_order_id );
			$this->assertEquals( wc_get_order( $parent_order_id ), $subscription->get_parent() );

			$subscription->set_parent_id( $new_order_id );
			$this->assertEquals( wc_get_order( $new_order_id ), $subscription->get_parent() );
		}
	}

	/**
	 * Test $subscription->needs_payment() if subscription is pending or failed or $0
	 *
	 * @dataProvider subscription_status_data_provider
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_needs_payment_pending_failed( $status ) {
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => $status ] );

		if ( in_array( $status, [ 'pending', 'failed' ], true ) ) {
			$subscription->set_total( 0 );
			$this->assertFalse( $subscription->needs_payment() ); // pending or failed subscriptions with $0 total don't need paying for

			$subscription->set_total( 10 );
			$this->assertTrue( $subscription->needs_payment() );
		} else {
			$this->assertFalse( $subscription->needs_payment() );
		}
	}

	/**
	 * Test $subscription->needs_payment() for the parent order
	 *
	 * @depends test_needs_payment_pending_failed
	 * @dataProvider subscription_status_data_provider
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_needs_payment_parent_order( $status ) {
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => $status ] );
		$order        = WCS_Helper_Subscription::create_order();

		$order->set_total( 100 );

		if ( in_array( $status, [ 'pending', 'failed' ], true ) ) {
			$subscription->set_total( 0 );
		}

		$subscription->set_parent_id( wcs_get_objects_property( $order, 'id' ) );
		$this->assertTrue( $subscription->needs_payment() );

		$subscription->set_parent_id( wcs_get_objects_property( $order, 'id' ) ); // we need to invalidate the cached order on the subscription
		$order->payment_complete();
		$this->assertFalse( $subscription->needs_payment() );
	}

	/**
	 * Test $subscription->needs_payment() for renewal orders
	 *
	 * @depends test_needs_payment_parent_order
	 * @dataProvider subscription_status_data_provider
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_needs_payment_renewal_orders( $status ) {

		// For pending status, the renewal order checks are by passed anyway as parent::needs_payment() evaluates true
		if ( 'pending' === $status ) {
			$this->markTestSkipped( 'Test not required' );
		}

		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => $status ] );

		if ( in_array( $status, [ 'pending', 'failed' ], true ) ) {
			$subscription->set_total( 100 );
		}
		$subscription->set_parent_id( 0 );

		$renewal_order = WCS_Helper_Subscription::create_renewal_order( $subscription );

		$renewal_order->set_total( 100 );

		$this->assertTrue( $subscription->needs_payment() );

		remove_filter( 'woocommerce_order_status_changed', 'WC_Subscriptions_Renewal_Order::maybe_record_subscription_payment', 10 );

		foreach ( [ 'on-hold', 'failed', 'cancelled' ] as $status ) {

			$renewal_order->update_status( $status );
			$this->assertTrue( $subscription->needs_payment() );
		}

		$renewal_order->update_status( 'processing' ); // update status also calls save() in WC 3.0+

		$this->assertFalse( $subscription->needs_payment() );

		add_filter( 'woocommerce_order_status_changed', 'WC_Subscriptions_Renewal_Order::maybe_record_subscription_payment', 10, 3 );
	}

	/**
	 * Tests for has_ended within the WC_Subscription
	 *
	 * @dataProvider subscription_status_data_provider
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_has_ended_statuses( $status ) {
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => $status ] );

		if ( in_array( $status, [ 'active', 'pending', 'on-hold' ], true ) ) {
			$this->assertFalse( $subscription->has_status( wcs_get_subscription_ended_statuses() ) );

			add_filter( 'wcs_subscription_ended_statuses', [ $this, 'filter_has_ended_statuses' ] );

			if ( 'active' === $status ) {
				$this->assertTrue( $subscription->has_status( wcs_get_subscription_ended_statuses() ) );
			} else {
				$this->assertFalse( $subscription->has_status( wcs_get_subscription_ended_statuses() ) );
			}

			remove_filter( 'wcs_subscription_ended_statuses', [ $this, 'filter_has_ended_statuses' ] );

		} else {
			$this->assertTrue( $subscription->has_status( wcs_get_subscription_ended_statuses() ) );
		}
	}

	public function filter_has_ended_statuses( $end_statuses ) {
		$end_statuses[] = 'active';
		return $end_statuses;
	}

	/**
	 * Testing $subscription->get_status()
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_status() {
		$subscriptions = WCS_Helper_Subscription::create_subscriptions();

		foreach ( $subscriptions as $status => $subscription ) {
			$this->assertEquals( $status, $subscription->get_status() );
		}
	}

	/**
	 * Tests that subscriptions loaded from the database with draft or auto-draft status are treated as pending.
	 */
	public function test_draft_subscription_statuses() {
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => 'active' ] );
		$subscription->set_status( 'draft' );
		$subscription->save();

		// Confirm that a draft subscription when loaded has a pending status.
		$this->assertEquals( 'pending', wcs_get_subscription( $subscription->get_id() )->get_status() );

		$subscription->set_status( 'auto-draft' );
		$subscription->save();

		// Confirm that a draft subscription when loaded has a pending status.
		$this->assertEquals( 'pending', wcs_get_subscription( $subscription->get_id() )->get_status() );
	}

	/**
	 * Testing $subscription->get_paid_order_statuses()
	 *
	 */
	public function test_get_paid_order_statuses() {
		$subscription    = WCS_Helper_Subscription::create_subscription();
		$expected_result = [
			'processing',
			'completed',
			'wc-processing',
			'wc-completed',
		];
		$this->assertEquals( $expected_result, $subscription->get_paid_order_statuses() );

		add_filter( 'woocommerce_payment_complete_order_status', [ $this, 'custom_paid_order_status_test_1' ] );
		array_push( $expected_result, 'pending', 'wc-pending' );
		$this->assertEquals( $expected_result, $subscription->get_paid_order_statuses() );
	}

	public function custom_paid_order_status_test_1( $subscription ) {
		return 'pending';
	}

	/**
	 * Testing WC_Subscription::test_get_total_initial_payment()
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_total_initial_payment() {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$this->assertEquals( 0, $subscription->get_total_initial_payment() );

		// even though subscription has an order total is 10, there's still no initial order therefore it should still be 0
		$subscription->set_total( 10 );
		$this->assertEquals( 0, $subscription->get_total_initial_payment() );

		$order = WCS_Helper_Subscription::create_order();
		$subscription->set_parent_id( wcs_get_objects_property( $order, 'id' ) );
		$this->assertEquals( 40, $subscription->get_total_initial_payment() );

		$order->set_total( 20 );
		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}
		$subscription->set_parent_id( wcs_get_objects_property( $order, 'id' ) ); // Refresh the cached order object
		$this->assertEquals( 20, $subscription->get_total_initial_payment() );

		$order->set_total( 0 );
		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}
		$subscription->set_parent_id( wcs_get_objects_property( $order, 'id' ) ); // Refresh the cached order object
		$this->assertEquals( 0, $subscription->get_total_initial_payment() );
	}

	public function get_date_to_display_data() {
		return [
			[ 'date_created', strtotime( '-1 day' ), '1 day ago' ],
			// should be '-' because we don't display next payment dates when subscriptions are not active (even if the subscription has a valid next payment set)
			[ 'next_payment', strtotime( '2015-07-20 10:19:40' ), '-' ],
			[ 'next_payment', strtotime( '+1 month' ), '-' ],
			[ 'last_order_date_created', strtotime( '2015-07-20 10:19:40' ), 'July 20, 2015' ],
			[ 'last_order_date_created', strtotime( '-5 hours' ), '5 hours ago' ],
			[ 'last_order_date_created', strtotime( '-5 days' ), '5 days ago' ],
			[ 'end', strtotime( '2015-07-20 10:19:40' ), 'July 20, 2015' ],
			[ 'end', strtotime( '+2 day' ), 'In 2 days' ],
			[ 'end', strtotime( '+1 day' ), 'In 24 hours' ],
			[ 'end', strtotime( '+5 hour' ), 'In 5 hours' ],
			[ 'end', 0, 'Not yet ended' ],
		];
	}

	/**
	 * Testing $subscription->get_date_to_display()
	 *
	 * @dataProvider get_date_to_display_data
	 */
	public function test_get_date_to_display( $date_type, $time_to_set, $expected ) {
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'start_date' => '2015-01-01 10:19:40', // make sure we have a start date before all the tests we want to run
			]
		);

		// We need an order to set to the last payment date on
		if ( 'last_order_date_created' === $date_type ) {
			WCS_Helper_Subscription::create_renewal_order( $subscription );
		}

		$date_to_set = ( 0 === $time_to_set ) ? $time_to_set : gmdate( 'Y-m-d H:i:s', $time_to_set );
		$subscription->update_dates( [ $date_type => $date_to_set ] );
		$this->assertEquals( $expected, $subscription->get_date_to_display( $date_type ) );
	}

	public function get_time_data() {
		return [
			[ 'end', '2015-10-14 01:01:01', strtotime( '2015-10-14 01:01:01' ) ],
			[ 'end', 0, 0 ],
			[ 'date_created', '2014-10-14 01:01:01', strtotime( '2014-10-14 01:01:01' ) ],
			[ 'next_payment', '2014-12-14 01:01:01', strtotime( '2014-12-14 01:01:01' ) ],
			[ 'next_payment', 0, 0 ],
			[ 'last_order_date_created', '2014-10-14 01:01:01', strtotime( '2014-10-14 01:01:01' ) ],
			[ 'trial_end', '2014-11-14 01:01:01', strtotime( '2014-11-14 01:01:01' ) ],
			[ 'trial_end', 0, 0 ],
		];
	}

	/**
	 * Testing $subscription->get_time()
	 *
	 * @dataProvider get_time_data
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_time( $date_type, $date_to_set, $expected ) {
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'start_date' => '2014-01-01 10:19:40', // make sure we have a start date before all the test dates we want to run
			]
		);

		// We need an order to set to the last payment date on
		if ( 'last_order_date_created' === $date_type ) {
			WCS_Helper_Subscription::create_renewal_order( $subscription );
		}

		$subscription->update_dates( [ $date_type => $date_to_set ] );
		$this->assertEquals( $expected, $subscription->get_time( $date_type ) );
	}

	/**
	 * Testing $subscription get_last_payment_date function
	 *
	 * @expectedDeprecated WC_Subscription::get_last_payment_date
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_last_payment_date() {
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => 'active' ] );

		$this->assertEquals( 0, PHPUnit_Utils::call_method( $subscription, 'get_last_payment_date' ) );

		$initial_order = WCS_Helper_Subscription::create_order();
		$initial_order = self::set_paid_dates_on_order( $initial_order, '2014-07-07 10:10:10' );
		$initial_order->save();

		$subscription->set_parent_id( wcs_get_objects_property( $initial_order, 'id' ) );
		$subscription->save();

		$this->assertEquals( '2014-07-07 10:10:10', PHPUnit_Utils::call_method( $subscription, 'get_last_payment_date' ) );

		$renewal_order = WCS_Helper_Subscription::create_renewal_order( $subscription );
		$renewal_order = self::set_paid_dates_on_order( $renewal_order, '2015-07-07 12:12:12' );
		$renewal_order->save();

		$this->assertEquals( '2015-07-07 12:12:12', PHPUnit_Utils::call_method( $subscription, 'get_last_payment_date' ) );
	}

	/**
	 * Set the created/paid dates on an order in a version independent way
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.2.0
	 */
	public static function set_paid_dates_on_order( $order, $paid_date ) {

		if ( is_callable( [ $order, 'set_date_created' ] ) ) {
			$order->set_date_created( wcs_date_to_time( $paid_date ) );
		} else {
			wp_update_post(
				[
					'ID'            => wcs_get_objects_property( $order, 'id' ),
					'post_date'     => get_date_from_gmt( $paid_date ),
					'post_date_gmt' => $paid_date,
				]
			);
		}

		if ( is_callable( [ $order, 'set_date_paid' ] ) ) {
			$order->set_date_paid( wcs_date_to_time( $paid_date ) );
		} else {
			update_post_meta( wcs_get_objects_property( $order, 'id' ), '_paid_date', get_date_from_gmt( $paid_date ) );
		}

		return $order;
	}

	/**
	 * Testing $subscription update_last_payment_date function
	 *
	 * @expectedDeprecated WC_Subscription::update_last_payment_date
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_update_last_payment_date() {
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'     => 'active',
				'start_date' => '2015-01-01 10:19:40', // make sure we have a start date before all the test dates
			]
		);

		$result = PHPUnit_Utils::call_method( $subscription, 'update_last_payment_date', [ '2015-07-07 10:10:10' ] );
		$this->assertFalse( $result );

		// test on original
		$initial_order     = WCS_Helper_Subscription::create_order();
		$initial_order_id  = wcs_get_objects_property( $initial_order, 'id' );
		$initial_date_paid = '2015-08-08 12:12:12';
		$subscription->set_parent_id( $initial_order_id );
		PHPUnit_Utils::call_method( $subscription, 'update_last_payment_date', [ $initial_date_paid ] );

		// Make sure initial order's dates are updated
		$initial_order = wc_get_order( $initial_order_id );
		$this->assertEquals( $initial_date_paid, wcs_get_datetime_utc_string( wcs_get_objects_property( $initial_order, 'date_paid' ) ) );

		// test on the latest renewal
		$renewal_order     = WCS_Helper_Subscription::create_renewal_order( $subscription );
		$renewal_order_id  = wcs_get_objects_property( $renewal_order, 'id' );
		$renewal_date_paid = '2015-10-08 12:12:12';
		PHPUnit_Utils::call_method( $subscription, 'update_last_payment_date', [ $renewal_date_paid ] );

		// Make sure renewal order's dates are updated
		$renewal_order = wc_get_order( $renewal_order_id );
		$this->assertEquals( $renewal_date_paid, wcs_get_datetime_utc_string( wcs_get_objects_property( $renewal_order, 'date_paid' ) ) );
	}

	/**
	 * Testing the protected $subscription->get_price_string_details method
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_price_string_details() {
		$subscription   = WCS_Helper_Subscription::create_subscription();
		$amount         = 40;
		$display_ex_tax = false;

		$expected = [
			'currency'                    => $subscription->get_currency(),
			'recurring_amount'            => $amount,
			'subscription_period'         => $subscription->get_billing_period(),
			'subscription_interval'       => $subscription->get_billing_interval(),
			'display_excluding_tax_label' => $display_ex_tax,
		];

		$result = PHPUnit_Utils::call_method( $subscription, 'get_price_string_details', [ $amount, $display_ex_tax ] );
		$this->assertEquals( $expected, $result );

		$subscription   = WCS_Helper_Subscription::create_subscription(
			[
				'billing_interval' => 3,
				'billing_period'   => 'day',
			]
		);
		$amount         = 10;
		$display_ex_tax = true;

		$expected = [
			'currency'                    => $subscription->get_currency(),
			'recurring_amount'            => $amount,
			'subscription_period'         => 'day',
			'subscription_interval'       => 3,
			'display_excluding_tax_label' => $display_ex_tax,
		];

		$result = PHPUnit_Utils::call_method( $subscription, 'get_price_string_details', [ $amount, $display_ex_tax ] );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests $subscription->cancel_order
	 *
	 * @dataProvider subscription_status_data_provider
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_cancel_order_data_provider( $status ) {
		if ( in_array( $status, [ 'cancelled', 'expired' ], true ) ) {
			// Test not required for these statuses.
			$this->markTestSkipped( 'Test not required' );
		}

		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'     => $status,
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 months' ) ),
			]
		);

		$subscription->cancel_order();
		$this->assertTrue( $subscription->has_status( 'cancelled' ) );

	}

	/**
	 * Another set of tests for cancel_order() to check if subscriptions are being set to pending-cancelled correctly.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_cancel_order_extra() {
		// create an active subscription with a valid next payment date to test it being updated to pending-cancel
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'     => 'active',
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 months' ) ),
			],
			[
				'schedule_next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
			]
		);

		$subscription->update_dates( [ 'next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ) ] );

		$subscription->cancel_order();
		$this->assertTrue( $subscription->has_status( 'pending-cancel' ) );

		//create a pending subscription and check it gets updated to cancelled rather than pending-cancelled
		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'     => 'pending',
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			],
			[
				'schedule_next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
			]
		);

		$subscription->cancel_order();
		$this->assertTrue( $subscription->has_status( 'cancelled' ) );
	}

	public function is_editable_data() {
		// returned in the format subscription status, is_manual, payment method supports, expected
		return [
			[ 'active', true, true, true ],
			[ 'active', true, false, true ],
			[ 'active', false, true, true ],
			[ 'active', false, false, false ],

			[ 'pending', true, true, true ],
			[ 'pending', true, false, true ],
			[ 'pending', false, true, true ],
			[ 'pending', false, false, true ],

			[ 'on-hold', true, true, true ],
			[ 'on-hold', true, false, true ],
			[ 'on-hold', false, true, true ],
			[ 'on-hold', false, false, false ],

			[ 'cancelled', true, true, true ],
			[ 'cancelled', true, false, true ],
			[ 'cancelled', false, true, true ],
			[ 'cancelled', false, false, false ],

			[ 'pending-cancel', true, true, true ],
			[ 'pending-cancel', true, false, true ],
			[ 'pending-cancel', false, true, true ],
			[ 'pending-cancel', false, false, false ],

			[ 'expired', true, true, true ],
			[ 'expired', true, false, true ],
			[ 'expired', false, true, true ],
			[ 'expired', false, false, false ],

			[ 'draft', true, true, true ],
			[ 'draft', true, false, true ],
			[ 'draft', false, true, true ],
			[ 'draft', false, false, true ],

			[ 'auto-draft', true, true, true ],
			[ 'auto-draft', true, false, true ],
			[ 'auto-draft', false, true, true ],
			[ 'auto-draft', false, false, true ],
		];
	}

	/**
	 * Testing $subscription->get_last_order
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_last_order() {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$this->assertFalse( $subscription->get_last_order() );

		$order    = WCS_Helper_Subscription::create_order();
		$order_id = wcs_get_objects_property( $order, 'id' );
		$subscription->set_parent_id( wcs_get_objects_property( $order, 'id' ) );
		$this->assertEquals( $order_id, $subscription->get_last_order( 'ids' ) );
		$this->assertEquals( $order_id, $subscription->get_last_order() );
		$this->assertEquals( wc_get_order( $order_id ), $subscription->get_last_order( 'all' ) );

		// Test for the status filtering parameter
		$order->update_status( 'failed' );
		$order->save();
		$this->assertFalse( $subscription->get_last_order( 'ids', array( 'parent', 'renewal' ), array( 'failed' ) ) );

		$renewal    = WCS_Helper_Subscription::create_renewal_order( $subscription );
		$renewal_id = wcs_get_objects_property( $renewal, 'id' );
		$this->assertEquals( $renewal_id, $subscription->get_last_order( 'ids' ) );
		$this->assertEquals( $renewal_id, $subscription->get_last_order() );

		// For some reason or another, WC 3.0 changes the values in the `WC_Order_Data_Store_CPT->internal_meta_keys` property, meaning we can’t compare two of the same order and trust they'll be seen as the same by assertEquals() or assertSame() because the `WC_Order->data_store` property will have different values for `WC_Order_Data_Store_CPT->internal_meta_keys`, so instead we just have to check type
		$last_order_object = $subscription->get_last_order( 'all' );
		$this->assertInstanceOf( 'WC_Order', $last_order_object );
		$this->assertEquals( $renewal_id, wcs_get_objects_property( $last_order_object, 'id' ) );
	}


	/**
	 * Testing WC_Subscription::get_view_order_url()
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_view_order_url() {
		$subscription = WCS_Helper_Subscription::create_subscription();

		$expected_permalink  = version_compare( WC_VERSION, '2.6.2', '>=' ) ? get_home_url() : '';
		$expected_permalink .= '?view-subscription=' . $subscription->get_id();

		$this->assertEquals( $expected_permalink, $subscription->get_view_order_url() );
	}

	/**
	 * Testing WC_Subscription::is_download_permitted
	 *
	 * @dataProvider subscription_status_data_provider
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_is_download_permitted( $status ) {
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => $status ] );

		if ( in_array( $status, [ 'active', 'pending-cancel' ], true ) ) {
			$this->assertTrue( $subscription->is_download_permitted() );
		} else {
			$this->assertFalse( $subscription->is_download_permitted() );
		}
	}

	public function has_product_data() {
		return [
			[ false, false, 'product_id', false ],
			[ false, true, 'product_id', false ],
			[ true, false, 'product_id', true ],
			[ true, true, 'product_id', true ],

			[ false, false, 'variation_id', false ],
			[ false, true, 'variation_id', true ],
			[ true, false, 'variation_id', false ],
			[ true, true, 'variation_id', true ],

			[ false, false, 'variable_id', false ],
			[ false, true, 'variable_id', true ],
			[ true, false, 'variable_id', false ],
			[ true, true, 'variable_id', true ],

			[ false, false, '4043', false ],
			// [ false, true, true, false ], - disabled due to test failing when using strict comparison.
			[ false, false, true, false ],
			[ false, false, 'rubbish', false ],
		];
	}

	/**
	 * Testing WC_Subscription::has_product
	 *
	 * @dataProvider has_product_data
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_has_product( $add_product, $add_variation, $input_id, $expected_outcome ) {
		$subscription = WCS_Helper_Subscription::create_subscription();

		$input = 0;
		if ( $add_product ) {
			$product = WCS_Helper_Product::create_simple_subscription_product();
			WCS_Helper_Subscription::add_product( $subscription, $product );

			if ( 'product_id' === $input_id ) {
				$input = $product->get_id();
			}
		}

		if ( $add_variation ) {
			$variable_product  = WC_Helper_Product::create_variation_product();
			$variations        = $variable_product->get_available_variations();
			$variation         = array_shift( $variations );
			$variation_product = wc_get_product( $variation['variation_id'] );

			WCS_Helper_Subscription::add_product( $subscription, $variation_product, 1, [ 'variation' => $variation ] );

			if ( 'variation_id' === $input_id ) {
				$input = $variation['variation_id'];
			} elseif ( 'variable_id' === $input_id ) {
				$input = $variable_product->get_id();
			}
		}

		if ( ! in_array( $input_id, [ 'variation_id', 'product_id', 'variable_id' ], true ) ) {
			$input = $input_id;
		} elseif ( empty( $input ) ) {
			$input = 10000; // a random non-existent item id
		}

		$this->assertEquals( $expected_outcome, $subscription->has_product( $input ) );
	}

	public function get_sign_up_fee_data() {
		return [
			// add product, product signup, add variation, variation signup expected
			[ false, 0, false, 0, 0 ],
			[ true, 0, true, 0, 0 ],
			[ true, 40, true, 0, 40 ],
			[ true, 20, true, 20, 40 ],
			[ true, 40, true, 20, 60 ],
		];
	}

	/**
	 * Testing WC_Subscription::get_sign_up_fee() and takes the data from get_sign_up_fee_data
	 *
	 * @dataProvider get_sign_up_fee_data
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_sign_up_fee( $add_product, $product_signup, $add_variation, $variation_signup, $expected_result ) {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$order        = WCS_Helper_Subscription::create_order();
		$subscription->set_parent_id( wcs_get_objects_property( $order, 'id' ) );

		$subscription->save();

		if ( $add_product ) {
			$product       = WCS_Helper_Product::create_simple_subscription_product();
			$order_item_id = WCS_Helper_Subscription::add_product( $order, $product );
			WCS_Helper_Subscription::add_product( $subscription, $product );

			if ( $product_signup > 0 ) {
				if ( is_callable( [ $order, 'get_item' ] ) ) { // WC 3.0, add meta to the item itself
					$order_item = $order->get_item( $order_item_id );
					$order_item->set_total( $product_signup );
					$order_item->save();
				} else {
					wc_update_order_item_meta( $order_item_id, '_line_total', $product_signup );
				}
			}
		}

		if ( $add_variation ) {
			$variable_product  = WC_Helper_Product::create_variation_product();
			$variations        = $variable_product->get_available_variations();
			$variation         = array_shift( $variations );
			$variation_product = wc_get_product( $variation['variation_id'] );

			$order_item_id        = WCS_Helper_Subscription::add_product( $order, $variation_product, 1, [ 'variation' => $variation ] );
			$subscription_item_id = WCS_Helper_Subscription::add_product( $subscription, $variation_product, 1, [ 'variation' => $variation ] );

			if ( $variation_signup > 0 ) {

				if ( is_callable( [ $order, 'get_item' ] ) ) { // WC 3.0, add meta to the item itself
					$order_item = $order->get_item( $order_item_id );
					$order_item->set_total( $variation_signup );
					$order_item->save();
				} else {
					wc_update_order_item_meta( $order_item_id, '_line_total', $variation_signup );
				}

				if ( is_callable( [ $order, 'get_item' ] ) ) { // WC 3.0, add meta to the item itself
					$subscription_item = $subscription->get_item( $subscription_item_id );
					$subscription_item->set_total( 0 );
					$subscription_item->save();

					// We need to flush the cache here because of a bug in WC <= 3.0.4 which means the global cache isn't updated
					global $wp_object_cache;
					$wp_object_cache->flush();

					// Now that we've set the order item meta, we need to update the order items on the subscription for WC 3.0, which is most easily done by reinstantiating the subscription
					$subscription = wcs_get_subscription( $subscription->get_id() );
				} else {
					wc_update_order_item_meta( $subscription_item_id, '_line_total', 0 );
				}
			}
		}

		$this->assertEquals( $expected_result, $subscription->get_sign_up_fee() );
	}

	public function get_items_sign_up_fee_data() {
		return [
			[
				true,
				true,
				[
					'input'      => 'item_id',
					'qty'        => 1,
					'has_trial'  => true,
					'signup_fee' => 40,
				],
				40,
			],
			[
				true,
				true,
				[
					'input'      => 'item_id',
					'qty'        => 2,
					'has_trial'  => true,
					'signup_fee' => 40,
				],
				20,
			],
			[
				true,
				true,
				[
					'input'      => 'item_id',
					'qty'        => 1,
					'has_trial'  => false,
					'signup_fee' => 40,
				],
				40,
			],
			[
				true,
				true,
				[
					'input'      => 'item_id',
					'qty'        => 2,
					'has_trial'  => true,
					'signup_fee' => 0,
				],
				0,
			],
			[
				true,
				false,
				[
					'input'      => 'item_id',
					'qty'        => 1,
					'has_trial'  => false,
					'signup_fee' => 0,
				],
				'exception',
			],
			[
				false,
				false,
				[
					'input'      => 'rubbish',
					'qty'        => 1,
					'has_trial'  => false,
					'signup_fee' => 0,
				],
				'exception',
			],
			[
				false,
				false,
				[
					'input'      => true,
					'qty'        => 1,
					'has_trial'  => false,
					'signup_fee' => 0,
				],
				0,
			],
		];
	}

	/**
	 * Testing WC_Subscription::get_items_sign_up_fee
	 *
	 * @dataProvider get_items_sign_up_fee_data
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_items_sign_up_fee( $has_parent, $has_item, $args, $expected ) {
		$subscription = WCS_Helper_Subscription::create_subscription();

		if ( $has_parent ) {
			$order = WCS_Helper_Subscription::create_order();
			$subscription->set_parent_id( wcs_get_objects_property( $order, 'id' ) );
			$subscription->save();
		}

		if ( $has_item ) {
			$product = WCS_Helper_Product::create_simple_subscription_product();

			$order_item_id        = WCS_Helper_Subscription::add_product( $order, $product, $args['qty'] );
			$subscription_item_id = WCS_Helper_Subscription::add_product( $subscription, $product, $args['qty'] );

			if ( $args['signup_fee'] > 0 ) {

				if ( is_callable( [ $order, 'get_item' ] ) ) { // WC 3.0, add meta to the item itself
					$order_item = $order->get_item( $order_item_id );
					$order_item->set_total( $args['signup_fee'] );
					$order_item->save();
				} else {
					wc_update_order_item_meta( $order_item_id, '_line_total', $args['signup_fee'] );
				}
			}

			if ( 'item_id' === $args['input'] ) {
				$args['input'] = $subscription_item_id;
			}

			if ( $args['has_trial'] ) {
				if ( is_callable( [ $subscription, 'get_item' ] ) ) { // WC 3.0, add meta to the item itself
					$item = $subscription->get_item( $subscription_item_id );
					$item->update_meta_data( '_has_trial', 'true' );
					$item->save();
					// Now that we've set the order item meta, we need to update the order items on the subscription for WC 3.0, which is most easily done by reinstantiating the subscription
					$subscription = wcs_get_subscription( $subscription->get_id() );
				} else {
					wc_update_order_item_meta( $subscription_item_id, '_has_trial', 'true' );
				}
			}
		}

		if ( 'exception' === $expected ) {

			$this->setExpectedException( 'InvalidArgumentException', 'Invalid data. No valid item id was passed in.' );
			$subscription->get_items_sign_up_fee( $args['input'] );

		} else {
			$this->assertEquals( $expected, $subscription->get_items_sign_up_fee( $args['input'] ) );

			if ( $expected > 10 && ! $args['has_trial'] ) {

				$expected -= ( 10 / $args['qty'] );

				if ( is_callable( [ $order, 'get_item' ] ) ) { // WC 3.0, add meta to the item itself
					$subscription_item = $subscription->get_item( $subscription_item_id );
					$subscription_item->set_total( 10 );
					$subscription_item->save();
					$subscription = wcs_get_subscription( $subscription->get_id() );
				} else {
					wc_update_order_item_meta( $subscription_item_id, '_line_total', 10 );
				}
			}

			$this->assertEquals( $expected, $subscription->get_items_sign_up_fee( $args['input'] ) );
		}
	}

	/**
	 * Test payment_failed is correctly setting all basic subscriptions to on-hold
	 *
	 * @dataProvider subscription_status_data_provider
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_payment_failed_statuses( $status ) {

		if ( in_array( $status, [ 'expired', 'pending-cancel', 'cancelled' ], true ) ) {
			// Test not required for these statuses.
			$this->markTestSkipped( 'Test not required' );
		}

		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => $status ] );
		$subscription->payment_failed();

		$this->assertEquals( 'on-hold', $subscription->get_status() );
	}

	/**
	 * Testing payment_failed is settings the last orders as failed.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_payment_failed() {
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => 'active' ] );
		$order        = WCS_Helper_Subscription::create_order();
		$order_id     = wcs_get_objects_property( $order, 'id' );

		$subscription->set_parent_id( $order_id );
		$this->assertNotEquals( 'failed', $order->get_status() );
		$subscription->payment_failed();
		$order = wc_get_order( $order_id ); // With WC 3.0, we need to reinit the order to make sure we have the correct status.

		$this->assertEquals( 'failed', $order->get_status() );
		$this->assertEquals( 'on-hold', $subscription->get_status() );

		$subscription     = WCS_Helper_Subscription::create_subscription();
		$renewal_order    = WCS_Helper_Subscription::create_renewal_order( $subscription );
		$renewal_order_id = wcs_get_objects_property( $renewal_order, 'id' );

		$this->assertNotEquals( 'failed', $renewal_order->get_status() );

		$subscription->payment_failed();

		$renewal_order = wc_get_order( $renewal_order_id ); // With WC 3.0, we need to reinit the order to make sure we have the correct status.

		$this->assertEquals( 'failed', $renewal_order->get_status() );
		$this->assertEquals( 'on-hold', $subscription->get_status() );

		foreach ( [ 'expired', 'cancelled', 'on-hold', 'active' ] as $status ) {
			$subscription = WCS_Helper_Subscription::create_subscription(
				[
					'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ), // need to move the start date because you cannot set a subscription end date to the same as the start date
				]
			);

			$this->assertNotEquals( 'cancelled', $subscription->get_status() );

			$subscription->payment_failed( $status );
			$this->assertEquals( $status, $subscription->get_status() );
		}
	}

	/**
	 * A basic WC_Subscription::payment_complete() test case.  These tests do not include checking the correct order notes are added
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_payment_complete() {
		$hpos_enabled = wcs_is_custom_order_tables_usage_enabled();

		$subscription = WCS_Helper_Subscription::create_subscription();
		$order        = WCS_Helper_Subscription::create_order();
		$order_id     = wcs_get_objects_property( $order, 'id' );

		$order->set_total( 10 );
		$subscription->set_parent_id( $order_id );
		$subscription->set_suspension_count( 3 );
		$subscription->save();
		$this->assertEquals( 3, $subscription->get_suspension_count() );
		if ( ! $hpos_enabled ) {
			// Keeping usage of `get_post_meta` here due legacy meta key retrieval (those should have getters/setters instead).
			$this->assertEquals( 3, get_post_meta( $subscription->get_id(), '_suspension_count', true ) );
		}

		$subscription->payment_complete();
		$subscription->save();
		$order = wc_get_order( $order_id ); // With WC 3.0, we need to reinit the order to make sure we have the correct status.

		$this->assertEquals( 'active', $subscription->get_status() );
		$this->assertEquals( 0, $subscription->get_suspension_count() );
		if ( ! $hpos_enabled ) {
			// Keeping usage of `get_post_meta` here due legacy meta key retrieval (those should have getters/setters instead).
			$this->assertEquals( 0, get_post_meta( $subscription->get_id(), '_suspension_count', true ) );
		}
		$this->assertThat(
			$order->get_status(),
			$this->logicalOr(
				'processing',
				'completed'
			)
		);
	}

	/**
	 * DataProvider for subscription->update_dates()
	 *
	 * @return array( array $dates_to_set, array $input, array $expected_outcome )
	 */
	public function update_dates_data() {
		return [
			[
				[
					'date_created'            => '2015-08-08 10:10:01',
					'last_order_date_created' => '2015-08-08 10:10:01',
				],
				[
					'date_created'            => 0,
					'last_order_date_created' => 0,
				],
				[
					'date_created'            => '2015-08-08 10:10:01',
					'last_order_date_created' => '2015-08-08 10:10:01',
				],
			],
			[
				[
					'start_date'   => '2015-01-08 10:10:01',
					'end'          => '2016-01-17 12:45:32',
					'next_payment' => '2015-06-17 12:45:32',
				],
				[
					'end'          => 0,
					'next_payment' => 0,
				],
				[
					'end'          => 0,
					'next_payment' => 0,
				],
			],
			[
				[ // dates to set on subscription before calling update_dates
					'start_date'              => '2015-05-08 12:49:32',
					'last_order_date_created' => '2015-06-08 12:49:32',
					'end'                     => '2016-08-17 12:49:32',
				],
				[ // update_dates() input array
					'trial_end'               => '2015-06-08 12:49:32',
					'last_order_date_created' => '2015-06-08 12:49:32',
					'next_payment'            => '2015-07-08 12:49:32',
				],
				[ // Expected
					'start_date'              => '2015-05-08 12:49:32',
					'trial_end'               => '2015-06-08 12:49:32',
					'last_order_date_created' => '2015-06-08 12:49:32',
					'next_payment'            => '2015-07-08 12:49:32',
					'end'                     => '2016-08-17 12:49:32',
				],
			],
		];
	}

	/**
	 * Testing WC_Subscription::update_dates()
	 *
	 * @dataProvider update_dates_data
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_update_date( $dates_to_set, $input, $expected_outcome ) {
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => 'active' ] );

		// We need an order to set the last payment date on
		if ( isset( $dates_to_set['last_order_date_created'] ) || isset( $input['last_order_date_created'] ) ) {
			$renewal_order = WCS_Helper_Subscription::create_renewal_order( $subscription );
		}

		if ( ! empty( $dates_to_set ) ) {
			$subscription->update_dates( $dates_to_set );
		}

		$subscription->update_dates( $input );

		foreach ( $expected_outcome as $date_type => $date ) {
			$this->assertEquals( $date, $subscription->get_date( $date_type ) );
		}
	}

	/**
	 * DataProvider for subscription->update_dates()
	 *
	 * @return array( array $dates_to_set, array $input, array $expected_outcome )
	 */
	public function update_dates_data_exceptions() {
		return [
			[
				[],
				'non-array',
				[
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid format. First parameter needs to be an array.',
				],
			],
			[
				[],
				[
					'end_of_trial' => '2015-08-01 15:11:20',
				],
				[
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid data. First parameter has a date that is not in the registered date types.',
				],
			],
			[
				[],
				[],
				[
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid data. First parameter was empty when passed to update_dates().',
				],
			],
			[
				[],
				[
					'date_created' => 'string_date',
				],
				[
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid date_created date. The date must be of the format: "Y-m-d H:i:s".',
				],
			],
			[
				[],
				[
					'next_payment' => 130800849,
				],
				[
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid next_payment date. The date must be of the format: "Y-m-d H:i:s".',
				],
			],
			[
				[],
				[
					'end' => '2015/06/04 10:08:01',
				],
				[
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid end date. The date must be of the format: "Y-m-d H:i:s".',
				],
			],
			[
				[ // dates to set on subscription before calling update_dates
					'start_date'              => '2015-01-08 10:10:01',
					'end'                     => '2016-01-17 12:46:32',
					'last_order_date_created' => '2015-06-17 12:46:32',
				],
				[ // update_dates() input array
					'end' => '2015-01-01 12:46:32',
				],
				[ // Expected
					'exception' => 'Exception',
					'message'   => 'The end date must occur after the last payment date.',
				],
			],
			[
				[ // dates to set on subscription before calling update_dates
					'end'        => '2016-01-17 12:47:32',
					'start_date' => '2015-03-08 12:47:32',
				],
				[ // update_dates() input array
					'end'          => '2015-01-17 12:47:32',
					'next_payment' => '2015-07-08 12:47:32',
				],
				[ // Expected
					'exception' => 'Exception',
					'message'   => 'The end date must occur after the next payment date.',
				],
			],
			[
				[ // dates to set on subscription before calling update_dates
					'start_date'              => '2015-01-08 10:10:01',
					'last_order_date_created' => '2015-06-08 12:48:32',
					'end'                     => '2016-01-17 12:48:32',
				],
				[ // update_dates() input array
					'end'                     => '2015-01-17 12:48:32',
					'next_payment'            => '2015-07-08 12:48:32',
					'last_order_date_created' => '2015-06-08 12:48:32',
					'trial_end'               => '2015-06-08 12:48:32',
					'start_date'              => '2015-05-08 12:48:32',
				],
				[ // Expected
					'exception' => 'Exception',
					'message'   => 'The end date must occur after the last payment date. The end date must occur after the next payment date. The end date must occur after the trial end date.',
				],
			],
			[
				[
					'start_date'   => '2015-05-08 12:50:32',
					'trial_end'    => '2015-06-17 12:50:32',
					'next_payment' => '2015-07-08 12:50:32',
					'end'          => '2016-01-17 12:50:32',
				],
				[
					'end'       => '2015-04-17 12:50:32',
					'trial_end' => '2015-03-08 12:50:32',
				],
				[
					'exception' => 'Exception',
					'message'   => 'The trial_end date must occur after the start date. The end date must occur after the next payment date.',
				],
			],
		];
	}

	/**
	 * Testing WC_Subscription::update_dates()
	 *
	 * @dataProvider update_dates_data_exceptions
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_update_date_exceptions( $dates_to_set, $input, $expected_outcome ) {
		$subscription = WCS_Helper_Subscription::create_subscription( [ 'status' => 'active' ] );

		// We need an order to set the last payment date on
		if ( isset( $dates_to_set['last_order_date_created'] ) || isset( $input['last_order_date_created'] ) ) {
			$renewal_order = WCS_Helper_Subscription::create_renewal_order( $subscription );
		}

		if ( ! empty( $dates_to_set ) ) {
			$subscription->update_dates( $dates_to_set );
		}

		$this->setExpectedException( $expected_outcome['exception'], $expected_outcome['message'] );
		$subscription->update_dates( $input );
	}

	public function subscription_status_data_provider() {
		return [
			[ 'active' ],
			[ 'pending' ],
			[ 'on-hold' ],
			[ 'cancelled' ],
			[ 'pending-cancel' ],
			[ 'expired' ],
		];
	}
}

