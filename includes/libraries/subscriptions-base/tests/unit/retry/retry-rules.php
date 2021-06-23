<?php

/**
 * Test suite for the WCS_Retry_Rules class
 */
class WCS_Retry_Rules_Test extends WCS_Unit_Test_Case {

	/**
	 * Set of invalid data to check against
	 */
	private $invalid_data = array( 123, '123', 'asdf' );

	private $mock_order_id = 101;

	protected static $retry_rules_object;

	protected static $default_retry_rules;

	public static function setUpBeforeClass() {

		self::$retry_rules_object = new WCS_Retry_Rules();

		self::$default_retry_rules = array(
			array(
				'retry_after_interval'            => DAY_IN_SECONDS / 2,
				'email_template_customer'         => '',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
			array(
				'retry_after_interval'            => DAY_IN_SECONDS / 2,
				'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
			array(
				'retry_after_interval'            => DAY_IN_SECONDS,
				'email_template_customer'         => '',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
			array(
				'retry_after_interval'            => DAY_IN_SECONDS * 2,
				'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
			array(
				'retry_after_interval'            => DAY_IN_SECONDS * 3,
				'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
		);
	}

	/**
	 * Check valid retry rules exist for known defaults
	 */
	public function test_has_rule() {

		foreach ( self::$default_retry_rules as $rule_index => $rule ) {
			$this->assertTrue( self::$retry_rules_object->has_rule( $rule_index, $this->mock_order_id ) );
		}

		foreach ( $this->invalid_data as $not_equal_data ) {
			$this->assertFalse( self::$retry_rules_object->has_rule( $not_equal_data, $this->mock_order_id ) );
		}
	}

	/**
	 * Check the retry rules for known defaults
	 */
	public function test_get_rule() {

		foreach ( self::$default_retry_rules as $expected_rule_index => $expected_rule ) {
			$actual_rule = self::$retry_rules_object->get_rule( $expected_rule_index, $this->mock_order_id );
			$this->assertEquals( new WCS_Retry_Rule( $expected_rule ), $actual_rule );
			$this->assertNotEquals( $expected_rule, $actual_rule );

			foreach ( $this->invalid_data as $not_equal_data ) {
				$this->assertNotEquals( $not_equal_data, $actual_rule );
			}
		}

		$this->assertNull( self::$retry_rules_object->get_rule( 101, $this->mock_order_id ) );
	}

	/**
	 * Check the default retry rule class is correct
	 */
	public function test_get_rule_class() {
		$this->assertEquals( 'WCS_Retry_Rule', self::$retry_rules_object->get_rule_class() );

		foreach ( $this->invalid_data as $not_equal_data ) {
			$this->assertNotEquals( $not_equal_data, self::$retry_rules_object->get_rule_class() );
		}
	}
}