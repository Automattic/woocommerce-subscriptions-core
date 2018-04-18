<?php
/**
 * WCS_Debug_Tool_Cache_Eraser Class
 *
 * Add a debug tool to the WooCommerce > System Status > Tools page for deleting related order caches.
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
 * WCS_Debug_Tool_Cache_Eraser Class
 *
 * Add a debug tool to the WooCommerce > System Status > Tools page for deleting related order caches.
 */
class WCS_Debug_Tool_Cache_Eraser extends WCS_Debug_Tool_Cache_Updater {

	public function __construct( $tool_key, $tool_name, $tool_description, WCS_Cache_Updater $data_store ) {
		$this->tool_key   = $tool_key;
		$this->data_store = $data_store;
		$this->tool_data  = array(
			'name'     => $tool_name,
			'button'   => $tool_name,
			'desc'     => $tool_description,
			'callback' => array( $this, 'delete_caches' ),
		);
	}

	/**
	 * Clear all of the store's related order caches.
	 */
	public function delete_caches() {
		if ( $this->is_data_store_cached() ) {
			$this->data_store->delete_all_caches();
		}
	}
}
