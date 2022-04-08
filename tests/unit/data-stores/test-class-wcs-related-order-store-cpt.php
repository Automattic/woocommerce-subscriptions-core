<?php
/**
 * Class WCS_Related_Order_Store_CPT_Test
 *
 * @package WooCommerce\SubscriptionsCore\Tests
 */

/**
 * Test suite for the WCS_Related_Order_Store_CPT class
 */
class WCS_Related_Order_Store_CPT_Test extends WCS_Base_Related_Order_Store_Test_Case {

	/**
	 * @var WCS_Related_Order_Store_CPT
	 */
	protected static $store;

	public static function set_up_before_class() {
		self::$store = new WCS_Related_Order_Store_CPT();
	}

	/**
	 * Make sure if there are no renewal, switch or resubscribe ordres, no orders are returned.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_get_related_order_ids_none( $relation_type ) {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$this->assertEquals( [], self::$store->get_related_order_ids( $subscription, $relation_type ) );
	}

	/**
	 * Make sure when there are renewal, switch or resubscribe orders, the correct order IDs are returned.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_get_related_order_ids( $relation_type ) {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$order_ids    = [];

		for ( $i = 0; $i < 3; $i++ ) {
			$order       = WCS_Helper_Subscription::create_order();
			$order_id    = wcs_get_objects_property( $order, 'id' );
			$order_ids[] = $order_id;

			$this->add_relation_mock( $order, $subscription, $relation_type );
		}

		rsort( $order_ids );

		$this->assertEquals( $order_ids, self::$store->get_related_order_ids( $subscription, $relation_type ) );
	}

	/**
	 * Make sure when there are renewal, switch or resubscribe orders associated with more than one
	 * subscription, the correct order IDs are still returned (both all subscriptions).
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_get_related_order_ids_many( $relation_type ) {
		$subscription_one = WCS_Helper_Subscription::create_subscription();
		$subscription_two = WCS_Helper_Subscription::create_subscription();
		$order_ids        = [];

		for ( $i = 0; $i < 3; $i++ ) {
			$order       = WCS_Helper_Subscription::create_order();
			$order_ids[] = wcs_get_objects_property( $order, 'id' );

			$this->add_relation_mock( $order, $subscription_one, $relation_type );
			$this->add_relation_mock( $order, $subscription_two, $relation_type );
		}

		rsort( $order_ids );

		$this->assertEquals( $order_ids, self::$store->get_related_order_ids( $subscription_one, $relation_type ) );
		$this->assertEquals( $order_ids, self::$store->get_related_order_ids( $subscription_two, $relation_type ) );
	}

	/**
	 * Make sure when an order has related subscriptions, the correct IDs are returned.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_get_related_subscription_ids( $relation_type ) {
		$order            = WCS_Helper_Subscription::create_order();
		$subscription_ids = [];

		for ( $i = 0; $i < 3; $i++ ) {
			$subscription       = WCS_Helper_Subscription::create_subscription();
			$subscription_ids[] = $subscription->get_id();

			$this->add_relation_mock( $order, $subscription, $relation_type );
		}

		$this->assertEquals( $subscription_ids, self::$store->get_related_subscription_ids( $order, $relation_type ) );
	}

	/**
	 * Make sure when a subscription has numerous related orders of different types, the correct IDs are returned.
	 *
	 * @dataProvider provider_relation_types
	 */
	public function test_get_related_subscription_ids_many( $relation_types ) {
		$order            = WCS_Helper_Subscription::create_order();
		$subscription_ids = [];

		foreach ( $relation_types as $relation_type ) {
			$subscription_ids[ $relation_type ] = [];

			for ( $i = 0; $i < 3; $i++ ) {
				$subscription                         = WCS_Helper_Subscription::create_subscription();
				$subscription_ids[ $relation_type ][] = $subscription->get_id();

				$this->add_relation_mock( $order, $subscription, $relation_type );
			}
		}

		foreach ( $relation_types as $relation_type ) {
			$this->assertEquals( $subscription_ids[ $relation_type ], self::$store->get_related_subscription_ids( $order, $relation_type ) );
		}
	}

