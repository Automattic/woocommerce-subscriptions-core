<?php
/**
 * Undocumented class
 */
class WC_Subscriptions_Data_Copier extends WP_UnitTestCase {

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

		$this->copier = new WC_Subscription_Data_Copier( $this->mock_order, $this->mock_subscription, 'subscription' );
	}

	/**
	 * Test WC_Subscription_Data_Copier::set_data() sets the data correctly via object setters.
	 */
	public function test_set_data_via_setter() {

		// Standard data setter (billing first name).
		$this->mock_subscription
			->expects( $this->once() )
			->method( 'set_billing_first_name' )
			->with( 'John' );

		$this->copier->set_data( '_billing_first_name', 'John' );

		// Mapped setter (_customer_user -> set_customer_id).
		$this->mock_subscription
			->expects( $this->once() )
			->method( 'set_customer_id' )
			->with( '123' );

		$this->copier->set_data( '_customer_user', '123' );

		// Boolean setters (set_prices_include_tax no -> false, yes -> true).
		$this->mock_subscription
			->expects( $this->exactly( 2 ) )
			->method( 'set_prices_include_tax' )
			->withConsecutive( [ true ], [ false ] );

		// Yes (↓) gets converted to true (↑).
		$this->copier->set_data( '_prices_include_tax', 'yes' );
		// No (↓) gets converted to false (↑↑).
		$this->copier->set_data( '_prices_include_tax', 'no' );

	}

	/**
	 * Test WC_Subscription_Data_Copier::set_data() doesn't set data for address indexes.
	 */
	public function test_set_data_nothing_set() {
		$this->mock_subscription
			->expects( $this->never() )
			->method( $this->anything() );

		$this->copier->set_data( '_billing_address_index', 'First Last Address Email Phone' );
		$this->copier->set_data( '_billing_address_index', 'First Last Address Email Phone' );
	}

	/**
	 * Test WC_Subscription_Data_Copier::set_data() sets custom meta (where there is no setter) via update_meta_data.
	 */
	public function test_set_data_custom_meta() {
		// Custom meta data
		$this->mock_subscription
			->expects( $this->once() )
			->method( 'update_meta_data' )
			->with( '_custom_meta', 'value' );

		$this->copier->set_data( '_custom_meta', 'value' );
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
