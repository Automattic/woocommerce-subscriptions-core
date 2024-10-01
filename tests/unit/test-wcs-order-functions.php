<?php

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
	 */
	public function test_get_subscriptions_for_order() {
		// no subscription (just a standard order)
		$order = wc_create_order();

		$this->assertEquals( array(), wcs_get_subscriptions_for_order( wcs_get_objects_property( $order, 'id' ) ) );

		// test get subscriptions given a parent order
		$subscription = WCS_Helper_Subscription::create_subscription();
		$subscription->set_parent_id( $order->get_id() );
		$subscription->save();

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

	/**
	 * Tests for wcs_get_subscription_orders()
	 */
	public function test_wcs_get_subscription_orders() {
		$subscription   = WCS_Helper_Subscription::create_subscription();
		$subscription_2 = WCS_Helper_Subscription::create_subscription();

		// Create some orders related to the subscription and not.
		$parent_order  = WCS_Helper_Subscription::create_parent_order( $subscription );
		$renewal_order = WCS_Helper_Subscription::create_renewal_order( $subscription );
		$switch_order  = WCS_Helper_Subscription::create_switch_order( $subscription );

		$parent_order_2  = WCS_Helper_Subscription::create_parent_order( $subscription_2 );
		$renewal_order_2 = WCS_Helper_Subscription::create_renewal_order( $subscription_2 );
		$renewal_order_3 = WCS_Helper_Subscription::create_renewal_order( $subscription_2 );

		// This order should never be returned.
		$order = wc_create_order(); // no subscription relation (just a standard order) to test the negative case.

		$parent_orders = wcs_get_subscription_orders( 'ids', 'parent' );

		$this->assertEquals( 2, count( $parent_orders ) );
		$this->assertArrayHasKey( $parent_order->get_id(), $parent_orders );
		$this->assertArrayHasKey( $parent_order_2->get_id(), $parent_orders );
		$this->assertArrayNotHasKey( $order->get_id(), $parent_orders );

		$renewal_orders = wcs_get_subscription_orders( 'ids', 'renewal' );

		$this->assertEquals( 3, count( $renewal_orders ) );
		$this->assertArrayHasKey( $renewal_order->get_id(), $renewal_orders );
		$this->assertArrayHasKey( $renewal_order_2->get_id(), $renewal_orders );
		$this->assertArrayHasKey( $renewal_order_3->get_id(), $renewal_orders );
		$this->assertArrayNotHasKey( $order->get_id(), $renewal_orders );

		$switch_orders = wcs_get_subscription_orders( 'ids', 'switch' );

		$this->assertEquals( 1, count( $switch_orders ) );
		$this->assertArrayHasKey( $switch_order->get_id(), $switch_orders );
		$this->assertArrayNotHasKey( $order->get_id(), $switch_orders );

		$all_orders = wcs_get_subscription_orders( 'ids', 'any' );

		$this->assertEquals( 6, count( $all_orders ) );
		$this->assertArrayHasKey( $parent_order->get_id(), $all_orders );
		$this->assertArrayHasKey( $parent_order_2->get_id(), $all_orders );
		$this->assertArrayHasKey( $renewal_order->get_id(), $all_orders );
		$this->assertArrayHasKey( $renewal_order_2->get_id(), $all_orders );
		$this->assertArrayHasKey( $renewal_order_3->get_id(), $all_orders );
		$this->assertArrayHasKey( $switch_order->get_id(), $all_orders );
		$this->assertArrayNotHasKey( $order->get_id(), $all_orders );
	}

	/**
	 * Test the wcs_get_orders_with_meta_query behavior.
	 */
	public function test_wcs_get_orders_with_meta_query() {

		$subscription_on_hold = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => WC_Subscription::STATUS_ON_HOLD,
			)
		);

		$subscription_active = WCS_Helper_Subscription::create_subscription(
			array(
				'status' => WC_Subscription::STATUS_ACTIVE,
			)
		);

		$order_pending = wc_create_order(
			array(
				'status' => WCS_Order_Status::PENDING,
			)
		);

		$order_on_hold = wc_create_order(
			array(
				'status' => WCS_Order_Status::ON_HOLD,
			)
		);

		$order_complete = wc_create_order(
			array(
				'status' => WCS_Order_Status::COMPLETED,
			)
		);

		// On-hold - a status used by both Orders and Subscriptions
		$subscriptions = wcs_get_orders_with_meta_query(
			array(
				'return' => 'ids',
				'type'   => 'shop_subscription',
				'status' => WC_Subscription::STATUS_ON_HOLD,
			)
		);
		$this->assertIsArray( $subscriptions );
		$this->assertEquals( [ $subscription_on_hold->get_id() ], $subscriptions );

		// Active - a subscriptions only status.
		$subscriptions = wcs_get_orders_with_meta_query(
			array(
				'return' => 'ids',
				'type'   => 'shop_subscription',
				'status' => 'wc-active',
			)
		);

		$this->assertIsArray( $subscriptions );
		$this->assertEquals( [ $subscription_active->get_id() ], $subscriptions );

		// Any status with type set to shop_subscription should return all subscriptions with all statuses.
		$subscriptions = wcs_get_orders_with_meta_query(
			array(
				'return' => 'ids',
				'type'   => 'shop_subscription',
				'status' => 'any',
			)
		);

		$this->assertIsArray( $subscriptions );
		sort( $subscriptions );
		$this->assertEquals(
			[
				$subscription_on_hold->get_id(),
				$subscription_active->get_id(),
			],
			$subscriptions
		);

		// Verify that we aren't modifying queries without type set, which should only return orders with any order statuses.
		$orders = wcs_get_orders_with_meta_query(
			array(
				'return' => 'ids',
				'status' => 'any',
			)
		);

		$this->assertIsArray( $orders );
		sort( $orders );
		$this->assertEquals(
			[
				$order_pending->get_id(),
				$order_on_hold->get_id(),
				$order_complete->get_id(),
			],
			$orders
		);

		$is_hpos_enabled = wcs_is_custom_order_tables_usage_enabled();

		// An invalid status
		$subscriptions = wcs_get_orders_with_meta_query(
			array(
				'return' => 'ids',
				'type'   => 'shop_subscription',
				'status' => 'rubbish',
			)
		);

		if ( $is_hpos_enabled ) {
			// No subscriptions should match the invalid status.
			$this->assertIsArray( $subscriptions );
			$this->assertEmpty( $subscriptions );
		} else {
			// In non-HPOS environments, WP_Query simply ignores invalid post_stati, so no clause would be applied.
			$this->assertIsArray( $subscriptions );
			sort( $subscriptions );
			$this->assertEquals(
				[
					$subscription_on_hold->get_id(),
					$subscription_active->get_id(),
				],
				$subscriptions
			);
		}

		// An invalid status is ignored and does not apply as a clause to the query, while the valid, active status still applies.
		$subscriptions = wcs_get_orders_with_meta_query(
			array(
				'return' => 'ids',
				'type'   => 'shop_subscription',
				'status' => [ 'rubbish', 'wc-active' ],
			)
		);

		$this->assertIsArray( $subscriptions );
		$this->assertEquals(
			[
				$subscription_active->get_id(),
			],
			$subscriptions
		);

		// An empty status
		$subscriptions = wcs_get_orders_with_meta_query(
			array(
				'return' => 'ids',
				'type'   => 'shop_subscription',
				'status' => '',
			)
		);

		if ( $is_hpos_enabled ) {
			// In HPOS environments, WooCommerce core will convert an empty `status` to all valid statuses, the equivalent of
			// setting status = 'any'
			$this->assertIsArray( $subscriptions );
			sort( $subscriptions );
			$this->assertEquals(
				[
					$subscription_on_hold->get_id(),
					$subscription_active->get_id(),
				],
				$subscriptions
			);
		} else {
			// In non-HPOS environments, WP_Query will set an empty post_status argument to `publish`.
			$this->assertIsArray( $subscriptions );
			$this->assertEmpty( $subscriptions );
		}
	}

	public function test_wcs_set_recurring_item_total() {
		/**
		 * Regular subscription item.
		 */
		$product = WCS_Helper_Product::create_simple_subscription_product( [ 'price' => 10 ] );

		$line_item = new WC_Order_Item_Product();
		$line_item->set_product( $product );
		$line_item->set_quantity( 1 );
		$line_item->set_total( 10 );
		$line_item->set_subtotal( 10 );

		wcs_set_recurring_item_total( $line_item );

		$this->assertEquals( 10, $line_item->get_total() );
		$this->assertEquals( 10, $line_item->get_subtotal() );

		/**
		 * Subscription item with trial.
		 */
		$trial_product = WCS_Helper_Product::create_simple_subscription_product(
			[
				'price'                     => 20,
				'subscription_trial_length' => 10,
			]
		);

		$line_item = new WC_Order_Item_Product();
		$line_item->set_product( $trial_product );
		$line_item->set_quantity( 1 );
		$line_item->set_total( 0 ); // Trial product's are free initially.
		$line_item->set_subtotal( 0 );

		wcs_set_recurring_item_total( $line_item );

		$this->assertEquals( 20, $line_item->get_total() );
		$this->assertEquals( 20, $line_item->get_subtotal() );

		/**
		 * Subscription item with sign-up fee.
		 */
		$sign_up_fee_product = WCS_Helper_Product::create_simple_subscription_product(
			[
				'price'                    => 30,
				'subscription_sign_up_fee' => 50,
			]
		);

		$line_item = new WC_Order_Item_Product();
		$line_item->set_product( $sign_up_fee_product );
		$line_item->set_quantity( 1 );
		$line_item->set_total( 80 ); // Initial total is the sum of the product price and sign-up fee.
		$line_item->set_subtotal( 80 );

		wcs_set_recurring_item_total( $line_item );

		$this->assertEquals( 30, $line_item->get_total() );
		$this->assertEquals( 30, $line_item->get_subtotal() );

		/**
		 * Subscription item with sign-up fee and trial.
		 */
		$sign_up_fee_trial_product = WCS_Helper_Product::create_simple_subscription_product(
			[
				'price'                     => 40,
				'subscription_sign_up_fee'  => 60,
				'subscription_trial_length' => 10,
			]
		);

		$line_item->set_product( $sign_up_fee_trial_product );
		$line_item->set_quantity( 1 );
		$line_item->set_total( 60 ); // Initial total is just the sign-up fee.
		$line_item->set_subtotal( 60 );

		wcs_set_recurring_item_total( $line_item );

		$this->assertEquals( 40, $line_item->get_total() );
		$this->assertEquals( 40, $line_item->get_subtotal() );

		/**
		 * Simple product
		 */
		$simple_product = WC_Helper_Product::create_simple_product();

		$line_item->set_product( $simple_product );
		$line_item->set_quantity( 1 );
		$line_item->set_total( 50 ); // Default price is $10.00. We set it to $50 here to confirm it's not changed.
		$line_item->set_subtotal( 50 );

		wcs_set_recurring_item_total( $line_item );

		$this->assertEquals( 50, $line_item->get_total() );
		$this->assertEquals( 50, $line_item->get_subtotal() );

		/**
		 * Subscription item with quantity.
		 */
		$sign_up_fee_trial_product = WCS_Helper_Product::create_simple_subscription_product(
			[
				'price'                     => 40,
				'subscription_sign_up_fee'  => 60,
				'subscription_trial_length' => 10,
			]
		);

		$line_item->set_product( $sign_up_fee_trial_product );
		$line_item->set_quantity( 2 );
		$line_item->set_total( 120 ); // Initial total is just the sign-up fee.
		$line_item->set_subtotal( 120 );

		wcs_set_recurring_item_total( $line_item );

		$this->assertEquals( 80, $line_item->get_total() );
		$this->assertEquals( 80, $line_item->get_subtotal() );
	}
}
