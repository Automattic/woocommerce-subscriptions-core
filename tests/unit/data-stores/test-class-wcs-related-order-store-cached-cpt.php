<?php
/**
 * Class WCS_Related_Order_Store_Cached_CPT_Test
 *
 * @package WooCommerce\SubscriptionsCore\Tests
 */

/**
 * Test suite for the WCS_Related_Order_Store_Cached_CPT class
 */
class WCS_Related_Order_Store_Cached_CPT_Test extends WCS_Base_Related_Order_Store_Test_Case {

	/**
	 * @var WCS_Related_Order_Store_CPT
	 */
	protected static $cache_store;

	public static function set_up_before_class() {
		self::$cache_store = new WCS_Related_Order_Store_Cached_CPT();
	}

	/**
	 * Make sure if there are no renewal, switch or resubscribe ordres, no orders are returned.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_get_related_order_ids_none( $relation_type ) {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$this->assertEquals( [], self::$cache_store->get_related_order_ids( $subscription, $relation_type ) );
	}

	/**
	 * Make sure when there are renewal, switch or resubscribe orders, the correct order IDs are returned.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_get_related_order_ids( $relation_type ) {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$order_ids    = [];

		// Create some related orders
		for ( $i = 0; $i < 3; $i++ ) {
			$order       = WCS_Helper_Subscription::create_order();
			$order_id    = wcs_get_objects_property( $order, 'id' );
			$order_ids[] = $order_id;

			$this->add_relation_mock( $order, $subscription, $relation_type );
		}

		rsort( $order_ids );

		// First call will be uncached
		$this->assertEquals( $order_ids, self::$cache_store->get_related_order_ids( $subscription, $relation_type ) );

		// 2nd call will use the cache
		$this->assertEquals( $order_ids, self::$cache_store->get_related_order_ids( $subscription, $relation_type ) );
	}

	/**
	 * Make sure when there are renewal order IDs in the old transient cache, the correct order IDs are returned.
	 */
	public function test_get_related_order_ids_from_transient_cache() {
		$subscription = WCS_Helper_Subscription::create_subscription();

		// Newly created subscriptions get an empty set of renewal orders set by default in the persistent cache, so delete that
		self::$cache_store->delete_caches_for_subscription( $subscription->get_id() );

		$transient_key  = 'wcs-related-orders-to-' . $subscription->get_id();
		$fake_order_ids = [ 123, 456, 789 ];

		set_transient( $transient_key, $fake_order_ids, DAY_IN_SECONDS );

		rsort( $fake_order_ids );

		$this->assertEquals( $fake_order_ids, self::$cache_store->get_related_order_ids( $subscription, 'renewal' ) );

		// Make sure the transient has been deleted so it is no longer used as a source of truth
		$this->assertFalse( get_transient( $transient_key ) );

		// Also make sure the persistent cache was updated and is being used as the source of truth now that we know the transient has been deleted
		$this->assertEquals( $fake_order_ids, self::$cache_store->get_related_order_ids( $subscription, 'renewal' ) );

		// Only renewal orders should use the transient cache, so make sure switch and resubscribe orders do not
		$this->assertEquals( [], self::$cache_store->get_related_order_ids( $subscription, 'switch' ) );
		$this->assertEquals( [], self::$cache_store->get_related_order_ids( $subscription, 'resubscribe' ) );
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

		// Create some related orders
		for ( $i = 0; $i < 3; $i++ ) {
			$order       = WCS_Helper_Subscription::create_order();
			$order_id    = wcs_get_objects_property( $order, 'id' );
			$order_ids[] = $order_id;

			$this->add_relation_mock( $order, $subscription_one, $relation_type );
			$this->add_relation_mock( $order, $subscription_two, $relation_type );
		}

		rsort( $order_ids );

		$this->assertEquals( $order_ids, self::$cache_store->get_related_order_ids( $subscription_one, $relation_type ) );
		$this->assertEquals( $order_ids, self::$cache_store->get_related_order_ids( $subscription_two, $relation_type ) );
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

		self::$cache_store->add_relation( $order, $subscription, $relation_type );

		$this->assertEquals( $subscription->get_id(), get_post_meta( $order_id, $this->get_meta_key( $relation_type ), true ) );
		$this->assertTrue( in_array( $order_id, $this->get_cache_from_source( $subscription, $relation_type ), true ) );
	}

