<?php
/**
 * Undocumented class
 */
class WC_Subscriptions_Data_Copier_Test extends WP_UnitTestCase {

	public $mock_subscription;

	public $mock_order;

	public $copier;

	public function set_up() {
		parent::set_up();

		$this->mock_subscription = $this->getMockBuilder( WC_Subscription::class )
									->disableOriginalConstructor()
									->getMock();

		$this->mock_order = $this->getMockBuilder( WC_Order::class )
									->disableOriginalConstructor()
									->getMock();

		$this->copier = new WC_Subscriptions_Data_Copier( $this->mock_order, $this->mock_subscription, 'subscription' );
	}

	/**
	 * Test WC_Subscription_Data_Copier::copy_data() sets the data correctly via object setters.
	 */
	public function test_copy_data() {
		$expected_customer_id = 1230;
		$order = WC_Helper_Order::create_order($expected_customer_id);

		$expected_order_meta = [
			'_custom_meta' => 'test meta value',
			'_custom_meta_array' => [ 'an' => 'array' ],
		];

		foreach ( $expected_order_meta as $meta_key => $meta_value ) {
			$order->add_meta_data( $meta_key, $meta_value );
		}
		$order->save();

		$subscription = $this->getMockBuilder( WC_Subscription::class )
		                     ->disableOriginalConstructor()
		                     ->getMock();

		$subscription
			->expects( $this->once() )
			->method( 'set_billing_first_name' )
			->with( $order->get_billing_first_name() );

		$subscription
			->expects( $this->once() )
			->method( 'set_billing_last_name' )
			->with( $order->get_billing_last_name() );

		$subscription
			->expects( $this->once() )
			->method( 'set_customer_id' )
			->with( $expected_customer_id );

		$subscription
			->expects( $this->once() )
			->method( 'set_prices_include_tax' )
			->with( $order->get_prices_include_tax() );

		$subscription
			->expects( $this->once() )
			->method( 'set_currency' )
			->with( $order->get_currency() );

		// Callback used to verify that update_meta is called for expected meta keys.
		$set_meta_keys        = [];
		$update_meta_callback = function ( $meta_key, $meta_value ) use ( $expected_order_meta, &$set_meta_keys ) {
			if ( key_exists( $meta_key, $expected_order_meta ) ) {
				$this->assertEquals( $expected_order_meta[ $meta_key ], $meta_value );
				$set_meta_keys[ $meta_key ] = true;
			}

			return true;
		};

		$subscription
			->expects( $this->atLeast( count( $expected_order_meta ) ) )
			->method( 'update_meta_data' )
			->will( $this->returnCallback( $update_meta_callback )
			);

		$copier = new WC_Subscriptions_Data_Copier( $order, $subscription, 'subscription' );
		$copier->copy_data();

		// Verify that all update_meta was called for the expected meta keys.
		$uncalled_set_meta_keys = array_keys( array_diff_key( $expected_order_meta, $set_meta_keys ) );
		$this->assertEmpty( $uncalled_set_meta_keys );

	}

	/**
	 * @expectedDeprecated wcs_subscription_meta
	 */
	public function test_deprecated_wcs_subscription_meta() {
		$expected_customer_id = 1230;
		$order                = WC_Helper_Order::create_order( $expected_customer_id );

		$order_meta = [
			'_3pd_custom_meta'     => 'doodacky',
			'_3pd_custom_meta_too' => 'doovalacky',
		];

		foreach ( $order_meta as $meta_key => $meta_value ) {
			$order->add_meta_data( $meta_key, $meta_value );
		}
		$order->save();

		$subscription = $this->getMockBuilder( WC_Subscription::class )
		                     ->disableOriginalConstructor()
		                     ->getMock();

		// Mock a third-party hooking onto the deprecated filter to remove their meta.
		add_filter(
			'wcs_subscription_meta',
			function ( $data ) {
				foreach ( $data as $index => $meta_data ) {
					if ( '_3pd_custom_meta' === $meta_data['meta_key'] ) {
						unset( $data[ $index ] );
					}
				}

				return $data;
			}
		);

		// Setup expectations for the setters to be called on the mock subscription (the "to" object).
		$subscription
			->expects( $this->once() )
			->method( 'set_billing_first_name' )
			->with( $order->get_billing_first_name() );

		$subscription
			->expects( $this->once() )
			->method( 'set_billing_last_name' )
			->with( $order->get_billing_last_name() );

		// Expect the update_meta_data to be called only for the non-deprecated meta key.
		$set_meta_keys                             = [];
		$expected_order_meta                       = [ '_3pd_custom_meta_too' => 'doovalacky' ];
		$expected_deprecated_order_meta_not_called = [ '_3pd_custom_meta' ];
		$update_meta_callback                      = function ( $meta_key, $meta_value ) use ( $expected_order_meta, $expected_deprecated_order_meta_not_called, &$set_meta_keys ) {
			$this->assertNotContains( $meta_key, $expected_deprecated_order_meta_not_called, "::update_meta() with the meta_key of $meta_key should not have been called." );
			if ( key_exists( $meta_key, $expected_order_meta ) ) {
				$this->assertEquals( $expected_order_meta[ $meta_key ], $meta_value );
				$set_meta_keys[ $meta_key ] = true;
			}

			return true;
		};

		$subscription
			->method( 'update_meta_data' )
			->will( $this->returnCallback( $update_meta_callback )
			);

		$copier = new WC_Subscriptions_Data_Copier( $order, $subscription, 'subscription' );
		$copier->copy_data();

		// Verify that all update_meta was called for the non-deprecated meta key.
		$uncalled_set_meta_keys = array_keys( array_diff_key( $expected_order_meta, $set_meta_keys ) );
		$this->assertEmpty( $uncalled_set_meta_keys );
	}

