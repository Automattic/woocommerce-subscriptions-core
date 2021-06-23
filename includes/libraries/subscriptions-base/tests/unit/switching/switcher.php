<?php

/**
 * Test suite for the WC_Subscription_Switcher class
 */
class WC_Switching_Test extends WCS_Unit_Test_Case {


	public function setUp() {

	}

	/**
	 * Basic tests for Switcher::recurring_end_date
	 *
	 * @dataProvider switching_end_date_lengths_setUp
	 * @since 2.0
	 */
	public function test_recurring_end_date( $key, $length, $end_date, $first_payment, $prorated, $end_time, $expected ) {
		$subscription  = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );

		$product = WCS_Helper_Product::create_simple_subscription_product( array( 'subscription_length' => $length ) );
		WC()->cart->cart_contents[ $key ] = array(
			'data'                => $product,
			'subscription_switch' => array(
				'subscription_id'            => $subscription->get_id(),
				'first_payment_timestamp'    => strtotime( $first_payment ),
				'end_timestamp'              => strtotime( $end_time ),
			)
		);

		if ( $prorated ) {
			WC()->cart->cart_contents[ $key ]['subscription_switch']['recurring_payment_prorated'] = true;
		}

		$end_date = WC_Subscriptions_Switcher::recurring_cart_end_date( $end_date, WC()->cart, $product );
		$this->assertEquals( $expected, $end_date );
	}

	/**
	 * Creates a subscription product, fills in cart details and acts a data provider to assist in
	 * testing WC_Subscriptions_Switcher::recurring_cart_end_date.
	 *
	 * @return array
	 * @since 2.0
	 */
	public static function switching_end_date_lengths_setUp() {

		// array ( $cart_item_key, $product_length, end_date/stub, $first_payment, $prorated, $end_tim, $expected_end_date)
		return array(
			// subscription length of 1 with prorated payment - should use the first prorated payment timestamp
			array( '1', 1, 'stub', '2015-01-01 08:10:55', true, 0, '2015-01-01 08:10:55' ),
			// subscription length of 1 - should use the end_timestamp set on the cart contents
			array( '2', 1, 'stub', '2015-01-01 08:10:55', false, '2015-02-01 08:10:55', '2015-02-01 08:10:55' ),
			// subscription length of 2 - should calculate end as 2 payment after end date
			array( '3', 2, 'stub', '2015-01-01 08:10:55', false, 0, '2015-03-01 08:10:55' ),

			array( '4', 2, 'stub', '2015-02-01 08:10:55', false, '2015-01-01 08:10:55', '2015-04-01 08:10:55' ),
			array( '5', 5, 'stub', '2015-07-24 08:10:55', false, '', '2015-12-24 08:10:55' ),
			array( '6', 0, 0, '2015-07-24 08:10:55', false, '', 0 ),
		);
	}

	/**
	 * @group issue_2637
	 *
	 * @author Jeremy Pry
	 */
	public static function test_subscription_switch_ids() {
		$subsciption = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );
		$product     = WCS_Helper_Product::create_simple_subscription_product();

		WC()->cart->cart_contents[1] = array(
			'data'                => $product,
			'subscription_switch' => array(
				'subscription_id' => $subsciption->get_id(),
			),
		);
	}
}
