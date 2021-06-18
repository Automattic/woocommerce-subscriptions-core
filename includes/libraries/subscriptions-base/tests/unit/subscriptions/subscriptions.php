<?php

/**
 * Class: WC_Subscriptions_Get_Date_Test
 */
class WC_Subscription_Test extends WCS_Unit_Test_Case {

	/** An array of basic subscriptions used to test against */
	public $subscriptions = array();

	/**
	 * Setup the suite for testing the WC_Subscription class
	 *
	 * @since 2.0
	 */
	public function setUp() {
		parent::setUp();

		$this->subscriptions = WCS_Helper_Subscription::create_subscriptions();
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-pending' );
	 *
	 * @since 2.0
	 */
	public function test_can_be_updated_to_pending() {

		$expected_results = array(
			'pending'        => false,
			'active'         => false,
			'on-hold'        => false,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result = $subscription->can_be_updated_to( 'pending' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to pending.' );

			$actual_result = $subscription->can_be_updated_to( 'wc-pending' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to wc-pending.' );
		}
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-active' );
	 *
	 * @since 2.0
	 */
	public function test_can_be_updated_to_active() {

		$expected_results = array(
			'pending'              => true,
			'active'               => false,
			'on-hold'              => true,
			'cancelled'            => false,
			'expired'              => false,
			'switched'             => false,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {

			if ( ! isset( $expected_results[ $status ] ) ) {
				continue;
			}

			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_be_updated_to( 'active' );

			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to active.' );

			$actual_result = $subscription->can_be_updated_to( 'wc-active' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to wc-active.' );
		}

		// Subscriptions pending cancelation can only be reactivated if the subscription's end date is still in the future.
		$subcription = $this->subscriptions['pending-cancel'];

		// End date in the future
		$subcription->update_dates( array( 'end' => gmdate( 'Y-m-d H:i:s', wcs_add_months( time(), 1 ) ) ) );
		$expected       = true;
		$can_be_updated = $subscription->can_be_updated_to( 'active' );

		$this->assertEquals( $expected, $can_be_updated, '[FAILED]: pending-cancel to active.' );

		$can_be_updated = $subscription->can_be_updated_to( 'wc-active' );
		$this->assertEquals( $expected, $can_be_updated, '[FAILED]: pending-cancel to wc-active.' );

		// End date in the past
		$subcription->update_dates( array( 'end' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
		$expected       = false;
		$can_be_updated = $subscription->can_be_updated_to( 'active' );

		$this->assertEquals( $expected, $can_be_updated, '[FAILED]: pending-cancel to active.' );

		$can_be_updated = $subscription->can_be_updated_to( 'wc-active' );
		$this->assertEquals( $expected, $can_be_updated, '[FAILED]: pending-cancel to wc-active.' );

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );

		$this->assertEquals( false, $this->subscriptions['on-hold']->can_be_updated_to( 'active' ), '[FAILED]: Should not be able to activate an on-hold subscription if the payment gateway does not support it.' );
		$this->assertEquals( true, $this->subscriptions['pending']->can_be_updated_to( 'active' ), '[FAILED]: Should be able to update pending status to active if the payment method does not support subscription reactivation.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-on-hold' );
	 *
	 * @since 2.0
	 */
	public function test_can_be_updated_to_onhold() {
		$expected_results = array(
			'pending'              => true,
			'active'               => true,
			'on-hold'              => false,
			'cancelled'            => false,
			'pending-cancel'       => false,
			'expired'              => false,
			'switched'             => false,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result = $subscription->can_be_updated_to( 'on-hold' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to on-hold.' );

			$actual_result = $subscription->can_be_updated_to( 'wc-on-hold' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to wc-on-hold.' );

		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );

		$this->assertEquals( false, $this->subscriptions['active']->can_be_updated_to( 'on-hold' ), '[FAILED]: Should not be able to put subscription on-hold if the payment gateway does not support it.' );
		$this->assertEquals( false, $this->subscriptions['pending']->can_be_updated_to( 'on-hold' ), '[FAILED]: Should be able to update pending status on-hold if the payment method does not support subscription suspension.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-cancelled' );
	 *
	 * @since 2.0
	 */
	public function test_can_be_updated_to_cancelled() {
		$expected_results = array(
			'pending'        => true,
			'active'         => true,
			'on-hold'        => true,
			'cancelled'      => false,
			'pending-cancel' => true, // subscription has pending-cancel and has not yet ended
			'expired'        => false,
			'switched'       => false,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result = $subscription->can_be_updated_to( 'wc-cancelled' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to wc-cancelled.' );

			$actual_result = $subscription->can_be_updated_to( 'cancelled' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to cancelled.' );

		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );

		$this->assertEquals( false, $this->subscriptions['pending-cancel']->can_be_updated_to( 'cancelled' ) );
		$this->assertEquals( false, $this->subscriptions['active']->can_be_updated_to( 'cancelled' ) );
		$this->assertEquals( false, $this->subscriptions['pending']->can_be_updated_to( 'cancelled' ) );
		$this->assertEquals( false, $this->subscriptions['on-hold']->can_be_updated_to( 'cancelled' ) );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-switched' );
	 *
	 * @since 2.0
	 */
	public function test_can_be_updated_to_switched() {
		$expected_results = array(
			'pending'              => false,
			'active'               => false,
			'on-hold'              => false,
			'cancelled'            => false,
			'pending-cancel'       => false,
			'expired'              => false,
			'switched'             => false, // should statuses be able to be udpated to their previous status ?!
		);

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result = $subscription->can_be_updated_to( 'wc-switched' );

			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to wc-switched.' );
		}
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-expired' );
	 *
	 * @since 2.0
	 */
	public function test_can_be_updated_to_expired() {
		$expected_results = array(
			'pending'        => true,
			'active'         => true,
			'on-hold'        => true,
			'cancelled'      => false,
			'pending-cancel' => true,
			'expired'        => true,
			'switched'       => false,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result = $subscription->can_be_updated_to( 'wc-expired' );

			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Updating subscription (' . $status . ') to wc-expired.' );
		}
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'wc-pending-cancel' );
	 *
	 * @since 2.0
	 */
	public function test_can_be_updated_to_pending_cancellation() {
		$expected_results = array(
			'pending'        => false,
			'active'         => true,
			'on-hold'        => true,
			'cancelled'      => true,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result = $subscription->can_be_updated_to( 'pending-cancel' );

			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to pending-cancel.' );
		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );

		$this->assertEquals( false, $this->subscriptions['active']->can_be_updated_to( 'pending-cancel' ), '[FAILED]: Active Subscription statuses cannot be updated to pending-cancel if the payment method does not support it.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'trash' );
	 *
	 * @since 2.0
	 */
	public function test_can_be_updated_to_trash() {
		$expected_results = array(
			'pending'        => true,
			'active'         => true,
			'on-hold'        => true,
			'cancelled'      => true,
			'pending-cancel' => true,
			'expired'        => true,
			'switched'       => true,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			// although wc-trash is not a legitimate status, it should still work
			$actual_result = $subscription->can_be_updated_to( 'wc-trash' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: ' . $status . ' to trash.' );

		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );

		$this->assertEquals( false, $this->subscriptions['active']->can_be_updated_to( 'trash' ), '[FAILED]: Should not be able to  move active subscription to the trash if the payment method does not support it.' );
		$this->assertEquals( false, $this->subscriptions['pending']->can_be_updated_to( 'trash' ), '[FAILED]: Should not be able to move a Pending subscription with a payment method that does not support subscription cancellation to the trash.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );
	}

	/**
	 * Test the logic around the function WC_Subscriptions::can_be_updated_to( 'deleted' );
	 *
	 * @since 2.0
	 */
	public function test_can_be_updated_to_deleted() {
		$expected_results = array(
			'pending'        => false,
			'active'         => false,
			'on-hold'        => false,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {

			$expected_result = $expected_results[ $status ];
			$actual_result = $subscription->can_be_updated_to( 'deleted' );
			$this->assertEquals( $expected_result, $actual_result );

		}
	}

	/**
	 * Test case testing what happens when a unexpected status is entered.
	 *
	 * @since 2.0
	 */
	public function test_can_be_updated_to_other() {
		$expected_results = array(
			'pending'        => false,
			'active'         => false,
			'on-hold'        => false,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result = $subscription->can_be_updated_to( 'fgsdyfg' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Should not be able to update subscription (' . $status . ') to fgsdyfg.' );

			$actual_result = $subscription->can_be_updated_to( 7783 );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Should not be be able to update subscription (' . $status . ') to 7783.' );
		}
	}

	/**
	 * Testing WC_Subscription::can_date_be_updated( 'date_created' )
	 *
	 * @since 2.0
	 */
	public function test_can_start_date_be_updated() {
		$expected_results = array(
			'pending'              => true,
			'active'               => false,
			'on-hold'              => false,
			'cancelled'            => false,
			'pending-cancel'       => false,
			'expired'              => false,
			'switched'             => false,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {
			$expected_result = $expected_results[ $status ];
			$actual_result = $subscription->can_date_be_updated( 'date_created' );

			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Should ' . ( ( $expected_results[ $status ] ) ? '' : 'not' ) .' be able to update date (' . $status . ') to start.' );
		}

	}

	/**
	 * Testing WC_Subscription::can_date_be_updated( 'trial_end' )
	 *
	 * @since 2.0
	 */
	public function test_can_date_be_updated() {
		$expected_results = array(
			'pending'              => true,
			'active'               => true,
			'on-hold'              => true,
			'cancelled'            => false,
			'pending-cancel'       => false,
			'expired'              => false,
			'switched'             => false,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {

			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_date_be_updated( 'trial_end' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Updating trial end date of subscription (' . $status . ').' );

			// test subscriptions with a completed payment count over 1
			add_filter( 'woocommerce_subscription_renewal_payment_completed_count', array( $this, 'completed_payment_count_stub' ) );

			$this->assertEquals( false, $subscription->can_date_be_updated( 'trial_end' ), '[FAILED]: Should not be able to update a subscription ( ' . $status . ' ) trial_end date if the completed payments counts is over 1.' );

			remove_filter( 'woocommerce_subscription_renewal_payment_completed_count', array( $this, 'completed_payment_count_stub' ) );

		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );

		$this->assertEquals( true, $this->subscriptions['pending']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should able to update pending subscription even if the payment gateway does not support it.' );
		$this->assertEquals( false, $this->subscriptions['active']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should not be able to update an active subscription trial_end date if the payment gateway does not support it.' );
		$this->assertEquals( false, $this->subscriptions['on-hold']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should not be able to update an active subscription trial_end date if the payment gateway does not support it.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );
	}

	/**
	 * Testing WC_Subscription::can_date_be_updated( 'end' ) and
	 * WC_Subscription::can_date_be_updated( 'next_payment' )
	 *
	 * @since 2.0
	 */
	public function test_can_end_and_next_payment_date_be_updated() {
		$expected_results = array(
			'pending'        => true,
			'active'         => true,
			'on-hold'        => true,
			'cancelled'      => false,
			'pending-cancel' => false,
			'expired'        => false,
			'switched'       => false,
		);

		foreach ( $this->subscriptions as $status => $subscription ) {

			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_date_be_updated( 'next_payment' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Updating next_payment date of subscription (' . $status . ').' );

			$expected_result = $expected_results[ $status ];
			$actual_result   = $subscription->can_date_be_updated( 'end' );
			$this->assertEquals( $expected_result, $actual_result, '[FAILED]: Updating end date of subscription (' . $status . ').' );
		}

		// Additional test cases checking the logic around WC_Subscription::payment_method_supports() function
		add_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );

		$this->assertEquals( true, $this->subscriptions['pending']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should able to update pending subscription even if the payment gateway does not support it.' );
		$this->assertEquals( false, $this->subscriptions['active']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should not be able to update an active subscription trial_end date if the payment gateway does not support it.' );
		$this->assertEquals( false, $this->subscriptions['on-hold']->can_date_be_updated( 'trial_end' ), '[FAILED]: Should not be able to update an active subscription trial_end date if the payment gateway does not support it.' );

		remove_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'payment_method_supports_false' ) );
	}

	/**
	 * Testing WC_Subscription::calculate_date() when given rubbish.
	 *
	 * @since 2.0
	 */
	public function test_calculate_date_rubbish() {

		$this->assertEmpty( $this->subscriptions['active']->calculate_date( 'dhfu' ) );
	}

	/**
	 * Test calculating next payment date
	 * Could possible remove this test as it's pretty redundant if we're also testing the function: WC_Subscription:calculate_next_payment_date()
	 *
	 * @since 2.0
	 */
	public function test_calculate_next_payment_date() {

		$start_date = current_time( 'mysql' );

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'     => 'active',
			'start_date' => $start_date,
		) );

		$expected_result = gmdate( 'Y-m-d H:i:s', wcs_add_months( strtotime( $start_date ), 1 ) );
		$actual_result   = $subscription->calculate_date( 'next_payment' );

		$this->assertEquals( $expected_result, $actual_result );
	}

	/**
	 * Test calculating next payment date
	 * Could possible remove this test as it's pretty redundant if we're also testing the function: WC_Subscription:calculate_next_payment_date()
	 *
	 * @since 2.0
	 */
	public function test_calculate_next_payment_date_when_start_time_is_last_payment_time() {

		$start_date = current_time( 'mysql' );

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'     => 'active',
			'start_date' => $start_date,
			'last_order_date_created' => $start_date,
			'last_order_date_paid' => $start_date,
		) );

		$expected_result = gmdate( 'Y-m-d H:i:s', wcs_add_months( strtotime( $start_date ), 1 ) );
		$actual_result   = $subscription->calculate_date( 'next_payment' );

		$this->assertEquals( $expected_result, $actual_result );
	}


	/**
	 * Test calculating trial_end date.
	 *
	 * @since 2.0
	 */
	public function test_calculate_trial_end_date() {
		$now = time();
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );

		$trial_end = gmdate( 'Y-m-d H:i:s', wcs_add_months( $now, 1 ) );
		$this->assertEquals( $trial_end, $subscription->calculate_date( 'trial_end' ) );

		// test subscriptions with a completed payment count over 1
		add_filter( 'woocommerce_subscription_renewal_payment_completed_count', array( $this, 'completed_payment_count_stub' ) );

		$this->assertEmpty( $subscription->calculate_date( 'trial_end' ), '[FAILED]: Should not be able to update a subscriptions trial_end date if the completed payments counts is over 1.' );
		$this->assertEmpty( $this->subscriptions['pending']->calculate_date( 'trial_end' ), '[FAILED]: Should not be able to update a subscription trial_end date if the completed payments counts is over 1.' );

		remove_filter( 'woocommerce_subscription_renewal_payment_completed_count', array( $this, 'completed_payment_count_stub' ) );
	}

	/**
	 * Testing the logic around calculating the end of prepaid term dates
	 *
	 * @since 2.0
	 */
	public function test_calculate_end_of_prepaid_term_date() {
		// Test with next payment being in the future. If there is a future payment that means the customer has paid up until that payment date.
		$now          = time();
		$start_date   = gmdate( 'Y-m-d H:i:s', $now );
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'           => 'active',
			'start_date'       => $start_date,
			'billing_period'   => 'month',
			'billing_interval' => 1,
		) );

		$this->assertEquals( strtotime( $start_date ), strtotime( $subscription->calculate_date( 'end_of_prepaid_term' ) ), '', 3 ); // delta set to 3 as a margin of error between the dates, shouldn't be more than 1 but just to be safe.

		$expected_date = gmdate( 'Y-m-d H:i:s', wcs_add_months( $now, 1 ) );

		$subscription->update_dates( array(
			'next_payment' => $expected_date
		) );

		$this->assertEquals( strtotime( $expected_date ), strtotime( $subscription->calculate_date( 'end_of_prepaid_term' ) ), '', 3 ); // delta set to 3 as a margin of error between the dates, shouldn't be more than 1 but just to be safe.

		$expected_date = gmdate( 'Y-m-d H:i:s', wcs_add_months( $now, 2 ) );

		$subscription->update_dates( array(
			'start'        => gmdate( 'Y-m-d H:i:s', strtotime( '-2 weeks', $now ) ),
			'next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 week', $now ) ),
			'end'          => $expected_date
		) );

		$this->assertEquals( strtotime( $expected_date ), strtotime( $subscription->calculate_date( 'end_of_prepaid_term' ) ), '', 3 ); // delta set to 3 as a margin of error between the dates, shouldn't be more than 1 but just to be safe.
	}

	/**
	 * Tests the WC_Subscription::get_date() also includes testing getting
	 * dates using the suffix. Fetching dates that already exists.
	 *
	 * @expectedDeprecated WC_Subscription::get_date
	 * @since 2.0
	 */
	public function test_get_date_already_set() {

		$start_date      = '2013-12-12 08:08:08';
		$parent_order    = WCS_Helper_Subscription::create_order( array(
			'post_date_gmt' => $start_date,
			'post_date'     => get_date_from_gmt( $start_date ),
		) );
		$parent_order_id = wcs_get_objects_property( $parent_order, 'id' );

		if ( is_callable( array( $parent_order, 'set_date_created' ) ) ) { // WC 3.0+
			$parent_order->set_date_created( wcs_date_to_time( $start_date ) );
			$parent_order->save();
		} else { // WC < 3.0
			wp_update_post( array(
				'ID'            => $parent_order_id,
				'post_date_gmt' => $start_date,
				'post_date'     => get_date_from_gmt( $start_date ),
			) );
		}

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'       => 'pending',
			'start_date'   => $start_date,
			'order_id'     => $parent_order_id,
			'date_created' => $start_date,
		) );

		$subscription->update_dates( array(
			'trial_end' => '2014-01-12 08:08:08',
			'end'       => '2014-08-12 08:08:08',
		) );

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
	 * @since 2.0
	 */
	public function test_get_date_other() {
		// set a date for the pending subscription to test against
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status' => 'pending',
		) );

		$this->setExpectedException( 'InvalidArgumentException', 'Invalid data. First parameter has a date that is not in the registered date types.' );

		$subscription->update_dates( array(
			'rubbish' => '2013-12-12 08:08:08',
		) );
	}

	/**
	 * Test the get_date() function specifying a date that is not GMT.
	 *
	 * @since 2.0
	 */
	public function test_get_date_not_gmt() {

		$start_date = '2014-01-01 01:01:01';

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'     => 'pending',
			'start_date' => $start_date,
		) );

		$this->assertEquals( get_date_from_gmt( $start_date ), $subscription->get_date( 'start', 'site' ) );
		$this->assertEquals( get_date_from_gmt( $start_date ), $subscription->get_date( 'start', 'other' ) );
	}

	/**
	 * Tests for WC_Subscription::get_gate( $date, 'gmt' )
	 *
	 * @since 2.0
	 */
	public function test_get_date_gmt() {

		$expected_result = '2014-01-01 01:01:01';

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'     => 'pending',
			'start_date' => $expected_result,
		) );

		$this->assertEquals( $expected_result, $subscription->get_date( 'start', 'gmt' ) );
		$this->assertEquals( $expected_result, $subscription->get_date( 'start' ) );
	}

	/**
	 * Tests for WC_Subscription::calculate_next_payment_date() on active subscriptions.
	 *
	 * @since 2.0
	 */
	public function test_calculate_next_payment_date_active() {

		$start_time = time();
		$start_date = gmdate( 'Y-m-d H:i:s', $start_time );
		$trial_end  = gmdate( 'Y-m-d H:i:s', wcs_add_months( $start_time, 1 ) );

		// Create a mock of Subscription that has a public calculate_next_payment_date() function.
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'     => 'active',
			'start_date' => $start_date,
			'trial_end'  => $trial_end,
		) );

		$calculate_next_payment_date_method = $this->get_accessible_protected_method( $subscription, 'calculate_next_payment_date' );

		$this->assertEquals( $trial_end, $calculate_next_payment_date_method->invoke( $subscription ) );

		// no trial, last payment or end date
		$subscription->update_dates( array(
			'date_created' => $start_date,
			'trial_end'    => 0,
		) );

		$this->assertEquals( wcs_add_months( $start_time, 1 ), strtotime( $calculate_next_payment_date_method->invoke( $subscription ) ) );

		// If the subscription has an end date and the next billing period comes after that
		WCS_Helper_Subscription::create_renewal_order( $subscription );

		$last_payment_time = wcs_add_months( $start_time, 2 );
		$last_payment_date = gmdate( 'Y-m-d H:i:s', $last_payment_time );

		$subscription->update_dates( array(
			'trial_end'    => 0,
			'last_order_date_created' => $last_payment_date,
			'end'          => gmdate( 'Y-m-d H:i:s', wcs_add_time( 1, 'day', $last_payment_time ) ),
		) );

		$this->assertEquals( 0, $calculate_next_payment_date_method->invoke( $subscription ) );

		$new_start_time = strtotime( '-1 month', $start_time );
		$new_start_date = gmdate( 'Y-m-d H:i:s', $new_start_time );

		// If the last payment date is later then the trial end date, calculate the next payment based on the last payment time
		$subscription->update_dates( array(
			'start'        => $new_start_date,
			'trial_end'    => gmdate( 'Y-m-d H:i:s', wcs_add_time( 1, 'week', strtotime( 'last month', $start_time ) ) ),
			'next_payment' => 0,
			'last_order_date_created' => $last_payment_date,
			'end'          => 0,
		) );
		$this->assertEquals( wcs_add_months( $last_payment_time, 1 ), strtotime( $calculate_next_payment_date_method->invoke( $subscription ) ) );

		// trial end is greater than start time but it is not in the future, therefore we use the last payment
		$subscription->update_dates( array(
			'start'        => $new_start_date,
			'trial_end'    => gmdate( 'Y-m-d H:i:s', wcs_add_time( 1, 'week', $new_start_time ) ),
			'next_payment' => 0,
			'last_order_date_created' => $last_payment_date,
			'end'          => 0,
		) );

		$this->assertEquals( gmdate( 'Y-m-d H:i:s', wcs_add_months( $last_payment_time, 1 ) ), $calculate_next_payment_date_method->invoke( $subscription ) );

		// make sure the payment is in the future, even if calculating it more than 10 years later
		$subscription->update_dates( array(
			'start'        => '2000-12-01 00:00:00',
			'trial_end'    => 0,
			'last_order_date_created' => 0,
			'end'          => 0,
		) );

		$this->assertTrue( strtotime( $calculate_next_payment_date_method->invoke( $subscription ) ) >= current_time( 'timestamp', true ) );
	}

	/**
	 * Tests for WC_Subscription::calculate_next_payment_date() on subscriptions with different statuses
	 * Overall this a pretty pointless test because there's no checks before calulating the next payment date for status
	 *
	 * @since 2.0
	 */
	public function test_calculate_next_payment_date_per_status() {

		$start_date = current_time( 'mysql', true );
		$statuses   = array(
			'pending',
			'cancelled',
			'on-hold',
			'switched',
			'expired',
		);

		$expected_next_payment_date = wcs_add_months( strtotime( $start_date ), 1 );

		foreach ( $statuses as $status ) {
			$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => $status, 'start_date' => $start_date ) );
			$this->assertEquals( $expected_next_payment_date, strtotime( $this->get_accessible_protected_method( $subscription, 'calculate_next_payment_date' )->invoke( $subscription ) ) );
		}
	}

	/**
	 * Test WC_Subscripiton::delete_date() throws an exception when trying to delete start date.
	 *
	 * @since 2.0
	 */
	public function test_delete_start_date() {
		// make sure the start date doesn't exist
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );

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
	 * @since 2.0
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
	 * @since 2.0
	 */
	public function test_delete_date_valid() {
		$this->subscriptions['active']->delete_date( 'end' );
		$this->assertEquals( 0, $this->subscriptions['active']->get_date( 'end' ) );
		$this->assertEmpty( get_post_meta( $this->subscriptions['active']->get_id(), wcs_get_date_meta_key( 'end' ), true ) );
	}

	/**
	 * Try deleting a date that doesn't exist.
	 *
	 * @since 2.0
	 */
	public function test_delete_date_other() {
		$this->subscriptions['pending']->delete_date( 'wcs_rubbish' );
		$this->assertEquals( 0, $this->subscriptions['pending']->get_date( 'wcs_rubbish' ) );
		$this->assertEmpty( get_post_meta( $this->subscriptions['pending']->get_id(), wcs_get_date_meta_key( 'wcs_rubbish' ), true ) );
	}

	/**
	 * Test completed payment count for subscription that has no renewal orders.
	 *
	 * @since 2.0
	 */
	public function test_get_completed_count_one() {
		$order = WCS_Helper_Subscription::create_order();
		$order->payment_complete();

		foreach ( array( 'active', 'on-hold', 'pending' ) as $status ) {
			WCS_Related_Order_Store::instance()->add_relation( $order, $this->subscriptions[ $status ], 'renewal' );

			$completed_payments = $this->subscriptions[ $status ]->get_payment_count();

			$expected_count = 1;

			$this->assertEquals( $expected_count, $completed_payments );
		}
	}

	/**
	 * Test completed_payment_count() for subscription that have not yet been completed.
	 * Only tests valid cases.
	 *
	 * @since 2.0
	 */
	public function test_get_completed_count_none() {

		foreach ( array( 'active', 'on-hold', 'pending' ) as $status ) {

			$completed_payments = $this->subscriptions[ $status ]->get_payment_count();
			$this->assertEmpty( $completed_payments );

		}
	}

	/**
	 * Testing WC_Subscription::get_completed_count() where the subscription has many completed payments.
	 *
	 * @since 2.0
	 */
	public function test_get_completed_count_many() {

		$expected_count = 0;

		// create and add a few orders as completed orders for each subscription and check if the completed orders count is correct
		for ( $i = 0; $i < 3; $i++ ) {
			$order = WCS_Helper_Subscription::create_order();

			foreach ( array( 'active', 'on-hold', 'pending' ) as $status ) {
				WCS_Related_Order_Store::instance()->add_relation( $order, $this->subscriptions[ $status ], 'renewal' );
			}

			$order->payment_complete();

			$expected_count++;
		}

		foreach ( array( 'active', 'on-hold', 'pending' ) as $status ) {

			$completed_payments = $this->subscriptions[ $status ]->get_payment_count();

			$this->assertEquals( $expected_count, $completed_payments );
		}
	}

	/**
	 * Testing WC_Subscription::get_completed_count() for those weird cases that we probably don't expect to happen, but potentially could.
	 *
	 * @since 2.0
	 */
	public function test_get_completed_count_invalid_cases() {
		// new WP_Post with subscription as parent
		$post_id = wp_insert_post(
			array(
				'post_author' => 1,
				'post_name'   => 'example',
				'post_title'  => 'example_title',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		update_post_meta( $post_id, '_subscription_renewal', $this->subscriptions['active']->get_id() );

		$this->assertEmpty( $this->subscriptions['active']->get_payment_count() );
	}

	/**
	 * Testing WC_Subscription::get_failed_payment_count for subscriptions that have no failed payments.
	 *
	 * @since 2.0
	 */
	public function test_get_failed_payment_count_none() {

		foreach ( array( 'active', 'on-hold', 'pending' ) as $status ) {
			// Continue when WC_Subscription::get_failed_payment_count() is fixed
			// $this->assertEmpty( $this->subscriptions[ $status ]->get_failed_payment_count() );
		}
	}

	/**
	 * Run a few tests for susbcriptions that have one failed payment.
	 *
	 * @since 2.0
	 */
	public function test_get_failed_payment_count_one() {

		$order = WCS_Helper_Subscription::create_order();
		wp_update_post( array( 'ID' => wcs_get_objects_property( $order, 'id' ), 'post_status' => 'wc-failed' ) );

		foreach ( array( 'active', 'on-hold', 'pending' ) as $status ) {

			WCS_Related_Order_Store::instance()->add_relation( $order, $this->subscriptions[ $status ], 'renewal' );

			$failed_payments = $this->subscriptions[ $status ]->get_failed_payment_count();

			$expected_count = 1;

			$this->assertEquals( $expected_count, $failed_payments );
		}

		// use this approach if $order->update_status( 'failed' ) creates issues
	}

	/**
	 * Tests for WC_Subscription::get_failed_payment_count() for a subscription that has
	 * many failed payments.
	 *
	 * @since 2.0
	 */
	public function test_get_failed_payment_count_many() {
		$orders = array();

		for ( $i = 0; $i < 20; $i++ ) {

			$order = WCS_Helper_Subscription::create_order();
			wp_update_post( array( 'ID' => wcs_get_objects_property( $order, 'id' ), 'post_status' => 'wc-failed' ) );
			$orders[] = $order;
		}

		foreach ( array( 'active', 'on-hold', 'pending' ) as $status ) {

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
	 * @since 2.0
	 */
	public function test_get_related_order() {
		// stub REMOTE_ADDR to run in test conditions @see wc_create_order():L104 - not sure if this value exists in travis so dont override if so.
		$_SERVER['REMOTE_ADDR'] = ( isset( $_SERVER['REMOTE_ADDR'] ) ) ? $_SERVER['REMOTE_ADDR'] : '';

		// setup active subscription for testing
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );
		$order = WCS_Helper_Subscription::create_order();
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
	 * @since 2.0
	 */
	public function test_get_related_orders() {

		$start_time = strtotime( '-1 month' );

		// setup fresh active subscription
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'     => 'active',
			'start_date' => gmdate( 'Y-m-d H:i:s', $start_time ),
		) );

		$orders = array();

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
	 * @since 2.0
	 */
	public function test_update_status_to_pending_canellation() {
		$expected_to_pass = array( 'active', 'on-hold', 'cancelled' );

		foreach ( WCS_Helper_Subscription::create_subscriptions() as $status => $subscription ) {

			// nothing to check on pending cancellation subs.
			if ( 'pending-cancel' === $status ) {
				continue;
			}

			if ( in_array( $status, $expected_to_pass ) ) {

				try {

					$start_date = gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) );
					$subscription->update_dates( array( 'start' => $start_date ) );

					$subscription->update_status( 'pending-cancel' );

					$this->assertEquals( time(), $subscription->get_time( 'end' ), '', 2 );
				} catch ( Exception $e ) {

					$this->fail( $e->getMessage() );
				}

			} else {
				$exception_caught = false;

				try {
					$subscription->update_status( 'pending-cancel' );
				} catch ( Exception $e ) {
					$exception_caught = 'Unable to change subscription status to "pending-cancel".' == $e->getMessage();
				}

				$this->assertTrue( $exception_caught, '[FAILED]: Expected exception was not caught when updating ' . $status . ' to pending cancellation.' );
			}
		}
	}

	/**
	 * Test updating a subscription status to active.
	 *
	 * @since 2.0
	 */
	public function test_update_status_to_active() {

		// list of subscription that will not throw a "cannot update status" exception
		$expected_to_pass = array( 'pending', 'pending-cancel', 'on-hold', 'active' );

		foreach ( WCS_Helper_Subscription::create_subscriptions() as $status => $subscription ) {

			if ( in_array( $status, $expected_to_pass ) ) {

				if ( 'pending-cancel' === $status ) {
					$subscription->update_dates( array( 'end' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ) ) );
				}

				$subscription->update_status( 'active' );

				// check the user has the default subscriber role
				$user_data = get_userdata( $subscription->get_user_id() );
				$roles     = $user_data->roles;

				$this->assertFalse( in_array( 'administrator', $roles ) );
				$this->assertTrue( in_array( 'subscriber', $roles ) );

			} else {

				$exception_caught = false;

				try {
					$subscription->update_status( 'active' );
				} catch ( Exception $e ) {
					$exception_caught = 'Unable to change subscription status to "active".' == $e->getMessage();
				}

				$this->assertTrue( $exception_caught, '[FAILED]: Expected exception was not caught when updating ' . $status . ' to active.' );

			}
		}
	}

	/**
	 * Testing WC_Subscription::set_suspension_count function
	 *
	 */
	public function test_set_suspension_count() {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$suspensions  = 10;

		$this->assertNotEquals( $suspensions, $subscription->get_suspension_count() );
		$this->assertNotEquals( $suspensions, get_post_meta( $subscription->get_id(), '_suspension_count', true ) );

		$subscription->set_suspension_count( $suspensions );
		$subscription->save();
		$this->assertEquals( $suspensions, $subscription->get_suspension_count() );
		$this->assertEquals( $suspensions, get_post_meta( $subscription->get_id(), '_suspension_count', true ) );
	}

	/**
	 * Test updating a subscription status to on-hold. This test does not check if the user's
	 * role has been updated to inactive, this is because the same user is used throughout testing
	 * and will almost always have an active subscription.
	 *
	 * Checks the suspension count on the subscription is updated correctly.
	 *
	 * @depends test_set_suspension_count
	 * @since 2.0
	 */
	public function test_update_status_to_onhold() {
		$expected_to_pass = array( 'pending', 'active' );
		$subscriptions    = WCS_Helper_Subscription::create_subscriptions();

		foreach ( $subscriptions as $status => $subscription ) {
			// skip over subscriptions with the status on-hold, we don't need to check the suspension count
			if ( $status == 'on-hold' ) {
				continue;
			}

			if ( in_array( $status, $expected_to_pass ) ) {

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
					$exception_caught = 'Unable to change subscription status to "on-hold".' == $e->getMessage();
				}

				$this->assertTrue( $exception_caught, '[FAILED]: Expected exception was not caught when updating ' . $status . ' to on-hold.' );

			}
		}
	}

	/**
	 * Test updating the status of a subscription to expired and making sure the
	 * correct end date is set correctly.
	 *
	 * @since 2.0
	 */
	public function test_update_status_to_expired() {
		$expected_to_pass = array( 'active', 'pending', 'pending-cancel', 'on-hold' );
		$now              = time();

		$subscriptions    = WCS_Helper_Subscription::create_subscriptions( array(
			'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 month', $now ) )
		) );

		foreach ( $subscriptions as $status => $subscription ) {

			// skip over subscriptions with the status expired or switched, we don't need to check the end date for them.
			if ( $status == 'expired' || $status == 'switched'  ) {
				// skip switched until bug is fixed - PR for the fix has been made.
				continue;
			}

			if ( in_array( $status, $expected_to_pass ) ) {

				try {
					$subscription->update_status( 'expired' );
					// end date should be set to the current time
					$this->assertEquals( $now, $subscription->get_time( 'end' ), '', 3 ); // delta set to 3 as a margin of error between the dates, shouldn't be more than 1 but just to be safe.
				} catch ( Exception $e ) {
					$this->fail( $e->getMessage() );
				}

			} else {

				// expecting an exception to be thrown
				$exception_caught = false;

				try {
					$subscription->update_status( 'expired' );
				} catch ( Exception $e ) {
					$exception_caught = 'Unable to change subscription status to "expired".' == $e->getMessage();
				}

				$this->assertTrue( $exception_caught, '[FAILED]: Expected exception was not caught when updating ' . $status . ' to expired.' );

			}
		}
	}

	/**
	 * Test updating a subscription status to cancelled. Potentially look at combining the test function
	 *
	 * @since 2.0
	 */
	public function test_update_status_to_cancelled() {
		$expected_to_pass = array( 'active', 'pending', 'pending-cancel', 'on-hold' );
		$now              = time();
		$subscriptions    = WCS_Helper_Subscription::create_subscriptions( array(
			'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 month', $now ) )
		) );

		foreach ( $subscriptions as $status => $subscription ) {
			// skip over subscriptions with the status cancelled as we don't need to check the end date
			if ( $status == 'cancelled' ) {
				continue;
			}

			if ( in_array( $status, $expected_to_pass ) ) {

				try {
					$subscription->update_status( 'cancelled' );

					// end date should be set to the current time
					$this->assertEquals( $now, $subscription->get_time( 'end' ), '', 3 ); // delta set to 3 as a margin of error between the dates, shouldn't be more than 1 but just to be safe.
				} catch ( Exception $e ) {
					$this->fail( $e->getMessage() );
				}

			} else {

				$exception_caught = false;

				try {
					$subscription->update_status( 'cancelled' );
				} catch ( Exception $e ) {
					$exception_caught = 'Unable to change subscription status to "cancelled".' == $e->getMessage();
				}

				$this->assertTrue( $exception_caught, '[FAILED]: Expected exception was not caught when updating ' . $status . ' to cancelled.' );

			}
		}
	}

