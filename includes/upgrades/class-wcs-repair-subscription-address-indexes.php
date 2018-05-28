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

class WCS_Repair_Subscription_Address_Indexes extends WCS_Background_Updater {

	/**
	 * WC_Logger instance for logging messages to.
	 *
	 * @var WC_Logger
	 */
	protected $logger;

	/**
	 * Constructor
	 *
	 * @param WC_Logger $logger The WC_Logger instance.
	 * @since 2.3.0
	 */
	public function __construct( WC_Logger $logger ) {
		$this->scheduled_hook = 'wcs_add_missing_subscription_address_indexes';
		$this->logger         = $logger;
	}

	/**
	 * Schedule the @see $this->scheduled_hook action to start updating subscriptions in
	 * @see $this->time_limit seconds (60 seconds by default).
	 *
	 * @since 2.3.0
	 */
	public function schedule_repair() {
		$this->schedule_background_update();
	}

	/**
	 * Update a subscription, setting its address indexes.
	 *
	 * @since 2.3.0
	 */
	protected function update_item( $subscription_id ) {
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

			$this->log( sprintf( 'Subscription ID %d address index(es) added.', $subscription_id ) );
		} catch ( Exception $e ) {
			$this->log( sprintf( '--- Exception caught repairing subscription %d - exception message: %s ---', $subscription_id, $e->getMessage() ) );
		}
	}

	/**
	 * Get a batch of subscriptions which need address indexes.
	 *
	 * @since 2.3.0
	 * @return array A list of subscription ids which need address indexes.
	 */
	protected function get_items_to_update() {
		return get_posts( array(
			'post_type'      => 'shop_subscription',
			'posts_per_page' => 20,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_billing_address_index',
					'compare' => 'NOT EXISTS',
				),
			),
		) );
	}

	/**
	 * Add a message to the wcs-add-subscriptions-address-indexes log.
	 *
	 * @since 2.3.0
	 * @param string The message to be logged
	 */
	protected function log( $message ) {
		$this->logger->add( 'wcs-add-subscription-address-indexes', $message );
	}
}
