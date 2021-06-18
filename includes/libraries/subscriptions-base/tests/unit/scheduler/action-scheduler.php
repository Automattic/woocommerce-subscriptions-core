<?php

/**
 * Test suite for the WCS_Action_Scheduler class
 *
 * @group scheduler
 */
class WCS_Action_Scheduler_Test extends WCS_Unit_Test_Case {

	protected static $subscription;

	protected static $renewal_order;

	protected $action_hooks = array(
		'trial_end'     => 'woocommerce_scheduled_subscription_trial_end',
		'next_payment'  => 'woocommerce_scheduled_subscription_payment',
		'payment_retry' => 'woocommerce_scheduled_subscription_payment_retry',
		'end'           => 'woocommerce_scheduled_subscription_expiration',
	);

	public static function setUpBeforeClass() {

		self::$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status' => 'active'
		) );

		self::$renewal_order = WCS_Helper_Subscription::create_renewal_order( self::$subscription );
	}

	/**
	 * Make sure when a subscription's date is updated, if the subscription's status is active, events are scheduled correctly
	 *
	 * @dataProvider provide_update_date_data
	 */
	public function test_update_date( $date_type, $datetime ) {

		$mock_subscription = $this->get_mock_subscription( strtotime( $datetime ) );

		$this->get_mock_scheduler()->update_date( $mock_subscription, $date_type, $datetime );

		$this->assertEquals( strtotime( $datetime ), as_next_scheduled_action( $this->action_hooks[ $date_type ], $this->get_action_args( $date_type ) ) );
	}

	/**
	 * Make sure when a subscription's date is updated, if the subscription's status is not active, events are not scheduled correctly
	 *
	 * @dataProvider provide_update_date_data
	 */
	public function test_update_date_inactive( $date_type, $datetime ) {

		$mock_subscription = $this->get_mock_subscription( strtotime( $datetime ), 'on-hold' );

		$this->get_mock_scheduler()->update_date( $mock_subscription, $date_type, $datetime );

		$this->assertEquals( false, as_next_scheduled_action( $this->action_hooks[ $date_type ], $this->get_action_args( $date_type ) ) );
	}

	public function provide_update_date_data() {
		return array(
			array( 'trial_end', date( 'Y-m-d H:i:s', strtotime( '+1 weeks' ) ) ),
			array( 'next_payment', date( 'Y-m-d H:i:s', strtotime( '+2 weeks' ) ) ),
			array( 'end', date( 'Y-m-d H:i:s', strtotime( '+3 weeks' ) ) ),
		);
	}

	/**
	 * Make sure when a subscription's retry date is updated, an event is scheduled correctly
	 */
	public function test_update_date_retry() {

		$datetime = date( 'Y-m-d H:i:s', strtotime( '+3 weeks' ) );

		$this->get_mock_scheduler()->update_date( $this->get_mock_subscription( strtotime( $datetime ), 'on-hold' ), 'payment_retry', $datetime );
		$this->assertEquals( strtotime( $datetime ), as_next_scheduled_action( $this->action_hooks['payment_retry'], $this->get_action_args( 'payment_retry' ) ) );
	}

	/**
	 * Make sure when a subscription's date is deleted, the subscription's scheduled events are unscheduled correctly
	 *
	 * @dataProvider provide_delete_date_data
	 */
	public function test_delete_date( $date_type, $timestamp ) {

		$hook = $this->action_hooks[ $date_type ];

		// Test with more than one scheduled action to make sure duplicates are cleared correctly
		as_schedule_single_action( $timestamp, $hook, $this->get_action_args( $date_type ) );
		as_schedule_single_action( $timestamp + DAY_IN_SECONDS, $hook, $this->get_action_args( $date_type ) );

		$this->get_mock_scheduler()->delete_date( $this->get_mock_subscription( $timestamp ), $date_type );
		$this->assertFalse( as_next_scheduled_action( $hook, $this->get_action_args( $date_type ) ) );
	}

	/**
	 * Get an array of timestamps for each date type to test that it is deleted correctly
	 */
	public function provide_delete_date_data() {
		return array(
			array( 'trial_end', strtotime( '+1 weeks' ) ),
			array( 'next_payment', strtotime( '+2 weeks' ) ),
			array( 'payment_retry', strtotime( '+3 weeks' ) ),
			array( 'end', strtotime( '+4 weeks' ) ),
		);
	}

	/**
	 * Make sure when a subscription's date is deleted, the subscription's scheduled events are unscheduled correctly, even when it's not active
	 *
	 * @dataProvider provide_delete_date_data_inactive
	 */
	public function test_delete_date_inactive( $date_type, $timestamp ) {

		$hook = $this->action_hooks[ $date_type ];

		// Test with more than one scheduled action to make sure duplicates are cleared correctly
		as_schedule_single_action( $timestamp, $hook, $this->get_action_args( $date_type ) );
		as_schedule_single_action( $timestamp + DAY_IN_SECONDS, $hook, $this->get_action_args( $date_type ) );

		$this->get_mock_scheduler()->delete_date( $this->get_mock_subscription( $timestamp, 'on-hold' ), $date_type );
		$this->assertFalse( as_next_scheduled_action( $hook, $this->get_action_args( $date_type ) ) );
	}

	/**
	 * Get an array of timestamps for each date type to test that it is deleted correctly
	 */
	public function provide_delete_date_data_inactive() {

		$delete_date_data = $this->provide_delete_date_data();

		foreach ( $delete_date_data as $index => $data ) {
			if ( 'end' == $data[0] ) {
				unset( $delete_date_data[ $index ] );
			}
		}

		return $delete_date_data;
	}

	/**
	 * Make sure when a subscription's status is updated, duplicate scheduled events are unschedule
	 */
	public function test_update_status_to_active() {

		// Make sure there are no scheduled actions
		$this->clear_hooks();

		$timestamp = strtotime( '+1 weeks' );

		$this->get_mock_scheduler()->update_status( $this->get_mock_subscription( $timestamp ), 'active', null );

		// Now sure there are scheduled actions for each date type
		foreach ( $this->action_hooks as $date_type => $hook ) {
			$this->assertEquals( $timestamp, as_next_scheduled_action( $hook, $this->get_action_args( $date_type ) ) );
		}
	}

	/**
	 * Make sure when a subscription's status is updated, duplicate scheduled events are unschedule
	 */
	public function test_update_status_to_pending_cancellation() {

		// Make sure there are no scheduled actions
		$this->clear_hooks();

		$timestamp = strtotime( '+1 weeks' );

		$this->get_mock_scheduler()->update_status( $this->get_mock_subscription( $timestamp ), 'pending-cancel', null );

		// Now sure the end of prepaid term hook is scheduled
		$this->assertEquals( $timestamp, as_next_scheduled_action( 'woocommerce_scheduled_subscription_end_of_prepaid_term', $this->get_action_args( 'end' ) ) );

		// But no other hooks are scheduled
		foreach ( $this->action_hooks as $date_type => $hook ) {
			$this->assertFalse( as_next_scheduled_action( $date_type, $this->get_action_args( $date_type ) ) );
		}
	}

	/**
	 * Make sure when a subscription's status is updated to an inactive status, scheduled events are cleared
	 *
	 * @dataProvider provide_inactive_statuses
	 */
	public function test_update_status_to_inactive( $inactive_status ) {

		// Make sure there are scheduled actions for each date type
		foreach ( $this->action_hooks as $date_type => $hook ) {
			as_schedule_single_action( strtotime( '+1 weeks' ), $hook, $this->get_action_args( $date_type ) );
		}

		$mock_subscription = $this->get_mock_subscription( time(), $inactive_status );
		$this->get_mock_scheduler()->update_status( $mock_subscription, $inactive_status, null );

		// Now make sure there are no scheduled actions
		foreach ( $this->action_hooks as $date_type => $hook ) {
			$this->assertFalse( as_next_scheduled_action( $hook, $this->get_action_args( $date_type ) ) );
		}
	}

	public function provide_inactive_statuses() {
		return array(
			array( 'on-hold' ),
			array( 'cancelled' ),
			array( 'switched' ),
			array( 'expired' ),
			array( 'trash' ),
		);
	}

	public function test_get_scheduled_action_hook() {

		if ( ! method_exists( 'ReflectionMethod', 'setAccessible' ) ) { // only available in PHP 5.3.2+
			return;
		}

		$expected_hooks = array(
			'date_created'  => '',
			'trial_end'     => 'woocommerce_scheduled_subscription_trial_end',
			'next_payment'  => 'woocommerce_scheduled_subscription_payment',
			'end'           => 'woocommerce_scheduled_subscription_expiration',
			'unknown_date'  => '',
		);

		$mock_scheduler    = $this->get_mock_scheduler();
		$mock_subscription = $this->get_mock_subscription();

		foreach ( $expected_hooks as $date_type => $expected_hook ) {
			$this->assertEquals( $expected_hook, $this->get_accessible_protected_method( $mock_scheduler, 'get_scheduled_action_hook' )->invoke( $mock_scheduler, $mock_subscription, $date_type ) );
		}

		$cancelled_subscription = $this->get_mock_subscription( time(), 'cancelled' );

		$this->assertEquals( 'woocommerce_scheduled_subscription_end_of_prepaid_term', $this->get_accessible_protected_method( $mock_scheduler, 'get_scheduled_action_hook' )->invoke( $mock_scheduler, $cancelled_subscription, 'end' ) );
	}

	public function test_get_action_args() {

		if ( ! method_exists( 'ReflectionMethod', 'setAccessible' ) ) { // only available in PHP 5.3.2+
			return;
		}

		$date_types = array(
			'date_created',
			'trial_end',
			'next_payment',
			'end',
			'unknown_date',
		);

		$mock_scheduler    = $this->get_mock_scheduler();
		$mock_subscription = $this->get_mock_subscription();

		foreach ( $date_types as $date_type ) {
			$this->assertEquals( array( 'subscription_id' => $mock_subscription->get_id() ), $this->get_accessible_protected_method( $mock_scheduler, 'get_action_args' )->invoke( $mock_scheduler, $date_type, $mock_subscription ) );
		}
	}

	public function test_get_action_args_payment_retry() {
		if ( method_exists( 'ReflectionMethod', 'setAccessible' ) ) { // only available in PHP 5.3.2+
			$mock_scheduler    = $this->get_mock_scheduler();
			$mock_subscription = $this->get_mock_subscription();
			$this->assertEquals( array( 'order_id' => wcs_get_objects_property( self::$renewal_order, 'id' ) ), $this->get_accessible_protected_method( $mock_scheduler, 'get_action_args' )->invoke( $mock_scheduler, 'payment_retry', $mock_subscription ) );
		}
	}

	protected function get_action_args( $date_type ) {
		return ( 'payment_retry' == $date_type ) ? array( 'order_id' => wcs_get_objects_property( self::$renewal_order, 'id' ) ) :  array( 'subscription_id' => self::$subscription->get_id() );
	}

	protected function get_mock_subscription( $time_return_value = null, $status_return_value = 'active' ) {

		if ( is_null( $time_return_value ) ) {
			$time_return_value = time();
		}

		$mock_subscription = parent::get_mock_subscription( self::$subscription->get_id(), array( 'get_time', 'get_status', 'get_last_order' ) );
		$mock_subscription->expects( $this->any() )->method( 'get_time' )->will( $this->returnValue( $time_return_value ) );
		$mock_subscription->expects( $this->any() )->method( 'get_status' )->will( $this->returnValue( $status_return_value ) );
		$mock_subscription->expects( $this->any() )->method( 'get_last_order' )->will( $this->returnValue( wcs_get_objects_property( self::$renewal_order, 'id' ) ) );

		return $mock_subscription;
	}

	protected function get_mock_scheduler() {

		$scheduler = $this->getMockBuilder( 'WCS_Action_Scheduler' )->disableOriginalConstructor()->setMethods( array( 'set_date_types_to_schedule', 'get_date_types_to_schedule' ) )->getMock();

		$scheduler->expects( $this->any() )->method( 'get_date_types_to_schedule' )->will( $this->returnValue( array(
			'date_created',
			'trial_end',
			'next_payment',
			'payment_retry',
			'last_payment',
			'cancelled',
			'end',
		) ) );

		return $scheduler;
	}

	protected function clear_hooks() {
		foreach ( $this->action_hooks as $date_type => $hook ) {
			do {
				as_unschedule_action( $hook, $this->get_action_args( $date_type ) );
				$next_scheduled = as_next_scheduled_action( $hook, $this->get_action_args( $date_type ) );
			} while ( false !== $next_scheduled );
		}
	}
}
