<?php

/**
 * Test suite for the WCS_Retry_Rule class
 */
class WCS_Retry_Rule_Test extends WCS_Unit_Test_Case {

	protected static $raw_rule;

	protected static $mock_rule;

	/**
	 * Set of invalid data to check against
	 */
	private $invalid_data = array( 123, '123', false );

	public static function setUpBeforeClass() {

		self::$raw_rule = array(
			'retry_after_interval'            => 60 * 60 * 24 * 2,
			'email_template_customer'         => 'WCS_Unit_Test_Email_Customer',
			'email_template_admin'            => '',
			'status_to_apply_to_order'        => 'unique_status',
			'status_to_apply_to_subscription' => 'unique_status',
		);

		self::$mock_rule = new WCS_Retry_Rule( self::$raw_rule );
	}

	/**
	 * Check the rule returns the correct interval
	 */
	public function test_get_retry_interval() {
		$this->assertEquals( self::$raw_rule['retry_after_interval'], self::$mock_rule->get_retry_interval() );
		$this->assert_not_equal_to_invalid_data( self::$mock_rule->get_retry_interval() );
	}

	/**
	 * Check the rule has the email
	 */
	public function test_has_email_template_admin() {
		$this->assertFalse( self::$mock_rule->has_email_template( 'admin' ) );
		$this->assert_false_for_invalid_data( 'has_email_template', array( 123, '123', true, self::$raw_rule['email_template_customer'] ) );
	}

	/**
	 * Check if this rule has an email template defined for sending to a specified recipient.
	 */
	public function test_has_email_template_customer() {
		$this->assertTrue( self::$mock_rule->has_email_template( 'customer' ) );
		$this->assert_false_for_invalid_data( 'has_email_template' );
	}

	/**
	 * Get the email template this rule defined for sending to a specified recipient.
	 */
	public function test_get_email_template_admin() {
		$this->assertEquals( self::$raw_rule['email_template_admin'], self::$mock_rule->get_email_template( 'admin' ) );
		$this->assert_not_equal_to_invalid_data( self::$mock_rule->get_email_template( 'admin' ), array( 123, '123', true, self::$raw_rule['email_template_customer'] ) );
	}

	/**
	 * Get the email template this rule defined for sending to a specified recipient.
	 */
	public function test_get_email_template_customer() {
		$this->assertEquals( self::$raw_rule['email_template_customer'], self::$mock_rule->get_email_template( 'customer' ) );
		$this->assert_not_equal_to_invalid_data( self::$mock_rule->get_email_template( 'customer' ) );
	}

	/**
	 * Get the status to apply to one of the related objects when this rule is applied.
	 */
	public function test_get_status_to_apply_order() {
		$this->assertEquals( self::$raw_rule['status_to_apply_to_order'], self::$mock_rule->get_status_to_apply( 'order' ) );
		$this->assert_not_equal_to_invalid_data( self::$mock_rule->get_status_to_apply( 'order' ) );
	}

	/**
	 * Get the status to apply to one of the related objects when this rule is applied.
	 */
	public function test_get_status_to_apply_subscription() {
		$this->assertEquals( self::$raw_rule['status_to_apply_to_subscription'], self::$mock_rule->get_status_to_apply( 'subscription' ) );
		$this->assert_not_equal_to_invalid_data( self::$mock_rule->get_status_to_apply( 'subscription' ) );
	}

	/**
	 * Get rule data as a raw array.
	 */
	public function test_get_raw_data() {
		$this->assertEquals( self::$raw_rule, self::$mock_rule->get_raw_data() );
		$this->assert_not_equal_to_invalid_data( self::$mock_rule->get_raw_data() );
	}

	/**
	 * Helper function to check a return value against a set of invalid data
	 */
	protected function assert_not_equal_to_invalid_data( $value_to_test, $invalid_data = array() ) {

		$invalid_data = empty( $invalid_data ) ? $this->invalid_data : $invalid_data;

		foreach ( $invalid_data as $not_equal_data ) {
			$this->assertNotEquals( $not_equal_data, $value_to_test );
		}
	}

	/**
	 * Helper function to check a return value against a set of invalid data
	 */
	protected function assert_false_for_invalid_data( $method_to_test, $invalid_data = array() ) {

		$invalid_data = empty( $invalid_data ) ? $this->invalid_data : $invalid_data;

		foreach ( $invalid_data as $not_equal_data ) {
			$this->assertFalse( self::$mock_rule->$method_to_test( $not_equal_data ) );
		}
	}
}