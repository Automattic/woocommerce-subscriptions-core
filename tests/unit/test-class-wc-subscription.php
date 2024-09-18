<?php

/**
 * Class: WC_Subscription_Test
 */
class WC_Subscription_Test extends WP_UnitTestCase {
	/**
	 * Test for `update_status` method.
	 *
	 * @param string $from Subscription status before update.
	 * @param string $to Subscription status after update.
	 * @param array $expected Expected values after update.
	 * @return void
	 * @dataProvider provide_test_update_status
	 * @throws Exception If the subscription status is invalid.
	 * @group test_update_status
	 */
	public function test_update_status( $from, $to, $expected ) {
		$subscription = WCS_Helper_Subscription::create_subscription(
			array(
				'status'                  => $from,
				'requires_manual_renewal' => true, // Required to allow the subscription status to be updated.
			)
		);
		$subscription->update_dates( [ 'end' => gmdate( 'Y-m-d H:i:s', wcs_add_months( time(), 1 ) ) ] );
		$subscription->update_status( $to );

		foreach ( $expected as $data_key => $expected_value ) {
			$this->assertEquals( $expected_value, $subscription->{ 'get_' . $data_key }() );
		}
	}

	/**
	 * Provider for `test_update_status` method.
	 *
	 * @return array
	 */
	public function provide_test_update_status() {
		return array(
			'pending-cancel => active' => array(
				'from'     => 'pending-cancel',
				'to'       => 'active',
				'expected' => array(
					'cancelled_email_sent' => 'false',
				),
			),
		);
	}
}
