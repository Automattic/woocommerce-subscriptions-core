<?php

/**
 * @since 2.4.3
 */
class WCS_Order_Functions_Test extends WP_UnitTestCase {

	public function test_find_matching_line_item() {
		$order        = WC_Helper_Order::create_order();
		$subscription = WCS_Helper_Subscription::create_subscription();

		foreach ( $order->get_items() as $item ) {
			// Validate falsy cases.
			$this->assertFalse( wcs_find_matching_line_item( $subscription, $item ) );

			// Add item to subscription.
			$subscription->add_item( $item );
			$subscription->save();

			break; // Lets just add one item.
		}

		foreach ( $subscription->get_items() as $subscription_item ) {
			$this->assertEquals( wcs_find_matching_line_item( $order, $subscription_item ), wcs_find_matching_line_item( $subscription, $subscription_item ) );
			$this->assertEquals( wcs_find_matching_line_item( $order, $subscription_item, 'match_product_ids' ), wcs_find_matching_line_item( $subscription, $subscription_item, 'match_product_ids' ) );
		}
	}
	/**
	 * Testing get_subscriptions_for_order
	 *
	 * @group order-functions
	 * @since 2.0
	 */
	public function test_get_subscriptions_for_order() {
		// no subscription (just a standard order)
		$order = wc_create_order();

		$this->assertEquals( array(), wcs_get_subscriptions_for_order( wcs_get_objects_property( $order, 'id' ) ) );

		// test get subscriptions given a parent order
		$subscription = WCS_Helper_Subscription::create_subscription();
		wp_update_post(
			array(
				'ID'          => $subscription->get_id(),
				'post_parent' => wcs_get_objects_property(
					$order,
					'id'
				),
			)
		);
		$subscription = wcs_get_subscription( $subscription );

		$tests = array(
			null,
			array( 'order_type' => array( 'parent' ) ),
			array( 'order_type' => array( 'parent', 'renewal' ) ),
			array( 'order_type' => array( 'parent', 'switched', 'renewal' ) ),
			array( 'order_type' => array( 'parent', 'renewal', 'rubbish' ) ),
			array( 'order_type' => array( 'any' ) ),
		);

		foreach ( $tests as $input ) {
			$result = wcs_get_subscriptions_for_order( wcs_get_objects_property( $order, 'id' ), $input );
			$this->assertEquals( array( $subscription->get_id() => $subscription ), $result );
		}

		$renewal      = WCS_Helper_Subscription::create_renewal_order( $subscription );
		$subscription = wcs_get_subscription( $subscription );

		// passing a renewal order
		$tests = array(
			array( 'order_type' => array( 'renewal' ) ),
			array( 'order_type' => array( 'renewal', 'parent' ) ),
			array( 'order_type' => array( 'parent', 'switched', 'renewal' ) ),
			array( 'order_type' => array( 'parent', 'renewal', 'rubbish' ) ),
			array( 'order_type' => array( 'any' ) ),
		);

		foreach ( $tests as $input ) {
			$result = wcs_get_subscriptions_for_order( wcs_get_objects_property( $renewal, 'id' ), $input );
			$this->assertEquals( array( $subscription->get_id() => $subscription ), $result );
		}

		// tests that should not get any results from wcs_get_subscriptions_for_order()

		// original order
		$this->assertEquals( array(), wcs_get_subscriptions_for_order( wcs_get_objects_property( $order, 'id' ), array( 'order_type' => array( 'renewal' ) ) ) );
		$this->assertEquals( array(), wcs_get_subscriptions_for_order( wcs_get_objects_property( $order, 'id' ), array( 'order_type' => array( 'switch' ) ) ) );
		$this->assertEquals( array(), wcs_get_subscriptions_for_order( wcs_get_objects_property( $order, 'id' ), array( 'order_type' => array( 'renewal', 'switch' ) ) ) );

		// renewal order
		$this->assertEquals( array(), wcs_get_subscriptions_for_order( wcs_get_objects_property( $renewal, 'id' ) ) );
		$this->assertEquals( array(), wcs_get_subscriptions_for_order( wcs_get_objects_property( $renewal, 'id' ) ) );
		$this->assertEquals( array(), wcs_get_subscriptions_for_order( wcs_get_objects_property( $renewal, 'id' ), array( 'order_type' => array( 'switch' ) ) ) );
		$this->assertEquals( array(), wcs_get_subscriptions_for_order( wcs_get_objects_property( $renewal, 'id' ), array( 'order_type' => array( 'parent', 'switch' ) ) ) );
	}
}
