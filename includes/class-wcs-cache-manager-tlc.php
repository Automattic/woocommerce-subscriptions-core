<?php
/**
 * Subscription Cache Manager Class using TLC transients
 *
 * Implements methods to deal with the soft caching layer
 *
 * @class    WCS_Cache_Manager_TLC
 * @version  2.0
 * @package  WooCommerce Subscriptions/Classes
 * @category Class
 * @author   Gabor Javorszky
 */
class WCS_Cache_Manager_TLC extends WCS_Cache_Manager {

	public $logger = null;

	// TODO: I should be a configuration rather than hardcode
	public static $cleanup_threshold = 1024 * 1024 * 100;

	public function __construct() {
		_deprecated_function( __METHOD__, '2.1.2' );
		add_action( 'woocommerce_loaded', array( $this, 'load_logger' ) );

		// Add filters for update / delete / trash post to purge cache
		add_action( 'trashed_post', array( $this, 'purge_delete' ), 9999 ); // trashed posts aren't included in 'any' queries
		add_action( 'untrashed_post', array( $this, 'purge_delete' ), 9999 ); // however untrashed posts are
		add_action( 'deleted_post', array( $this, 'purge_delete' ), 9999 ); // if forced delete is enabled
		add_action( 'updated_post_meta', array( $this, 'purge_from_metadata' ), 9999, 4 ); // tied to _subscription_renewal
		add_action( 'deleted_post_meta', array( $this, 'purge_from_metadata' ), 9999, 4 ); // tied to _subscription_renewal
		add_action( 'added_post_meta', array( $this, 'purge_from_metadata' ), 9999, 4 ); // tied to _subscription_renewal

		add_action( 'init', array( $this, 'initialize_cron_check_size' ) );
	}

	/**
	 * If the log is bigger than a threshold it will be
	 * truncated to 0 bytes.
	 */
	public static function cleanup_logs() {
		$handle = 'wcs-cache';
		if ( is_callable( 'wc_get_log_file_path' ) ) {
			$file = wc_get_log_file_path( $handle );
		} else {
			$file = WC()->plugin_path() . '/logs/' .  $handle . '-' . sanitize_file_name( wp_hash( $handle ) ) . '.txt';
		}

		if ( filesize( $file ) >= self::$cleanup_threshold ) {
			$size = 64 * 1024;
			// read the last $size bytes of the logs (it's useful to keep
			// some log data), from this chunk of data we only care
			// about the latest 1000 entries
			$fp = fopen( $file, 'r' );
			fseek( $fp, -1 * $size, SEEK_END );
			$data = '';
			while ( ! feof( $fp ) ) {
				$data .= fread( $fp, $size );
			}
			fclose( $fp );

			// Remove first line (which is probably incomplete)
			// and also any empty line
			$lines = explode( "\n", $data );
			$lines = array_filter( array_slice( $lines, 1 ) );
			$lines = array_filter( array_slice( $lines, -1000 ) );
			$lines[] = '---- log file automatically truncated ' . gmdate( 'Y-m-d H:i:s' ) . ' ---';

			$fp = fopen( $file, 'w' );
			fwrite( $fp, implode( "\n", $lines ) );
			fclose( $fp );
		}
	}

