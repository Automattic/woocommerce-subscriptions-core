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
	 * The dividing line between IDs of retries created by the post and table stores.
	 *
	 * @var int
	 */
	private $initial_autoincrement_id = 0;

	/**
	 * The option we'll use to save the autoincrement id.
	 *
	 * @var string
	 */
	private $initial_autoincrement_id_option;

	/**
	 * Setup the class, if required
	 *
	 * @void
	 */
	public function init() {
		add_action( 'wcs_tables_created', array( $this, 'set_autoincrement' ) );

		self::destination_store()->init();
		self::source_store()->init();

		$this->initial_autoincrement_id_option = WC_Subscriptions_admin::$option_prefix . '_retries_table_autoincrement_id';
		$this->initial_autoincrement_id        = (int) get_option( $this->initial_autoincrement_id_option, 0 );
	}

	/**
	 * Save the details of a retry to the database
	 *
	 * @param WCS_Retry $retry Retry to save.
	 *
	 * @return int the retry's ID
	 */
	public function save( WCS_Retry $retry ) {
		return self::destination_store()->save( $retry );
	}

	/**
	 * Get the details of a retry from the database, and migrates when necessary.
	 *
	 * @param int $retry_id Retry we want to get.
	 *
	 * @return WCS_Retry
	 */
	public function get_retry( $retry_id ) {
		if ( $retry_id < $this->initial_autoincrement_id ) {
			$retry = self::source_store()->get_retry( $retry_id );

			if ( $retry ) {
				self::destination_store()->save( new WCS_Retry( array(
					'order_id' => $retry->get_order_id(),
					'status'   => $retry->get_status(),
					'date_gmt' => $retry->get_date_gmt(),
					'rule_raw' => $retry->get_rule()->get_raw_data(),
				) ) );
			}

			return $retry;
		} else {
			return self::destination_store()->get_retry( $retry_id );
		}
	}

	/**
	 * Get a set of retries from the database
	 *
	 * @param array $args A set of filters.
	 *
	 * @return array An array of WCS_Retry objects
	 */
	public function get_retries( $args ) {
		return self::source_store()->get_retries( $args );
	}

	/**
	 * Get the IDs of all retries from the database for a given order
	 *
	 * @param int $order_id order we want to look for.
	 *
	 * @return array
	 */
	protected function get_retry_ids_for_order( $order_id ) {
		return self::source_store()->get_retry_ids_for_order( $order_id );
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

	/**
	 * Set table autoincrement value to be one higher than the posts table.
	 *
	 * @void
	 */
	public function set_autoincrement() {
		if ( empty( $this->initial_autoincrement_id ) ) {
			$this->initial_autoincrement_id = $this->get_initial_autoincrement_id();
		}

		global $wpdb;
		$wpdb->insert(
			self::destination_store()->get_full_table_name(),
			array(
				'retry_id' => $this->initial_autoincrement_id,
				'order_id' => 0,
			)
		);
		$wpdb->delete(
			self::destination_store()->get_full_table_name(),
			array( 'retry_id' => $this->initial_autoincrement_id )
		);
	}

	/**
	 * Gets the initial autoincrement id for our custom table.
	 *
	 * @return int
	 */
	private function get_initial_autoincrement_id() {
		global $wpdb;

		$id = $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );
		$id ++;

		update_option( $this->initial_autoincrement_id_option, $id );

		return $id;
	}
}