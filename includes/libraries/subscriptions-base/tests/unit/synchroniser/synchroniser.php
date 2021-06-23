<?php
/**
 * Test suite for the WC_Subscription_Synchroniser class
 */
class WC_Synchroniser_Test extends WCS_Unit_Test_Case {

	/**
	 * Basic tests for WC_Subscriptions_Synchroniser::is_payment_upfront() and WC_Subscriptions_Synchroniser::calculate_first_payment_date();
	 *
	 * @see WC_Subscriptions_Synchroniser::is_payment_upfront();
	 * @see WC_Subscriptions_Synchroniser::calculate_first_payment_date()
	 *
	 * @dataProvider synchronised_products_setUp
	 * @since 2.6
	 */
	public function test_first_payment( $from_date, $billing_period, $billing_interval, $sync_date, $proration_setting, $grace_days, $expected_first_payment_date, $expected_is_payment_upfront ) {

		$product = WCS_Helper_Product::create_simple_subscription_product( array(
				'price'                          => 50,
				'regular_price'                  => 50,
				'subscription_price'             => 50,
				'subscription_period'            => $billing_period,
				'subscription_period_interval'   => $billing_interval,
				'subscription_payment_sync_date' => $sync_date,
			)
		);

		update_option( WC_Subscriptions_Synchroniser::$setting_id, 'yes' );
		update_option( WC_Subscriptions_Synchroniser::$setting_id_proration, $proration_setting );
		update_option( WC_Subscriptions_Synchroniser::$setting_id_days_no_fee, $grace_days );

		$is_payment_upfront = WC_Subscriptions_Synchroniser::is_payment_upfront( $product, $from_date );
		$this->assertEquals( $expected_is_payment_upfront, $is_payment_upfront );

		$first_payment_timestamp = WC_Subscriptions_Synchroniser::calculate_first_payment_date( $product, 'timestamp', $from_date );
		$this->assertEquals( strtotime( $expected_first_payment_date ), $first_payment_timestamp );

		$first_payment_date = WC_Subscriptions_Synchroniser::calculate_first_payment_date( $product, 'mysql', $from_date );
		$this->assertEquals( $expected_first_payment_date, $first_payment_date );

		// Clean up, especially for WP 4.6
		delete_option( WC_Subscriptions_Synchroniser::$setting_id );
		delete_option( WC_Subscriptions_Synchroniser::$setting_id_proration );
		delete_option( WC_Subscriptions_Synchroniser::$setting_id_days_no_fee );
	}

