<?php
/**
 * Undocumented class
 */
class WC_Subscriptions_Data_Copier_Test extends WP_UnitTestCase {

	public $mock_subscription;

	public $mock_order;

	public $copier;

	private $original_db;

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

	public function tear_down() {
		parent::tear_down();

		// Restore the database to its original state.
		if ( $this->original_db ) {
			$GLOBALS['wpdb'] = $this->original_db;
		}
	}

	/**
	 * Test WC_Subscription_Data_Copier::copy_data() sets the data correctly via object setters.
	 */
	public function test_copy_data() {
		// Mock order data
		$order_meta_data   = [];
		$order_meta_data[] = [
			'meta_key'   => '_billing_first_name', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 'John', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		];
		$order_meta_data[] = [
			'meta_key'   => '_billing_last_name', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 'Doe', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		];
		$order_meta_data[] = [
			'meta_key'   => '_customer_user', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => '1230', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		];
		$order_meta_data[] = [
			'meta_key'   => '_prices_include_tax', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		];
		$order_meta_data[] = [
			'meta_key'   => '_order_currency', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 'USD', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		];
		$order_meta_data[] = [
			'meta_key'   => '_custom_meta', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 'test meta value', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		];
		// Test the serialized data is set as meta as an array.
		$order_meta_data[] = [
			'meta_key'   => '_custom_meta_array', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => maybe_serialize( [ 'an' => 'array' ] ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		];

		// Mock the direct database query to return the order meta data.
		$this->mock_meta_database_query_results( $order_meta_data );

		// Setup expectations for the setters to be called on the mock subscription (the "to" object).
		$this->mock_subscription
			->expects( $this->once() )
			->method( 'set_billing_first_name' )
			->with( 'John' );

		$this->mock_subscription
			->expects( $this->once() )
			->method( 'set_billing_last_name' )
			->with( 'Doe' );

		$this->mock_subscription
			->expects( $this->once() )
			->method( 'set_customer_id' )
			->with( '1230' );

		$this->mock_subscription
			->expects( $this->once() )
			->method( 'set_prices_include_tax' )
			->with( true );

		$this->mock_subscription
			->expects( $this->once() )
			->method( 'set_currency' )
			->with( 'USD' );

		$this->mock_subscription
			->expects( $this->exactly( 2 ) )
			->method( 'update_meta_data' )
			->withConsecutive(
				[ '_custom_meta', 'test meta value' ],
				[ '_custom_meta_array', [ 'an' => 'array' ] ] // <-- Test that the serialized data from the database is set as an array.
			);

		$this->copier->copy_data();
	}

	/**
	 * @expectedDeprecated wcs_subscription_meta
	 */
	public function test_deprecated_wcs_subscription_meta() {
		// Mock order data
		$order_meta_data   = [];
		$order_meta_data[] = [
			'meta_key'   => '_billing_first_name', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 'John', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		];
		$order_meta_data[] = [
			'meta_key'   => '_billing_last_name', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 'Doe', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		];
		$order_meta_data[] = [
			'meta_key'   => '_3pd_custom_meta', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 'doodacky', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		];
		$order_meta_data[] = [
			'meta_key'   => '_3pd_custom_meta_too', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 'doovalacky', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		];

		// Mock the direct database query to return the order meta data.
		$this->mock_meta_database_query_results( $order_meta_data );

		// Mock a third-party hooking onto the deprecated filter to remove their meta.
		add_filter(
			'wcs_subscription_meta',
			function( $data ) {
				foreach ( $data as $index => $meta_data ) {
					if ( '_3pd_custom_meta' === $meta_data['meta_key'] ) {
						unset( $data[ $index ] );
					}
				}
				return $data;
			}
		);

		// Setup expectations for the setters to be called on the mock subscription (the "to" object).
		$this->mock_subscription
			->expects( $this->once() )
			->method( 'set_billing_first_name' )
			->with( 'John' );

		$this->mock_subscription
			->expects( $this->once() )
			->method( 'set_billing_last_name' )
			->with( 'Doe' );

		// Only expect the update_meta_data to be called once for the custom meta data that remains.
		$this->mock_subscription
			->expects( $this->once() )
			->method( 'update_meta_data' )
			->with( '_3pd_custom_meta_too', 'doovalacky' );

		$this->copier->copy_data();
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

	private function mock_meta_database_query_results( $return, $function = 'get_results' ) {
		$mock_db = $this->getMockBuilder( wpdb::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_db->expects( $this->any() )
			->method( $function )
			->will( $this->returnValue( $return ) );

		// Keep a record of the wpdb instance so we can restore it later.
		$this->original_db = $GLOBALS['wpdb'];

		// Override the global $wpdb object with our mock.
		$GLOBALS['wpdb'] = $mock_db;
	}
}
