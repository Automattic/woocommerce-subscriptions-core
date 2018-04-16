<?php
/**
 * Subscriptions Debug Tools
 *
 * Add tools for debugging and managing Subscriptions to the
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
 * WCS_Debug_Tool_Related_Order_Cache_Eraser Class
 *
 * Add debug tools to the WooCommerce > System Status > Tools page for deleting related order cache.
 */
class WCS_Debug_Tool_Related_Order_Cache_Eraser extends Abstract_WCS_Debug_Tool {

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
		$this->data_store = $related_order_store;
		$this->tool_key   = 'delete_related_order_caches';
		$this->tool_data  = array(
			'name'     => __( 'Delete Related Order Cache', 'woocommerce-subscriptions' ),
			'button'   => __( 'Delete related order caches', 'woocommerce-subscriptions' ),
			'desc'     => __( 'This will clear the persistent cache of all renewal, switch, resubscribe and other order types for all subscriptions in your store. Expect slower performance of checkout, renewal and other subscription related functions after taking this action. The caches will be regenerated overtime as related order queries are run.', 'woocommerce-subscriptions' ),
			'callback' => array( $this, 'delete_related_order_caches' ),
		);
	}

	/**
	 * Attach callbacks and hooks, if the store supports deleting caches.
	 */
	public function init() {
		if ( $this->can_data_store_delete_caches() ) {
			parent::init();
		}
	}

	/**
	 * Clear all of the store's related order caches.
	 */
	public function delete_related_order_caches() {
		if ( $this->can_data_store_delete_caches() ) {
			$this->data_store->delete_caches_for_all_subscriptions();
		}
	}

	/**
	 * Check if the store can clear related order caches.
	 */
	protected function can_data_store_delete_caches() {
		return ( is_callable( array( $this->data_store, 'delete_caches_for_all_subscriptions' ) ) );
	}
}
