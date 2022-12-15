<?php

/**
 * @group wcs-helper-functions
 */
class WCS_Helper_Functions_Test extends WP_UnitTestCase {

	/**
	 * @dataProvider wcs_maybe_unprefix_key_provider
	 *
	 * @param $expected
	 * @param $key
	 * @param $prefix
	 */
	public function test_wcs_maybe_unprefix_key( $expected, $key, $prefix ) {
		$new_key = null === $prefix ? wcs_maybe_unprefix_key( $key ) : wcs_maybe_unprefix_key( $key, $prefix );
		$this->assertEquals( $expected, $new_key );
	}

	public function wcs_maybe_unprefix_key_provider() {
		return array(
			array( 'foo_key', '_foo_key', null ),
			array( 'subscription_renewal', '_subscription_renewal', null ),
			array( 'key', '_foo_key', '_foo_' ),
			array( 'foo_key', 'foo_key', null ),
		);
	}

	/**
	 * Test @see wcs_get_minor_version_string() to make sure it returns the correct minor version string for a specific input.
	 *
	 * @dataProvider wcs_get_minor_version_string_provider
	 *
	 * @param $version
	 * @param $expected
	 */
	public function test_wcs_get_minor_version_string( $version, $expected ) {
		$this->assertEquals( wcs_get_minor_version_string( $version ), $expected );
	}

	/**
	 * A data provider for @see test_wcs_get_minor_version_string().
	 *
	 * @return array
	 */
	public function wcs_get_minor_version_string_provider() {
		return array(
			array( 'bogus', '0.0' ),
			array( 'bogus.version.string', '0.0' ),
			array( '', '0.0' ),
			array( '1', '1.0' ),
			array( '1.1', '1.1' ),
			array( '1.1.0', '1.1' ),
			array( '2.10.3', '2.10' ),
			array( '2.0.0-RC-1', '2.0' ),
			array( '2.0beta-2', '2.0' ),
			array( '3.2-beta-2', '3.2' ),
		);
	}

	public function test_wcs_sort_objects() {
		$wcs_object_sorter = new WCS_Object_Sorter( 'date_paid' );
		$subscription      = WCS_Helper_Subscription::create_subscription();

		// Create some orders related to subscription.
		WCS_Helper_Subscription::create_parent_order( $subscription );
		for ( $i = 0; $i <= 5; $i++ ) {
			$order = WCS_Helper_Subscription::create_renewal_order( $subscription );
			$order->set_date_paid( strtotime( "-$i week" ) );
			$order->save();
		}

		$orders   = $subscription->get_related_orders( 'all', array( 'renewal', 'parent' ) );
		$orders_2 = $orders;

		// Sort.
		wcs_sort_objects( $orders, 'date_paid' );
		uasort( $orders_2, array( $wcs_object_sorter, 'ascending_compare' ) );

		// Assert.
		$this->assertEquals( $orders_2, $orders );

		// Sort.
		wcs_sort_objects( $orders, 'date_paid', 'descending' );
		uasort( $orders_2, array( $wcs_object_sorter, 'descending_compare' ) );

		// Assert.
		$this->assertEquals( $orders_2, $orders );
	}

	/**
	 * Test error condition.
	 * @requires PHP 7.0.0
	 */
	public function test_wcs_sort_objects_throw() {
		$this->expectException( InvalidArgumentException::class );
		wcs_sort_objects( $orders, 'date_paid', 'FAKE_ORDER' );
	}

	public function test_wcs_trial_has_passed() {
		$order        = WC_Helper_Order::create_order();
		$subscription = WCS_Helper_Subscription::create_subscription();

		$subscription->update_dates(
			array(
				'start'     => gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) ),
				'trial_end' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 weeks' ) ),
			)
		);

		// Validate WP_Error.
		$this->assertWPError( wcs_trial_has_passed( 'INVALID_SUBSCRIPTION' ) );
		$this->assertWPError( wcs_trial_has_passed( $order ) );

		// Trial ends in one week.
		$this->assertFalse( wcs_trial_has_passed( $subscription ) );

		// Trial finished yesterday.
		$subscription->update_dates( array( 'trial_end' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) ) );
		$this->assertTrue( wcs_trial_has_passed( $subscription ) );

		// Subscription with no trial.
		$subscription->update_dates( array( 'trial_end' => 0 ) );
		$this->assertFalse( wcs_trial_has_passed( $subscription ) );
	}

	public function test_wcs_compare_order_billing_shipping_address() {
		// Create an order with the same billing and shipping address.
		$order = WC_Helper_Order::create_order();
		$order->set_shipping_first_name( $order->get_billing_first_name() );
		$order->set_shipping_last_name( $order->get_billing_last_name() );
		$order->set_shipping_company( $order->get_billing_company() );
		$order->set_shipping_address_1( $order->get_billing_address_1() );
		$order->set_shipping_address_2( $order->get_billing_address_2() );
		$order->set_shipping_city( $order->get_billing_city() );
		$order->set_shipping_state( $order->get_billing_state() );
		$order->set_shipping_postcode( $order->get_billing_postcode() );
		$order->set_shipping_country( $order->get_billing_country() );

		$this->assertTrue( wcs_compare_order_billing_shipping_address( $order ) );

		// Adjust the shipping address to create a mismatch.
		$order->set_shipping_address_1( $order->get_billing_address_1() . ' different' );

		$this->assertFalse( wcs_compare_order_billing_shipping_address( $order ) );
	}
}
