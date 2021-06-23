<?php

class WCS_Object_Sorter_Test extends WCS_Unit_Test_Case {
	/**
	 * @var WC_Order[]
	 */
	protected $orders = array();

	public function setUp() {
		for ( $i = 1; $i <= 2; $i++ ) {
			$this->orders[] = WC_Helper_Order::create_order( $i );
		}

		$this->assertGreaterThan( $this->orders[0]->get_customer_id(), $this->orders[1]->get_customer_id() );
	}

	/**
	 * Asserts calls that should return 0.
	 */
	public function test_ZERO_conditions() {
		$this->assertEquals( 0, $this->get_instance( 'customer_id' )->ascending_compare( $this->orders[0], array() ) );
		$this->assertEquals( 0, $this->get_instance( '' )->ascending_compare( $this->orders[0], $this->orders[1] ) );
		$this->assertEquals( 0, $this->get_instance( 'NON_EXISTING_PROPERTY_OR_CALLABLE_FUNCTION' )->ascending_compare( $this->orders[0], $this->orders[1] ) );
		$this->assertEquals( 0, $this->get_instance( 'customer_id' )->ascending_compare( $this->orders[0], $this->orders[0] ) );
		$this->assertEquals( 0, $this->get_instance( 'customer_id' )->descending_compare( $this->orders[1], $this->orders[1] ) );
	}

	/**
	 * @covers WCS_Object_Sorter::ascending_compare
	 */
	public function test_ascending_compare() {
		// 1 Conditions.
		$this->assertEquals( 1, $this->get_instance( 'customer_id' )->ascending_compare( $this->orders[1], $this->orders[0] ) );

		// -1 Conditions.
		$this->assertEquals( -1, $this->get_instance( 'customer_id' )->ascending_compare( $this->orders[0], $this->orders[1] ) );
	}

	/**
	 * @covers WCS_Object_Sorter::descending_compare
	 */
	public function test_descending_compare() {
		// 1 Conditions.
		$this->assertEquals( 1, $this->get_instance( 'customer_id' )->descending_compare( $this->orders[0], $this->orders[1] ) );

		// -1 Conditions.
		$this->assertEquals( -1, $this->get_instance( 'customer_id' )->descending_compare( $this->orders[1], $this->orders[0] ) );
	}

	/**
	 * Test error condition.
	 * @expectedException ArgumentCountError
	 * @requires PHP 7.1.0
	 */
	public function test_error_conditions() {
		$this->get_instance( 'customer_id' )->descending_compare( $this->orders[1] );
	}

	/**
	 * @param string $property
	 *
	 * @return WCS_Object_Sorter
	 */
	private function get_instance( $property ) {
		return new WCS_Object_Sorter( $property );
	}
}