	/**
	 * Creates a subscription product, fills in cart details and acts a data provider to assist in
	 * testing WC_Subscriptions_Switcher::recurring_cart_end_date.
	 *
	 * @return array
	 * @since 2.6
	 */
	public static function synchronised_products_setUp() {

			// array ( $from_date, $product_period, $product_interval, $sync_date, $proration_setting, $grace_days, $expected_first_payment_date, $is_payment_upfront )
		return array(

			// For month as period
			//   Simple case - interval - 1
			array( '2019-08-22 08:10:55', 'month', 1, 20, 'recurring', 5, '2019-09-20 03:00:00', true ), //101
			array( '2019-08-22 08:10:55', 'month', 1, 22, 'recurring', 5, '2019-08-22 03:00:00', true ), //102
			array( '2019-08-22 08:10:55', 'month', 1, 24, 'recurring', 5, '2019-08-24 03:00:00', false ), //103
			array( '2019-08-22 08:10:55', 'month', 1, 20, 'no', 0, '2019-09-20 03:00:00', false ), //104
			array( '2019-08-22 08:10:55', 'month', 1, 22, 'no', 0, '2019-08-22 03:00:00', true ), //105
			array( '2019-08-22 08:10:55', 'month', 1, 24, 'no', 0, '2019-08-24 03:00:00', false ), //106
			array( '2019-08-22 08:10:55', 'month', 1, 20, 'yes', 0, '2019-09-20 03:00:00', false ), //107
			array( '2019-08-22 08:10:55', 'month', 1, 22, 'yes', 0, '2019-08-22 03:00:00', true ), //108
			array( '2019-08-22 08:10:55', 'month', 1, 24, 'yes', 0, '2019-08-24 03:00:00', false ), //109

			//   Interval more than 1
			array( '2019-08-22 08:10:55', 'month', 6, 20, 'recurring', 5, '2020-02-20 03:00:00', true ), //111
			array( '2019-08-22 08:10:55', 'month', 6, 22, 'recurring', 5, '2019-08-22 03:00:00', true ), //112
			array( '2019-08-22 08:10:55', 'month', 6, 25, 'recurring', 5, '2019-08-25 03:00:00', false ), //113
			array( '2019-08-22 08:10:55', 'month', 6, 20, 'no', 0, '2019-09-20 03:00:00', false ), //114
			array( '2019-08-22 08:10:55', 'month', 6, 22, 'no', 0, '2019-08-22 03:00:00', true ), //115
			array( '2019-08-22 08:10:55', 'month', 6, 25, 'no', 0, '2019-08-25 03:00:00', false ), //116
			array( '2019-08-22 08:10:55', 'month', 6, 20, 'yes', 0, '2019-09-20 03:00:00', false ), //117
			array( '2019-08-22 08:10:55', 'month', 6, 22, 'yes', 0, '2019-08-22 03:00:00', true ), //118
			array( '2019-08-22 08:10:55', 'month', 6, 25, 'yes', 0, '2019-08-25 03:00:00', false ), //119

			// For week as period
			//   Simple case - interval - 1
			array( '2019-08-22 08:10:55', 'week', 1, 2, 'recurring', 5, '2019-08-27 03:00:00', true ), //121
			array( '2019-08-22 08:10:55', 'week', 1, 4, 'recurring', 5, '2019-08-22 03:00:00', true ), //122
			array( '2019-08-22 08:10:55', 'week', 1, 6, 'recurring', 5, '2019-08-24 03:00:00', false ), //123
			array( '2019-08-22 08:10:55', 'week', 1, 3, 'no', 0, '2019-08-28 03:00:00', false ), //124
			array( '2019-08-22 08:10:55', 'week', 1, 4, 'no', 0, '2019-08-22 03:00:00', true ), //125
			array( '2019-08-22 08:10:55', 'week', 1, 5, 'no', 0, '2019-08-23 03:00:00', false ), //126
			array( '2019-08-22 08:10:55', 'week', 1, 3, 'yes', 0, '2019-08-28 03:00:00', false ), //127
			array( '2019-08-22 08:10:55', 'week', 1, 4, 'yes', 0, '2019-08-22 03:00:00', true ), //128
			array( '2019-08-22 08:10:55', 'week', 1, 5, 'yes', 0, '2019-08-23 03:00:00', false ), //129

			//   Interval more than 1
			array( '2019-08-22 08:10:55', 'week', 3, 2, 'recurring', 5, '2019-09-10 03:00:00', true ), //131
			array( '2019-08-22 08:10:55', 'week', 3, 4, 'recurring', 5, '2019-08-22 03:00:00', true ), //132
			array( '2019-08-22 08:10:55', 'week', 3, 6, 'recurring', 5, '2019-08-24 03:00:00', false ), //133
			array( '2019-08-22 08:10:55', 'week', 3, 3, 'no', 0, '2019-08-28 03:00:00', false ), //134
			array( '2019-08-22 08:10:55', 'week', 3, 4, 'no', 0, '2019-08-22 03:00:00', true ), //135
			array( '2019-08-22 08:10:55', 'week', 3, 5, 'no', 0, '2019-08-23 03:00:00', false ), //136
			array( '2019-08-22 08:10:55', 'week', 3, 3, 'yes', 0, '2019-08-28 03:00:00', false ), //137
			array( '2019-08-22 08:10:55', 'week', 3, 4, 'yes', 0, '2019-08-22 03:00:00', true ), //138
			array( '2019-08-22 08:10:55', 'week', 3, 5, 'yes', 0, '2019-08-23 03:00:00', false ), //139

			// For year as period
			//   Simple case - interval - 1
			array( '2019-08-22 08:10:55', 'year', 1, array( 'month' => '08', 'day' => '20' ), 'recurring', 5, '2020-08-20 03:00:00', true ), //141
			array( '2019-08-22 08:10:55', 'year', 1, array( 'month' => '08', 'day' => '22' ), 'recurring', 5, '2019-08-22 03:00:00', true ), //142
			array( '2019-08-22 08:10:55', 'year', 1, array( 'month' => '08', 'day' => '25' ), 'recurring', 5, '2019-08-25 03:00:00', false ), //143
			array( '2019-08-22 08:10:55', 'year', 1, array( 'month' => '08', 'day' => '20' ), 'no', 0, '2020-08-20 03:00:00', false ), //144
			array( '2019-08-22 08:10:55', 'year', 1, array( 'month' => '08', 'day' => '22' ), 'no', 0, '2019-08-22 03:00:00', true ), //145
			array( '2019-08-22 08:10:55', 'year', 1, array( 'month' => '08', 'day' => '25' ), 'no', 0, '2019-08-25 03:00:00', false ), //146
			array( '2019-08-22 08:10:55', 'year', 1, array( 'month' => '08', 'day' => '20' ), 'yes', 0, '2020-08-20 03:00:00', false ), //147
			array( '2019-08-22 08:10:55', 'year', 1, array( 'month' => '08', 'day' => '22' ), 'yes', 0, '2019-08-22 03:00:00', true ), //148
			array( '2019-08-22 08:10:55', 'year', 1, array( 'month' => '08', 'day' => '25' ), 'yes', 0, '2019-08-25 03:00:00', false ), //149

			//   Interval more than 1
			array( '2019-08-22 08:10:55', 'year', 4, array( 'month' => '08', 'day' => '20' ), 'recurring', 5, '2023-08-20 03:00:00', true ), //151
			array( '2019-08-22 08:10:55', 'year', 4, array( 'month' => '08', 'day' => '22' ), 'recurring', 5, '2019-08-22 03:00:00', true ), //152
			array( '2019-08-22 08:10:55', 'year', 4, array( 'month' => '08', 'day' => '25' ), 'recurring', 5, '2019-08-25 03:00:00', false ), //153
			array( '2019-08-22 08:10:55', 'year', 4, array( 'month' => '08', 'day' => '20' ), 'no', 0, '2020-08-20 03:00:00', false ), //154
			array( '2019-08-22 08:10:55', 'year', 4, array( 'month' => '08', 'day' => '22' ), 'no', 0, '2019-08-22 03:00:00', true ), //155
			array( '2019-08-22 08:10:55', 'year', 4, array( 'month' => '08', 'day' => '25' ), 'no', 0, '2019-08-25 03:00:00', false ), //156
			array( '2019-08-22 08:10:55', 'year', 4, array( 'month' => '08', 'day' => '20' ), 'yes', 0, '2020-08-20 03:00:00', false ), //157
			array( '2019-08-22 08:10:55', 'year', 4, array( 'month' => '08', 'day' => '22' ), 'yes', 0, '2019-08-22 03:00:00', true ), //158
			array( '2019-08-22 08:10:55', 'year', 4, array( 'month' => '08', 'day' => '25' ), 'yes', 0, '2019-08-25 03:00:00', false ), //159

			//   Last day of month cases
			array( '2019-08-30 08:10:55', 'month', 6, 27, 'recurring', 5, '2020-02-27 03:00:00', true ), //161
			array( '2019-08-30 08:10:55', 'month', 6, 28, 'recurring', 5, '2019-08-31 03:00:00', false ), //162
			// array( '2019-08-30 08:10:55', 'month', 6, 30, 'recurring', 5, '2019-08-30 03:00:00', true ), //162 UI does not allow
			// array( '2019-08-30 08:10:55', 'month', 6, 31, 'recurring', 5, '2019-08-31 03:00:00', false ), //163 UI does not allow
			array( '2019-08-30 08:10:55', 'month', 6, 27, 'no', 0, '2019-09-27 03:00:00', false ), //164
			array( '2019-08-30 08:10:55', 'month', 6, 28, 'no', 5, '2019-08-31 03:00:00', false ), //165
			// array( '2019-08-30 08:10:55', 'month', 6, 30, 'no', 0, '2019-08-30 03:00:00', true ), //165 UI does not allow
			// array( '2019-08-30 08:10:55', 'month', 6, 31, 'no', 0, '2019-08-31 03:00:00', false ), //166 UI does not allow
			array( '2019-08-30 08:10:55', 'month', 6, 27, 'yes', 0, '2019-09-27 03:00:00', false ), //167
			array( '2019-08-30 08:10:55', 'month', 6, 28, 'yes', 5, '2019-08-31 03:00:00', false ), //168
			// array( '2019-08-30 08:10:55', 'month', 6, 30, 'yes', 0, '2019-08-30 03:00:00', true ), //168 UI does not allow
			// array( '2019-08-30 08:10:55', 'month', 6, 31, 'yes', 0, '2019-08-31 03:00:00', false ), //169 UI does not allow

			array( '2019-02-22 08:10:55', 'week', 3, 4, 'recurring', 3, '2019-03-14 03:00:00', true ), //171
			array( '2019-02-22 08:10:55', 'week', 3, 5, 'recurring', 3, '2019-02-22 03:00:00', true ), //172
			array( '2019-02-22 08:10:55', 'week', 3, 6, 'recurring', 3, '2019-02-23 03:00:00', false ), //173
			array( '2019-02-22 08:10:55', 'week', 3, 4, 'no', 0, '2019-02-28 03:00:00', false ), //174
			array( '2019-02-22 08:10:55', 'week', 3, 5, 'no', 0, '2019-02-22 03:00:00', true ), //175
			array( '2019-02-22 08:10:55', 'week', 3, 6, 'no', 0, '2019-02-23 03:00:00', false ), //176
			array( '2019-02-22 08:10:55', 'week', 3, 4, 'yes', 0, '2019-02-28 03:00:00', false ), //177
			array( '2019-02-22 08:10:55', 'week', 3, 5, 'yes', 0, '2019-02-22 03:00:00', true ), //178
			array( '2019-02-22 08:10:55', 'week', 3, 6, 'yes', 0, '2019-02-23 03:00:00', false ), //179

			array( '2016-04-29 08:10:55', 'year', 2, array( 'month' => '04', 'day' => '27' ), 'recurring', 5, '2018-04-27 03:00:00', true ), //181
			array( '2016-04-29 08:10:55', 'year', 2, array( 'month' => '04', 'day' => '29' ), 'recurring', 5, '2016-04-29 03:00:00', true ), //182
			array( '2016-04-29 08:10:55', 'year', 2, array( 'month' => '04', 'day' => '30' ), 'recurring', 5, '2016-04-30 03:00:00', false ), //183
			array( '2016-04-29 08:10:55', 'year', 2, array( 'month' => '04', 'day' => '27' ), 'no', 0, '2017-04-27 03:00:00', false ), //184
			array( '2016-04-29 08:10:55', 'year', 2, array( 'month' => '04', 'day' => '29' ), 'no', 0, '2016-04-29 03:00:00', true ), //185
			array( '2016-04-29 08:10:55', 'year', 2, array( 'month' => '04', 'day' => '30' ), 'no', 0, '2016-04-30 03:00:00', false ), //186
			array( '2016-04-29 08:10:55', 'year', 2, array( 'month' => '04', 'day' => '27' ), 'yes', 0, '2017-04-27 03:00:00', false ), //187
			array( '2016-04-29 08:10:55', 'year', 2, array( 'month' => '04', 'day' => '29' ), 'yes', 0, '2016-04-29 03:00:00', true ), //188
			array( '2016-04-29 08:10:55', 'year', 2, array( 'month' => '04', 'day' => '30' ), 'yes', 0, '2016-04-30 03:00:00', false ), //189

			// February specific cases for last day of month (>27)
			array( '2016-02-29 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '27' ), 'recurring', 5, '2018-02-27 03:00:00', true ), //191
			array( '2016-02-29 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '28' ), 'recurring', 5, '2018-02-28 03:00:00', true ), //192
			//array( '2016-02-29 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '29' ), 'recurring', 5, '2016-02-29 03:00:00', true ), //193 UI does not allow
			array( '2016-02-29 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '27' ), 'no', 0, '2017-02-27 03:00:00', false ), //194
			array( '2016-02-29 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '28' ), 'no', 0, '2017-02-28 03:00:00', false ), //195
			//array( '2016-02-29 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '29' ), 'no', 0, '2016-02-29 03:00:00', true ), //196 UI does not allow
			array( '2016-02-29 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '27' ), 'yes', 0, '2017-02-27 03:00:00', false ), //197
			array( '2016-02-29 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '28' ), 'yes', 0, '2017-02-28 03:00:00', false ), //198
			//array( '2016-02-29 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '29' ), 'yes', 0, '2016-02-29 03:00:00', true ), //199 UI does not allow

			array( '2019-02-28 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '27' ), 'recurring', 5, '2021-02-27 03:00:00', true ), //201
			array( '2019-02-28 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '28' ), 'recurring', 5, '2019-02-28 03:00:00', true ), //202
			//array( '2019-02-28 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '29' ), 'recurring', 5, '2019-02-28 03:00:00', true ), //203 UI does not allow
			array( '2019-02-28 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '27' ), 'no', 0, '2020-02-27 03:00:00', false ), //204
			array( '2019-02-28 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '28' ), 'no', 0, '2019-02-28 03:00:00', true ), //205
			//array( '2019-02-28 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '29' ), 'no', 0, '2019-02-28 03:00:00', true ), //206 UI does not allow
			array( '2019-02-28 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '27' ), 'yes', 0, '2020-02-27 03:00:00', false ), //207
			array( '2019-02-28 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '28' ), 'yes', 0, '2019-02-28 03:00:00', true ), //208
			//array( '2019-02-28 08:10:55', 'year', 2, array( 'month' => '02', 'day' => '29' ), 'yes', 0, '2019-02-28 03:00:00', true ), //209 UI does not allow

			// Purchases outside the (+-) grace number of days.
			// Monthly.
			array( '2019-08-01 08:10:55', 'month', 1, 15, 'recurring', 5, '2019-08-15 03:00:00', true ), //210 Sync day -14 days.
			array( '2019-08-22 08:10:55', 'month', 1, 15, 'recurring', 5, '2019-09-15 03:00:00', true ), //211 Sync day +6 days.

			// Every 6 months.
			array( '2019-08-01 08:10:55', 'month', 6, 15, 'recurring', 5, '2020-01-15 03:00:00', true ), //212 Sync day -14 days.
			array( '2019-08-22 08:10:55', 'month', 6, 15, 'recurring', 5, '2020-02-15 03:00:00', true ), //213 Sync day +6 days.

			// Weekly (synced to Mon.)
			array( '2019-08-08 08:10:55', 'week', 1, 1, 'recurring', 3, '2019-08-12 03:00:00', true ), //214 Sync day -4 days.
			array( '2019-08-16 08:10:55', 'week', 1, 1, 'recurring', 3, '2019-08-19 03:00:00', true ), //215 Sync day +4 days.

			// Every 3 weeks.
			array( '2019-08-08 08:10:55', 'week', 3, 1, 'recurring', 3, '2019-08-26 03:00:00', true ), //216 Sync day -4 days.
			array( '2019-08-16 08:10:55', 'week', 3, 1, 'recurring', 3, '2019-09-02 03:00:00', true ), //217 Sync day +4 days.

			// Yearly.
			array( '2019-08-17 08:10:55', 'year', 1, array( 'month' => '09', 'day' => '01' ), 'recurring', 10, '2019-09-01 03:00:00', true ), //218 Sync day -15 days.
			array( '2019-09-16 08:10:55', 'year', 1, array( 'month' => '09', 'day' => '01' ), 'recurring', 10, '2020-09-01 03:00:00', true ), //219 Sync day +15 days

			// Every 4 years.
			array( '2019-08-17 08:10:55', 'year', 4, array( 'month' => '09', 'day' => '01' ), 'recurring', 10, '2022-09-01 03:00:00', true ), //220 Sync day -15 days.
			array( '2019-09-16 08:10:55', 'year', 4, array( 'month' => '09', 'day' => '01' ), 'recurring', 10, '2023-09-01 03:00:00', true ), //221 Sync day +15 days.
			// Every 4 months.
			array( '2019-11-28 08:10:55', 'month', 4, 1, 'recurring', 7, '2019-12-01 03:00:00', false ), //222 Sync day -3 days.

			// Grace period of 30 days and year end cases
			array( '2019-11-29 08:10:55', 'year', 2, array( 'month' => '01', 'day' => '01' ), 'recurring', 30, '2021-01-01 03:00:00', true ), //222
			array( '2019-12-24 08:10:55', 'year', 2, array( 'month' => '01', 'day' => '01' ), 'recurring', 30, '2020-01-01 03:00:00', false ), //223
			array( '2019-01-01 08:10:55', 'year', 2, array( 'month' => '01', 'day' => '01' ), 'recurring', 30, '2019-01-01 03:00:00', true ), //224
			array( '2019-08-29 08:10:55', 'year', 2, array( 'month' => '10', 'day' => '10' ), 'recurring', 30, '2020-10-10 03:00:00', true ), //226
			array( '2019-09-24 08:10:55', 'year', 2, array( 'month' => '10', 'day' => '10' ), 'recurring', 30, '2019-10-10 03:00:00', false ), //225
			array( '2019-10-10 08:10:55', 'year', 2, array( 'month' => '10', 'day' => '10' ), 'recurring', 30, '2019-10-10 03:00:00', true ), //227
		);
	}
}
