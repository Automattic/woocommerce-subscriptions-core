<?php
/**
 * Class WCS_Related_Order_Store_Test
 *
 * @package WooCommerce\SubscriptionsCore\Tests
 */

/**
 * Test suite for the WCS_Related_Order_Store class
 */
class WCS_Related_Order_Store_Test extends WCS_Base_Related_Order_Store_Test_Case {

	/**
	 * Make sure calling WCS_Related_Order_Store::instance() returns a valid WCS_Related_Order_Store.
	 */
	public function test_default_instance_class() {
		$this->assertInstanceOf( 'WCS_Related_Order_Store_Cached_CPT', WCS_Related_Order_Store::instance() );
	}

	/**
	 * Make sure the 'wcs_related_order_store_class' filter is applied to the instance
	 */
	public function test_instance_filter() {
		$this->clear_related_order_store_instance();
		add_filter( 'wcs_related_order_store_class', [ $this, 'get_instance_class' ] );
		$this->assertInstanceOf( $this->get_instance_class(), WCS_Related_Order_Store::instance() );
		remove_filter( 'wcs_related_order_store_class', [ $this, 'get_instance_class' ] );
		$this->clear_related_order_store_instance();
	}

	/**
	 * Make sure the 'wcs_related_order_store_class' filter is applied to the instance and
	 * return our mock class (WCS_Mock_Related_Order_Store)
	 */
	public function get_instance_class() {
		return 'WCS_Mock_Related_Order_Store';
	}

	/**
	 * Make sure calling WCS_Related_Order_Store::instance() before plugins loaded is called
	 * triggers an error.
	 *
	 * @expectedIncorrectUsage WCS_Related_Order_Store::instance
	 */
	public function test_instance_doing_it_wrong() {
		global $wp_actions;

		$this->clear_related_order_store_instance();

		$doing_it_wrong_run_count_original = did_action( 'doing_it_wrong_run' );

		// Remove the 'plugins_loaded' action so that did_action() thinks it hasn't been triggered yet
		$wp_actions_original = $wp_actions;
		unset( $wp_actions['plugins_loaded'] );

		WCS_Related_Order_Store::instance();

		// Make sure the 'doing_it_wrong_run' hook has been triggered
		$this->assertGreaterThan( $doing_it_wrong_run_count_original, did_action( 'doing_it_wrong_run' ) );

		// Restore the 'plugins_load' action count to avoid messing with any other code
		$wp_actions['plugins_loaded'] = $wp_actions_original['plugins_loaded'];
	}

	/**
	 * Make sure matter how many times WCS_Related_Order_Store::instance() is called,
	 * WCS_Related_Order_Store->init() is only called once.
	 */
	public function test_instance_init() {

		$this->clear_related_order_store_instance();

		add_filter( 'wcs_related_order_store_class', [ $this, 'get_instance_class' ] );

		$this->assertInstanceOf( $this->get_instance_class(), WCS_Related_Order_Store::instance() );

		for( $i = 1; $i < 4; $i++ ) {
			WCS_Related_Order_Store::instance();
			$this->assertEquals( 1, WCS_Related_Order_Store::instance()->get_init_call_count() );
		}

		remove_filter( 'wcs_related_order_store_class', [ $this, 'get_instance_class' ] );

		$this->clear_related_order_store_instance();
	}

	/**
	 * Check WCS_Related_Order_Store->check_relation_type() does not throw an exception
	 * for valid relation types.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_get_relation_types( $expected_relation_type ) {
		$returned_relation_types = WCS_Related_Order_Store::instance()->get_relation_types();
		$this->assertTrue( in_array( $expected_relation_type, $returned_relation_types ) );
	}

	/**
	 * Check WCS_Related_Order_Store->check_relation_type() does not throw an exception
	 * for valid relation types.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_check_relation_type_valid( $relation_type ) {
		$mock_related_order_store = $this->getMockForAbstractClass( 'WCS_Related_Order_Store' );
		
		$this->assertNull(
			PHPUnit_Utils::call_method(
				$mock_related_order_store,
				'check_relation_type',
				[ $relation_type ]
			)
		);
	}

	/**
	 * Check WCS_Related_Order_Store->check_relation_type() throws an exception for invalid relation types.
	 *
	 * @dataProvider provider_relation_type
	 * @expectedException InvalidArgumentException
	 */
	public function test_check_relation_type_invalid( $relation_type ) {
		$invalid_relation_type    = sprintf( '%s_invalid_test', $relation_type );
		$mock_related_order_store = $this->getMockForAbstractClass( 'WCS_Related_Order_Store' );

		$this->assertNull(
			PHPUnit_Utils::call_method(
				$mock_related_order_store,
				'check_relation_type',
				[ $invalid_relation_type ]
			)
		);
	}
}
