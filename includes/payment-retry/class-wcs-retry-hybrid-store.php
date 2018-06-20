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
	 * Where we're saving/migrating our data.
	 *
	 * @var WCS_Retry_Store
	 */
	private static $destination_store;

	/**
	 * Where the data comes from.
	 *
	 * @var WCS_Retry_Store
	 */
	private static $source_store;

	/**
	 * Setup the class, if required
	 *
	 * @void
	 */
	public function init() {
		add_filter( 'init', array( self::destination_store(), 'init' ) );
		add_filter( 'init', array( self::source_store(), 'init' ) );
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

	/**
	 * Access the object used to interface with the destination store.
	 *
	 * @return WCS_Retry_Store
	 */
	public static function destination_store() {
		if ( empty( self::$destination_store ) ) {
			$class                   = self::get_destination_store_class();
			self::$destination_store = new $class();
		}

		return self::$destination_store;
	}

	/**
	 * Get the class used for instantiating retry storage via self::destination_store()
	 *
	 * @return mixed
	 */
	protected static function get_destination_store_class() {
		return apply_filters( 'wcs_retry_destination_store_class', 'WCS_Retry_Database_Store' );
	}

	/**
	 * Access the object used to interface with the source store.
	 *
	 * @return WCS_Retry_Store
	 */
	public static function source_store() {
		if ( empty( self::$source_store ) ) {
			$class              = self::get_source_store_class();
			self::$source_store = new $class();
		}

		return self::$destination_store;
	}

	/**
	 * Get the class used for instantiating retry storage via self::source_store()
	 *
	 * @return mixed
	 */
	protected static function get_source_store_class() {
		return apply_filters( 'wcs_retry_source_store_class', 'WCS_Retry_Post_Store' );
	}
}