<?php

/**
 * Test suite for the WCS_Customer_Store class
 */
class WCS_Customer_Store_Test extends WCS_Base_Customer_Store_Test_Case {

	/**
	 * Make sure calling WCS_Customer_Store::instance() returns a valid WCS_Customer_Store.
	 */
	public function test_default_instance_class() {
		$this->assertInstanceOf( 'WCS_Customer_Store_Cached_CPT', WCS_Customer_Store::instance() );
	}

	/**
	 * Make sure the 'wcs_customer_store_class' filter is applied to the instance
	 */
	public function test_instance_filter() {
		$this->clear_instance();
		add_filter( 'wcs_customer_store_class', [ $this, 'get_instance_class' ] );
		$this->assertInstanceOf( $this->get_instance_class(), WCS_Customer_Store::instance() );
		remove_filter( 'wcs_customer_store_class', [ $this, 'get_instance_class' ] );
		$this->clear_instance();
	}

	/**
	 * Make sure the 'wcs_customer_store_class' filter is applied to the instance
	 */
	public function get_instance_class() {
		return 'WCS_Mock_Customer_Data_Store';
	}

	/**
	 * Make sure calling WCS_Customer_Store::instance() before plugins loaded is called
	 * triggers an error.
	 *
	 * @expectedIncorrectUsage WCS_Customer_Store::instance
	 */
	public function test_instance_doing_it_wrong() {
		global $wp_actions;

		$this->clear_instance();

		$doing_it_wrong_run_count_original = did_action( 'doing_it_wrong_run' );

		// Remove the 'plugins_loaded' action so that did_action() thinks it hasn't been triggered yet
		$wp_actions_original = $wp_actions;
		unset( $wp_actions['plugins_loaded'] );

		WCS_Customer_Store::instance();

		// Make sure the 'doing_it_wrong_run' hook has been triggered
		$this->assertGreaterThan( $doing_it_wrong_run_count_original, did_action( 'doing_it_wrong_run' ) );

		// Restore the 'plugins_load' action count to avoid messing with any other code
		$wp_actions['plugins_loaded'] = $wp_actions_original['plugins_loaded'];
	}

	/**
	 * Make sure matter how many times WCS_Customer_Store::instance() is called,
	 * WCS_Customer_Store->init() is only called once.
	 */
	public function test_instance_init() {
		$this->clear_instance();

		add_filter( 'wcs_customer_store_class', [ $this, 'get_instance_class' ] );

		$this->assertInstanceOf( $this->get_instance_class(), WCS_Customer_Store::instance() );

		for( $i = 1; $i < 4; $i++ ) {
			WCS_Customer_Store::instance();
			$this->assertEquals( 1, WCS_Customer_Store::instance()->get_init_call_count() );
		}

		remove_filter( 'wcs_customer_store_class', [ $this, 'get_instance_class' ] );

		$this->clear_instance();
	}
}
