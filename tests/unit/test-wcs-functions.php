<?php

function wcs_max_log_size_filter() {
	return $GLOBALS['wcs_max_log_size_filter'];
}
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
/**
 *
 */
class WCS_Functions_Test extends WP_UnitTestCase {

	public function test_wcs_is_subscription() {
		// test cases
		$subscription_object    = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );
		$subscription_id_int    = $subscription_object->get_id();
		$subscription_id_float  = (float) $subscription_id_int;
		$subscription_id_string = (string) $subscription_id_int;

		$non_subscription_object     = $this->factory->post->create_and_get();
		$non_subscription_id_int     = 9993993;
		$non_subscription_id_float   = 9993993.23;
		$non_subscription_id_string  = '9993993';
		$non_subscription_id_zeropad = '009993993';

		$this->assertEquals( true, wcs_is_subscription( $subscription_object ) );
		$this->assertEquals( true, wcs_is_subscription( $subscription_id_int ) );
		$this->assertEquals( true, wcs_is_subscription( $subscription_id_float ) );
		$this->assertEquals( true, wcs_is_subscription( $subscription_id_string ) );

		$this->assertEquals( false, wcs_is_subscription( $non_subscription_object ) );
		$this->assertEquals( false, wcs_is_subscription( $non_subscription_id_int ) );
		$this->assertEquals( false, wcs_is_subscription( $non_subscription_id_float ) );
		$this->assertEquals( false, wcs_is_subscription( $non_subscription_id_string ) );