	/**
	 * Test WC_Subscription_Data_Copier::filter_excluded_meta_keys() filters out keys based on custom NOT IN and NOT LIKE clauses
	 * in the filtered SQL query.
	 *
	 * @expectedDeprecated wcs_subscription_meta_query
	 */
	public function test_filter_excluded_meta_keys_via_query_with_callback() {
		// Add filter to exclude custom meta keys.
		add_filter(
			'wcs_subscription_meta_query',
			function( $query ) {
				$query .= " AND meta_key NOT IN ('_key1', '_key2')'";
				$query .= " AND meta_key NOT IN ('_key3')'";
				$query .= " AND meta_key NOT LIKE '_%_custom_%'";
				$query .= " AND meta_key NOT LIKE '_%_name'";
				$query .= ' AND meta_key NOT LIKE "_billing_%"';
				return $query;
			}
		);

		$excluded_data = array(
			// Excluded via query.
			'_key1'                => 'value1', // Excluded via NOT IN.
			'_key2'                => 'value2', // Excluded via NOT IN.
			'_key3'                => 'value3', // This isn't excluded by filter -- only key1 and key2 are excluded.
			'_plugin_custom_meta'  => 'value5', // Excluded via the '_%_custom_%' LIKE query.
			'_plugin_custom_meta1' => 'value6', // Excluded via the '_%_custom_%' LIKE query.
			'_first_name'          => 'value7', // Excluded via the '_%_name' LIKE query.
			'_last_name'           => 'value8', // Excluded via the '_%_name' LIKE query.
			'_billing_first_name'  => 'value9', // Excluded via the '_billing_%' LIKE query.
		);

		$included_data = array(
			'_key4'             => 'value4', // This isn't excluded by filter -- only key1, key2 and key3 are excluded.
			'_custom_meta'      => 'value4', // This isn't excluded by filter -- doesn't quite match the `_%_custom_%` LIKE clause.
			'_nickname'         => 'value8', // This isn't excluded by filter -- doesn't quite match the `_%_name` LIKE clause.
			'_customer_billing' => 'value9', // This isn't excluded by filter -- doesn't quite match the `_billing_%` LIKE clause.
		);

		$data = $this->copier->filter_excluded_meta_keys_via_query( array_merge( $excluded_data, $included_data ) );

		// Make sure the expected keys are returned.
		foreach ( $included_data as $key => $value ) {
			$this->assertArrayHasKey( $key, $data );
			$this->assertEquals( $value, $data[ $key ] );
		}

		// Make sure the keys we excluded via the default `NOT IN` clause are excluded.
		foreach ( $excluded_data as $key => $value ) {
			$this->assertArrayNotHasKey( $key, $data );
		}
	}

	/**
	 * Test WC_Subscription_Data_Copier::filter_excluded_meta_keys() filters out keys based on custom NOT IN and NOT LIKE clauses
	 * in the filtered SQL query.
	 */
	public function test_filter_excluded_meta_keys_via() {
		// These keys are excluded by default (with no third party filters).
		$excluded_data = array(
			'_transaction_id'       => 'txn_123',
			'_billing_interval'     => '2',
			'_billing_period'       => 'month',
			'_created_via'          => 'checkout',
			'_order_key'            => 'wc_order_xyz',
			'_payment_method'       => 'bacs',
			'_payment_method_title' => 'Direct Bank Transfer',
		);

		// These keys are not excluded by default.
		$included_data = array(
			'_customer_user'      => 1,
			'_order_total'        => 100.00,
			'_custom_meta'        => 'value',
			'data_value'          => 'value',
			'_order_tax'          => 10.00,
			'_stripe_customer_id' => 'cus_123', // Excluded via the '_%_custom_%' LIKE query.
			'_stripe_source_id'   => 'card_123', // Excluded via the '_%_custom_%' LIKE query.
		);

		$data = array_merge( $excluded_data, $included_data );
		$data = $this->copier->filter_excluded_meta_keys_via_query( $data );

		// Make sure the expected keys are returned.
		foreach ( $included_data as $key => $value ) {
			$this->assertArrayHasKey( $key, $data );
			$this->assertEquals( $value, $data[ $key ] );
		}

		// Make sure the keys we excluded via the default `NOT IN` clause are excluded.
		foreach ( $excluded_data as $key => $value ) {
			$this->assertArrayNotHasKey( $key, $data );
		}
	}
}
