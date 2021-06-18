<?php

/**
 * @group    coupons
 * @requires PHP 5.3
 */
class WCS_Coupons_Test extends WCS_Unit_Test_Case {

	/**
	 * Data provider function for recurring coupon types.
	 *
	 * @return array
	 */
	public function recurring_coupon_types() {
		return array(
			array( 'sign_up_fee' ),
			array( 'sign_up_fee_percent' ),
			array( 'recurring_fee' ),
			array( 'recurring_percent' ),
		);
	}

	/**
	 * Data provider function for pseudo coupon types.
	 *
	 * @return array
	 */
	public function pseudo_coupon_types() {
		return array(
			array( 'renewal_percent' ),
			array( 'renewal_fee' ),
			array( 'renewal_cart' ),
		);
	}

	/**
	 * Test our custom recurring coupon types.
	 *
	 * @dataProvider recurring_coupon_types
	 *
	 * @param string $type
	 */
	public function test_recurring_coupons( $type ) {
		/** @var WC_Coupon $coupon */
		$coupon = call_user_func( array( 'WCS_Helper_Coupon', "create_{$type}_coupon" ), $type );
		$this->assertInstanceOf( 'WC_Coupon', $coupon );
		$this->assertEquals( $type, wcs_get_coupon_property( $coupon, 'discount_type' ) );
		WCS_Helper_Coupon::delete_coupon( wcs_get_coupon_property( $coupon, 'id' ) );
	}

	/**
	 * Test our pseudo coupon types.
	 *
	 * @dataProvider pseudo_coupon_types
	 *
	 * @param string $type
	 */
	public function test_pseudo_coupons( $type ) {
		/** @var WC_Coupon $coupon */
		$coupon = call_user_func( array( 'WCS_Helper_Coupon', "create_{$type}_coupon" ), $type );
		$this->assertInstanceOf( 'WC_Coupon', $coupon );
		$this->assertEquals( $type, wcs_get_coupon_property( $coupon, 'discount_type' ) );
		WCS_Helper_Coupon::delete_coupon( wcs_get_coupon_property( $coupon, 'id' ) );
	}

	/**
	 * Test that we can create limited recurring coupons.
	 *
	 * @dataProvider recurring_coupon_types
	 *
	 * @param string $type
	 */
	public function test_creating_limited_payment_coupon( $type ) {
		$is_pre_30         = wcs_is_woocommerce_pre( '3.2' );
		$is_sign_up_coupon = false !== strpos( $type, 'sign_up' );

		/** @var WC_Coupon $coupon */
		$coupon = call_user_func(
			array( 'WCS_Helper_Coupon', "create_{$type}_coupon" ),
			null,
			null,
			array( '_wcs_number_payments' => 3 )
		);
		$code   = wcs_get_coupon_property( $coupon, 'code' );
		$this->assertInstanceOf( 'WC_Coupon', $coupon );
		$this->assertEquals( $type, wcs_get_coupon_property( $coupon, 'discount_type' ) );

		// Sign up fee coupons cannot be limited.
		$assertion = $is_sign_up_coupon || $is_pre_30 ? 'False' : 'True';
		call_user_func(
			array( $this, "assert{$assertion}" ),
			WC_Subscriptions_Coupon::coupon_is_limited( $code )
		);

		// Sign up fee coupons return false instead of the limit.
		$expected = $is_sign_up_coupon || $is_pre_30 ? false : 3;
		$this->assertEquals( $expected, WC_Subscriptions_Coupon::get_coupon_limit( $code ) );
		WCS_Helper_Coupon::delete_coupon( wcs_get_coupon_property( $coupon, 'id' ) );
	}

	/**
	 * Test that a limited payment coupon is removed.
	 */
	public function test_limited_payment_coupon_removed() {
		// Skip for WC prior to 3.2.
		if ( wcs_is_woocommerce_pre( '3.2' ) ) {
			$this->markTestSkipped( 'WooCommerce 3.2+ is required for limited payment coupons' );
		}

		// Create coupons.
		$coupon  = WCS_Helper_Coupon::create_recurring_fee_coupon( __FUNCTION__, '5', array( '_wcs_number_payments' => 1 ) );
		$product = WCS_Helper_Product::create_simple_subscription_product( array( 'price' => 10 ) );

		// Create the parent order.
		$start_date   = '2013-12-12 08:08:08';
		$parent_order = WCS_Helper_Subscription::create_order();
		$parent_order->set_date_created( wcs_date_to_time( $start_date ) );
		$parent_order->apply_coupon( $coupon );
		$parent_order->save();

		// Create initial subscription.
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'   => 'pending',
			'order_id' => $parent_order->get_id(),
		) );
		WCS_Helper_Subscription::add_product( $subscription, $product );
		$this->assertEquals( '10.00', $subscription->get_total() );

		// Apply the coupon.
		$subscription->apply_coupon( $coupon );
		$subscription->calculate_totals();
		$subscription->save();
		$this->assertTrue( WC_Subscriptions_Coupon::order_has_limited_recurring_coupon( $subscription ) );
		$this->assertEquals( '5.00', $subscription->get_total() );

		// Create renewal order.
		$renewal_order = wcs_create_renewal_order( $subscription );

		// Ensure the coupon has been copied over to the renewal.
		$this->assertTrue( WC_Subscriptions_Coupon::order_has_limited_recurring_coupon( $renewal_order ) );

		// After processing the renewal payment, make sure the coupon has been removed.
		$renewal_order->update_status( 'processing' );

		// Load a new instance of the subscription to get updated coupon line items.
		$subscription = wcs_get_subscription( $subscription->get_id() );
		$this->assertEmpty( wcs_get_used_coupon_codes( $subscription ) );
	}
}
