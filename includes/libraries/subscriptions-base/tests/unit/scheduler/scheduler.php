<?php

/**
 * Test suite for the WCS_Scheduler class
 *
 * @group scheduler
 */
class WCS_Scheduler_Test extends WCS_Unit_Test_Case {

	/**
	 * Make sure WCS_Scheduler has the correct date types set by default by checking WCS_Scheduler->get_date_types_to_schedule()
	 *
	 * @return null
	 */
	public function test_date_types_to_schedule_default() {

		if ( ! method_exists( 'ReflectionMethod', 'setAccessible' ) ) { // only available in PHP 5.3.2+
			return;
		}

		$date_types_to_schedule = wcs_get_subscription_date_types();
		unset( $date_types_to_schedule['start'], $date_types_to_schedule['last_payment'] );

		$this->assertEquals( array_keys( $date_types_to_schedule ), $this->get_schedulers_date_types_to_schedule() );
	}

	/**
	 * Make sure WCS_Scheduler has the correct date types set when using the 'woocommerce_subscription_dates' filter by checking WCS_Scheduler->get_date_types_to_schedule()
	 *
	 * @return null
	 */
	public function test_date_types_to_schedule_filter_subscription_dates() {

		if ( ! method_exists( 'ReflectionMethod', 'setAccessible' ) ) { // only available in PHP 5.3.2+
			return;
		}

		add_filter( 'woocommerce_subscription_dates', array( &$this, 'custom_date_types' ) );
		$this->assertEquals( array_keys( $this->custom_date_types() ), $this->get_schedulers_date_types_to_schedule() );
		remove_filter( 'woocommerce_subscription_dates', array( &$this, 'custom_date_types' ) );
	}

	/**
	 * Make sure WCS_Scheduler has the correct date types set when using the 'woocommerce_subscriptions_date_types_to_schedule' filter by checking WCS_Scheduler->get_date_types_to_schedule()
	 *
	 * @return null
	 */
	public function test_date_types_to_schedule_filter_date_types_to_schedule() {

		if ( ! method_exists( 'ReflectionMethod', 'setAccessible' ) ) { // only available in PHP 5.3.2+
			return;
		}

		add_filter( 'woocommerce_subscriptions_date_types_to_schedule', array( &$this, 'custom_date_type_keys' ) );
		$this->assertEquals( $this->custom_date_type_keys(), $this->get_schedulers_date_types_to_schedule() );
	}

	/**
	 * Get a set of unique date type name => value pairs for applying to date filters.
	 *
	 * @return array
	 */
	public function custom_date_types() {
		return array(
			'custom_start'        => _x( 'Start Date', 'table heading', 'woocommerce-subscriptions' ),
			'custom_trial_end'    => _x( 'Trial End', 'table heading', 'woocommerce-subscriptions' ),
			'custom_next_payment' => _x( 'Next Payment', 'table heading', 'woocommerce-subscriptions' ),
			'custom_last_payment' => _x( 'Last Order Date', 'table heading', 'woocommerce-subscriptions' ),
			'custom_cancelled'    => _x( 'Cancelled Date', 'table heading', 'woocommerce-subscriptions' ),
			'custom_end'          => _x( 'End Date', 'table heading', 'woocommerce-subscriptions' ),
		);
	}

	/**
	 * Get a set of unique date type names for applying to date filters.
	 *
	 * @return array
	 */
	public function custom_date_type_keys() {
		return array_keys( $this->custom_date_types() );
	}

	/**
	 * Get the values returned by get_date_types_to_schedule() on a new instance of WCS_Scheduler.
	 *
	 * Requires making the protected WCS_Scheduler::get_date_types_to_schedule() accessible.
	 *
	 * @return array
	 */
	protected function get_schedulers_date_types_to_schedule() {

		$abstract_scheduler = $this->getMockForAbstractClass( 'WCS_Scheduler' );
		$abstract_scheduler->set_date_types_to_schedule();

		$get_date_types_to_schedule_method = $this->get_accessible_protected_method( 'WCS_Scheduler', 'get_date_types_to_schedule' );

		return $get_date_types_to_schedule_method->invoke( $abstract_scheduler );
	}
}