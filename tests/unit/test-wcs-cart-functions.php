<?php
/**
 *
 */
class WCS_Cart_Functions_Test extends WP_UnitTestCase {

	/**
	* Data provider for mock cart object
	*/
	public function provider_mock_cart() {
		return array(
			array( $this->getMockBuilder( 'WC_Cart' )->setMethods( array( 'get_cart', 'get_item_data' ) )->disableOriginalConstructor()->getMock() ),
		);
	}

	/**
	* Data provider for mock cart object
	*/
	public function provider_mock_cart_and_product() {

		$product_type_map = array(
			array( 'subscription', true ),
			array( 'subscription_variation', true ),
			array( 'variable-subscription', true ),
			array( array( 'subscription', 'subscription_variation', 'variable-subscription' ), true ),
		);

		$mock_subscription_product = $this->getMockBuilder( 'WC_Product_Subscription' )->setMethods( array( 'is_type' ) )->disableOriginalConstructor()->getMock();
		$mock_subscription_product->expects( $this->any() )->method( 'is_type' )->will( $this->returnValueMap( $product_type_map ) );

		return array(
			array( $this->getMockBuilder( 'WC_Cart' )->disableOriginalConstructor()->getMock(), $mock_subscription_product ),
		);
	}

	/**
	* Data provider for test_wcs_cart_totals_shipping_method()
	*
	*/
	public function provider_test_wcs_cart_totals_shipping_method() {
		$product = WCS_Helper_Product::create_simple_subscription_product( array( 'price' => 10 ) ); // The product needs a standard price, otherwise WC won't make it purchasable.
		$cart    = clone WC()->cart;

		// We need to add a product to the cart because WCS when generating the price string for a shipping rate will append the subscription interval and period.
		$cart->add_to_cart( $product->get_id() );

		return array(
			// Free shipping methods.
			array( $cart, new WC_Shipping_Flat_Rate() ),
			array( $cart, new WC_Shipping_Free_Shipping() ),
			array( $cart, new WC_Shipping_Local_Pickup() ),
			// Shipping Methods with a cost.
			array( $cart, new WC_Shipping_Flat_Rate(), 5, true ),
			array( $cart, new WC_Shipping_Local_Pickup(), 5, true ),
		);
	}

	/**
	* Testing wcs_cart_totals_shipping_method()
	* @param object $cart WC_Cart object
	* @param object $shipping_method WC_Shipping object
	* @param string $expected expected return from wcs_cart_totals_shipping_method()
	*
	* @dataProvider provider_test_wcs_cart_totals_shipping_method
	*/
	public function test_wcs_cart_totals_shipping_method( $cart, $shipping_method, $cost = 0, $price_expected = false ) {
		// Create a WC_Shipping_Rate from the shopping method
		$shipping_rate         = new WC_Shipping_Rate( $shipping_method->id, $shipping_method->method_title, $cost, array(), $shipping_method->id );
		$shipping_method_label = wcs_cart_totals_shipping_method( $shipping_rate, $cart );

		if ( $price_expected ) {
			$this->assertStringContainsString( (string) $cost, $shipping_method_label );
			$this->assertStringContainsString( $shipping_method->method_title, $shipping_method_label );
		} else {
			$this->assertEquals( $shipping_method->method_title, $shipping_method_label );
		}
	}

	/**
	* Data provider for test_wcs_cart_price_string
	*
	*/
	public function provider_test_wcs_cart_price_string() {

		$cart = $this->getMockBuilder( 'WC_Cart' )->setMethods( array( 'get_cart', 'get_item_data' ) )->disableOriginalConstructor()->getMock();
		return array(
			array( $cart, 'month', 1, 11, 10, '10.00', '/ month for 11 months' ),
			array( $cart, 'month', 1, 12, 10, '10.00', '/ month for 12 months' ),
			array( $cart, 'month', 1, 1, 10, '10.00', 'for 1 month' ),
			array( $cart, 'month', 12, 24, 10, '10.00', 'every 12 months for 24 months' ),
			array( $cart, 'day', 1, 11, 10, '10.00', '/ day for 11 days' ),
			array( $cart, 'day', 30, 60, 10, '10.00', 'every 30 days for 60 days' ),
			array( $cart, 'day', 10, 15, 10, '10.00', 'every 10 days for 15 days' ),
			array( $cart, 'year', 1, 3, 10, '10.00', '/ year for 3 years' ),
			array( $cart, 'year', 2, 4, 10, '10.00', 'every 2 years for 4 years' ),
			array( $cart, 'month', 1, 10, 10.99, '10.99', '/ month for 10 months' ),
			array( $cart, 'month', 2, 10, 100.01, '100.01', 'every 2 months for 10 months' ),
			array( $cart, 'day', 3, 9, 0, '0.00', 'every 3 days for 9 days' ),
		);
	}