	/**
	 * Make sure if there are no related subscriptions, no IDs are returned.
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

		// Make sure all relations are setup and cached correctly
		foreach ( [ $persistent_relation_type, $relation_type ] as $type ) {
			foreach ( [ $subscription_one, $subscription_two ] as $subscription ) {
				foreach ( [ $order_id_to_delete, $order_id_to_keep ] as $order_id ) {
					$cache = $this->get_cache_from_source( $subscription, $type );
					$this->assertTrue( in_array( $order_id, $cache, true ) );
				}
			}
		}

		self::$cache_store->delete_relation( $order_to_delete, $subscription_one, $relation_type );

		// Make sure the specified relation was deleted from the cache
		$subscription_one_cache = $this->get_cache_from_source( $subscription_one, $relation_type );
		$this->assertFalse( in_array( $order_id_to_delete, $subscription_one_cache, true ) );

		// But not the same relation on the same subscription for other orders
		$this->assertTrue( in_array( $order_id_to_keep, $subscription_one_cache, true ) );

		// And not the same relation for the same orders for other subscriptions
		$subscription_two_cache = $this->get_cache_from_source( $subscription_two, $relation_type );
		$this->assertTrue( in_array( $order_id_to_delete, $subscription_two_cache, true ) );
		$this->assertTrue( in_array( $order_id_to_keep, $subscription_two_cache, true ) );

		// And no other relation types for the same subscriptions on the same order
		foreach ( [ $subscription_one, $subscription_two ] as $subscription ) {
			$persistent_related_order_cache = $this->get_cache_from_source( $subscription, $persistent_relation_type );
			$this->assertTrue( in_array( $order_id_to_delete, $persistent_related_order_cache, true ) );
			$this->assertTrue( in_array( $order_id_to_keep, $persistent_related_order_cache, true ) );
		}
	}

	/**
	 * Make sure all related orders are cleared.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_delete_relations( $relation_type ) {
		$subscription_one         = WCS_Helper_Subscription::create_subscription();
		$subscription_two         = WCS_Helper_Subscription::create_subscription();
		$order_to_delete          = WCS_Helper_Subscription::create_order();
		$order_id_to_delete       = wcs_get_objects_property( $order_to_delete, 'id' );
		$order_to_keep            = WCS_Helper_Subscription::create_order();
		$order_id_to_keep         = wcs_get_objects_property( $order_to_keep, 'id' );
		$persistent_relation_type = 'persistent_relation';

		$this->add_relation_mock( $order_to_delete, $subscription_one, $relation_type );
		$this->add_relation_mock( $order_to_delete, $subscription_two, $relation_type );
		$this->add_relation_mock( $order_to_keep, $subscription_one, $relation_type );
		$this->add_relation_mock( $order_to_keep, $subscription_two, $relation_type );

		// Also add a persistent relation to make sure unrelated relations are not deleted
		$this->add_relation_mock( $order_to_delete, $subscription_one, $persistent_relation_type );
		$this->add_relation_mock( $order_to_delete, $subscription_two, $persistent_relation_type );
		$this->add_relation_mock( $order_to_keep, $subscription_one, $persistent_relation_type );
		$this->add_relation_mock( $order_to_keep, $subscription_two, $persistent_relation_type );

		// Make sure all relations are setup and cached correctly
		foreach ( [ $persistent_relation_type, $relation_type ] as $type ) {
			foreach ( [ $subscription_one, $subscription_two ] as $subscription ) {
				foreach ( [ $order_id_to_delete, $order_id_to_keep ] as $order_id ) {
					$cache = $this->get_cache_from_source( $subscription, $type );
					$this->assertTrue( in_array( $order_id, $cache, true ) );
				}
			}
		}

		self::$cache_store->delete_relations( $order_to_delete, $relation_type );

		// Make sure the specified relation was deleted from the cache
		$subscription_one_cache = $this->get_cache_from_source( $subscription_one, $relation_type );
		$subscription_two_cache = $this->get_cache_from_source( $subscription_two, $relation_type );
		$this->assertFalse( in_array( $order_id_to_delete, $subscription_one_cache, true ) );
		$this->assertFalse( in_array( $order_id_to_delete, $subscription_two_cache, true ) );

		// But not the same relation on the same subscription for other orders
		$this->assertTrue( in_array( $order_id_to_keep, $subscription_one_cache, true ) );
		$this->assertTrue( in_array( $order_id_to_keep, $subscription_two_cache, true ) );

		// And no other relation types for the same subscriptions on the same order
		foreach ( [ $subscription_one, $subscription_two ] as $subscription ) {
			$persistent_related_order_cache = $this->get_cache_from_source( $subscription, $persistent_relation_type );
			$this->assertTrue( in_array( $order_id_to_delete, $persistent_related_order_cache, true ) );
			$this->assertTrue( in_array( $order_id_to_keep, $persistent_related_order_cache, true ) );
		}
	}

	/**
	 * Check the related renewal order cache value is set when creating a subscription, becuase it should be set by
	 * WCS_Related_Order_Store_Cached_CPT::set_empty_renewal_order_cache()
	 */
	public function test_set_empty_renewal_order_cache() {
		// get_post_meta() returns an empty string ('') by default when the 3rd param (single) is true, so if we have an empty array, we know it's the cache value
		$subscription = WCS_Helper_Subscription::create_subscription();
		$this->assertEquals( [], $this->get_cache_from_source( $subscription, 'renewal' ) );
	}

