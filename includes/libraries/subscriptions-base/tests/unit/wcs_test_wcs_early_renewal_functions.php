<?php

class WCS_Early_Renewal_Functions_Unit_Tests extends WCS_Unit_Test_Case {
	/**
	 * @covers ::wcs_get_last_non_early_renewal_order
	 */
	public function test_wcs_get_last_non_early_renewal_order() {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$orders       = array();

		// Create some orders related to subscription.
		for ( $i = 1; $i <= 6; $i++ ) {
			$renewal_order = WCS_Helper_Subscription::create_renewal_order( $subscription );
			$renewal_order->set_date_created( wcs_date_to_time( '2017-01-0' . $i ) );
			$renewal_order->save();
			$orders[] = $renewal_order->get_id();
		}

		$last_order        = wc_get_order( $orders[ count( $orders ) - 1 ] );
		$penultimate_order = wc_get_order( $orders[ count( $orders ) - 2 ] );

		$this->assertEquals( $last_order->get_id(), wcs_get_last_non_early_renewal_order( $subscription )->get_id() );

		// Mark last order as early renewal.
		$last_order->update_meta_data( '_subscription_renewal_early', $subscription->get_id() );
		$last_order->save();

		$this->assertNotEquals( $last_order->get_id(), wcs_get_last_non_early_renewal_order( $subscription )->get_id() );
		$this->assertEquals( $penultimate_order->get_id(), wcs_get_last_non_early_renewal_order( $subscription )->get_id() );
	}
}
