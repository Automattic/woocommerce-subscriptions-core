<?php
/**
 * Subscription Cache Manager Class
 *
 * Implements methods to deal with the soft caching layer
 *
 * @class    WCS_Cache_Manager
 * @version  2.0
 * @package  WooCommerce Subscriptions/Classes
 * @category Class
 * @author   Gabor Javorszky
 */
class WCS_Cache_Manager {

	private static $logger;

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'woocommerce_loaded', __CLASS__ . '::load_logger' );
	}

	/**
	 * Initialises a WC_Logger instance to log cache purges with WCS_DEBUG on
	 */
	public static function load_logger() {
		self::$logger = new WC_Logger();
	}

	public static function log( $message ) {
		if ( defined( 'WCS_DEBUG' ) && WCS_DEBUG ) {
			self::$logger->add( 'wcs-cache', $message );
		}
	}

	/**
	 * Clearing for orders / subscriptions with sanitizing bits
	 *
	 * @param $post_id integer the ID of an order / subscription
	 */
	public static function purge_subscription_cache_on_update( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( 'shop_subscription' !== $post_type && 'shop_order' !== $post_type ) {
			return;
		}

		if ( 'shop_subscription' === $post_type ) {
			self::log( 'ID is subscription, calling wcs_clear_related_order_cache for ' . $post_id );

			self::wcs_clear_related_order_cache( $post_id );
		} else {

			self::log( 'ID is order, getting subscription.' );

			$subscription = wcs_get_subscriptions_for_order( $post_id );

			if ( empty( $subscription ) ) {
				self::log( 'No sub for this ID: ' . $post_id );
				return;
			}
			$subscription = array_shift( $subscription );

			self::log( 'Got subscription, calling wcs_clear_related_order_cache for ' . $subscription->id );

			self::wcs_clear_related_order_cache( $subscription->id );
		}
	}

	/**
	 * Clearing cache when a post is deleted
	 *
	 * @param $post_id integer the ID of a post
	 */
	public static function purge_delete( $post_id ) {
		if ( 'shop_order' !== get_post_type( $post_id ) ) {
			return;
		}

		$linked_subscription = get_post_meta( $post_id, '_subscription_renewal', false );

		// don't call this if there's nothing to call on
		if ( $linked_subscription ) {
			self::log( 'Calling purge from ' . current_filter() . ' on ' . $linked_subscription[0] );
			self::purge_subscription_cache_on_update( $linked_subscription[0] );
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
	public static function purge_from_metadata( $meta_id, $object_id, $meta_key, $_meta_value ) {
		if ( '_subscription_renewal' !== $meta_key || 'shop_order' !== get_post_type( $object_id ) ) {
			return;
		}

		self::log( 'Calling purge from ' . current_filter() . ' on ' . $object_id . ' and ' . $_meta_value . ' due to _subscription_renewal meta.' );

		self::purge_subscription_cache_on_update( $_meta_value );
		self::purge_subscription_cache_on_update( $object_id );
	}


	public static function wcs_clear_related_order_cache( $id = null ) {
		// if nothing was passed in, there's nothing to delete
		if ( null === $id ) {
			return;
		}

		// if it's not a Subscription, we don't deal with it
		if ( is_object( $id ) && $id instanceof WC_Subscription ) {
			$id = $id->id;
		} elseif ( is_numeric( $id ) ) {
			$id = absint( $id );
		} else {
			return;
		}

		$key = tlc_transient( 'wcs-related-orders-to-' . $id )->key;
		$logger = new WC_Logger();

		$logger->add( 'wcs-cache', 'In the clearing, key being purged is this: ' . "\n\n{$key}\n\n" );
		// have to do this manually for now
		delete_transient( 'tlc__' . $key );
		delete_transient( 'tlc_up__' . $key );
	}
}

WCS_Cache_Manager::get_instance();