	/**
	 * Make sure props are added to WCS_Subscription_Data_Store_CPT data store props.
	 */
	public function test_add_related_order_cache_props() {
		$expected = [
			'_subscription_switch_order_ids_cache'      => 'subscription_switch_order_ids_cache',
			'_subscription_renewal_order_ids_cache'     => 'subscription_renewal_order_ids_cache',
			'_subscription_resubscribe_order_ids_cache' => 'subscription_resubscribe_order_ids_cache',
		];

		$this->assertEquals( $expected, self::$cache_store->add_related_order_cache_props( [], new WCS_Subscription_Data_Store_CPT() ) );
	}

	/**
	 * Make sure props are not added to data stores other than WCS_Subscription_Data_Store_CPT
	 */
	public function test_add_related_order_cache_props_ignored() {
		$this->assertEquals( [], self::$cache_store->add_related_order_cache_props( [], new StdClass() ) );
	}

	/**
	 * Provide a method to set the relation directly to avoid a breaking change in WCS_Related_Order_Store::add_relation()
	 * breaking tests that aren't primarily designed to test WCS_Related_Order_Store::add_relation().
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

		// This needs to be added so any calls to get_related_subscription_ids() will return correct values.
		add_post_meta( $order_id, $this->get_meta_key( $relation_type ), $subscription_id, false );

		$related_order_ids = get_post_meta( $subscription_id, $this->get_cache_meta_key( $relation_type ), true );

		if ( '' === $related_order_ids ) {
			$related_order_ids = [];
		}

		if ( ! in_array( $order_id, $related_order_ids, true ) ) {
			array_unshift( $related_order_ids, $order_id );
			update_post_meta( $subscription_id, $this->get_cache_meta_key( $relation_type ), $related_order_ids, false );
		}
	}

	/**
	 * @return string
	 */
	protected function get_cache_meta_key( $relation_type ) {
		return sprintf( '%s_order_ids_cache', $this->get_meta_key( $relation_type ) );
	}

	/**
	 * Get the raw cache from post meta for a given subscription and relationship type.
	 *
	 * @param int|WC_Order $subscription A subscription to remove a linked order from.
	 * @param string $relation_type The relationship between the subscription and the orders. Must be 'renewal', 'switch' or 'resubscribe.
	 * @return mixed String or array.
	 */
	private function get_cache_from_source( $subscription, $relation_type ) {
		if ( is_object( $subscription ) ) {
			$subscription = $subscription->get_id();
		}
		return get_post_meta( $subscription, $this->get_cache_meta_key( $relation_type ), true );
	}
}
