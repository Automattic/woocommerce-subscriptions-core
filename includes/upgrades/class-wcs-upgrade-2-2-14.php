<?php
/**
 * Repair subscriptions that have been suspended in PayPal but not WooCommerce.
 *
 * If a subscription was suspended at PayPal.com when running Subscriptions v2.1.4 or newer (with the patch
 * from #1831), then it will not have been correctly suspended in WooCommerce.
 *
 * The root issue has been in v2.2.8, with #2199, but the existing subscriptions affected will still need
 * to be updated to ensure their status is correct.
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin/Upgrades
 * @version  2.2.14
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCS_Upgrade_2_2_14 {

	private static $action_hook = 'wcs_repair_subscriptions_suspended_paypal_not_woocommerce';
	private static $batch_size  = 30;

	/**
	 * Schedule an WP-Cron event to run in 5 minutes.
	 *
	 * @since 2.2.14
	 */
	public static function schedule_repair() {
		wc_schedule_single_action( gmdate( 'U' ) + ( MINUTE_IN_SECONDS * 5 ), self::$action_hook );
	}

	/**
	 * Repair a batch of subscriptions.
	 *
	 * Fix any subscriptions that were suspended in PayPal, but were not suspended in WooCommerce.
	 *
	 * @since 2.2.9
	 */
	public static function repair_subscriptions_paypal_suspended() {
		$subscriptions_to_repair = self::get_subscriptions_to_repair();

		foreach ( $subscriptions_to_repair as $subscription_id ) {
			try {
				$subscription = wcs_get_subscription( $subscription_id );
				if ( false === $subscription ) {
					throw new Exception( 'Failed to instantiate subscription object' );
				}

				$subscription->set_status(
					'on-hold',
					__(
						'Subscription suspended due to Database repair script. This subscription was suspended via PayPal.',
						'woocommerce-subscriptions'
					)
				);
			} catch ( Exception $e ) {
				self::log( sprintf( '--- Exception caught repairing subscription %d - exception message: %s ---', $subscription_id, $e->getMessage() ) );
			}
		}

		// If we've processed a full batch, schedule the next batch to be repaired.
		if ( count( $subscriptions_to_repair ) === self::$batch_size ) {
			self::schedule_repair();
		} else {
			self::log( '2.2.14 repair suspended PayPal subscriptions complete' );
		}
	}

	/**
	 * Get a batch of subscriptions to repair.
	 *
	 * @since 2.2.14
	 * @return array A list of subscription ids which may need to be repaired.
	 */
	public static function get_subscriptions_to_repair() {
		$subscriptions_to_repair = get_posts( array(
			'post_type'      => 'shop_subscription',
			'posts_per_page' => self::$batch_size,
			'post_status'    => wcs_get_subscription_status_name( 'active' ),
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_schedule_next_payment',
					'value'   => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
					'compare' => '<=',
					'type'    => 'DATETIME',
				),
				array(
					'key'   => '_payment_method',
					'value' => 'paypal',
				),
				array(
					'key'     => '_paypal_subscription_id',
					'value'   => 'B-%',
					'compare' => 'NOT LIKE',
				),
			),
		) );

		return $subscriptions_to_repair;
	}

	/**
	 * Add a message to the wcs-upgrade-subscriptions-paypal-suspended log
	 *
	 * @param string The message to be logged
	 *
	 * @since 2.2.14
	 */
	protected static function log( $message ) {
		WCS_Upgrade_Logger::add( $message, 'wcs-upgrade-subscriptions-paypal-suspended' );
	}
}