		// // garbage
		$this->assertEquals( false, wcs_is_subscription( 'foo' ) );
		$this->assertEquals( false, wcs_is_subscription( array( 4 ) ) );
		$this->assertEquals( false, wcs_is_subscription( false ) );
		$this->assertEquals( false, wcs_is_subscription( true ) );
		$this->assertEquals( false, wcs_is_subscription( null ) );
	}

	public function test_wcs_do_subscriptions_exist() {
		$this->assertEquals( false, wcs_do_subscriptions_exist(), 'Subscriptions should not exist, yet wcs_do_subscriptions_exist is reporting they do' );

		WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );

		$this->assertEquals( true, wcs_do_subscriptions_exist(), 'There should be a subscription, yet wcs_do_subscriptions_exist is reporting they do not.' );
	}


	public function test_wcs_get_subscription() {
		$subscription_object     = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );
		$non_subscription_object = $this->factory->post->create_and_get();

		$this->assertEquals( $subscription_object, wcs_get_subscription( $subscription_object ) );
		$this->assertEquals( $subscription_object, wcs_get_subscription( $subscription_object->get_id() ) );

		$this->assertEquals( false, wcs_get_subscription( $non_subscription_object ) );

		$this->assertEquals( false, wcs_get_subscription( 'foo' ), 'Passing a string does not return false.' );
		$this->assertEquals( false, wcs_get_subscription( null ), 'Passing null does not return false.' );
		$this->assertEquals( false, wcs_get_subscription( array( 4 ) ), 'Passing an array does not return false.' );
		$this->assertEquals( false, wcs_get_subscription( array( $subscription_object->get_id() ) ), 'Passing an array with the subscription\'s id does not return false.' );
		$this->assertEquals( false, wcs_get_subscription( true ), 'Passing true does not return false.' );
		$this->assertEquals( false, wcs_get_subscription( false ), 'Passing false does not return false.' );
	}

	/**
	 * This should throw a PHP error about a missing argument
	 */
	public function test_garbage_wcs_get_subscription() {
		if ( ! method_exists( 'PHPUnit_Runner_Version', 'id' ) || version_compare( PHPUnit_Runner_Version::id(), '6.0', '>=' ) ) {
			$this->expectException( PHP_VERSION_ID >= 70100 ? 'ArgumentCountError' : '\PHPUnit\Framework\Error\Warning' );
		} else {
			$this->setExpectedException( PHP_VERSION_ID >= 70100 ? 'ArgumentCountError' : 'PHPUnit_Framework_Error', null );
		}

		$this->assertEquals( false, wcs_get_subscription() );
	}

	/**
	 * @dataProvider wcs_create_subscription_errors_provider
	 */
	public function test_wcs_create_subscription_errors( $args, $error_code, $message = '' ) {
		$subscription = wcs_create_subscription( $args );

		$this->assertEquals( true, is_wp_error( $subscription ), $message );
		$this->assertEquals( $error_code, $subscription->get_error_code(), $message );
	}

	public function wcs_create_subscription_errors_provider() {
		return array(

			// #0: breaking the start date format
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => wcs_strtotime_dark_knight( 'now' ),
					'date_created'       => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_start_date_format',
			),

			// #1: breaking the created date (has to be in the past)
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => gmdate( 'Y-m-d H:i:s', wcs_strtotime_dark_knight( '-1 week' ) ),
					'date_created'       => gmdate( 'Y-m-d H:i:s', wcs_strtotime_dark_knight( '+1 week' ) ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_date_created',
			),

			// #2 Breaking the start date other ways
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => null,
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_start_date_format',
				'Passed NULL into start date, it\' valid.',
			),

			// #3
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => false,
					'date_created'       => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_start_date_format',
				'Passed false to start_date',
			),

			// #4
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => true,
					'date_created'       => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_start_date_format',
				'Passed true to start_date',
			),

			// #5
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => 'foo',
					'created_via'        => '',
					'date_created'       => gmdate( 'Y-m-d H:i:s' ),
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_start_date_format',
				'Passed "foo" to start_date',
			),

			// #6
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => array( 42 ),
					'date_created'       => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_start_date_format',
				'Passed array( 42 ) to start_date.',
			),

			// #7
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => array( 'foo' ),
					'date_created'       => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_start_date_format',
				'Passed array( "foo" ) to start_date',
			),

			// #8
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => new stdClass(),
					'date_created'       => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_start_date_format',
				'Passed new stdClass to start_date',
			),

			// #9
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => -1,
					'date_created'       => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_start_date_format',
				'Passed -1 to start_date',
			),

			// #10: Break the customer IDs
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_customer_id',
			),

			// #11
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 'foo',
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_customer_id',
			),

			// #12
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => array( 1 ),
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_customer_id',
			),

			// #13
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => -1,
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_customer_id',
				'Passed -1 to customer_id.',
			),

			// #14
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => false,
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_customer_id',
				'Passed false to customer_id',
			),

			// #15
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => true,
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_customer_id',
			),

			// #16: Let's break the billing periods
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_billing_period',
			),

			// #17
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'eon',
					'billing_interval'   => 3,
				),
				'woocommerce_subscription_invalid_billing_period',
			),

			// #18
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 0,
				),
				'woocommerce_subscription_invalid_billing_interval',
			),

			// #19
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
				),
				'woocommerce_subscription_invalid_billing_interval',
			),

			// #20
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => 'foo',
				),
				'woocommerce_subscription_invalid_billing_interval',
			),

			// #21
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => array( 3 ),
				),
				'woocommerce_subscription_invalid_billing_interval',
				'Passed array( 3 ) into billing interval, apparently valid.',
			),

			// #22
			array(
				array(
					'status'             => '',
					'order_id'           => 0,
					'customer_note'      => '',
					'customer_id'        => 1,
					'start_date'         => gmdate( 'Y-m-d H:i:s' ),
					'created_via'        => '',
					'currency'           => 'GBP',
					'prices_include_tax' => 'yes',
					'billing_period'     => 'month',
					'billing_interval'   => array( 'foo' ),
				),
				'woocommerce_subscription_invalid_billing_interval',
				'Passed array( "foo" ) into billing_interval, apparently valid',
			),
		);
	}


	/**
	 * @dataProvider wcs_create_subscription_provider
	 */
	public function test_wcs_create_subscription_no_order( $args, $expects ) {
		$default_expects = array(
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s' ),
		);
		$expects         = wp_parse_args( $expects, $default_expects );

		$default_include_tax = get_option( 'woocommerce_prices_include_tax' );
		update_option( 'woocommerce_prices_include_tax', $expects['set_tax'] );

		$subscription    = wcs_create_subscription( $args );
		$subscription_id = $subscription->get_id();

		$this->assertEquals( false, is_wp_error( $subscription ) );
		$this->assertEquals( true, wcs_is_subscription( $subscription ) );

		$this->assertEquals( $expects['currency'], $subscription->get_currency() );
		$this->assertEquals( $expects['period'], $subscription->get_billing_period() );
		$this->assertEquals( $expects['interval'], $subscription->get_billing_interval() );
		$this->assertEquals( $expects['customer'], $subscription->get_customer_id() );
		$this->assertEquals( $expects['version'], $subscription->get_version() );

		// Parameters for wcs_create_subscription() expects 'include_tax' to be 'yes' or 'no' value.
		$this->assertEquals( 'no' !== $expects['include_tax'], $subscription->get_prices_include_tax() );
		$this->assertEquals( $expects['created_via'], $subscription->get_created_via() );

		$this->assertEquals( $expects['status'], 'wc-' . $subscription->get_status() );
		$this->assertEquals( $expects['parent_id'], $subscription->get_parent_id() );
		$this->assertEquals( $expects['excerpt'], $subscription->get_customer_note() );
		$this->assertDateTimeString( $subscription->get_date( 'date_created' ) );
		$this->assertEquals( wcs_date_to_time( $expects['post_date_gmt'] ), $subscription->get_time( 'date_created' ), sprintf( 'Expected %s and actual %s dates are out of bound of the allowed 2 second discrepancy (actual difference is %s).', $expects['post_date_gmt'], $subscription->get_date( 'date_created' ), ( wcs_date_to_time( $expects['post_date_gmt'] ) - $subscription->get_time( 'date_created' ) ) ), 2 );

		if ( ! wcs_is_custom_order_tables_usage_enabled() ) {
			// Verify that non-HPOS storage continues to use legacy meta_keys / values until intentionally deprecated.
			$subscription_metas = array_map(
				function ( $item ) {
					return $item[0];
				},
				get_post_meta( $subscription_id )
			);
			$this->assertEquals( $expects['currency'], $subscription_metas['_order_currency'] );
			$this->assertEquals( $expects['period'], $subscription_metas['_billing_period'] );
			$this->assertEquals( $expects['interval'], $subscription_metas['_billing_interval'] );
			$this->assertEquals( $expects['customer'], $subscription_metas['_customer_user'] );
			$this->assertEquals( $expects['version'], $subscription_metas['_order_version'] );
			$this->assertEquals( $expects['include_tax'], $subscription_metas['_prices_include_tax'] );
			// `_created_via` can only be returned this way when not using the specific getter (`get_created_via`)
			$this->assertEquals( $expects['created_via'], get_post_meta( $subscription_id, '_created_via', true ) );
		}

		update_option( 'woocommerce_prices_include_tax', $default_include_tax );
	}

	public function wcs_create_subscription_provider() {
		$custom_start_date = gmdate( 'Y-m-d H:i:s', wcs_strtotime_dark_knight( '-1 week' ) );

		return array(
			array(
				array(
					'billing_period'   => 'month',
					'billing_interval' => 3,
					'customer_id'      => 1,
					'currency'         => 'GBP',
				),
				array(
					'currency'    => 'GBP',
					'period'      => 'month',
					'interval'    => 3,
					'customer'    => 1,
					'version'     => WC_VERSION,
					'include_tax' => 'yes',
					'set_tax'     => 'yes',
					'status'      => 'wc-pending',
					'parent_id'   => 0,
					'excerpt'     => '',
					'created_via' => '',
				),
			),

			array(
				array(
					'billing_period'   => 'day',
					'billing_interval' => 9,
					'status'           => 'active',
					'customer_note'    => 'This is a test',
					'created_via'      => 'Woo Subs 2 test case',
					'customer_id'      => 2,
					'start_date'       => $custom_start_date,
					'date_created'     => $custom_start_date,
				),
				array(
					'currency'      => 'USD',
					'period'        => 'day',
					'interval'      => 9,
					'customer'      => 2,
					'version'       => WC_VERSION, // Version is no longer settable, will always be set to current WC version.
					'include_tax'   => 'no',
					'set_tax'       => 'no',
					'status'        => 'wc-active',
					'parent_id'     => 0,
					'excerpt'       => 'This is a test',
					'created_via'   => 'Woo Subs 2 test case',
					'post_date_gmt' => $custom_start_date,
				),
			),
		);
	}

	/**
	 * @dataProvider wcs_subscription_statuses_provider
	 */
	public function test_wcs_get_subscription_statuses( $key, $value ) {
		$statuses = wcs_get_subscription_statuses();
		$this->assertIsArray( $statuses );
		$this->assertArrayHasKey( $key, $statuses );
		$this->assertEquals( $value, $statuses[ $key ] );
	}

	public function wcs_subscription_statuses_provider() {
		return array(
			array( 'wc-pending', 'Pending' ),
			array( 'wc-active', 'Active' ),
			array( 'wc-on-hold', 'On hold' ),
			array( 'wc-switched', 'Switched' ),
			array( 'wc-expired', 'Expired' ),
			array( 'wc-pending-cancel', 'Pending Cancellation' ),
		);
	}

	public function test_wcs_get_subscription_statuses_filterable() {
		add_filter( 'wcs_subscription_statuses', array( $this, 'filter_wcs_get_subscription_statuses' ) );

		$statuses = wcs_get_subscription_statuses();
		$this->assertArrayHasKey( 'wc-foo', $statuses );
		$this->assertEquals( 'Foo', $statuses['wc-foo'] );

		remove_filter( 'wcs_subscription_statuses', array( $this, 'filter_wcs_get_subscription_statuses' ) );
	}

	public function filter_wcs_get_subscription_statuses( $statuses ) {
		$statuses['wc-foo'] = 'Foo';

		return $statuses;
	}

	/**
	 * @dataProvider wcs_get_subscription_status_name_provider
	 */
	public function test_wcs_get_subscription_status_name( $candidate, $expected ) {
		$this->assertEquals( $expected, wcs_get_subscription_status_name( $candidate ) );
	}

	public function wcs_get_subscription_status_name_provider() {
		return array(
			array( 'wc-pending', 'Pending' ),
			array( 'pending', 'Pending' ),
			array( 'foo', 'foo' ),
			array( '42', '42' ),
			array( '', '' ),
			array( 'foowc-bar', 'foowc-bar' ),
		);
	}

	/**
	 * @dataProvider wcs_sanitize_status_key_provider
	 */
	public function test_wcs_sanitize_status_key( $actual, $expected ) {
		$this->assertEquals( $expected, wcs_sanitize_subscription_status_key( $actual ) );
	}

	public function wcs_sanitize_status_key_provider() {
		return array(
			array( 'pending', 'wc-pending' ),
			array( 'wc-pending', 'wc-pending' ),
			array( 'foo', 'wc-foo' ),
			array( '', '' ),
			array( 42, '' ),
			array( array(), '' ),
			array( true, '' ),
			array( -1, '' ),
			array( false, '' ),
			array( null, '' ),
			array( new stdClass(), '' ),
			array( new WP_Error( 'foo' ), '' ),
		);
	}

	/**
	 * ->assertWPError is not a built in phpunit assertion. It's defined in
	 * tmp/wordpress-tests-lib/includes/testcase.php
	 *
	 * @dataProvider wcs_get_subscription_status_name_error_provider
	 */
	public function test_wcs_get_subscription_status_name_error( $candidate ) {
		$this->assertWPError( wcs_get_subscription_status_name( $candidate ), 'Can not get status name. Status is not a string.' );
	}

	public function wcs_get_subscription_status_name_error_provider() {
		return array(
			array( 42 ),
			array( array( 'foo' ) ),
			array( true ),
			array( false ),
			array( null ),
			array( new stdClass() ),
			array( 0 ),
			array( -1 ),
			array( new WP_Error( 'foo' ) ),
		);
	}

	/**
	 * @dataProvider wcs_subscription_dates_provider
	 */
	public function test_wcs_get_subscription_date_types( $key, $value ) {
		$date_types = wcs_get_subscription_date_types();
		$this->assertIsArray( $date_types );
		$this->assertArrayHasKey( $key, $date_types );
		$this->assertEquals( $value, $date_types[ $key ] );
	}

	public function wcs_subscription_dates_provider() {
		return array(
			array( 'start', 'Start Date' ),
			array( 'trial_end', 'Trial End' ),
			array( 'next_payment', 'Next Payment' ),
			array( 'last_payment', 'Last Order Date' ),
			array( 'end', 'End Date' ),
		);
	}

	public function test_wcs_get_subscription_date_types_filterable() {
		add_filter( 'woocommerce_subscription_dates', array( $this, 'filter_wcs_get_subscription_date_types' ) );

		$date_types = wcs_get_subscription_date_types();

		$this->assertIsArray( $date_types );
		$this->assertArrayHasKey( 'big_bang', $date_types );
		$this->assertEquals( 'Big Bang', $date_types['big_bang'] );

		remove_filter( 'woocommerce_subscription_dates', array( $this, 'filter_wcs_get_subscription_date_types' ) );
	}

	public function filter_wcs_get_subscription_date_types( $dates ) {
		$dates['big_bang'] = _x( 'Big Bang', 'table column header', 'woocommerce-subscriptions' );

		return $dates;
	}


	public function test_wcs_get_date_meta_key() {
		$this->assertEquals( '_schedule_foo', wcs_get_date_meta_key( 'foo' ) );
	}

	public function test_wcs_get_date_meta_key_filterable() {
		add_filter(
			'woocommerce_subscription_date_meta_key_prefix',
			array(
				$this,
				'filter_wcs_get_date_meta_key',
			),
			10,
			2
		);

		$this->assertEquals( '_r2d2_foo', wcs_get_date_meta_key( 'foo' ) );

		remove_filter(
			'woocommerce_subscription_date_meta_key_prefix',
			array(
				$this,
				'filter_wcs_get_date_meta_key',
			)
		);
	}

	public function filter_wcs_get_date_meta_key( $prefix, $date_type ) {
		return sprintf( '_r2d2_%s', $date_type );
	}

	/**
	 * @dataProvider wcs_get_date_meta_key_error_provider
	 */
	public function test_wcs_get_date_meta_key_errors( $date_type, $message = 'Date type is not a string.' ) {
		$this->assertWPError( wcs_get_date_meta_key( $date_type ), $message );
	}

	public function wcs_get_date_meta_key_error_provider() {
		return array(
			array( '', 'Date type can not be an empty string.' ),
			array( 4 ),
			array( -1 ),
			array( 34.56 ),
			array( array( 'foo' ) ),
			array( array() ),
			array( true ),
			array( false ),
			array( new stdClass() ),
			array( new WP_Error( 'foo' ) ),
			array( null ),
		);
	}

	/**
	 * Deals with cases where order_id is not a shop_order
	 */
	public function test_1_wcs_get_subscriptions() {
		$non_subscription_id = $this->factory->post->create();

		$args = array( 'order_id' => $non_subscription_id );

		$subscriptions = wcs_get_subscriptions( $args );

		$this->assertEquals( array(), $subscriptions );
	}

	/**
	 * Deals with cases where we're filtering for status
	 */
	public function test_2_wcs_get_subscriptions() {

		$subscription_1 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'pending',
			)
		);

		$subscription_2 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'pending',
			)
		);

		$subscription_3 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'on-hold',
			)
		);

		$subscription_4 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'switched',
			)
		);

		$subscription_5 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'active',
			)
		);

		$subscription_6 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'cancelled',
			)
		);

		$subscription_7 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'expired',
			)
		);

		$subscription_8 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'pending-cancel',
			)
		);

		// Check for on-hold
		$subscriptions = wcs_get_subscriptions( array( 'subscription_status' => 'on-hold' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		unset( $subscriptions );

		// Pending
		$subscriptions = wcs_get_subscriptions( array( 'subscription_status' => 'pending' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 2, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		unset( $subscriptions );

		// Switched
		$subscriptions = wcs_get_subscriptions( array( 'subscription_status' => 'switched' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		unset( $subscriptions );

		// Any
		$subscriptions = wcs_get_subscriptions( array( 'subscription_status' => 'any' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 8, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_8->get_id(), $subscriptions );
		unset( $subscriptions );

		// Trash
		$subscriptions = wcs_get_subscriptions( array( 'subscription_status' => 'trash' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEmpty( $subscriptions );
		unset( $subscriptions );

		// Active
		$subscriptions = wcs_get_subscriptions( array( 'subscription_status' => 'active' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		unset( $subscriptions );

		// Cancelled
		$subscriptions = wcs_get_subscriptions( array( 'subscription_status' => 'cancelled' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		unset( $subscriptions );

		// Expired
		$subscriptions = wcs_get_subscriptions( array( 'subscription_status' => 'expired' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		unset( $subscriptions );

		// Pending Cancellation
		$subscriptions = wcs_get_subscriptions( array( 'subscription_status' => 'pending-cancel' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_8->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		unset( $subscriptions );

		$is_hpos_enabled = wcs_is_custom_order_tables_usage_enabled();

		// An invalid status
		$subscriptions = wcs_get_subscriptions( array( 'subscription_status' => 'rubbish' ) );

		if ( $is_hpos_enabled ) {
			// No subscriptions should match the invalid status.
			$this->assertIsArray( $subscriptions );
			$this->assertEquals( 0, count( $subscriptions ) );
		} else {
			// In non-HPOS environments, WP_Query simply ignores invalid post_stati, so no clause would be applied.
			$this->assertIsArray( $subscriptions );
			$this->assertEquals( 8, count( $subscriptions ) );
			$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
			$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions );
			$this->assertArrayHasKey( $subscription_3->get_id(), $subscriptions );
			$this->assertArrayHasKey( $subscription_4->get_id(), $subscriptions );
			$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions );
			$this->assertArrayHasKey( $subscription_6->get_id(), $subscriptions );
			$this->assertArrayHasKey( $subscription_7->get_id(), $subscriptions );
			$this->assertArrayHasKey( $subscription_8->get_id(), $subscriptions );
		}

		unset( $subscriptions );

		// An invalid status is ignored and does not apply as a clause to the query, while the valid status still applies.
		$subscriptions = wcs_get_subscriptions( array( 'subscription_status' => [ 'rubbish', 'active' ] ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );

		unset( $subscriptions );
	}

	/**
	 * Tests filtering for valid order id vs no order id
	 */
	public function test_3_wcs_get_subscriptions() {
		$order_1 = WCS_Helper_Subscription::create_order();
		$order_2 = WCS_Helper_Subscription::create_order();

		#subscription under order_1
		$subscription_1 = WCS_Helper_Subscription::create_subscription( array( 'order_id' => wcs_get_objects_property( $order_1, 'id' ) ) );

		#subscriptions under order_2
		$subscription_2 = WCS_Helper_Subscription::create_subscription( array( 'order_id' => wcs_get_objects_property( $order_2, 'id' ) ) );
		$subscription_3 = WCS_Helper_Subscription::create_subscription( array( 'order_id' => wcs_get_objects_property( $order_2, 'id' ) ) );

		$subscriptions_1 = wcs_get_subscriptions( array( 'order_id' => wcs_get_objects_property( $order_1, 'id' ) ) );
		$subscriptions_2 = wcs_get_subscriptions( array( 'order_id' => wcs_get_objects_property( $order_2, 'id' ) ) );

		$this->assertIsArray( $subscriptions_1 );
		$this->assertEquals( 1, count( $subscriptions_1 ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions_1 );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions_1 );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions_1 );

		$this->assertIsArray( $subscriptions_2 );
		$this->assertEquals( 2, count( $subscriptions_2 ) );
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions_2 );
		$this->assertArrayHasKey( $subscription_3->get_id(), $subscriptions_2 );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions_2 );

	}

	/**
	 * Tests ordering by start_date
	 */
	public function test_5_wcs_get_subscriptions() {

		$subscription_1 = WCS_Helper_Subscription::create_subscription(
			array(
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '2016-05-07 00:00:00' ) ),
			)
		);

		$subscription_2 = WCS_Helper_Subscription::create_subscription(
			array(
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '2016-06-08 00:00:00' ) ),
			)
		);

		$subscription_3 = WCS_Helper_Subscription::create_subscription(
			array(
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '2016-07-30 09:08:08' ) ),
			)
		);

		$subscription_4 = WCS_Helper_Subscription::create_subscription(
			array(
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime( '2016-07-30 08:08:08' ) ),
			)
		);

		$subscriptions = wcs_get_subscriptions( array( 'orderby' => 'start_date' ) );
		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 4, count( $subscriptions ) );
		$correct_order = array(
			$subscription_3->get_id() => $subscription_3,
			$subscription_4->get_id() => $subscription_4,
			$subscription_2->get_id() => $subscription_2,
			$subscription_1->get_id() => $subscription_1,
		);
		$this->assertEquals( $subscriptions, $correct_order );
	}

	/**
	 * Test for non-existent product
	 */
	public function test_1_wcs_get_subscriptions_for_product() {

		// Create a test user
		$user_id1 = wp_create_user( 'susan', 'testuser', 'susan@example.com' );

		// Create a test subscription with 'pending' status
		$subscription1 = WCS_Helper_Subscription::create_subscription(
			array(
				'status'      => 'pending',
				'start_date'  => '2015-07-14 00:00:00',
				'customer_id' => $user_id1,
			)
		);

		// Link first subscription to an order
		$order1 = WCS_Helper_Subscription::create_order();
		$subscription1->set_parent_id( wcs_get_objects_property( $order1, 'id' ) );

		$subscription1->save();

		// Add product to first subscription
		$product1 = WCS_Helper_Product::create_simple_subscription_product();
		WCS_Helper_Subscription::add_product( $subscription1, $product1 );
		$product_id1 = $product1->get_id();

		// Test for non-existent product
		$subscriptions = wcs_get_subscriptions_for_product( $product_id1 + 1 );

		$this->assertEquals( array(), $subscriptions );
		unset( $subscriptions );
	}

	/**
	 * Deals with valid cases without fields and args
	 */
	public function test_2_wcs_get_subscriptions_for_product() {

		$subscription_1 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'pending',
			)
		);
		$subscription_1->save();

		$subscription_2 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'active',
			)
		);

		$subscription_3 = WCS_Helper_Subscription::create_subscription( array() );
		$subscription_4 = WCS_Helper_Subscription::create_subscription( array() );
		$subscription_5 = WCS_Helper_Subscription::create_subscription( array() );
		$subscription_6 = WCS_Helper_Subscription::create_subscription( array() );

		$product1 = WCS_Helper_Product::create_simple_subscription_product();
		WCS_Helper_Subscription::add_product( $subscription_1, $product1 );
		$product_id1 = $product1->get_id();

		$subscriptions = wcs_get_subscriptions_for_product( $product_id1 );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		unset( $subscriptions );

		WCS_Helper_Subscription::add_product( $subscription_2, $product1 );

		$subscriptions = wcs_get_subscriptions_for_product( $product_id1 );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 2, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		unset( $subscriptions );

		$product2 = WCS_Helper_Product::create_simple_subscription_product();
		WCS_Helper_Subscription::add_product( $subscription_3, $product2 );
		WCS_Helper_Subscription::add_product( $subscription_4, $product2 );
		WCS_Helper_Subscription::add_product( $subscription_5, $product2 );
		$product_id2 = $product2->get_id();

		$subscriptions_1 = wcs_get_subscriptions_for_product( $product_id1 );
		$subscriptions_2 = wcs_get_subscriptions_for_product( $product_id2 );

		$this->assertIsArray( $subscriptions_1 );
		$this->assertEquals( 2, count( $subscriptions_1 ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions_1 );
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions_1 );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions_1 );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions_1 );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions_1 );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions_1 );

		$this->assertIsArray( $subscriptions_2 );
		$this->assertEquals( 3, count( $subscriptions_2 ) );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions_2 );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions_2 );
		$this->assertArrayHasKey( $subscription_3->get_id(), $subscriptions_2 );
		$this->assertArrayHasKey( $subscription_4->get_id(), $subscriptions_2 );
		$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions_2 );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions_2 );
		unset( $subscriptions );
	}

	/**
	 * Tests filtering for particular product ids and certain fields
	 */
	public function test_3_wcs_get_subscriptions_for_product() {
		$subscription_1 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'pending',
			)
		);
		$subscription_1->save();

		$subscription_2 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'active',
			)
		);

		$subscription_3 = WCS_Helper_Subscription::create_subscription( array() );
		$subscription_4 = WCS_Helper_Subscription::create_subscription( array() );
		$subscription_5 = WCS_Helper_Subscription::create_subscription( array() );
		$subscription_6 = WCS_Helper_Subscription::create_subscription( array() );

		$product1 = WCS_Helper_Product::create_simple_subscription_product();
		$product2 = WCS_Helper_Product::create_simple_subscription_product();
		WCS_Helper_Subscription::add_product( $subscription_1, $product1 );
		WCS_Helper_Subscription::add_product( $subscription_2, $product1 );
		WCS_Helper_Subscription::add_product( $subscription_3, $product2 );
		$product_id1 = $product1->get_id();

		$subscriptions = wcs_get_subscriptions_for_product( $product_id1, 'ids' );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 2, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		unset( $subscriptions );

		$subscriptions = wcs_get_subscriptions_for_product( $product_id1, 'subscription' );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 2, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertInstanceOf( 'WC_Subscription', $subscriptions[ $subscription_1->get_id() ] );
		$this->assertInstanceOf( 'WC_Subscription', $subscriptions[ $subscription_2->get_id() ] );
		unset( $subscriptions );
	}

	/**
	 * Tests the args argument, with multiple args and multiple product ids
	 */
	public function test_4_wcs_get_subscriptions_for_product() {
		$subscription_1 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'active',
			)
		);

		$subscription_2 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'pending',
			)
		);

		$subscription_3 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'on-hold',
			)
		);

		$subscription_4 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'switched',
			)
		);

		$subscription_5 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'expired',
			)
		);

		$subscription_6 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'cancelled',
			)
		);

		$subscription_7 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'active',
			)
		);

		$subscription_8 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'pending-cancel',
			)
		);

		$subscription_9 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'expired',
			)
		);

		$product1 = WCS_Helper_Product::create_simple_subscription_product();
		$product2 = WCS_Helper_Product::create_simple_subscription_product();

		WCS_Helper_Subscription::add_product( $subscription_1, $product1 );
		WCS_Helper_Subscription::add_product( $subscription_2, $product1 );
		WCS_Helper_Subscription::add_product( $subscription_3, $product1 );
		WCS_Helper_Subscription::add_product( $subscription_4, $product1 );
		WCS_Helper_Subscription::add_product( $subscription_5, $product1 );
		WCS_Helper_Subscription::add_product( $subscription_9, $product1 );
		$product_id1 = $product1->get_id();
		$product_id2 = $product2->get_id();

		$subscriptions = wcs_get_subscriptions_for_product( $product_id1, 'ids', array( 'subscription_status' => 'expired' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 2, count( $subscriptions ) );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_9->get_id(), $subscriptions );
		unset( $subscriptions );

		$subscriptions = wcs_get_subscriptions_for_product(
			$product_id1,
			'ids',
			array(
				'subscription_status' => 'expired',
				'limit'               => 1,
			)
		);

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_9->get_id(), $subscriptions );
		unset( $subscriptions );

		WCS_Helper_Subscription::add_product( $subscription_2, $product2 );
		WCS_Helper_Subscription::add_product( $subscription_3, $product2 );
		WCS_Helper_Subscription::add_product( $subscription_4, $product2 );
		WCS_Helper_Subscription::add_product( $subscription_5, $product2 );
		WCS_Helper_Subscription::add_product( $subscription_6, $product2 );
		WCS_Helper_Subscription::add_product( $subscription_9, $product2 );

		$subscriptions = wcs_get_subscriptions_for_product( array( $product_id1, $product_id2 ), 'ids', array( 'subscription_status' => array( 'expired', 'cancelled' ) ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 3, count( $subscriptions ) );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_9->get_id(), $subscriptions );
		unset( $subscriptions );

		$subscriptions = wcs_get_subscriptions_for_product(
			array( $product_id1, $product_id2 ),
			'ids',
			array(
				'subscription_status' => array( 'expired', 'cancelled' ),
				'limit'               => 2,
			)
		);

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 2, count( $subscriptions ) );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_9->get_id(), $subscriptions );
		unset( $subscriptions );

		/**
		 * Test the offset arg.
		 */
		$subscriptions = wcs_get_subscriptions_for_product(
			$product_id1,
			'ids',
			array(
				'limit'  => 2,
				'offset' => 1,
			)
		);

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 2, count( $subscriptions ) );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions ); // Skipped by offset
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions ); // Doesn't have product 1 or 2
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions ); // Doesn't have product 1 or 2
		$this->assertArrayNotHasKey( $subscription_9->get_id(), $subscriptions );
		unset( $subscriptions );

		// There's only 1 subscription with an active status so offsetting by 1 should return no results.
		$subscriptions = wcs_get_subscriptions_for_product(
			$product_id1,
			'ids',
			array(
				'subscription_status' => 'active',
				'limit'               => 10,
				'offset'              => 1,
			)
		);

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 0, count( $subscriptions ) );
		unset( $subscriptions );

		$subscriptions = wcs_get_subscriptions_for_product(
			array( $product_id1, $product_id2 ),
			'ids',
			array(
				'limit'  => 10,
				'offset' => 3,
			)
		);

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 4, count( $subscriptions ) );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions ); // Skipped by offset
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions ); // Skipped by offset
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions ); // Skipped by offset
		$this->assertArrayHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions ); // Doesn't have product 1 or 2
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions ); // Doesn't have product 1 or 2
		$this->assertArrayHasKey( $subscription_9->get_id(), $subscriptions );
		unset( $subscriptions );
	}

	/**
	 * Tests the with variation ids instead of product ids
	 */
	public function test_5_wcs_get_subscriptions_for_product() {
		$subscription_1 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'active',
			)
		);

		$subscription_2 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'pending',
			)
		);

		$subscription_3 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'on-hold',
			)
		);

		$subscription_4 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'switched',
			)
		);

		$subscription_5 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'expired',
			)
		);

		$subscription_6 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'cancelled',
			)
		);

		$subscription_7 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'active',
			)
		);

		$subscription_8 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'pending-cancel',
			)
		);

		$subscription_9 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'expired',
			)
		);

		$product1            = WCS_Helper_Product::create_variable_subscription_product();
		$product2            = WCS_Helper_Product::create_variable_subscription_product();
		$product_id1         = $product1->get_id();
		$product_id2         = $product2->get_id();
		$variations1         = $product1->get_children();
		$product1_variation1 = wc_get_product( $variations1[0] );
		$product1_variation2 = wc_get_product( $variations1[1] );
		$variations2         = $product2->get_children();
		$product2_variation1 = wc_get_product( $variations2[0] );
		$product2_variation2 = wc_get_product( $variations2[1] );

		WCS_Helper_Subscription::add_product( $subscription_1, $product1_variation1 );
		WCS_Helper_Subscription::add_product( $subscription_2, $product1_variation1 );
		WCS_Helper_Subscription::add_product( $subscription_3, $product1_variation1 );
		WCS_Helper_Subscription::add_product( $subscription_4, $product1_variation2 );
		WCS_Helper_Subscription::add_product( $subscription_5, $product1_variation2 );
		WCS_Helper_Subscription::add_product( $subscription_9, $product1_variation2 );
		WCS_Helper_Subscription::add_product( $subscription_1, $product2_variation2 );
		WCS_Helper_Subscription::add_product( $subscription_2, $product2_variation2 );
		WCS_Helper_Subscription::add_product( $subscription_3, $product2_variation2 );
		WCS_Helper_Subscription::add_product( $subscription_6, $product2_variation1 );
		WCS_Helper_Subscription::add_product( $subscription_7, $product2_variation1 );
		WCS_Helper_Subscription::add_product( $subscription_8, $product2_variation1 );

		$subscriptions = wcs_get_subscriptions_for_product( $product_id1, 'ids' );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 6, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_9->get_id(), $subscriptions );
		unset( $subscriptions );

		$subscriptions = wcs_get_subscriptions_for_product( $variations1[0], 'ids' );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 3, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_9->get_id(), $subscriptions );
		unset( $subscriptions );

		$subscriptions = wcs_get_subscriptions_for_product( $variations1[1], 'ids' );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 3, count( $subscriptions ) );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_9->get_id(), $subscriptions );
		unset( $subscriptions );

		$subscriptions = wcs_get_subscriptions_for_product( array( $variations1[0], $variations1[1], $variations2[0] ), 'ids', array( 'subscription_status' => 'active' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 2, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_9->get_id(), $subscriptions );
		unset( $subscriptions );

		$subscriptions = wcs_get_subscriptions_for_product( array_merge( $variations1, $variations2 ), 'ids', array( 'subscription_status' => array( 'on-hold', 'active' ) ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 3, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_2->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_3->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_4->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_5->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_6->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_7->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_8->get_id(), $subscriptions );
		$this->assertArrayNotHasKey( $subscription_9->get_id(), $subscriptions );
		unset( $subscriptions );

		$subscriptions = wcs_get_subscriptions_for_product(
			array_merge( $variations1, $variations2 ),
			'ids',
			array(
				'subscription_status' => array( 'on-hold', 'active' ),
				'limit'               => 2,
			)
		);

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 2, count( $subscriptions ) );
		unset( $subscriptions );
	}

	/**
	 * Tests the with variation ids and switching
	 */
	public function test_6_wcs_get_subscriptions_for_product() {
		$subscription_1 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'active',
			)
		);
		$subscription_2 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'on-hold',
			)
		);
		$subscription_3 = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => 'cancelled',
			)
		);

		$variable    = WCS_Helper_Product::create_variable_subscription_product();
		$variations  = $variable->get_children();
		$variation_1 = wc_get_product( array_shift( $variations ) );
		$variation_2 = wc_get_product( array_shift( $variations ) );

		$item_1 = WCS_Helper_Subscription::add_product( $subscription_1, $variation_1 );
		$item_2 = WCS_Helper_Subscription::add_product( $subscription_2, $variation_1 );
		$item_3 = WCS_Helper_Subscription::add_product( $subscription_2, $variation_2 );
		$item_4 = WCS_Helper_Subscription::add_product( $subscription_3, $variation_2 );

		/* Switches */
		// Subscription 1: Variation 1 => Variation 2.
		wcs_update_order_item_type( $item_1, 'line_item_switched', $subscription_1->get_id() );
		WCS_Helper_Subscription::add_product( $subscription_1, $variation_2 );

		// Subscription 2: Variation 2 => Variation 1.
		wcs_update_order_item_type( $item_3, 'line_item_switched', $subscription_2->get_id() );
		WCS_Helper_Subscription::add_product( $subscription_2, $variation_1 );

		/* Assertions */
		// Only 1 subscription remains with variation 1 ($subscription_2)
		$subscriptions = wcs_get_subscriptions_for_product( $variation_1->get_id(), 'ids' );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions );
		unset( $subscriptions );

		// 2 subscriptions exist with variation 2 [$subscription_1 (switched from variation 1) and $subscription_3]
		$subscriptions = wcs_get_subscriptions_for_product( $variation_2->get_id(), 'ids' );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 2, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_1->get_id(), $subscriptions );
		$this->assertArrayHasKey( $subscription_3->get_id(), $subscriptions );
		unset( $subscriptions );

		// Check limit and offset works while querying switched variations.
		$subscriptions = wcs_get_subscriptions_for_product(
			$variation_2->get_id(),
			'ids',
			array(
				'limit'  => 10,
				'offset' => 1,
			)
		);

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayNotHasKey( $subscription_1->get_id(), $subscriptions ); // Skipped by offset
		$this->assertArrayHasKey( $subscription_3->get_id(), $subscriptions );
		unset( $subscriptions );

		// Check status filter. Subscription 1 is active but switched from variation 1 to variation 2 so no results should be returned.
		$subscriptions = wcs_get_subscriptions_for_product( $variation_1->get_id(), 'ids', array( 'subscription_status' => 'active' ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 0, count( $subscriptions ) );
		unset( $subscriptions );

		// Check status filter. Subscription 1 is active but switched from variation 1 to variation 2 so only subscription 2 should be returned.
		$subscriptions = wcs_get_subscriptions_for_product( $variation_1->get_id(), 'ids', array( 'subscription_status' => array( 'active', 'on-hold' ) ) );

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( 1, count( $subscriptions ) );
		$this->assertArrayHasKey( $subscription_2->get_id(), $subscriptions );
		unset( $subscriptions );
	}

	/**
	 * @dataProvider duplicate_site_lock_replacement_provider
	 *
	 * @param string $url    The site URL
	 * @param string $domain The domain that should not be found in the replacement staging lock string.
	 */
	public function test_duplicate_site_lock_replacement( $url, $domain ) {
		$filter = function ( $_ ) use ( $url ) {
			return $url;
		};
		add_filter( 'site_url', $filter );
		$replaced_url = WCS_Staging::get_duplicate_site_lock_key();

		// If we're able to find the base domain, then the domain is at risk for search/replace
		// and staging detection is no good.
		$this->assertFalse( strpos( $replaced_url, $domain ) );
		$this->assertFalse( strpos( $replaced_url, $url ) );
		remove_filter( 'site_url', $filter );
	}


	public function duplicate_site_lock_replacement_provider() {
		return array(
			array( 'http://example.com', 'example.com' ),
			array( 'https://example.com', 'example.com' ),
			array( 'http://site.com', 'site.com' ),
			array( 'https://site.com', 'site.com' ),
			array( 'http://12345.com', '12345.com' ),
			array( 'https://123456.co', '123456.co' ),
			array( 'http://themostawesomedomainevercreated.restaurant', 'themostawesomedomainevercreated.restaurant' ),

			// not sure if this is a legit domain value, but include it anyway
			array( '//foo.bar', 'foo.bar' ),
		);
	}

	public function test_set_payment_meta() {
		$hpos_enabled = wcs_is_custom_order_tables_usage_enabled();

		$subscription = WCS_Helper_Subscription::create_subscription();

		// user_meta, usermeta
		wcs_set_payment_meta( $subscription, array( 'user_meta' => array( 'user_meta_1' => array( 'value' => 'user_meta_1_value' ) ) ) );
		$subscription->save();
		$this->assertContains( 'user_meta_1_value', get_user_meta( $subscription->get_user_id(), 'user_meta_1' ) );

		wcs_set_payment_meta( $subscription, array( 'usermeta' => array( 'user_meta_2' => array( 'value' => 'user_meta_2_value' ) ) ) );
		$subscription->save();
		$this->assertContains( 'user_meta_2_value', get_user_meta( $subscription->get_user_id(), 'user_meta_2' ) );

		// post_meta, postmeta
		wcs_set_payment_meta( $subscription, array( 'post_meta' => array( 'post_meta_1' => array( 'value' => 'post_meta_1_value' ) ) ) );
		$subscription->save();
		$this->assertContains( 'post_meta_1_value', array_column( $subscription->get_meta( 'post_meta_1', false ), 'value' ) );
		if ( ! $hpos_enabled ) {
			$post_meta_1_items_values = array_map(
				function ( $meta ) {
					return $meta->get_data()['value'] ?? '';
				},
				$subscription->get_meta( 'post_meta_1', false )
			);
			$this->assertContains( 'post_meta_1_value', $post_meta_1_items_values );
		}

		wcs_set_payment_meta( $subscription, array( 'postmeta' => array( 'post_meta_2' => array( 'value' => 'post_meta_2_value' ) ) ) );
		$subscription->save();

		$this->assertContains( 'post_meta_2_value', array_column( $subscription->get_meta( 'post_meta_2', false ), 'value' ) );
		if ( ! $hpos_enabled ) {
			$post_meta_2_items_values = array_map(
				function ( $meta ) {
					return $meta->get_data()['value'] ?? '';
				},
				$subscription->get_meta( 'post_meta_2', false )
			);
			$this->assertContains( 'post_meta_2_value', $post_meta_2_items_values );
		}

		// options
		wcs_set_payment_meta( $subscription, array( 'options' => array( 'option_1' => array( 'value' => 'option_1_value' ) ) ) );
		$this->assertEquals( 'option_1_value', get_option( 'option_1' ) );

		$this->expectException( InvalidArgumentException::class );
		wcs_set_payment_meta( $subscription, null );
	}

	private function assertDateTimeString( $actual ) {
		$this->assertMatchesRegularExpression( '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $actual );
	}
}
