<?php
/**
 * Subscriptions Customer Cache Eraser Debug Tool
 *
 * Add a tool for deleting customer subscription caches to the
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
 * WCS_Debug_Tool_Customer_Cache_Eraser Class
 *
 * Add debug tools to the WooCommerce > System Status > Tools page for deleting all customer subscription caches.
 */
class WCS_Debug_Tool_Customer_Cache_Eraser extends WCS_Debug_Tool_Related_Order_Cache_Eraser {

	/**
	 * Constructor
	 *
	 * @param WCS_Customer_Store $related_order_store
	 */
	public function __construct( WCS_Customer_Store $data_store ) {
		$this->data_store = $data_store;
		$this->tool_key   = 'delete_customers_subscription_caches';
		$this->tool_data  = array(
			'name'     => __( 'Delete Customer\'s Subscription Cache', 'woocommerce-subscriptions' ),
			'button'   => __( 'Delete customer\'s subscription caches', 'woocommerce-subscriptions' ),
			'desc'     => __( 'This will clear the persistent cache of all of subscriptions stored against the users in your store. Expect slower performance of checkout, renewal and other subscription related functions after taking this action. The caches will be regenerated overtime as queries to find a given user\'s subscriptions are run.', 'woocommerce-subscriptions' ),
			'callback' => array( $this, 'delete_caches' ),
		);
	}
}