	/**
	 * Creates a weekly crontab (if it doesn't exists) that
	 * will truncate the log file if it goes bigger than a
	 * threshold
	 */
	public function initialize_cron_check_size() {
		$hook = 'wcs_cleanup_big_logs';
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time(), 'daily', $hook );
		}

		add_action( $hook, __CLASS__ . '::cleanup_logs' );
	}

	/**
	 * Attaches logger
	 */
	public function load_logger() {
		_deprecated_function( __METHOD__, '2.1.2', 'WC_Subscriptions::$cache->' . __FUNCTION__ );
		$this->logger = new WC_Logger();
	}

	/**
	 * Wrapper function around WC_Logger->log
	 *
	 * @param string $message Message to log
	 */
	public function log( $message ) {
		_deprecated_function( __METHOD__, '2.1.2', 'WC_Subscriptions::$cache->' . __FUNCTION__ );
		if ( defined( 'WCS_DEBUG' ) && WCS_DEBUG ) {
			$this->logger->add( 'wcs-cache', $message );
		}
	}

	/**
	 * Wrapper function around our cache library.
	 *
	 * @param string $key The key to cache the data with
	 * @param string|array $callback name of function, or array of class - method that fetches the data
	 * @param array $params arguments passed to $callback
	 * @param integer $expires number of seconds for how long to keep the cache. Don't set it to 0, as the cache will be autoloaded. Default is a week.
	 *
	 * @return bool|mixed
	 */
	public function cache_and_get( $key, $callback, $params = array(), $expires = WEEK_IN_SECONDS ) {
		_deprecated_function( __METHOD__, '2.1.2', 'WC_Subscriptions::$cache->' . __FUNCTION__ );
		$expires = absint( $expires );

		$transient = tlc_transient( $key )
			->updates_with( $callback, $params )
			->expires_in( $expires );

		return $transient->get();
	}

	/**
	 * Clearing for orders / subscriptions with sanitizing bits
	 *
	 * @param $post_id integer the ID of an order / subscription
	 */
	public function purge_subscription_cache_on_update( $post_id ) {
		_deprecated_function( __METHOD__, '2.1.2', 'WC_Subscriptions::$cache->' . __FUNCTION__ );
		$post_type = get_post_type( $post_id );

		if ( 'shop_subscription' !== $post_type && 'shop_order' !== $post_type ) {
			return;
		}

		if ( 'shop_subscription' === $post_type ) {
			$this->log( 'ID is subscription, calling wcs_clear_related_order_cache for ' . $post_id );

			$this->wcs_clear_related_order_cache( $post_id );
		} else {

			$this->log( 'ID is order, getting subscription.' );

			$subscription = wcs_get_subscriptions_for_order( $post_id );

			if ( empty( $subscription ) ) {
				$this->log( 'No sub for this ID: ' . $post_id );
				return;
			}
			$subscription = array_shift( $subscription );

			$this->log( 'Got subscription, calling wcs_clear_related_order_cache for ' . $subscription->get_id() );

			$this->wcs_clear_related_order_cache( $subscription->get_id() );
		}
	}

	/**
	 * Clearing cache when a post is deleted
	 *
	 * @param $post_id integer the ID of a post
	 */
	public function purge_delete( $post_id ) {
		_deprecated_function( __METHOD__, '2.1.2', 'WC_Subscriptions::$cache->' . __FUNCTION__ );
		if ( 'shop_order' !== get_post_type( $post_id ) ) {
			return;
		}

		$linked_subscription = get_post_meta( $post_id, '_subscription_renewal', false );

		// don't call this if there's nothing to call on
		if ( $linked_subscription ) {
			$this->log( 'Calling purge from ' . current_filter() . ' on ' . $linked_subscription[0] );
			$this->purge_subscription_cache_on_update( $linked_subscription[0] );
		}
	}

	/**
	 * When the _subscription_renewal metadata is added / deleted / updated on the Order, we need to initiate cache invalidation for both the new
	 * value of the meta ($_meta_value), and the object it's being added to: $object_id.
	 *
	 * @param $meta_id integer the ID of the meta in the meta table
	 * @param $object_id integer the ID of the post we're updating on
	 * @param $meta_key string the meta_key in the table
	 * @param $_meta_value mixed the value we're deleting / adding / updating
	 */
	public function purge_from_metadata( $meta_id, $object_id, $meta_key, $_meta_value ) {
		_deprecated_function( __METHOD__, '2.1.2', 'WC_Subscriptions::$cache->' . __FUNCTION__ );
		if ( '_subscription_renewal' !== $meta_key || 'shop_order' !== get_post_type( $object_id ) ) {
			return;
		}

		$this->log( 'Calling purge from ' . current_filter() . ' on object ' . $object_id . ' and meta value ' . $_meta_value . ' due to _subscription_renewal meta.' );

		$this->purge_subscription_cache_on_update( $_meta_value );
		$this->purge_subscription_cache_on_update( $object_id );
	}

	/**
	 * Wrapper function to clear cache that relates to related orders
	 *
	 * @param null $id
	 */
	public function wcs_clear_related_order_cache( $id = null ) {
		_deprecated_function( __METHOD__, '2.1.2', 'WC_Subscriptions::$cache->' . __FUNCTION__ );
		// if nothing was passed in, there's nothing to delete
		if ( null === $id ) {
			return;
		}

		// if it's not a Subscription, we don't deal with it
		if ( is_object( $id ) && $id instanceof WC_Subscription ) {
			$id = $id->get_id();
		} elseif ( is_numeric( $id ) ) {
			$id = absint( $id );
		} else {
			return;
		}

		$key = tlc_transient( 'wcs-related-orders-to-' . $id )->key;

		$this->log( 'In the clearing, key being purged is this: ' . "\n\n{$key}\n\n" );

		$this->delete_cached( $key );
	}

	/**
	 * Delete cached data with key
	 *
	 * @param string $key Key that needs deleting
	 */
	public function delete_cached( $key ) {
		_deprecated_function( __METHOD__, '2.1.2', 'WC_Subscriptions::$cache->' . __FUNCTION__ );
		if ( ! is_string( $key ) || empty( $key ) ) {
			return;
		}
		// have to do this manually for now
		delete_transient( 'tlc__' . $key );
		delete_transient( 'tlc_up__' . $key );
	}
}