	/**
	 * Test updating a subscription to either expired, cancelled or switched.
	 *
	 * @since 2.0
	 */
	public function test_user_inactive_update_status_to_cancelled() {
		// create a new user with no active subscriptions
		$user_id      = wp_create_user( 'susan', 'testuser', 'susan@example.com' );
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'pending', 'start_date' => '2015-07-14 00:00:00', 'customer_id' => $user_id ) );

		try {
			$subscription->update_status( 'cancelled' );
		} catch ( Exception $e ) {
			$this->fail( $e->getMessage() );
		}

		// check the user has the default inactive role
		$user_data = get_userdata( $subscription->get_user_id() );
		$roles = $user_data->roles;

		$this->assertContains( 'customer', $roles );

		// create a new user with 1 currently active subscription
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active', 'start_date' => '2015-07-14 00:00:00', 'customer_id' => $user_id ) );

		try {
			$subscription->update_status( 'cancelled' );
		} catch ( Exception $e ) {
			$this->fail( $e->getMessage() );
		}

		$user_data = get_userdata( $subscription->get_user_id() );
		$roles = $user_data->roles;

		$this->assertContains( 'customer', $roles );
	}

	/**
	 * Test to make sure that a users role is set to inactive when updating an active
	 * or pending subscription to expired.
	 *
	 * @since 2.0
	 */
	public function test_user_inactive_update_status_to_expired() {
		// create a new user with no active subscriptions
		$user_id      = wp_create_user( 'susan', 'testuser', 'susan@example.com' );
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'pending', 'start_date' => '2015-07-14 00:00:00', 'customer_id' => $user_id ) );

		try {
			$subscription->update_status( 'expired' );
		} catch ( Exception $e ) {
			$this->fail( $e->getMessage() );
		}

		// check the user has the default inactive role
		$user_data = get_userdata( $subscription->get_user_id() );
		$roles = $user_data->roles;
		$this->assertContains( 'customer', $roles );

		// create a new user with 1 currently active subscription
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active', 'start_date' => '2015-07-14 00:00:00', 'customer_id' => $user_id ) );

		try {
			$subscription->update_status( 'cancelled' );
		} catch ( Exception $e ) {
			$this->fail( $e->getMessage() );
		}

		$user_data = get_userdata( $subscription->get_user_id() );
		$roles = $user_data->roles;
		$this->assertContains( 'customer', $roles );
	}

	/**
	 * Test  updating a subscription status to the trash
	 *
	 * @since 2.0
	 */
	public function test_update_status_to_trash() {
	}

	/**
	 * Test the logic within WC_Subscription::update_status( 'deleted' )
	 *
	 * @since 2.0
	 */
	public function test_update_status_to_deleted() {
	}

	/**
	 *
	 *
	 * @since 2.0
	 */
	public function test_update_status_to_other() {
	}

	/**
	 * Check exceptions are thrown correctly when trying to update status from active to pending.
	 *
	 * @since 2.0
	 */
	public function test_update_status_exception_thrown_one() {

		if ( version_compare( phpversion(), '5.3', '>=' ) ) {
			$this->setExpectedException( 'Exception', 'Unable to change subscription status to "pending".' );
			$this->subscriptions['active']->update_status( 'pending' );
		}
	}

	/**
	 * Check exceptions are thrown correctly when trying to update status from pending to pending-cancel.
	 *
	 * @since 2.0
	 */
	public function test_update_status_exception_thrown_two() {

		if ( version_compare( phpversion(), '5.3', '>=' ) ) {
			$this->setExpectedException( 'Exception', 'Unable to change subscription status to "pending-cancel".' );
			$this->subscriptions['pending']->update_status( 'pending-cancel' );
		}
	}

	/**
	 * Test $subscription->set_parent_id()
	 *
	 * @since 2.0
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
	 * @since 2.0
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
	 * Test update_parent with a product ID or some sort of string
	 *
	 * @since 2.0
	 */
	public function test_update_parent_invalid() {
		//$this->markTestSkipped( 'This test has not been implemented yet.' );
	}

	/**
	 * Test $subscription->needs_payment() if subscription is pending or failed or $0
	 *
	 * @dataProvider subscription_status_data_provider
	 * @since 2.0
	 */
	public function test_needs_payment_pending_failed( $status ) {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => $status ) );

		if ( in_array( $status, array( 'pending', 'failed' ) ) ) {
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
	 * @since 2.0
	 */
	public function test_needs_payment_parent_order( $status ) {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => $status ) );
		$order        = WCS_Helper_Subscription::create_order();

		$order->set_total( 100 );

		if ( in_array( $status, array( 'pending', 'failed' ) ) ) {
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
	 * @since 2.0
	 */
	public function test_needs_payment_renewal_orders( $status ) {

		// For pending status, the renewal order checks are by passed anyway as parent::needs_payment() evaluates true
		if ( 'pending' === $status ) {
			return;
		}

		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => $status ) );

		if ( in_array( $status, array( 'pending', 'failed' ) ) ) {
			$subscription->set_total( 100 );
		}
		$subscription->set_parent_id( 0 );

		$renewal_order = WCS_Helper_Subscription::create_renewal_order( $subscription );

		$renewal_order->set_total( 100 );

		$this->assertTrue( $subscription->needs_payment() );

		remove_filter( 'woocommerce_order_status_changed', 'WC_Subscriptions_Renewal_Order::maybe_record_subscription_payment', 10 );

		foreach ( array( 'on-hold', 'failed', 'cancelled' ) as $status ) {

			$renewal_order->update_status( $status );
			$this->assertTrue( $subscription->needs_payment() );
		}

		$renewal_order->update_status( 'processing' ); // update status also calls save() in WC 3.0+

		$this->assertFalse( $subscription->needs_payment() );

		add_filter( 'woocommerce_order_status_changed', 'WC_Subscriptions_Renewal_Order::maybe_record_subscription_payment', 10, 3 );
	}

	/**
	 * Tests for $subscription->payment_method_supports
	 *
	 * @since 2.0
	 */
	public function test_payment_method_supports() {
		$subscription  = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );
		$supports      = array(
			'random_text',
			'gateway_scheduled_payments',
			'subscriptions',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_cancellation',
			'subscription_date_changes',
			'subscription_amount_changes',
			'subscription_payment_method_change_customer',
		);

		WC_PayPal_Standard_Subscriptions::init();
		add_filter( 'wooocommerce_paypal_credentials_are_set', '__return_true' );
		add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );

		foreach ( $supports as $feature ) {
			$subscription->set_payment_method( false );

			// filter checks
			add_filter( 'woocommerce_subscription_payment_gateway_supports', '__return_false' );

			$this->assertFalse( $subscription->payment_method_supports( $feature ) );

			remove_filter( 'woocommerce_subscription_payment_gateway_supports', '__return_false' );

			// manual subscription
			$this->assertTrue( $subscription->payment_method_supports( $feature ) );

			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			$subscription->set_payment_method( $available_gateways['paypal'] );
			$subscription->set_requires_manual_renewal( false );

			if ( in_array( $feature, array( 'random_text', 'subscription_date_changes', 'subscription_amount_changes' ) ) ) {
				$this->assertFalse( $subscription->payment_method_supports( $feature ) );
			} else {
				$this->assertTrue( $subscription->payment_method_supports( $feature ), 'supports = ' . $feature );
			}
		}

		remove_filter( 'wooocommerce_paypal_credentials_are_set', '__return_true' );
		remove_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
	}

	/**
	 * Test is_manual inside WC_Subscription class.
	 *
	 * @dataProvider subscription_status_data_provider
	 * @since 2.0
	 */
	public function test_is_manual( $status ) {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => $status ) );

		$this->assertTrue( $subscription->is_manual() );

		add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
		$this->assertTrue( $subscription->is_manual() );

		// With an invalid gateway, is_manual() should return true even if get_manual_renewal() returns false becuase it's been set to false
		$subscription->set_payment_method( 'non-empty-string' );
		$subscription->set_requires_manual_renewal( false );
		$this->assertFalse( $subscription->get_requires_manual_renewal() );
		$this->assertTrue( $subscription->is_manual() );

		$available_gateways = WC()->payment_gateways->payment_gateways();

		// With a valid gateway that supports subscriptions, is_manual() should return false
		$subscription->set_payment_method( $available_gateways['paypal'] );
		$subscription->set_requires_manual_renewal( false );
		$this->assertFalse( $subscription->get_requires_manual_renewal() );
		$this->assertFalse( $subscription->is_manual() );

		// With a valid gateway that supports subscriptions, is_manual() should return true if it's manually set to true
		$subscription->set_requires_manual_renewal( true );
		$this->assertTrue( $subscription->get_requires_manual_renewal() );
		$this->assertTrue( $subscription->is_manual() );

		remove_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
	}

	/**
	 * Test update_manual within the WC_Subscription class
	 *
	 * @dataProvider subscription_status_data_provider
	 * @since 2.0
	 */
	public function test_set_requires_manual_renewal( $status ) {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => $status ) );
		add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );

		$this->assertTrue( $subscription->is_manual() );

		$available_gateways = WC()->payment_gateways->payment_gateways();
		$subscription->set_payment_method( $available_gateways['paypal'] );

		$subscription->set_requires_manual_renewal( false );
		$this->assertFalse( $subscription->is_manual() );

		$subscription->set_requires_manual_renewal( 'false' );
		$this->assertFalse( $subscription->is_manual() );

		$subscription->set_requires_manual_renewal( true );
		$this->assertTrue( $subscription->is_manual() );

		$subscription->set_requires_manual_renewal( 'true' );
		$this->assertTrue( $subscription->is_manual() );

		$subscription->set_requires_manual_renewal( 'junk' );
		$this->assertTrue( $subscription->is_manual() );

		remove_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
	}

	/**
	 * Tests for has_ended within the WC_Subscription
	 *
	 * @dataProvider subscription_status_data_provider
	 * @since 2.0
	 */
	public function test_has_ended_statuses( $status ) {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => $status ) );

		if ( in_array( $status, array( 'active', 'pending', 'on-hold' ) ) ) {
			$this->assertFalse( $subscription->has_status( wcs_get_subscription_ended_statuses() ) );

			add_filter( 'wcs_subscription_ended_statuses', array( $this, 'filter_has_ended_statuses' ) );

			if ( 'active' == $status ) {
				$this->assertTrue( $subscription->has_status( wcs_get_subscription_ended_statuses() ) );
			} else {
				$this->assertFalse( $subscription->has_status( wcs_get_subscription_ended_statuses() ) );
			}

			remove_filter( 'wcs_subscription_ended_statuses', array( $this, 'filter_has_ended_statuses' ) );

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
	 * @since 2.0
	 */
	public function test_get_status() {
		$subscriptions = WCS_Helper_Subscription::create_subscriptions();

		foreach ( $subscriptions as $status => $subscription ) {
			$this->assertEquals( $status, $subscription->get_status() );
		}

		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );
		wp_update_post( array( 'ID' => $subscription->get_id(), 'post_status' => 'draft' ) );
		$this->assertEquals( 'pending', $subscription->get_status() );

		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );
		wp_update_post( array( 'ID' => $subscription->get_id(), 'post_status' => 'auto-draft' ) );
		$this->assertEquals( 'pending', $subscription->get_status() );
	}

	/**
	 * Testing $subscription->get_paid_order_statuses()
	 *
	 */
	public function test_get_paid_order_statuses() {
		$subscription    = WCS_Helper_Subscription::create_subscription();
		$expected_result = array(
			'processing',
			'completed',
			'wc-processing',
			'wc-completed',
		);
		$this->assertEquals( $expected_result, $subscription->get_paid_order_statuses() );

		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'custom_paid_order_status_test_1' ) );
		array_push( $expected_result, 'pending', 'wc-pending');
		$this->assertEquals( $expected_result, $subscription->get_paid_order_statuses() );
	}

	public function custom_paid_order_status_test_1( $subscription ) {
		return 'pending';
	}

	/**
	 * Testing WC_Subscription::test_get_total_initial_payment()
	 *
	 * @since 2.0
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
		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}
		$subscription->set_parent_id( wcs_get_objects_property( $order, 'id' ) ); // Refresh the cached order object
		$this->assertEquals( 20, $subscription->get_total_initial_payment() );

		$order->set_total( 0 );
		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}
		$subscription->set_parent_id( wcs_get_objects_property( $order, 'id' ) ); // Refresh the cached order object
		$this->assertEquals( 0, $subscription->get_total_initial_payment() );
	}

	public function get_date_to_display_data() {
		return array(
			array( 'date_created', strtotime( '-1 day' ), '1 day ago' ),
			// should be '-' because we don't display next payment dates when subscriptions are not active (even if the subscription has a valid next payment set)
			array( 'next_payment', strtotime( '2015-07-20 10:19:40' ), '-' ),
			array( 'next_payment', strtotime( '+1 month' ), '-' ),
			array( 'last_order_date_created', strtotime( '2015-07-20 10:19:40' ), 'July 20, 2015' ),
			array( 'last_order_date_created', strtotime( '-5 hours' ), '5 hours ago' ),
			array( 'last_order_date_created', strtotime( '-5 days' ), '5 days ago' ),
			array( 'end', strtotime( '2015-07-20 10:19:40' ), 'July 20, 2015' ),
			array( 'end', strtotime( '+2 day' ), 'In 2 days' ),
			array( 'end', strtotime( '+1 day' ), 'In 24 hours' ),
			array( 'end', strtotime( '+5 hour' ), 'In 5 hours' ),
			array( 'end', 0, 'Not yet ended' ),
		);
	}

	/**
	 * Testing $subscription->get_date_to_display()
	 *
	 * @dataProvider get_date_to_display_data
	 */
	public function test_get_date_to_display( $date_type, $time_to_set, $expected ) {
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'start_date' => '2015-01-01 10:19:40', // make sure we have a start date before all the tests we want to run
		) );

		// We need an order to set to the last payment date on
		if ( 'last_order_date_created' === $date_type ) {
			WCS_Helper_Subscription::create_renewal_order( $subscription );
		}

		$date_to_set  = ( 0 == $time_to_set ) ? $time_to_set : gmdate( 'Y-m-d H:i:s', $time_to_set );
		$subscription->update_dates( array( $date_type => $date_to_set ) );
		$this->assertEquals( $expected, $subscription->get_date_to_display( $date_type ) );
	}

	public function get_time_data() {
		return array(
			array( 'end', '2015-10-14 01:01:01', strtotime( '2015-10-14 01:01:01' ) ),
			array( 'end', 0, 0 ),
			array( 'date_created', '2014-10-14 01:01:01', strtotime( '2014-10-14 01:01:01' ) ),
			array( 'next_payment', '2014-12-14 01:01:01', strtotime( '2014-12-14 01:01:01' ) ),
			array( 'next_payment', 0, 0 ),
			array( 'last_order_date_created', '2014-10-14 01:01:01', strtotime( '2014-10-14 01:01:01' ) ),
			array( 'trial_end', '2014-11-14 01:01:01', strtotime( '2014-11-14 01:01:01' ) ),
			array( 'trial_end', 0, 0 ),
		);
	}

	/**
	 * Testing $subscription->get_time()
	 *
	 * @dataProvider get_time_data
	 * @since 2.0
	 */
	public function test_get_time( $date_type, $date_to_set, $expected ) {
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'start_date' => '2014-01-01 10:19:40', // make sure we have a start date before all the test dates we want to run
		) );

		// We need an order to set to the last payment date on
		if ( 'last_order_date_created' === $date_type ) {
			WCS_Helper_Subscription::create_renewal_order( $subscription );
		}

		$subscription->update_dates( array( $date_type => $date_to_set ) );
		$this->assertEquals( $expected, $subscription->get_time( $date_type ) );
	}

	/**
	 * Testing $subscription get_last_payment_date function
	 *
	 * @expectedDeprecated WC_Subscription::get_last_payment_date
	 * @since 2.0
	 */
	public function test_get_last_payment_date() {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );

		$this->assertEquals( 0, $this->get_accessible_protected_method( $subscription, 'get_last_payment_date' )->invoke( $subscription ) );

		$initial_order = WCS_Helper_Subscription::create_order();
		$initial_order = self::set_paid_dates_on_order( $initial_order, '2014-07-07 10:10:10' );
		$subscription->set_parent_id( wcs_get_objects_property( $initial_order, 'id' ) );

		$this->assertEquals( '2014-07-07 10:10:10', $this->get_accessible_protected_method( $subscription, 'get_last_payment_date' )->invoke( $subscription ) );

		$renewal_order = WCS_Helper_Subscription::create_renewal_order( $subscription );
		$renewal_order = self::set_paid_dates_on_order( $renewal_order, '2015-07-07 12:12:12' );

		$this->assertEquals( '2015-07-07 12:12:12', $this->get_accessible_protected_method( $subscription, 'get_last_payment_date' )->invoke( $subscription ) );
	}

	/**
	 * Set the created/paid dates on an order in a version independent way
	 *
	 * @since 2.2.0
	 */
	public static function set_paid_dates_on_order( $order, $paid_date ) {

		if ( is_callable( array( $order, 'set_date_create' ) ) ) {
			$order->set_date_create( wcs_date_to_time( $paid_date ) );
		} else {
			wp_update_post( array(
				'ID'            => wcs_get_objects_property( $order, 'id' ),
				'post_date'     => get_date_from_gmt( $paid_date ),
				'post_date_gmt' => $paid_date,
			) );
		}

		if ( is_callable( array( $order, 'set_date_paid' ) ) ) {
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
	 * @since 2.0
	 */
	public function test_update_last_payment_date() {
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'     => 'active',
			'start_date' => '2015-01-01 10:19:40', // make sure we have a start date before all the test dates
		) );

		$result = $this->get_accessible_protected_method( $subscription, 'update_last_payment_date' )->invoke( $subscription, '2015-07-07 10:10:10' );
		$this->assertFalse( $result );

		// test on original
		$initial_order     = WCS_Helper_Subscription::create_order();
		$initial_order_id  = wcs_get_objects_property( $initial_order, 'id' );
		$initial_date_paid = '2015-08-08 12:12:12';
		$subscription->set_parent_id( $initial_order_id );
		$this->get_accessible_protected_method( $subscription, 'update_last_payment_date' )->invoke( $subscription, $initial_date_paid );

		// Make sure initial order's dates are updated
		$initial_order = wc_get_order( $initial_order_id );
		$this->assertEquals( $initial_date_paid, wcs_get_datetime_utc_string( wcs_get_objects_property( $initial_order, 'date_paid' ) ) );

		// test on the latest renewal
		$renewal_order     = WCS_Helper_Subscription::create_renewal_order( $subscription );
		$renewal_order_id  = wcs_get_objects_property( $renewal_order, 'id' );
		$renewal_date_paid = '2015-10-08 12:12:12';
		$this->get_accessible_protected_method( $subscription, 'update_last_payment_date' )->invoke( $subscription, $renewal_date_paid );

		// Make sure renewal order's dates are updated
		$renewal_order = wc_get_order( $renewal_order_id );
		$this->assertEquals( $renewal_date_paid, wcs_get_datetime_utc_string( wcs_get_objects_property( $renewal_order, 'date_paid' ) ) );
	}

	/**
	 * Testing the protected $subscription->get_price_string_details method
	 *
	 * @since 2.0
	 */
	public function test_get_price_string_details() {
		$subscription   = WCS_Helper_Subscription::create_subscription();
		$amount         = 40;
		$display_ex_tax = false;

		$expected = array(
			'currency'                    => $subscription->get_currency(),
			'recurring_amount'            => $amount,
			'subscription_period'         => $subscription->get_billing_period(),
			'subscription_interval'       => $subscription->get_billing_interval(),
			'display_excluding_tax_label' => $display_ex_tax,
		);

		$result = $this->get_accessible_protected_method( $subscription, 'get_price_string_details' )->invoke( $subscription, $amount, $display_ex_tax );
		$this->assertEquals( $expected, $result );

		$subscription   = WCS_Helper_Subscription::create_subscription( array( 'billing_interval' => 3, 'billing_period' => 'day' ) );
		$amount         = 10;
		$display_ex_tax = true;

		$expected = array(
			'currency'              => $subscription->get_currency(),
			'recurring_amount'      => $amount,
			'subscription_period'   => 'day',
			'subscription_interval' => 3,
			'display_excluding_tax_label'  => $display_ex_tax,
		);

		$result = $this->get_accessible_protected_method( $subscription, 'get_price_string_details' )->invoke( $subscription, $amount, $display_ex_tax );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests $subscription->cancel_order
	 *
	 * @dataProvider subscription_status_data_provider
	 * @since 2.0
	 */
	public function test_cancel_order_data_provider( $status ) {
		if ( ! in_array( $status, array( 'cancelled', 'expired' ) ) ) {

			$subscription = WCS_Helper_Subscription::create_subscription( array(
				'status'     => $status,
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 months' ) ),
			) );

			$subscription->cancel_order();
			$this->assertTrue( $subscription->has_status( 'cancelled' ) );
		}
	}

	/**
	 * Another set of tests for cancel_order() to check if subscriptions are being set to pending-cancelled correctly.
	 *
	 * @since 2.0
	 */
	public function test_cancel_order_extra() {
		// create an active subscription with a valid next payment date to test it being updated to pending-cancel
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'     => 'active',
			'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 months' ) ),
		), array(
			'schedule_next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
		) );

		$subscription->update_dates( array( 'next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ) ) );

		$subscription->cancel_order();
		$this->assertTrue( $subscription->has_status( 'pending-cancel' ) );

		//create a pending subscription and check it gets updated to cancelled rather than pending-cancelled
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'     => 'pending',
			'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
		), array(
			'schedule_next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
		) );

		$subscription->cancel_order();
		$this->assertTrue( $subscription->has_status( 'cancelled' ) );
	}

	public function is_editable_data() {
		// returned in the format subscription status, is_manual, payment method supports, expected
		return array(
			array( 'active', true, true, true ),
			array( 'active', true, false, true ),
			array( 'active', false, true, true ),
			array( 'active', false, false, false ),

			array( 'pending', true, true, true ),
			array( 'pending', true, false, true ),
			array( 'pending', false, true, true ),
			array( 'pending', false, false, true ),

			array( 'on-hold', true, true, true ),
			array( 'on-hold', true, false, true ),
			array( 'on-hold', false, true, true ),
			array( 'on-hold', false, false, false ),

			array( 'cancelled', true, true, true ),
			array( 'cancelled', true, false, true ),
			array( 'cancelled', false, true, true ),
			array( 'cancelled', false, false, false ),

			array( 'pending-cancel', true, true, true ),
			array( 'pending-cancel', true, false, true ),
			array( 'pending-cancel', false, true, true ),
			array( 'pending-cancel', false, false, false ),

			array( 'expired', true, true, true ),
			array( 'expired', true, false, true ),
			array( 'expired', false, true, true ),
			array( 'expired', false, false, false ),

			array( 'draft', true, true, true ),
			array( 'draft', true, false, true ),
			array( 'draft', false, true, true ),
			array( 'draft', false, false, true ),

			array( 'auto-draft', true, true, true ),
			array( 'auto-draft', true, false, true ),
			array( 'auto-draft', false, true, true ),
			array( 'auto-draft', false, false, true ),
		);
	}

	/**
	 * Testing $subscription->test_is_editable
	 *
	 * @dataProvider is_editable_data
	 * @since 2.0
	 */
	public function test_is_editable( $status, $is_manual, $payment_supports, $expected ) {

		if ( in_array( $status, array( 'draft', 'auto-draft' ) ) ) {
			$subscription = WCS_Helper_Subscription::create_subscription();
			wp_update_post( array( 'ID' => $subscription->get_id(), 'post_status' => $status ) );
			$subscription = wcs_get_subscription( $subscription->get_id() );
		} else {
			$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => $status ) );
		}

		$available_gateways = WC()->payment_gateways->payment_gateways();
		$subscription->set_payment_method( $available_gateways['paypal'] );
		$subscription->set_requires_manual_renewal( $is_manual );

		add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
		add_filter( 'woocommerce_subscription_payment_gateway_supports', '__return_' . ( ( $payment_supports ) ? 'true' : 'false' ) );

		$this->assertEquals( $expected, $subscription->is_editable() );

		remove_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
		remove_filter( 'woocommerce_subscription_payment_gateway_supports', '__return_' . ( ( $payment_supports ) ? 'true' : 'false' ) );
	}

	/**
	 * Testing $subscription->get_last_order
	 *
	 * @since 2.0
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
	 * Tests for WC_Subscription get_payment_method_to_display
	 *
	 * @since 2.0
	 */
	public function test_get_payment_method_to_display() {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$this->assertEquals( 'Manual Renewal', $subscription->get_payment_method_to_display() );

		add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );

		$available_gateways = WC()->payment_gateways->payment_gateways();
		$subscription->set_payment_method( $available_gateways['paypal'] );
		$subscription->set_requires_manual_renewal( false );

		$this->assertEquals( 'PayPal', $subscription->get_payment_method_to_display() );

		remove_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
	}

	public function set_payment_method_data() {
		return array(
			// gateway id, turn off auto payment, supports subscription flag, expected manual, expected payment method, expected
			array( 'paypal', 'yes', true, true, 'true', 'paypal', 'PayPal' ),
			array( 'paypal', 'yes', false, true, 'true', 'paypal', 'PayPal' ),
			array( 'paypal', 'no', true, false, 'false', 'paypal', 'PayPal' ),
			array( 'paypal', 'no', false, true, 'true', 'paypal', 'PayPal' ),

			array( '', 'no', true, true, 'true', '', '' ),

			array( 'custom', 'yes', true, true, 'true', 'custom', '' ),
			array( 'custom', 'yes', false, true, 'true', 'custom', '' ),
			array( 'custom', 'no', true, true, 'true', 'custom', '' ),
			array( 'custom', 'no', false, true, 'true', 'custom', '' ),
		);
	}

	/**
	 * Testing WC_Subscription::set_payment_method
	 *
	 * @dataProvider set_payment_method_data
	 * @since 2.0
	 */
	public function test_set_payment_method( $payment_method_id, $turn_off_auto_payment, $payment_supports, $expected_manual, $expected_meta_value, $expected_payment_method, $expected_payment_title ) {
		$subscription = WCS_Helper_Subscription::create_subscription();

		$available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_gateway    = isset( $available_gateways[ $payment_method_id ] ) ? $available_gateways[ $payment_method_id ] : $payment_method_id;

		add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );

		if ( $payment_supports && $payment_gateway != $payment_method_id ) {
			$payment_gateway->supports[] = 'subscriptions';
		}

		update_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments', $turn_off_auto_payment );
		$subscription->set_payment_method( $payment_gateway );

		$this->assertEquals( $expected_manual, $subscription->is_manual() );
		$this->assertEquals( $expected_manual, $subscription->get_requires_manual_renewal() );
		$this->assertEquals( $expected_payment_method, $subscription->get_payment_method() );
		$this->assertEquals( $expected_payment_title, $subscription->get_payment_method_title() );

		$subscription->save();

		$this->assertEquals( $expected_meta_value, get_post_meta( $subscription->get_id(), '_requires_manual_renewal', true ) );
		$this->assertEquals( $expected_payment_method, get_post_meta( $subscription->get_id(), '_payment_method', true ) );
		$this->assertEquals( $expected_payment_title, get_post_meta( $subscription->get_id(), '_payment_method_title', true ) );

		if ( $payment_supports && $payment_gateway != $payment_method_id ) {
			array_pop( $payment_gateway->supports );
		}

		remove_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
	}

	/**
	 * Testing WC Subscription::set_payment_method_meta
	 *
	 * @since 2.0
	 */
	public function test_set_payment_method_meta() {
		$subscription = WCS_Helper_Subscription::create_subscription();

		$this->assertEmpty( get_user_meta( $subscription->get_user_id(), 'users_card_id', true ) );
		$this->assertEmpty( get_user_meta( $subscription->get_user_id(), 'user_tokens', true ) );

		$this->assertEmpty( get_post_meta( $subscription->get_id(), 'subscription_key', true ) );
		$this->assertEmpty( get_post_meta( $subscription->get_id(), 'imported_subscription', true ) );
		$this->assertEmpty( get_post_meta( $subscription->get_id(), 'postmeta_key', true ) );

		$inputs = array(
			'user_meta'    => array( 'users_card_id' => array( 'value' => 'cc_k40fjoenv3c' ) ),
			'usermeta'     => array( 'user_tokens' => array( 'value' => '40' ) ),
			'post_meta'    => array( 'subscription_key' => array( 'value' => '1002_400' ), 'imported_subscription' => array( 'value' => 'true' ) ),
			'postmeta'     => array( 'postmeta_key' => array( 'value' => 'postmeta_value' ) ),
			'options'      => array( 'last_subscriptions_version_test' => array( 'value' => '2.0' ) ),
			'custom_table' => array( 'custom_customer_api_key' => array( 'value' => '85c3e4fac0ba4d85519978fdc3d1d9be' ) )
		);

		$this->get_accessible_protected_method( $subscription, 'set_payment_method_meta' )->invoke( $subscription, 'paypal', $inputs );
		$subscription->save();

		$this->assertEquals( 'cc_k40fjoenv3c', get_user_meta( $subscription->get_user_id(), 'users_card_id', true ) );
		$this->assertEquals( '40', get_user_meta( $subscription->get_user_id(), 'user_tokens', true ) );

		$this->assertEquals( '1002_400', get_post_meta( $subscription->get_id(), 'subscription_key', true ) );
		$this->assertEquals( 'true', get_post_meta( $subscription->get_id(), 'imported_subscription', true ) );
		$this->assertEquals( 'postmeta_value', get_post_meta( $subscription->get_id(), 'postmeta_key', true ) );

		$this->assertEquals( '2.0', get_option( 'last_subscriptions_version_test', '' ) );
	}

	/**
	 * Testing WC_Subscription::get_view_order_url()
	 *
	 * @since 2.0
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
	 * @since 2.0
	 */
	public function test_is_download_permitted( $status ) {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => $status ) );

		if ( in_array( $status, array( 'active', 'pending-cancel' ) ) ) {
			$this->assertTrue( $subscription->is_download_permitted() );
		} else {
			$this->assertFalse( $subscription->is_download_permitted() );
		}
	}

	public function has_product_data() {
		return array(
			array( false, false, 'product_id', false ),
			array( false, true, 'product_id', false ),
			array( true, false, 'product_id', true ),
			array( true, true, 'product_id', true ),

			array( false, false, 'variation_id', false ),
			array( false, true, 'variation_id', true ),
			array( true, false, 'variation_id', false ),
			array( true, true, 'variation_id', true ),

			array( false, false, 'variable_id', false ),
			array( false, true, 'variable_id', true ),
			array( true, false, 'variable_id', false ),
			array( true, true, 'variable_id', true ),

			array( false, false, '4043', false ),
			array( false, true, true, false ),
			array( false, false, true, false ),
			array( false, false, 'rubbish', false ),
		);
	}

	/**
	 * Testing WC_Subscription::has_product
	 *
	 * @dataProvider has_product_data
	 * @since 2.0
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

			WCS_Helper_Subscription::add_product( $subscription, $variation_product, 1, array( 'variation' => $variation ) );

			if ( 'variation_id' === $input_id ) {
				$input = $variation['variation_id'];
			} else if ( 'variable_id' === $input_id ) {
				$input = $variable_product->get_id();
			}
		}

		if ( ! in_array( $input_id, array( 'variation_id', 'product_id', 'variable_id' ) ) ) {
			$input = $input_id;
		} else if ( empty( $input ) ) {
			$input = 10000; // a random non-existent item id
		}

		$this->assertEquals( $expected_outcome, $subscription->has_product( $input ) );
	}

	public function get_sign_up_fee_data() {
		return array(
			// add product, product signup, add variation, variation signup expected
			array( false, 0, false, 0, 0 ),
			array( true, 0, true, 0, 0 ),
			array( true, 40, true, 0, 40 ),
			array( true, 20, true, 20, 40 ),
			array( true, 40, true, 20, 60 ),
		);
	}

	/**
	 * Testing WC_Subscription::get_sign_up_fee() and takes the data from get_sign_up_fee_data
	 *
	 * @dataProvider get_sign_up_fee_data
	 * @since 2.0
	 */
	public function test_get_sign_up_fee( $add_product, $product_signup, $add_variation, $variation_signup, $expected_result ) {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$order        = WCS_Helper_Subscription::create_order();
		$subscription->set_parent_id( wcs_get_objects_property( $order, 'id' ) );;
		$subscription->save();

		if ( $add_product ) {
			$product = WCS_Helper_Product::create_simple_subscription_product();
			$order_item_id = WCS_Helper_Subscription::add_product( $order, $product );
			WCS_Helper_Subscription::add_product( $subscription, $product );

			if ( $product_signup > 0 ) {
				if ( is_callable( array( $order, 'get_item' ) ) ) { // WC 3.0, add meta to the item itself
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

			$order_item_id        = WCS_Helper_Subscription::add_product( $order, $variation_product, 1, array( 'variation' => $variation ) );
			$subscription_item_id = WCS_Helper_Subscription::add_product( $subscription, $variation_product, 1, array( 'variation' => $variation ) );

			if ( $variation_signup > 0 ) {

				if ( is_callable( array( $order, 'get_item' ) ) ) { // WC 3.0, add meta to the item itself
					$order_item = $order->get_item( $order_item_id );
					$order_item->set_total( $variation_signup );
					$order_item->save();
				} else {
					wc_update_order_item_meta( $order_item_id, '_line_total', $variation_signup );
				}

				if ( is_callable( array( $order, 'get_item' ) ) ) { // WC 3.0, add meta to the item itself
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
		return array(
			array( true, true,
				array(
					'input'      => 'item_id',
					'qty'        => 1,
					'has_trial'  => true,
					'signup_fee' => 40
				), 40 ),
			array( true, true,
				array(
					'input'      => 'item_id',
					'qty'        => 2,
					'has_trial'  => true,
					'signup_fee' => 40
				), 20 ),
			array( true, true,
				array(
					'input'      => 'item_id',
					'qty'        => 1,
					'has_trial'  => false,
					'signup_fee' => 40
				), 40 ),
			array( true, true,
				array(
					'input'      => 'item_id',
					'qty'        => 2,
					'has_trial'  => true,
					'signup_fee' => 0
				), 0 ),
			array( true, false,
				array(
					'input'      => 'item_id',
					'qty'        => 1,
					'has_trial'  => false,
					'signup_fee' => 0
				), 'exception' ),
			array( false, false,
				array(
					'input'      => 'rubbish',
					'qty'        => 1,
					'has_trial'  => false,
					'signup_fee' => 0
				), 'exception' ),
			array( false, false,
				array(
					'input'      => true,
					'qty'        => 1,
					'has_trial'  => false,
					'signup_fee' => 0
				), 0 ),
		);
	}

	/**
	 * Testing WC_Subscription::get_items_sign_up_fee
	 *
	 * @dataProvider get_items_sign_up_fee_data
	 * @since 2.0
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

				if ( is_callable( array( $order, 'get_item' ) ) ) { // WC 3.0, add meta to the item itself
					$order_item = $order->get_item( $order_item_id );
					$order_item->set_total( $args['signup_fee'] );
					$order_item->save();
				} else {
					wc_update_order_item_meta( $order_item_id, '_line_total', $args['signup_fee'] );
				}
			}

			if ( 'item_id' == $args['input'] ) {
				$args['input'] = $subscription_item_id;
			}

			if ( $args['has_trial'] ) {
				if ( is_callable( array( $subscription, 'get_item' ) ) ) { // WC 3.0, add meta to the item itself
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

				if ( is_callable( array( $order, 'get_item' ) ) ) { // WC 3.0, add meta to the item itself
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
	 * @since 2.0
	 */
	public function test_payment_failed_statuses( $status ) {

		if ( ! in_array( $status, array( 'expired', 'pending-cancel', 'cancelled' ) ) ) {

			$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => $status ) );
			$subscription->payment_failed();

			$this->assertEquals( 'on-hold', $subscription->get_status() );
		}
	}

	/**
	 * Testing payment_failed is settings the last orders as failed.
	 *
	 * @since 2.0
	 */
	public function test_payment_failed() {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );
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

		foreach ( array( 'expired', 'cancelled', 'on-hold', 'active' ) as $status ) {
			$subscription = WCS_Helper_Subscription::create_subscription( array(
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ), // need to move the start date because you cannot set a subscription end date to the same as the start date
			) );

			$this->assertNotEquals( 'cancelled', $subscription->get_status() );

			$subscription->payment_failed( $status );
			$this->assertEquals( $status, $subscription->get_status() );
		}
	}

	/**
	 * A basic WC_Subscription::payment_complete() test case.  These tests do not include checking the correct order notes are added
	 *
	 * @since 2.0
	 */
	public function test_payment_complete() {

		$subscription = WCS_Helper_Subscription::create_subscription();
		$order        = WCS_Helper_Subscription::create_order();
		$order_id     = wcs_get_objects_property( $order, 'id' );

		$order->set_total( 10 );
		$subscription->set_parent_id( $order_id );
		$subscription->set_suspension_count( 3 );
		$subscription->save();
		$this->assertEquals( 3, $subscription->get_suspension_count() );
		$this->assertEquals( 3, get_post_meta( $subscription->get_id(), '_suspension_count', true ) );

		$subscription->payment_complete();
		$subscription->save();
		$order = wc_get_order( $order_id ); // With WC 3.0, we need to reinit the order to make sure we have the correct status.

		$this->assertEquals( 'active', $subscription->get_status() );
		$this->assertEquals( 0, $subscription->get_suspension_count() );
		$this->assertEquals( 0, get_post_meta( $subscription->get_id(), '_suspension_count', true ) );
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
		return array(
			array(
				array(
					'date_created'            => '2015-08-08 10:10:01',
					'last_order_date_created' => '2015-08-08 10:10:01',
				),
				array(
					'date_created'            => 0,
					'last_order_date_created' => 0,
				),
				array(
					'date_created'            => '2015-08-08 10:10:01',
					'last_order_date_created' => '2015-08-08 10:10:01',
				)
			),
			array(
				array(
					'start_date'   => '2015-01-08 10:10:01',
					'end'          => '2016-01-17 12:45:32',
					'next_payment' => '2015-06-17 12:45:32',
				),
				array(
					'end'          => 0,
					'next_payment' => 0,
				),
				array(
					'end'          => 0,
					'next_payment' => 0
				)
			),
			array(
				array( // dates to set on subscription before calling update_dates
					'start_date'              => '2015-05-08 12:49:32',
					'last_order_date_created' => '2015-06-08 12:49:32',
					'end'                     => '2016-08-17 12:49:32',
				),
				array( // update_dates() input array
					'trial_end'    => '2015-06-08 12:49:32',
					'last_order_date_created' => '2015-06-08 12:49:32',
					'next_payment' => '2015-07-08 12:49:32',
				),
				array( // Expected
					'start_date'              => '2015-05-08 12:49:32',
					'trial_end'               => '2015-06-08 12:49:32',
					'last_order_date_created' => '2015-06-08 12:49:32',
					'next_payment'            => '2015-07-08 12:49:32',
					'end'                     => '2016-08-17 12:49:32',
				)
			),
		);
	}

	/**
	 * Testing WC_Subscription::update_dates()
	 *
	 * @dataProvider update_dates_data
	 * @since 2.0
	 */
	public function test_update_date( $dates_to_set, $input, $expected_outcome ) {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );

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
		return array(
			array(
				array(),
				'non-array',
				array(
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid format. First parameter needs to be an array.'
				)
			),
			array(
				array(),
				array(
					'end_of_trial' => '2015-08-01 15:11:20'
				),
				array(
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid data. First parameter has a date that is not in the registered date types.'
				)
			),
			array(
				array(),
				array(),
				array(
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid data. First parameter was empty when passed to update_dates().'
				)
			),
			array( array(),
				array(
					'date_created' => 'string_date'
				),
				array(
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid date_created date. The date must be of the format: "Y-m-d H:i:s".'
					)
				),
			array( array(),
				array(
					'next_payment' => 130800849
				),
				array(
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid next_payment date. The date must be of the format: "Y-m-d H:i:s".'
				)
			),
			array( array(),
				array(
					'end' => '2015/06/04 10:08:01'
				),
				array(
					'exception' => 'InvalidArgumentException',
					'message'   => 'Invalid end date. The date must be of the format: "Y-m-d H:i:s".'
				)
			),
			array(
				array( // dates to set on subscription before calling update_dates
					'start_date'   => '2015-01-08 10:10:01',
					'end'          => '2016-01-17 12:46:32',
					'last_order_date_created' => '2015-06-17 12:46:32',
				),
				array( // update_dates() input array
					'end'          => '2015-01-01 12:46:32',
				),
				array( // Expected
					'exception'    => 'Exception',
					'message'      => 'The end date must occur after the last payment date.'
				)
			),
			array(
				array( // dates to set on subscription before calling update_dates
					'end'          => '2016-01-17 12:47:32',
					'start_date'   => '2015-03-08 12:47:32',
				),
				array( // update_dates() input array
					'end'          => '2015-01-17 12:47:32',
					'next_payment' => '2015-07-08 12:47:32'
				),
				array( // Expected
					'exception'    => 'Exception',
					'message'      => 'The end date must occur after the next payment date.'
				)
			),
			array(
				array( // dates to set on subscription before calling update_dates
					'start_date' => '2015-01-08 10:10:01',
					'last_order_date_created' => '2015-06-08 12:48:32',
					'end'          => '2016-01-17 12:48:32',
				),
				array( // update_dates() input array
					'end'          => '2015-01-17 12:48:32',
					'next_payment' => '2015-07-08 12:48:32',
					'last_order_date_created' => '2015-06-08 12:48:32',
					'trial_end'    => '2015-06-08 12:48:32',
					'start_date'   => '2015-05-08 12:48:32',
				),
				array( // Expected
					'exception' => 'Exception',
					'message'   => 'The end date must occur after the last payment date. The end date must occur after the next payment date. The end date must occur after the trial end date.'
				)
			),
			array(
				array(
					'start_date'   => '2015-05-08 12:50:32',
					'trial_end'    => '2015-06-17 12:50:32',
					'next_payment' => '2015-07-08 12:50:32',
					'end'          => '2016-01-17 12:50:32',
				),
				array(
					'end'          => '2015-04-17 12:50:32',
					'trial_end'    => '2015-03-08 12:50:32',
				),
				array(
					'exception' => 'Exception',
					'message'   => 'The trial_end date must occur after the start date. The end date must occur after the next payment date.'
				)
			),
		);
	}

	/**
	 * Testing WC_Subscription::update_dates()
	 *
	 * @dataProvider update_dates_data_exceptions
	 * @since 2.0
	 */
	public function test_update_date_exceptions( $dates_to_set, $input, $expected_outcome ) {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );

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
		return array(
			array( 'active' ),
			array( 'pending' ),
			array( 'on-hold' ),
			array( 'cancelled' ),
			array( 'pending-cancel' ),
			array( 'expired' ),
		);
	}
}

