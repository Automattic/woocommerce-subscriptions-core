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
			$order_id    = $order->get_id();
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

		$this->assertEquals( $subscription->get_id(), $order->get_meta( $this->get_meta_key( $relation_type ), true ) );
		if ( ! wcs_is_custom_order_tables_usage_enabled() ) {
			$this->assertEquals( $subscription->get_id(), $order->get_meta( $this->get_meta_key( $relation_type ), true ) );
		}
		$this->assertContains( $order_id, $this->get_cache_from_source( $subscription, $relation_type ), true );
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
	 * Make sure a related order is removed from the cached relationship array after deletion.
	 *
	 * @dataProvider provider_relation_type
	 */
	public function test_cache_updates_after_related_object_is_deleted_one_to_many( $relation_type ) {
		$subscription       = WCS_Helper_Subscription::create_subscription();
		$order_to_delete    = WCS_Helper_Subscription::create_order();
		$order_id_to_delete = $order_to_delete->get_id();
		$order_to_keep      = WCS_Helper_Subscription::create_order();
		$order_id_to_keep   = $order_to_keep->get_id();

		self::$cache_store->add_relation( $order_to_delete, $subscription, $relation_type );
		self::$cache_store->add_relation( $order_to_keep, $subscription, $relation_type );

		// Verify that the related orders are correct and cached.
		$related_order_ids = self::$cache_store->get_related_order_ids( $subscription, $relation_type );
		$this->assertContains( $order_id_to_keep, $related_order_ids );
		$this->assertContains( $order_id_to_delete, $related_order_ids );

		$order_to_delete->delete( true );

		// Verify that the deleted order was removed from the cached relationship
		$related_order_ids = self::$cache_store->get_related_order_ids( $subscription, $relation_type );
		$this->assertContains( $order_id_to_keep, $related_order_ids );
		$this->assertNotContains( $order_id_to_delete, $related_order_ids );
	}

	/**
	 * Check the related renewal order cache value is set when creating a subscription, because it should be set by
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
	 * Test that when a related order is added, the subscription's modified date is updated.
	 */
	public function test_modified_date_is_updated() {
		$subscription  = WCS_Helper_Subscription::create_subscription();
		$related_order = WCS_Helper_Subscription::create_order();

		$subscription->set_date_modified( strtotime( '- 2 days' ) );
		$original_modified_date = $subscription->get_date_modified();

		self::$cache_store->add_relation( $related_order, $subscription, 'renewal' );
		$this->assertNotEquals( $original_modified_date->getTimestamp(), $subscription->get_date_modified()->getTimestamp() );
		$this->assertEqualsWithDelta( time(), $subscription->get_date_modified()->getTimestamp(), 1 );
	}

	/**
	 * Test that when a related order cache is emptied, the subscription's modified date is updated.
	 */
	public function test_modified_date_is_updated_when_emptied() {
		$subscription  = WCS_Helper_Subscription::create_subscription();
		$related_order = WCS_Helper_Subscription::create_order();

		self::$cache_store->add_relation( $related_order, $subscription, 'renewal' );

		$subscription->set_date_modified( strtotime( '- 3 days' ) );
		$original_modified_date = $subscription->get_date_modified();

		self::$cache_store->set_empty_renewal_order_cache( $subscription );

		$this->assertNotEquals( $original_modified_date->getTimestamp(), $subscription->get_date_modified()->getTimestamp() );
		$this->assertEqualsWithDelta( time(), $subscription->get_date_modified()->getTimestamp(), 1 );
	}

	/**
	 * Test that updating the related order cache to the same value (empty in this case), does not update the subscription's modified date.
	 */
	public function test_modified_date_is_not_updated() {
		$subscription = WCS_Helper_Subscription::create_subscription();

		self::$cache_store->set_empty_renewal_order_cache( $subscription );

		$subscription->set_date_modified( strtotime( '- 2 days' ) );
		$original_modified_date = $subscription->get_date_modified();

		self::$cache_store->set_empty_renewal_order_cache( $subscription );

		$this->assertEquals( $original_modified_date->getTimestamp(), $subscription->get_date_modified()->getTimestamp() );
	}

	/**
	 * Provide a method to set the relation directly to avoid a breaking change in WCS_Related_Order_Store::add_relation()
	 * breaking tests that aren't primarily designed to test WCS_Related_Order_Store::add_relation().
	 *
	 * @param int|WC_Order $subscription A subscription to remove a linked order from.
	 * @param int|WC_Order $order An order that may be linked with the subscription.
	 * @param string $relation_type The relationship between the subscription and the orders. Must be 'renewal', 'switch' or 'resubscribe.
	 */
	private function add_relation_mock( $order, $subscription, $relation_type ) {
		if ( ! is_object( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! is_object( $subscription ) ) {
			$subscription = wcs_get_subscription( $subscription );
		}

		// Remove the filtering of cached props so we can use the data stores to directly manipulate the meta for stubbing.
		add_filter( 'wcs_subscription_data_store_props_to_ignore', '__return_empty_array', 999, 2 );

		$subscription_data_store = WC_Data_Store::load( 'subscription' );
		$order_data_store        = WC_Data_Store::load( 'order' );

		// This needs to be added so any calls to get_related_subscription_ids() will return correct values.
		$order_data_store->add_meta(
			$order,
			(object) [
				'key'   => $this->get_meta_key( $relation_type ),
				'value' => $subscription->get_id(),
			]
		);
		$relationship_cache_meta_key = $this->get_cache_meta_key( $relation_type );
		$meta_data                   = $subscription_data_store->read_meta( $subscription );

		$related_order_ids = [];
		$existing_meta_id  = null;
		foreach ( $meta_data as $meta ) {
			if ( $meta->meta_key === $relationship_cache_meta_key ) {
				$related_order_ids = maybe_unserialize( $meta->meta_value ) ?? [];
				$existing_meta_id  = $meta->meta_id;
				break;
			}
		}

		if ( ! in_array( $order->get_id(), $related_order_ids, true ) ) {
			// Fill in the stubbed cached meta
			array_unshift( $related_order_ids, $order->get_id() );
			if ( null !== $existing_meta_id ) {
				$subscription_data_store->update_meta(
					$subscription,
					(object) [
						'id'    => $existing_meta_id,
						'key'   => $relationship_cache_meta_key,
						'value' => $related_order_ids,
					]
				);
			} else {
				$subscription_data_store->add_meta(
					$subscription,
					(object) [
						'key'   => $relationship_cache_meta_key,
						'value' => $related_order_ids,
					]
				);
			}
		}

		remove_filter( 'wcs_subscription_data_store_props_to_ignore', '__return_empty_array', 999 );
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
		if ( ! is_object( $subscription ) ) {
			$subscription = wcs_get_subscription( $subscription );
		}

		$cache_meta_key = $this->get_cache_meta_key( $relation_type );

		// Cached relationship meta is filtered out meta props, so we must load it directly from the Datastore.
		add_filter( 'wcs_subscription_data_store_props_to_ignore', '__return_empty_array', 999, 2 );
		$data_store = WC_Data_Store::load( 'subscription' );

		$meta_data = $data_store->read_meta( $subscription );
		remove_filter( 'wcs_subscription_data_store_props_to_ignore', '__return_empty_array', 999 );
		foreach ( $meta_data as $meta ) {
			if ( $meta->meta_key === $cache_meta_key ) {
				return maybe_unserialize( $meta->meta_value ) ?? '';
			}
		}

		return '';
	}
}