	/**
	* Test wcs_cart_price_string()
	* @param object $cart WC_Cart object
	* @param string $period subscription period
	* @param int $interval subscription interval
	* @param int $length subscription length
	* @param int $amount price amount
	* @param string $exp_amount expected amount string
	* @param string $exp_string expected timing string
	*
	* @dataProvider provider_test_wcs_cart_price_string
	*/
	public function test_wcs_cart_price_string( $cart, $period, $interval, $length, $amount, $exp_amount, $exp_string ) {

		$cart->subscription_period          = $period;
		$cart->subscription_period_interval = $interval;
		$cart->subscription_length          = $length;

		$this->assertStringContainsString( $exp_amount, wcs_cart_price_string( $amount, $cart ) );
		$this->assertStringContainsString( $exp_string, wcs_cart_price_string( $amount, $cart ) );
	}

	/**
	* Test wcs_cart_pluck()
	* For data that exists on cart object
	*
	* @param object $cart WC_Cart object
	* @dataProvider provider_mock_cart
	*/
	public function test_wcs_cart_pluck_cart_field( $cart ) {

		// $cart->field is set
		$cart->subscription_period_interval = 2;
		$cart->subscription_period          = 'year';

		$this->assertEquals( 2, wcs_cart_pluck( $cart, 'subscription_period_interval' ) );
		$this->assertEquals( 'year', wcs_cart_pluck( $cart, 'subscription_period' ) );
	}

	/**
	* Test wcs_cart_pluck()
	* For data that is in $cart_item[ $field ]
	*
	* @param object $cart WC_Cart object
	* @dataProvider provider_mock_cart
	*/
	public function test_wcs_cart_pluck_item_field( $cart ) {

		$cart_item = array(
			'subscription_period_interval' => 1,
			'subscription_period'          => 'month',
		);

		$cart_items = array( $cart_item );

		// Stub get_cart() method
		$cart->expects( $this->any() )->method( 'get_cart' )->will( $this->returnValue( $cart_items ) );

		// $cart_item[ $field ] is set
		$this->assertEquals( 1, wcs_cart_pluck( $cart, 'subscription_period_interval' ) );
		$this->assertEquals( 'month', wcs_cart_pluck( $cart, 'subscription_period' ) );
	}

	/**
	* Test wcs_cart_pluck()
	* For data that is in $cart_item['data']->field
	*
	* @param object $cart WC_Cart object
	* @dataProvider provider_mock_cart_and_product
	*/
	public function test_wcs_cart_pluck_item_data_field( $cart, $mock_subscription_product ) {

		wcs_set_objects_property( $mock_subscription_product, 'subscription_period_interval', 3, 'set_prop_only' );
		wcs_set_objects_property( $mock_subscription_product, 'subscription_period', 'week', 'set_prop_only' );

		$cart_item = array( 'data' => $mock_subscription_product );

		$cart_items = array( $cart_item );

		// Stub get_cart() method
		$cart->expects( $this->any() )->method( 'get_cart' )->will( $this->returnValue( $cart_items ) );

		$this->assertEquals( 3, wcs_cart_pluck( $cart, 'subscription_period_interval' ) );
		$this->assertEquals( 'week', wcs_cart_pluck( $cart, 'subscription_period' ) );
	}

	/**
	* Test wcs_cart_pluck()
	* For use of third parameter and default when no other data is set
	*
	* @param object $cart WC_Cart object
	* @dataProvider provider_mock_cart_and_product
	*/
	public function test_wcs_cart_pluck_default( $cart, $mock_subscription_product ) {

		wcs_set_objects_property( $mock_subscription_product, 'subscription_period_interval', null, 'set_prop_only' );
		wcs_set_objects_property( $mock_subscription_product, 'subscription_period', null, 'set_prop_only' );

		$cart_item = array( 'data' => $mock_subscription_product );

		$cart_items = array( $cart_item );

		// Stub get_cart() method
		$cart->expects( $this->any() )->method( 'get_cart' )->will( $this->returnValue( $cart_items ) );

		// Use default
		$this->assertEquals( 5, wcs_cart_pluck( $cart, 'subscription_period_interval', 5 ) );
		$this->assertEquals( 'day', wcs_cart_pluck( $cart, 'subscription_period', 'day' ) );
		$this->assertEquals( null, wcs_cart_pluck( $cart, 'subscription_period_interval' ) );
		$this->assertEquals( null, wcs_cart_pluck( $cart, 'subscription_period' ) );
	}

	/**
	* Test wcs_add_cart_first_renewal_payment_date()
	*
	* @param object $cart WC_Cart object
	* @dataProvider provider_mock_cart
	*/
	public function test_wcs_add_cart_first_renewal_payment_date( $cart ) {

		// Set payment date, Default date format Y-M-D H:I:S
		$payment_date            = '2055-11-01 09:33:12';
		$cart->next_payment_date = $payment_date;

		$expected_date = date_i18n( wc_date_format(), strtotime( get_date_from_gmt( $payment_date ) ) );
		// translators: ignore
		$expected_date = sprintf( __( 'First renewal: %s', 'woocommerce-subscriptions' ), $expected_date );

		// Set order total html
		$order_total_html = '<span class="class">Some HTML</span>';

		// Expected html
		$expected_html = $order_total_html . '<div class="first-payment-date"><small>' . $expected_date . '</small></div>';

		$this->assertEquals( $expected_html, wcs_add_cart_first_renewal_payment_date( $order_total_html, $cart ) );

		// No next payment date
		$cart->next_payment_date = 0;

		$this->assertEquals( $order_total_html, wcs_add_cart_first_renewal_payment_date( $order_total_html, $cart ) );
	}