	/**
	 * Make sure if there are no related subscriptions, no IDs are returned.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_get_related_subscription_ids_none( $relation_type ) {
		$order = WCS_Helper_Subscription::create_order();
		$this->assertEquals( array(), self::$store->get_related_subscription_ids( $order, $relation_type ) );
	}

	/**
	 * Make sure if there are no related subscriptions, no IDs are returned.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_add_relation( $relation_type ) {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$order        = WCS_Helper_Subscription::create_order();
		$order_id     = wcs_get_objects_property( $order, 'id' );

		self::$store->add_relation( $order, $subscription, $relation_type );

		$this->assertEquals( $subscription->get_id(), get_post_meta( $order_id, $this->get_meta_key( $relation_type ), true ) );

		// Also make sure the same ID is not added more than once on subsequent calls
		self::$store->add_relation( $order, $subscription, $relation_type );
		$meta_values = get_post_meta( $order_id, $this->get_meta_key( $relation_type ) );
		$this->assertEquals( 1, count( $meta_values ) );
	}

	/**
	 * Make sure a specific related order is deleted, and no others
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_delete_relation( $relation_type ) {
		$subscription_one   = WCS_Helper_Subscription::create_subscription();
		$subscription_two   = WCS_Helper_Subscription::create_subscription();
		$order_to_delete    = WCS_Helper_Subscription::create_order();
		$order_id_to_delete = wcs_get_objects_property( $order_to_delete, 'id' );
		$order_to_keep      = WCS_Helper_Subscription::create_order();
		$order_id_to_keep   = wcs_get_objects_property( $order_to_keep, 'id' );

		$persistent_relation_type = 'persistent_relation';

		$this->add_relation_mock( $order_to_delete, $subscription_one, $relation_type );
		$this->add_relation_mock( $order_to_keep, $subscription_one, $relation_type );
		$this->add_relation_mock( $order_to_delete, $subscription_two, $relation_type );
		$this->add_relation_mock( $order_to_keep, $subscription_two, $relation_type );

		// Also add a persistent relation to make sure unrelated relations are not deleted
		$this->add_relation_mock( $order_to_delete, $subscription_one, $persistent_relation_type );
		$this->add_relation_mock( $order_to_delete, $subscription_two, $persistent_relation_type );
		$this->add_relation_mock( $order_to_keep, $subscription_one, $persistent_relation_type );
		$this->add_relation_mock( $order_to_keep, $subscription_two, $persistent_relation_type );

		// Make sure all relations are setup correctly
		foreach ( [ $persistent_relation_type, $relation_type ] as $type ) {
			foreach ( [ $order_id_to_delete, $order_id_to_keep ] as $order_id ) {
				$related_subscriptions = get_post_meta( $order_id, $this->get_meta_key( $type ), false );
				$this->assertTrue( in_array( (string) $subscription_one->get_id(), $related_subscriptions, true ) );
				$this->assertTrue( in_array( (string) $subscription_two->get_id(), $related_subscriptions, true ) );
			}
		}

		self::$store->delete_relation( $order_to_delete, $subscription_one, $relation_type );

		// Make sure the specified relation was deleted
		$order_to_delete_related_subscriptions = get_post_meta( $order_id_to_delete, $this->get_meta_key( $relation_type ), false );
		$this->assertFalse( in_array( (string) $subscription_one->get_id(), $order_to_delete_related_subscriptions, true ) );

		// But not the same relation on the same order for other subscriptions
		$this->assertTrue( in_array( (string) $subscription_two->get_id(), $order_to_delete_related_subscriptions, true ) );

		// And not the same relation for the same subscriptions for other orders
		$order_to_keep_related_subscriptions = get_post_meta( $order_id_to_keep, $this->get_meta_key( $relation_type ), false );
		$this->assertTrue( in_array( (string) $subscription_one->get_id(), $order_to_keep_related_subscriptions, true ) );
		$this->assertTrue( in_array( (string) $subscription_two->get_id(), $order_to_keep_related_subscriptions, true ) );

		// And not other relation types for the same subscriptions on the same order
		$persistent_related_subscriptions = get_post_meta( $order_id_to_delete, $this->get_meta_key( $persistent_relation_type ), false );
		$this->assertTrue( in_array( (string) $subscription_one->get_id(), $persistent_related_subscriptions, true ) );
		$this->assertTrue( in_array( (string) $subscription_two->get_id(), $persistent_related_subscriptions, true ) );
	}

	/**
	 * Make sure all related orders are cleared.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_delete_relations( $relation_type ) {
		$subscription_one = WCS_Helper_Subscription::create_subscription();
		$subscription_two = WCS_Helper_Subscription::create_subscription();
		$order_one        = WCS_Helper_Subscription::create_order();
		$order_id_one     = wcs_get_objects_property( $order_one, 'id' );
		$order_two        = WCS_Helper_Subscription::create_order();
		$order_id_two     = wcs_get_objects_property( $order_two, 'id' );

		$persistent_relation_type = 'persistent_relation';

		$this->add_relation_mock( $order_one, $subscription_one, $relation_type );
		$this->add_relation_mock( $order_one, $subscription_two, $relation_type );
		$this->add_relation_mock( $order_two, $subscription_one, $relation_type );
		$this->add_relation_mock( $order_two, $subscription_two, $relation_type );

		// Also add a persistent relation to make sure unrelated relations are not deleted
		$this->add_relation_mock( $order_one, $subscription_one, $persistent_relation_type );
		$this->add_relation_mock( $order_one, $subscription_two, $persistent_relation_type );
		$this->add_relation_mock( $order_two, $subscription_one, $persistent_relation_type );
		$this->add_relation_mock( $order_two, $subscription_two, $persistent_relation_type );

		// Make sure all relations are setup correctly
		foreach ( [ $persistent_relation_type, $relation_type ] as $type ) {
			foreach ( [ $order_id_one, $order_id_two ] as $order_id ) {
				$related_subscriptions = get_post_meta( $order_id, $this->get_meta_key( $type ), false );
				$this->assertTrue( in_array( (string) $subscription_one->get_id(), $related_subscriptions, true ) );
				$this->assertTrue( in_array( (string) $subscription_two->get_id(), $related_subscriptions, true ) );
			}
		}

		self::$store->delete_relations( $order_one, $relation_type );

		// Make sure all of the specified relation from the specified order was deleted
		$order_one_related_subscriptions = get_post_meta( $order_id_one, $this->get_meta_key( $relation_type ), false );
		$this->assertEquals( [], $order_one_related_subscriptions );

		// But not the same relation for the same subscriptions for other orders
		$order_two_related_subscriptions = get_post_meta( $order_id_two, $this->get_meta_key( $relation_type ), false );
		$this->assertTrue( in_array( (string) $subscription_one->get_id(), $order_two_related_subscriptions, true ) );
		$this->assertTrue( in_array( (string) $subscription_two->get_id(), $order_two_related_subscriptions, true ) );

		// And not other relation types for the same subscriptions on the same order
		$persistent_related_subscriptions = get_post_meta( $order_id_one, $this->get_meta_key( $persistent_relation_type ), false );
		$this->assertTrue( in_array( (string) $subscription_one->get_id(), $persistent_related_subscriptions, true ) );
		$this->assertTrue( in_array( (string) $subscription_two->get_id(), $persistent_related_subscriptions, true ) );
	}

	/**
	 * Provide a method to set the relation directly to avoid a breakage of WCS_Related_Order_Store::add_relation_mock()
	 * breaking tests that aren't primarily design to test add_relation_mock().
	 *
	 * @param int|WC_Order $subscription A subscription to remove a linked order from.
	 * @param int|WC_Order $order An order that may be linked with the subscription.
	 * @param string $relation_type The relationship between the subscription and the orders. Must be 'renewal', 'switch' or 'resubscribe.
	 */
	private function add_relation_mock( $order_id, $subscription_id, $relation_type ) {

		if ( is_object( $order_id ) ) {
			$order_id = wcs_get_objects_property( $order_id, 'id' );
		}

		if ( is_object( $subscription_id ) ) {
			$subscription_id = $subscription_id->get_id();
		}

		add_post_meta( $order_id, $this->get_meta_key( $relation_type ), $subscription_id, false );
	}
}
