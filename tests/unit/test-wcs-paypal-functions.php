<?php
/**
 *
 */
class WCS_PayPal_Functions_Tests extends WP_UnitTestCase {

	public function test_get_subscriptions_by_paypal_id() {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$paypal_id    = 'I-1234567890';

		$subscription->update_meta_data( '_paypal_subscription_id', $paypal_id );
		$subscription->save();

		$this->assertEquals( array( $subscription->get_id() => $subscription->get_id() ), WCS_PayPal::get_subscriptions_by_paypal_id( $paypal_id ) );
		$this->assertEquals( array( $subscription->get_id() => $subscription ), WCS_PayPal::get_subscriptions_by_paypal_id( $paypal_id, 'objects' ) );

		// Test that no subscriptions are returned for a non-existent PayPal ID.
		$this->assertEquals( array(), WCS_PayPal::get_subscriptions_by_paypal_id( 'I-1234567891' ) );
	}
}
