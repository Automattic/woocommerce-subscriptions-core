<?php
/**
 * Store retry details in the WordPress custom table.
 *
 * @package        WooCommerce Subscriptions
 * @subpackage     WCS_Retry_Store
 * @category       Class
 * @author         Prospress
 */

class WCS_Retry_Database_Store extends WCS_Retry_Store {

	protected static $table = 'woocommerce_subscriptions_payment_retries';
	protected static $wpdb;

	/**
	 * Setup the class, if required
	 *
	 * @return null
	 */
	public function init() {
		global $wpdb;

		self::$wpdb = $wpdb;
	}

	/**
	 * Save the details of a retry to the database
	 *
	 * @param WCS_Retry $retry
	 *
	 * @return int the retry's ID
	 */
	public function save( WCS_Retry $retry ) {
	}

	/**
	 * Get the details of a retry from the database
	 *
	 * @param int $retry_id
	 *
	 * @return WCS_Retry
	 */
	public function get_retry( $retry_id ) {
	}

	/**
	 *
	 */
	public function get_retries( $args ) {
	}

	/**
	 * Get the IDs of all retries from the database for a given order
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function get_retry_ids_for_order( $order_id ) {
	}
}
