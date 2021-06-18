<?php

/**
 * Test suite for the WCS_Retry_Post_Store class
 */
class WCS_Retry_Post_Store_Test extends WCS_Unit_Test_Case {

	protected static $retry_data;
	protected static $store;

	/**
	 * Set of invalid data to check against
	 */
	private $invalid_data = array( 123, '123', false );

	/**
	 * A custom retry store class
	 */
	private $test_retry_store_class = 'WCS_Retry_Post_Store_Test_Lolz';

	public static function setUpBeforeClass() {
		self::$store      = new WCS_Retry_Post_Store();
		self::$retry_data = array(
			'id'       => 0,
			'order_id' => 1235,
			'status'   => 'unique_status',
			'date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 seconds' ) ),
			'rule_raw' => array(
				'retry_after_interval'            => DAY_IN_SECONDS / 2,
				'email_template_customer'         => 'WCS_Unit_Test_Email_Customer',
				'email_template_admin'            => 'WCS_Unit_Test_Email_Admin',
				'status_to_apply_to_order'        => 'unique_status',
				'status_to_apply_to_subscription' => 'unique_status',
			),
		);

		self::$store->init();
	}

	/**
	 * Make sure the 'payment_retry' post type is registered (the only purpose of WCS_Retry_Post_Store::init())
	 *
	 * @return null
	 */
	public function test_init_post_type_registration() {
		$this->assertContains( 'payment_retry', get_post_types() );
	}


	/**
	 * Make sure the 'payment_retry' post type has the correct label and description values. Mainly for codecoverage
	 * as all values are passed through i18n functions.
	 *
	 * @return null
	 */
	public function test_init_post_type_labels_description() {
		$retry_post_type_object = get_post_type_object( 'payment_retry' );

		$this->assertEquals( 'Renewal Payment Retries', $retry_post_type_object->label );
		$this->assertEquals( 'Payment retry posts store details about the automatic retry of failed renewal payments.', $retry_post_type_object->description );

		$this->assertEquals( 'Renewal Payment Retries', $retry_post_type_object->labels->name );
		$this->assertEquals( 'Renewal Payment Retry', $retry_post_type_object->labels->singular_name );
		$this->assertEquals( 'Renewal Payment Retries', $retry_post_type_object->labels->menu_name );

		$this->assertEquals( 'Add', $retry_post_type_object->labels->add_new );
		$this->assertEquals( 'Add New Retry', $retry_post_type_object->labels->add_new_item );

		$this->assertEquals( 'Edit', $retry_post_type_object->labels->edit );
		$this->assertEquals( 'Edit Retry', $retry_post_type_object->labels->edit_item );
		$this->assertEquals( 'New Retry', $retry_post_type_object->labels->new_item );

		$this->assertEquals( 'View Retry', $retry_post_type_object->labels->view );
		$this->assertEquals( 'View Retry', $retry_post_type_object->labels->view_item );

		$this->assertEquals( 'Search Renewal Payment Retries', $retry_post_type_object->labels->search_items );
		$this->assertEquals( 'No retries found', $retry_post_type_object->labels->not_found );
		$this->assertEquals( 'No retries found in trash', $retry_post_type_object->labels->not_found_in_trash );
	}

	/**
	 * Save the details of a retry to the database
	 *
	 * @param WCS_Retry $retry
	 *
	 * @return int the retry's ID
	 */
	public function test_save() {

		$post_id = self::$store->save( new WCS_Retry( self::$retry_data ) );
		$this->assertInternalType( 'int', $post_id );

		// Now assert the post was saved with correct data
		$post = get_post( $post_id );

		$this->assertEquals( 'payment_retry', $post->post_type );
		$this->assertEquals( self::$retry_data['order_id'], $post->post_parent );
		$this->assertEquals( self::$retry_data['status'], $post->post_status );
		$this->assertEquals( self::$retry_data['date_gmt'], $post->post_date_gmt );

		// Finally assert the rule was saved in meta
		foreach ( self::$retry_data['rule_raw'] as $rule_key => $rule_value ) {
			$this->assertEquals( $rule_value, get_post_meta( $post_id, '_rule_' . $rule_key, $rule_value ) );
		}
	}

	/**
	 * Get the details of a retry from the database
	 *
	 * @param int $retry_id
	 *
	 * @return WCS_Retry
	 */
	public function test_get_retry() {

		$expected_retry_data = self::$retry_data;

		// Create a new instance of a retry
		$expected_retry_data['id'] = self::$store->save( new WCS_Retry( self::$retry_data ) );
		$actual_retry              = self::$store->get_retry( $expected_retry_data['id'] );

		$this->check_retry_data( $expected_retry_data, $actual_retry );

		// Check the null return value for an invalid ID
		$this->assertNull( self::$store->get_retry( 'invalid_id' ) );
	}

	/**
	 * Tests delete_retry.
	 */
	public function test_delete_retry() {
		// Create a new instance of a retry
		$retry_id = self::$store->save( new WCS_Retry( self::$retry_data ) );
		self::$store->delete_retry( $retry_id );

		$this->assertNull( self::$store->get_retry( $retry_id ) );
	}

	/**
	 * Get the IDs of all retries from the database for a given order
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function test_get_retry_ids_for_order() {

		$order_id  = 1321;
		$post_ids  = $this->create_mock_retries( array( 'order_id' => $order_id ) );
		$retry_ids = self::$store->get_retry_ids_for_order( $order_id );

		$this->assertNotEmpty( $retry_ids );
		$this->assertEquals( $post_ids, $retry_ids );
	}

	/** Methods Inherited from WCS_Retry_Store **/

	/**
	 * Get the details of all retries (if any) for a given order
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function test_get_retries_for_order() {

		$order_id = 3455;
		$post_ids = $this->create_mock_retries( array( 'order_id' => $order_id ) );
		$retries  = self::$store->get_retries_for_order( $order_id );

		$this->assertNotEmpty( $retries );
		$this->assertEquals( count( $post_ids ), count( $retries ) );

		$expected_retry_data = self::$retry_data;

		foreach ( $post_ids as $post_id ) {

			$expected_retry_data['id']       = $post_id;
			$expected_retry_data['order_id'] = $order_id;

			$this->assertArrayHasKey( $post_id, $retries );
			$this->check_retry_data( $expected_retry_data, $retries[ $post_id ] );
		}
	}

	/**
	 * Get the number of retries stored in the database for a given order
	 */
	public function test_get_retry_count_for_order() {

		$order_id    = 89144;
		$retry_count = rand( 3, 12 );

		$this->create_mock_retries( array( 'order_id' => $order_id, 'number_of_retries' => $retry_count ) );

		$actual_retry_count = self::$store->get_retry_count_for_order( $order_id );

		$this->assertNotFalse( $actual_retry_count );
		$this->assertEquals( $retry_count, $actual_retry_count );
	}

	/**
	 * Get the details of the last retry (if any) recorded for a given order
	 */
	public function test_get_last_retry_for_order() {

		$order_id = 233377;
		$post_ids = $this->create_mock_retries( array( 'order_id' => $order_id, 'number_of_retries' => 5 ) );
		$post_id  = array_pop( $post_ids );

		$actual_last_retry = self::$store->get_last_retry_for_order( $order_id );

		$this->check_retry_data( array_merge( self::$retry_data, array(
			'id'       => $post_id,
			'order_id' => $order_id
		) ), $actual_last_retry );
	}

	/**
	 * Tests get_retries and it's arguments.
	 */
	public function test_get_retries() {
		$order_id_1   = 2509;
		$order_id_2   = 1710;
		$retry_count  = wp_rand( 7, 17 );
		$status       = 'pending';
		$one_year_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-1 year' ) );
		$post_ids = $this->create_mock_retries( array(
			'order_id'          => $order_id_1,
			'number_of_retries' => $retry_count,
			'status'            => $status,
		) );
		$post_ids = $post_ids + $this->create_mock_retries( array(
			'order_id'          => $order_id_2,
			'number_of_retries' => $retry_count,
			'date_gmt'          => $one_year_ago
		) );

		// Validate status arg.
		$retries = self::$store->get_retries();
		$this->assertCount( $retry_count * 2, $retries );

		$retries = self::$store->get_retries( array( 'status' => $status ) );
		foreach ( $retries as $retry ) {
			$this->assertEquals( $status, $retry->get_status() );
		}

		// Validate date_query arg.
		$retries = self::$store->get_retries( array(
			'date_query' => array(
				array(
					'year' => date( 'Y' ) - 1,
				),
			),
		) );
		$this->assertCount( $retry_count, $retries );
		foreach ( $retries as $retry ) {
			$this->assertEquals( $retry->get_date_gmt(), $one_year_ago );
		}

		// Validate order_id arg.
		$retries = self::$store->get_retries( array( 'order_id' => $order_id_2 ) );
		foreach ( $retries as $retry ) {
			$this->assertEquals( $retry->get_order_id(), $order_id_2 );
		}

		// Validate limit arg.
		$retries = self::$store->get_retries( array( 'limit' => 2 ) );
		$this->assertCount( 2, $retries );

		// Validates id param.
		$retries = self::$store->get_retries( array(), 'ids' );
		foreach ( $retries as $retry_id ) {
			$this->assertNotInstanceOf( 'WCS_Retry', $retry_id );
		}
	}

	/**
	 * Check the data on a retry matches what is expected
	 */
	protected function check_retry_data( $expected_retry_data, $actual_retry ) {

		$this->assertInstanceOf( 'WCS_Retry', $actual_retry );
		$this->assertEquals( $expected_retry_data['id'], $actual_retry->get_id() );
		$this->assertEquals( $expected_retry_data['order_id'], $actual_retry->get_order_id() );
		$this->assertEquals( $expected_retry_data['status'], $actual_retry->get_status() );
		$this->assertEquals( get_date_from_gmt( $expected_retry_data['date_gmt'] ), $actual_retry->get_date() );
		$this->assertEquals( $expected_retry_data['date_gmt'], $actual_retry->get_date_gmt() );
		$this->assertEquals( wcs_date_to_time( $expected_retry_data['date_gmt'] ), $actual_retry->get_time() );

		$expected_rule = new WCS_Retry_Rule( $expected_retry_data['rule_raw'] );
		$this->assertEquals( $expected_rule, $actual_retry->get_rule() );
		$this->assertEquals( $expected_retry_data['rule_raw'], $actual_retry->get_rule()->get_raw_data() );

		foreach ( $expected_retry_data as $key => $valid_value ) {

			if ( 'rule_raw' === $key ) {
				$method_name = 'get_rule';
			} else {
				$method_name = 'get_' . $key;
			}

			$actual_value = $actual_retry->$method_name();

			foreach ( $this->invalid_data as $invalid_value ) {
				$this->assertNotEquals( $invalid_value, $actual_value );
			}
		}
	}

	/**
	 * Check the data on a retry matches what is expected
	 *
	 * @param $args array to set custom retry data, especially 'order_id', and also a 'number_of_retries' to control the number created.
	 */
	protected function create_mock_retries( $args ) {

		$args = wp_parse_args( $args, array_merge( self::$retry_data, array( 'number_of_retries' => rand( 3, 12 ) ) ) );

		$retry_post_ids = array();

		for ( $i = 1; $i <= $args['number_of_retries']; $i ++ ) {
			$retry_post_ids[] = self::$store->save( new WCS_Retry( $args ) );
		}

		return $retry_post_ids;
	}
}
