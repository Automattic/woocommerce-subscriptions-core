<?php
/**
 * Hybrid wrapper around post and database store.
 *
 * @package        WooCommerce Subscriptions
 * @subpackage     WCS_Retry_Store
 * @category       Class
 * @author         Prospress
 */

/**
 * Class WCS_Retry_Hybrid_Store
 *
 * Hybrid wrapper around post and database store.
 */
class WCS_Retry_Hybrid_Store extends WCS_Retry_Store {
	/**
	 * Setup the class, if required
	 *
	 * @return null
	 */
	public function init() {
		// TODO: Implement init() method.
	}

	/**
	 * Save the details of a retry to the database
	 *
	 * @param WCS_Retry $retry Retry to save.
	 *
	 * @return int the retry's ID
	 */
	public function save( WCS_Retry $retry ) {
		// TODO: Implement save() method.
	}

	/**
	 * Get the details of a retry from the database
	 *
	 * @param int $retry_id Retry we want to get.
	 *
	 * @return WCS_Retry
	 */
	public function get_retry( $retry_id ) {
		// TODO: Implement get_retry() method.
	}

	/**
	 * Get a set of retries from the database
	 *
	 * @param array $args A set of filters.
	 *
	 * @return array An array of WCS_Retry objects
	 */
	public function get_retries( $args ) {
		// TODO: Implement get_retries() method.
	}

	/**
	 * Get the IDs of all retries from the database for a given order
	 *
	 * @param int $order_id order we want to look for.
	 *
	 * @return array
	 */
	protected function get_retry_ids_for_order( $order_id ) {
		// TODO: Implement get_retry_ids_for_order() method.
	}
}