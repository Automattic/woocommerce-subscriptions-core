<?php
/**
 * Test Class for includes/wcs-api.php
 *
 * @since 2.0
 * @author Prospress Inc.
 */
class WCS_API_Test extends WCS_API_Unit_Test_Case {

	public function setUp() {
		parent::setUp();

		if ( ! defined( 'WC_API_REQUEST_VERSION' ) ) {
			define( 'WC_API_REQUEST_VERSION', 3 );
		}
	}

	/**
	 * Test WCS_API::includes()
	 *
	 * @dataProvider includes_provider
	 */
	public function test_includes( $input, $expected ) {
		$this->assertEquals( WCS_API::includes( $input ), $expected );
	}

	/**
	 * DataProvider for @see $this->test_includes.
	 *
	 * @return array Returns inputs and the expected values in the format:
	 *		array(
	 *			array( $input, $expected_output ),
	 *			array( $input, $expected_output ),
	 *		)
	 */
	public function includes_provider() {
		return array(
			array( array(), array( 'WC_API_Subscriptions', 'WC_API_Subscriptions_Customers' ) ),
			array( array( 'WC_API_Customers' ), array( 'WC_API_Customers', 'WC_API_Subscriptions', 'WC_API_Subscriptions_Customers' ) ),
			array( array( 'WC_API_Customers', 'WC_API_Orders' ), array( 'WC_API_Customers', 'WC_API_Orders', 'WC_API_Subscriptions', 'WC_API_Subscriptions_Customers' ) ),
		);
	}
}
