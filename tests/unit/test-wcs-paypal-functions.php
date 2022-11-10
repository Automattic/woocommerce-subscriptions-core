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

	public function test_get_order_id_and_key() {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$parent_order = WCS_Helper_Subscription::create_parent_order( $subscription );
		$paypal_id    = 'I-1234567890';

		$subscription->update_meta_data( '_paypal_subscription_id', $paypal_id );
		$subscription->save();

		// Test retrieving the subscription and key from a PayPal ID.
		$args = array(
			'subscr_id' => $paypal_id,
		);

		$result = WCS_Paypal_Standard_IPN_Handler::get_order_id_and_key( $args, 'shop_subscription' );

		$this->assertEquals( $subscription->get_id(), $result['order_id'] );
		$this->assertEquals( $subscription->get_order_key(), $result['order_key'] );

		// Test retrieving the subscription and key from custom data.
		$args = array(
			'custom' => wcs_json_encode(
				array(
					'order_id'  => $parent_order->get_id(),
					'order_key' => $parent_order->get_order_key(),
				)
			),
		);

		$result = WCS_Paypal_Standard_IPN_Handler::get_order_id_and_key( $args, 'shop_subscription' );

		$this->assertEquals( $subscription->get_id(), $result['order_id'] );
		$this->assertEquals( $subscription->get_order_key(), $result['order_key'] );

		// Test retrieving the parent order and key from custom data.
		$result = WCS_Paypal_Standard_IPN_Handler::get_order_id_and_key( $args, 'shop_order' );

		$this->assertEquals( $parent_order->get_id(), $result['order_id'] );
		$this->assertEquals( $parent_order->get_order_key(), $result['order_key'] );
	}
}
