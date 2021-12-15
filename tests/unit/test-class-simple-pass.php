<?php
/**
 * Class Simple_Pass_Test
 *
 * @package WooCommerce\SubscriptionsCore\Tests
 */

use WCPay\Payment_Information;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Payment_Initiated_By;
use WCPay\Constants\Payment_Capture_Type;
use WCPay\Payment_Methods\Sepa_Payment_Gateway;
use WCPay\Payment_Methods\CC_Payment_Gateway;

/**
 * Simple_Pass_Test unit tests.
 */
class Simple_Pass_Test extends WP_UnitTestCase {

	public function test_always_pass() {
		$this->assertTrue( true );
	}
}
