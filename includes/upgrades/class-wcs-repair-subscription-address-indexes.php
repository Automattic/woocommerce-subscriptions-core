<?php
/**
 * Repair subscriptions that have missing address indexes.
 *
 * Post WooCommerce Subscriptions 2.3 address indexes are used when searching via the admin subscriptions table.
 * Subscriptions created prior to WC 3.0 won't have those meta keys set and so this repair script will generate them.
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin/Upgrades
 * @version  2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCS_Repair_Subscription_Address_Indexes {

	private static $action_hook = 'wcs_add_missing_subscription_address_indexes';
	private static $batch_size  = 30;

	/**
	 * Schedule the repair function to run in 5 minutes.
	 *
	 * @since 2.3.0
	 */
	public static function schedule_repair() {
		if ( false === wc_next_scheduled_action( self::$action_hook ) ) {
			wc_schedule_single_action( gmdate( 'U' ) + ( MINUTE_IN_SECONDS * 5 ), self::$action_hook );
		}
	}

	/**
	 * Repair a batch of subscriptions with missing address indexes.
	 *
	 * @since 2.3.0
	 */
	public static function repair_subscriptions_without_address_indexes() {
		$subscriptions_to_repair = self::get_subscriptions_to_repair();

		foreach ( $subscriptions_to_repair as $subscription_id ) {
			try {
				$subscription = wcs_get_subscription( $subscription_id );

				if ( false === $subscription ) {
					throw new Exception( 'Failed to instantiate subscription object' );
				}

				update_post_meta( $subscription_id, '_billing_address_index', implode( ' ', $subscription->get_address( 'billing' ) ) );

				// If the subscription has a shipping address set (requires shipping), set the shipping address index.
				if ( $subscription->get_shipping_address_1() || $subscription->get_shipping_address_2() ) {
					update_post_meta( $subscription_id, '_shipping_address_index', implode( ' ', $subscription->get_address( 'shipping' ) ) );
				}

				self::log( sprintf( 'Subscription ID %d address index(es) added.', $subscription_id ) );
			} catch ( Exception $e ) {
				self::log( sprintf( '--- Exception caught repairing subscription %d - exception message: %s ---', $subscription_id, $e->getMessage() ) );
			}
		}

		// If we've processed a full batch, schedule the next batch to be repaired.
		if ( count( $subscriptions_to_repair ) === self::$batch_size ) {
			self::schedule_repair();
		} else {
			self::log( 'Add address indexes for subscriptions complete' );
		}
	}

	/**
	 * Get a batch of subscriptions which need address indexes.
	 *
	 * @since 2.3.0
	 * @return array A list of subscription ids which need address indexes.
	 */
	private static function get_subscriptions_to_repair() {
		$subscriptions_to_repair = get_posts( array(
			'post_type'      => 'shop_subscription',
			'posts_per_page' => self::$batch_size,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_billing_address_index',
					'compare' => 'NOT EXISTS',
				),
			),
		) );

		return $subscriptions_to_repair;
	}

	/**
	 * Add a message to the wcs-add-subscriptions-address-indexes log.
	 *
	 * @since 2.3.0
	 * @param string The message to be logged
	 */
	protected static function log( $message ) {
		WCS_Upgrade_Logger::add( $message, 'wcs-add-subscription-address-indexes' );
	}
}
