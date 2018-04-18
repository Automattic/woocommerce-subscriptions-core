<?php
/**
 * WCS_Debug_Tool_Cache_Updater Class
 *
 * Shared methods for tool on the WooCommerce > System Status > Tools page that need to
 * update a cached data store's cache.
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
 * WCS_Debug_Tool_Cache_Updater Class
 *
 * Add a debug tool to the WooCommerce > System Status > Tools page for generating related order cache.
 */
abstract class WCS_Debug_Tool_Cache_Updater extends WCS_Debug_Tool {

	/**
	 * @var mixed $data_Store The store used for updating the related order cache.
	 */
	protected $data_store;

	/**
	 * Attach callbacks and hooks, if the store supports getting uncached items, which is required to generate cache
	 * and also acts as a proxy to determine if the related order store is using caching
	 */
	public function init() {
		if ( $this->is_data_store_cached() ) {
			parent::init();
		}
	}

	/**
	 * Check if the store can get the uncached items, required to generate the cache.
	 */
	protected function is_data_store_cached() {
		return is_a( $this->data_store, 'WCS_Cache_Updater' );
	}
}
