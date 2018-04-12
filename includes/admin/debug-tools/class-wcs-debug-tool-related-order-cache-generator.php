<?php
/**
 * WCS_Debug_Tool_Related_Order_Cache_Generator Class
 *
 * Add a debug tool to the WooCommerce > System Status > Tools page for generating related order cache.
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
 * WCS_Debug_Tool_Related_Order_Cache_Generator Class
 *
 * Add a debug tool to the WooCommerce > System Status > Tools page for generating related order cache.
 */
class WCS_Debug_Tool_Related_Order_Cache_Generator extends WCS_Debug_Tool_Background_Updater {

	/**
	 * @var WCS_Related_Order_Store $data_Store The store used for deleting the related order cache.
	 */
	private $data_store;

	/**
	 * Constructor
	 *
	 * @param WCS_Related_Order_Store $related_order_store
	 */
	public function __construct( WCS_Related_Order_Store $related_order_store ) {
		$this->scheduled_hook = 'wcs_generate_related_order_caches';
		$this->data_store     = $related_order_store;
		$this->tool_key       = 'generate_related_order_caches';
		$this->tool_data      = array(
			'name'     => __( 'Generate Related Order Cache', 'woocommerce-subscriptions' ),
			'button'   => __( 'Generate related order caches', 'woocommerce-subscriptions' ),
			'desc'     => __( 'This will generate the persistent cache of all renewal, switch, resubscribe and other order types for all subscriptions in your store. The caches will be generated overtime in the background (via Action Scheduler).', 'woocommerce-subscriptions' ),
			'callback' => array( $this, 'generate_related_order_caches' ),
		);
	}

	/**
	 * Attach callbacks and hooks, if the store supports getting uncached items, which is required to generate cache
	 * and also acts as a proxy to determine if the related order store is using caching
	 */
	public function init() {
		if ( $this->can_data_store_get_uncached() ) {
			parent::init();
		}
	}

	/**
	 * Schedule the @see $this->scheduled_hook action to start generating related order cache generation in
	 * @see $this->time_limit seconds (60 seconds by default).
	 */
	public function generate_related_order_caches() {
		$this->schedule_background_update();
	}

	/**
	 * Get the IDs of all subscriptions without a related order cache set.
	 */
	protected function get_items_to_update() {
		return $this->data_store->get_subscription_ids_without_cache();
	}

	/**
	 * Update a given subscription's related order cache by retrieving related orders, which will also set the cache
	 * when it's not already set.
	 *
	 * Called by @see parent::background_updater().
	 *
	 * @param int $subscription_id The ID of a shop_subscription/WC_Subscription object.
	 */
	protected function update_item( $subscription_id ) {
		$subscription = wcs_get_subscription( $subscription_id );
		if ( $subscription ) {
			$subscription->get_related_orders( 'ids', 'any' );
		}
	}

	/**
	 * Check if the store can get the uncached items, required to generate the cache.
	 */
	protected function can_data_store_get_uncached() {
		return ( is_callable( array( $this->data_store, 'get_subscription_ids_without_cache' ) ) );
	}
}
