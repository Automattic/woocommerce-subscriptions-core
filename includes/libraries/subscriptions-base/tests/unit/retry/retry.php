<?php

/**
 * Test the WCS_Retry class's public methods
 */
class WCS_Retry_Test extends WCS_Unit_Test_Case {

	protected static $mock_retry;

	protected static $retry_data;

	/**
	 * Set of invalid data to check against
	 */
	private $invalid_data = array( 123, '123', false );

	/**
	 * Createa mock retry object to test against
	 */
	public static function setUpBeforeClass() {

		self::$retry_data = array(
			'id'       => 108,
			'order_id' => 1235,
			'status'   => 'unique_status',
			'date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
			'rule_raw' => array(
				'retry_after_interval'            => DAY_IN_SECONDS / 2,
				'email_template_customer'         => 'WCS_Unit_Test_Email_Customer',
				'email_template_admin'            => 'WCS_Unit_Test_Email_Admin',
				'status_to_apply_to_order'        => 'unique_status',
				'status_to_apply_to_subscription' => 'unique_status',
			),
		);

		self::$mock_retry = new WCS_Retry( self::$retry_data );
	}

	/**
	 * Check the retry has the correct ID
	 */
	public function test_get_id() {
		$this->assertEquals( self::$retry_data['id'], self::$mock_retry->get_id() );
		$this->assert_not_equal_to_invalid_data( self::$mock_retry->get_id() );
	}

	/**
	 * Check the retry has the correct order ID
	 */
	public function test_get_order_id() {
		$this->assertEquals( self::$retry_data['order_id'], self::$mock_retry->get_order_id() );
		$this->assert_not_equal_to_invalid_data( self::$mock_retry->get_id() );
	}

	/**
	 * Check the retry has the correct status
	 */
	public function test_get_status() {
		$this->assertEquals( self::$retry_data['status'], self::$mock_retry->get_status() );
		$this->assert_not_equal_to_invalid_data( self::$mock_retry->get_id() );
	}

	/**
	 * Check the retry status is updated correctly
	 */
	public function test_update_status() {

		// Create a new retry instance, one stored in the DB
		$retry_data = self::$retry_data;
		unset( $retry_data['id'] );
		$retry_id = WCS_Retry_Manager::store()->save( new WCS_Retry( $retry_data ) );
		$retry    = WCS_Retry_Manager::store()->get_retry( $retry_id );

		foreach ( array( 'pending', 'complete', 'failed' ) as $new_status ) {

			$retry->update_status( $new_status );

			// Make sure the status is updated in memory
			$this->assertEquals( $new_status, $retry->get_status() );
			$this->assert_not_equal_to_invalid_data( $retry->get_status() );

			// Make sure the status is updated in the store
			$retry_in_db = WCS_Retry_Manager::store()->get_retry( $retry_id );
			$this->assertEquals( $new_status, $retry_in_db->get_status() );
			$this->assert_not_equal_to_invalid_data( $retry_in_db->get_status() );
		}
	}

	/**
	 * Check the retry has the correct date
	 */
	public function test_get_date() {
		$this->assertEquals( get_date_from_gmt( self::$retry_data['date_gmt'] ), self::$mock_retry->get_date() );
		$this->assert_not_equal_to_invalid_data( self::$mock_retry->get_id() );
	}

	/**
	 * Check the retry has the correct date in UTC/GMT timezone
	 */
	public function test_get_date_gmt() {
		$this->assertEquals( self::$retry_data['date_gmt'], self::$mock_retry->get_date_gmt() );
		$this->assert_not_equal_to_invalid_data( self::$mock_retry->get_id() );
	}

	/**
	 * Check the retry GMT date is updated correctly
	 */
	public function test_update_date_gmt() {

		$retry_data = self::$retry_data;
		unset( $retry_data['id'] );
		$retry_id = WCS_Retry_Manager::store()->save( new WCS_Retry( $retry_data ) );
		$retry    = WCS_Retry_Manager::store()->get_retry( $retry_id );

		for ( $i = 0; $i < 3; $i++ ) {

			$new_date = gmdate( 'Y-m-d H:i:s', strtotime( "+$i days" ) );

			$retry->update_date_gmt( $new_date );

			// Make sure the status is updated in memory
			$this->assertEquals( $new_date, $retry->get_date_gmt() );
			$this->assert_not_equal_to_invalid_data( $retry->get_date_gmt() );

			// Make sure the status is updated in the store
			$retry_in_db = WCS_Retry_Manager::store()->get_retry( $retry_id );
			$this->assertEquals( $new_date, $retry_in_db->get_date_gmt() );
			$this->assert_not_equal_to_invalid_data( $retry_in_db->get_date_gmt() );
		}
	}

	/**
	 * Check the retry returns the correct timestamp
	 */
	public function test_get_time() {
		$this->assertEquals( wcs_date_to_time( self::$retry_data['date_gmt'] ), self::$mock_retry->get_time() );
		$this->assert_not_equal_to_invalid_data( self::$mock_retry->get_id() );
	}

	/**
	 * Check the retry returns the correct rule
	 */
	public function test_get_rule() {
		$rule = new WCS_Retry_Rule( self::$retry_data['rule_raw'] );
		$this->assertEquals( $rule, self::$mock_retry->get_rule() );
		$this->assert_not_equal_to_invalid_data( self::$mock_retry->get_id() );
	}

	/**
	 * Helper function to check a return value against a set of invalid data
	 */
	protected function assert_not_equal_to_invalid_data( $value_to_test ) {
		foreach ( $this->invalid_data as $not_equal_data ) {
			$this->assertNotEquals( $not_equal_data, $value_to_test );
		}
	}
}