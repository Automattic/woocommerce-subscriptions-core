<?php
/**
 * Class WCS_Customer_Store_CPT_Test
 *
 * @package WooCommerce\SubscriptionsCore\Tests
 */

/**
 * Test suite for the WCS_Customer_Store_CPT class
 */
class WCS_Customer_Store_CPT_Test extends WCS_Base_Customer_Store_Test_Case {

	/**
	 * @var WCS_Customer_Store_CPT
	 */
	protected static $store;

	public static function setUpBeforeClass() {
		self::$store = new WCS_Customer_Store_CPT();
	}

	/**
	 * Make sure if there are no subscriptions for a given user, no subscriptions are returned.
	 */
	public function test_get_users_subscription_ids_none() {
		$this->assertEquals( [], self::$store->get_users_subscription_ids( $this->customer_id_without_subscriptions ) );
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

		$this->assertEquals( $subscription_ids, self::$store->get_users_subscription_ids( $this->customer_id ) );
	}
}
