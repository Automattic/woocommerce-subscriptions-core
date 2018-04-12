<?php
/**
 * Subscriptions Customer Cache Generator Debug Tool
 *
 * Add a tool for generating customer subscription caches to the
 * WooCommerce > System Status > Tools administration screen.
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin
 * @version  2.3
 * @since    2.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * WCS_Debug_Tool_Customer_Cache_Generator Class
 *
 * Add a debug tool to the WooCommerce > System Status > Tools page for generating customer subscription caches.
 */
class WCS_Debug_Tool_Customer_Cache_Generator extends WCS_Debug_Tool_Related_Order_Cache_Generator {

	/**
	 * Constructor
	 *
	 * @param WCS_Customer_Store $related_order_store
	 */
	public function __construct( WCS_Customer_Store $data_store ) {
		$this->scheduled_hook = 'wcs_generate_customer_subscription_caches';
		$this->data_store     = $data_store;
		$this->tool_key       = 'generate_customer_subscription_caches';
		$this->tool_data      = array(
			'name'     => __( 'Generate Customer Subscription Cache', 'woocommerce-subscriptions' ),
			'button'   => __( 'Generate customer subscription cache', 'woocommerce-subscriptions' ),
			'desc'     => __( 'This will generate the persistent cache of all of subscriptions stored against the users in your store. The caches will be generated overtime in the background (via Action Scheduler).', 'woocommerce-subscriptions' ),
			'callback' => array( $this, 'generate_caches' ),
		);
	}

	/**
	 * Get the IDs of all users without a subscription cache set.
	 */
	protected function get_items_to_update() {
		return $this->data_store->get_user_ids_without_cache();
	}

	/**
	 * Update a given user's subscription cache by retrieving subscriptions, which will also set the cache
	 * when it's not already set.
	 *
	 * Called by @see parent::background_updater().
	 *
	 * @param int $user_id The ID of a user.
	 */
	protected function update_item( $user_id ) {
		$this->data_store->get_users_subscription_ids( $user_id );
	}

	/**
	 * Check if the store can get the uncached items, required to generate the cache.
	 */
	protected function can_data_store_get_uncached() {
		return ( is_callable( array( $this->data_store, 'get_user_ids_without_cache' ) ) );
	}
}
