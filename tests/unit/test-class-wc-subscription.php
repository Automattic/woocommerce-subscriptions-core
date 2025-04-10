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

	public function test_get_paginated_related_orders(): void {
		$subscription = WCS_Helper_Subscription::create_subscription(
			array(
				'status'                  => 'active',
				'requires_manual_renewal' => true,
			)
		);

		// We create a mix of renewal and switch orders partly to provide a more authentic test, and also because these
		// are cached separately from one another (and we want to ensure we are fetching from both in our tests).
		for ( $i = 0; $i < 7; $i++ ) {
			$i % 2
				? WCS_Helper_Subscription::create_renewal_order( $subscription )
				: WCS_Helper_Subscription::create_switch_order( $subscription );
		}

		$all_related_orders        = $subscription->get_related_orders();
		$zero_related_orders       = $subscription->get_paginated_related_orders( 'ids', array( 'renewal', 'switch' ), 1, 0 )->orders;
		$all_orders_page_1_limit_4 = $subscription->get_paginated_related_orders( 'ids', array( 'parent', 'renewal', 'switch' ), 1, 4 )->orders;
		$all_orders_page_2_limit_4 = $subscription->get_paginated_related_orders( 'ids', array( 'parent', 'renewal', 'switch' ), 2, 4 )->orders;
		$renewal_orders_only       = $subscription->get_paginated_related_orders( 'ids', 'renewal' )->orders;

		$this->assertCount( 0, $zero_related_orders, 'No orders were returned when requesting page 1 of 0 related orders.' );
		$this->assertCount( 7, $all_related_orders, 'A total of 7 orders exist in relation to the test subscription.' );
		$this->assertCount( 4, $all_orders_page_1_limit_4, 'First 4 related orders successfully retrieved (page 1).' );
		$this->assertCount( 3, $all_orders_page_2_limit_4, 'Final 3 related orders successfully retrieved (page 2).' );
		$this->assertCount( 3, $renewal_orders_only, 'All 3 renewal orders were successfully fetched.' );
	}

	public function test_get_paginated_related_orders_with_invalid_page_number(): void {
		$this->setExpectedIncorrectUsage( WC_Subscription::class . '::get_paginated_related_orders' );
		WCS_Helper_Subscription::create_subscription()->get_paginated_related_orders( 'ids', array( 'switch' ), 0 );
	}

	public function test_get_paginated_related_orders_with_invalid_limit(): void {
		$this->setExpectedIncorrectUsage( WC_Subscription::class . '::get_paginated_related_orders' );
		WCS_Helper_Subscription::create_subscription()->get_paginated_related_orders( 'ids', array( 'switch' ), 1, -2 );
	}
}
