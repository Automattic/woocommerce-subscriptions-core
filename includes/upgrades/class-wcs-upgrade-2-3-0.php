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
 * @version  2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCS_Repair_Suspended_PayPal_Subscriptions extends WCS_Background_Updater {

	/**
	 * WC Logger instance for logging messages.
	 *
	 * @var WC_Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param WC_Logger $logger The WC Logger instance.
	 * @since 2.3.0
	 */
	public function __construct( WC_Logger $logger ) {
		$this->scheduled_hook = 'wcs_repair_subscriptions_suspended_paypal_not_woocommerce';
		$this->logger         = $logger;
	}

	/**
	 * Schedule the @see $this->scheduled_hook action to start repairing subscriptions in
	 * @see $this->time_limit seconds (60 seconds by default).
	 *
	 * @since 2.3.0
	 */
	public function schedule_repair() {
		$this->schedule_background_update();
	}

	/**
	 * Repair a batch of subscriptions.
	 *
	 * Fix any subscriptions that were suspended in PayPal, but were not suspended in WooCommerce.
	 *
	 * @since 2.3.0
	 */
	public static function repair_subscriptions_paypal_suspended() {
		$subscriptions_to_repair = self::get_subscriptions_to_repair();

		foreach ( $subscriptions_to_repair as $subscription_id ) {
			try {
				$subscription = wcs_get_subscription( $subscription_id );
				if ( false === $subscription ) {
					throw new Exception( 'Failed to instantiate subscription object' );
				}

				$subscription->update_status(
					'on-hold',
					__(
						'Subscription suspended by Database repair script. This subscription was suspended via PayPal.',
						'woocommerce-subscriptions'
					)
				);
				self::log( sprintf( 'Subscription ID %d suspended from 2.3.0 PayPal database repair script.', $subscription_id ) );
			} catch ( Exception $e ) {
				self::log( sprintf( '--- Exception caught repairing subscription %d - exception message: %s ---', $subscription_id, $e->getMessage() ) );
			}
		}

		// If we've processed a full batch, schedule the next batch to be repaired.
		if ( count( $subscriptions_to_repair ) === self::$batch_size ) {
			self::schedule_repair();
		} else {
			self::log( '2.3.0 Repair Suspended PayPal Subscriptions complete' );
		}
	}

	/**
	 * Get a list of subscriptions to repair.
	 *
	 * @since 2.3.0
	 * @return array A list of subscription ids which may need to be repaired.
	 */
	protected function get_items_to_update() {
		return get_posts( array(
			'posts_per_page' => 20,
			'post_type'      => 'shop_subscription',
			'post_status'    => wcs_sanitize_subscription_status_key( 'active' ),
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_schedule_next_payment',
					'value'   => date( 'Y-m-d H:i:s', wcs_strtotime_dark_knight( '-3 days' ) ),
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
	}

	/**
	 * Add a message to the wcs-upgrade-subscriptions-paypal-suspended log
	 *
	 * @param string $message The message to be logged
	 * @since 2.3.0
	 */
	protected function log( $message ) {
		$this->logger->add( 'wcs-upgrade-subscriptions-paypal-suspended', $message );
	}
}