	/**
	* Test wcs_get_cart_item_name()
	*
	* @param object $cart WC_Cart object
	* @dataProvider provider_mock_cart
	*/
	public function test_wcs_get_cart_item_name( $cart ) {

		// Create mock product
		$product = $this->getMockBuilder( 'WC_Product' )->disableOriginalConstructor()->getMock();

		// Create cart item
		$cart_item = array( 'data' => $product );

		// Default
		$this->assertEquals( '', wcs_get_cart_item_name( $cart_item ) );

		// Specify title
		$title = 'Product Title';

		// Stub get_title()
		$product->expects( $this->any() )->method( 'get_title' )->will( $this->returnValue( $title ) );

		$this->assertEquals( $title, wcs_get_cart_item_name( $cart_item ) );

		// Use include parameter
		$include = array( 'attributes' => true );

		$item_data = 'blue';

		// Stub get_item_data()
		$wc_cart   = WC()->cart;
		WC()->cart = $cart;
		WC()->cart->expects( $this->any() )->method( 'get_item_data' )->will( $this->returnValue( $item_data ) );

		$name = $title . ' (' . $item_data . ')';

		$this->assertEquals( $name, wcs_get_cart_item_name( $cart_item, $include ) );

		// Reset cart
		WC()->cart = $wc_cart;
	}

	/**
	 * Tests WC_Subscriptions_Cart::get_recurring_cart_key()
	 */
	public function test_get_recurring_cart_key() {
		$cart_item = array( 'data' => WCS_Helper_Product::create_simple_subscription_product() );

		// The default monthly product should have a key the matches the format: YYYY_MM_DD_monthly. Later tests will test specific payment dates.
		$this->assertMatchesRegularExpression( '/^\d{4}_[0-9]{2}_[0-9]{2}_monthly$/', WC_Subscriptions_Cart::get_recurring_cart_key( $cart_item ) );

		// Period and interval.
		$next_payment_time = '2027-11-24 09:33:12';
		$cart_item['data']->update_meta_data( '_subscription_period_interval', 3 );
		$cart_item['data']->update_meta_data( '_subscription_period', 'year' );

		$this->assertSame( '2027_11_24_every_3rd_year', WC_Subscriptions_Cart::get_recurring_cart_key( $cart_item, wcs_date_to_time( $next_payment_time ) ) );

		// Synced product.
		update_option( WC_Subscriptions_Synchroniser::$setting_id, 'yes' ); // Enable syncing.
		$next_payment_time = '2024-05-14 03:00:00';
		$cart_item['data']->update_meta_data( '_subscription_period_interval', 2 );
		$cart_item['data']->update_meta_data( '_subscription_period', 'weekly' );
		$cart_item['data']->update_meta_data( '_subscription_payment_sync_date', '4' );

		$this->assertSame( '2024_05_14_every_2nd_weekly_synced', WC_Subscriptions_Cart::get_recurring_cart_key( $cart_item, wcs_date_to_time( $next_payment_time ) ) );

		// Trial with end date.
		$next_payment_time = '2024-05-14 14:29:42';
		$cart_item['data']->update_meta_data( '_subscription_trial_length', 2 );
		$cart_item['data']->update_meta_data( '_subscription_trial_period', 'day' );
		$cart_item['data']->update_meta_data( '_subscription_period', 'month' );
		$cart_item['data']->update_meta_data( '_subscription_period_interval', 1 );

		$this->assertSame( '2024_05_14_monthly_after_a_2_day_trial_synced', WC_Subscriptions_Cart::get_recurring_cart_key( $cart_item, wcs_date_to_time( $next_payment_time ) ) );

		// End date.
		$next_payment_time = '2028-03-12 14:29:42';
		$cart_item['data']->update_meta_data( '_subscription_length', 12 );
		$cart_item['data']->delete_meta_data( '_subscription_trial_length' );
		$cart_item['data']->delete_meta_data( '_subscription_trial_period' );
		$cart_item['data']->delete_meta_data( '_subscription_payment_sync_date' );

		$this->assertSame( '2028_03_12_monthly_for_12_months', WC_Subscriptions_Cart::get_recurring_cart_key( $cart_item, wcs_date_to_time( $next_payment_time ) ) );

		// Filter.
		add_action(
			'woocommerce_subscriptions_recurring_cart_key',
			function( $key, $filter_cart_item ) use ( $cart_item ) {
				$this->assertSame( $filter_cart_item, $cart_item );
				return $key . '_filtered';
			},
			10,
			2
		);

		$this->assertSame( '2028_03_12_monthly_for_12_months_filtered', WC_Subscriptions_Cart::get_recurring_cart_key( $cart_item, wcs_date_to_time( $next_payment_time ) ) );
	}

}

