<?php
/**
 * Class WCS_Customer_Store_Cached_CPT_Test
 *
 * @package WooCommerce\SubscriptionsCore\Tests
 */

/**
 * Test suite for the WCS_Customer_Store_Cached_CPT class
 */
class WCS_Customer_Store_Cached_CPT_Test extends WCS_Base_Customer_Store_Test_Case {

	/**
	 * @var WCS_Customer_Store_Cached_CPT
	 */
	protected static $cache_store;

	public static function set_up_before_class() {
		self::$cache_store = new WCS_Customer_Store_Cached_CPT();
	}

	/**
	 * Make sure if there are no subscriptions for a given user, no subscriptions are returned.
	 */
	public function test_get_users_subscription_ids_none() {
		$this->assertEquals( [], self::$cache_store->get_users_subscription_ids( $this->customer_id_without_subscriptions ) );
	}

	/**
	 * Make sure when a user has subscriptions, they are returned.
	 */
	public function test_get_users_subscription_ids() {
		$subscription_ids = [];

		// Create some subscriptions for the user
		for ( $i = 0; $i < 3; $i++ ) {
			$subscription       = WCS_Helper_Subscription::create_subscription( [ 'customer_id' => $this->customer_id ] );
			$subscription_ids[] = $subscription->get_id();
		}

		rsort( $subscription_ids ); // Default order of WCS_Customer_Store_CPT::get_users_subscription_ids() is descending by date and ID

		$this->assertEquals( $subscription_ids, self::$cache_store->get_users_subscription_ids( $this->customer_id ) );
		$this->assertEquals( $subscription_ids, $this->get_cache_from_source( $this->customer_id ) );
	}

	/**
	 * Make sure when there are subscription IDs in the old transient cache, the correct IDs are returned from there.
	 */
	public function test_get_users_subscription_ids_from_transient_cache() {
		$fake_user_id          = 123;
		$transient_key         = "wcs_user_subscriptions_{$fake_user_id}";
		$fake_subscription_ids = [ 123, 456, 789 ];

		set_transient( $transient_key, $fake_subscription_ids, DAY_IN_SECONDS );

		rsort( $fake_subscription_ids );

		$this->assertEquals( $fake_subscription_ids, self::$cache_store->get_users_subscription_ids( $fake_user_id ) );

		// Make sure the transient has been deleted so it is no longer used as a source of truth
		$this->assertFalse( get_transient( $transient_key ) );

		// Also make sure the persistent cache was updated and is being used as the source of truth now that we know the transient has been deleted
		$this->assertEquals( $fake_subscription_ids, self::$cache_store->get_users_subscription_ids( $fake_user_id ) );
	}

	/**
	 * Makes sure new subscriptions are being added to the beginning of the array.
	 */
	public function test_add_subscription_id_to_cache() {
		$subscription_ids = [];

		// Create some subscriptions for the user
		for ( $i = 0; $i < 5; $i ++ ) {
			$subscription       = WCS_Helper_Subscription::create_subscription( [ 'customer_id' => $this->customer_id ] );
			$subscription_ids[] = $subscription->get_id();
		}

		rsort( $subscription_ids );
		$this->assertEquals( $subscription_ids, self::$cache_store->get_users_subscription_ids( $this->customer_id ) );
		$this->assertEquals( $subscription_ids, $this->get_cache_from_source( $this->customer_id ) );

		// Create new subscription.
		$last_subscription = WCS_Helper_Subscription::create_subscription( [ 'customer_id' => $this->customer_id ] );

		self::$cache_store->maybe_update_for_post_meta_change( 'add', $last_subscription->get_id(), '_customer_user', $this->customer_id );

		$user_subscriptions_ids        = self::$cache_store->get_users_subscription_ids( $this->customer_id );
		$user_subscriptions_cached_ids = $this->get_cache_from_source( $this->customer_id );

		// First item in array should be our last subscription.
		$this->assertEquals( $last_subscription->get_id(), $user_subscriptions_cached_ids[0] );
		$this->assertEquals( $last_subscription->get_id(), $user_subscriptions_ids[0] );

		// Validate second item in array is previous one.
		$this->assertEquals( $subscription->get_id(), $user_subscriptions_cached_ids[1] );
		$this->assertEquals( $subscription->get_id(), $user_subscriptions_ids[1] );
	}

	/**
	 * @return string
	 */
	protected function get_cache_meta_key() {
		$class = new WCS_Customer_Store_Cached_CPT();
		return $class->get_cache_meta_key();
	}

	/**
	 * Get the raw cache from user meta for a given customer.
	 *
	 * @param int| $user_id The user/customer ID to retrieve cache for
	 * @return mixed String or array.
	 */
	private function get_cache_from_source( $user_id ) {
		return get_user_meta( $user_id, $this->get_cache_meta_key(), true );
	}
}
