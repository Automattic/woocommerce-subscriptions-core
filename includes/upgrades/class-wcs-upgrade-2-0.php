<?php
/**
 * Upgrade subscriptions data to v2.0
 *
 * @author		Prospress
 * @category	Admin
 * @package		WooCommerce Subscriptions/Admin/Upgrades
 * @version		2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Upgrade_2_0 {

	/**
	 * Migrate subscriptions out of order item meta and into post/post meta tables for their own post type.
	 *
	 * @since 2.0
	 */
	public static function upgrade_subscriptions( $batch_size ) {
		global $wpdb;

		WCS_Upgrade_Logger::add( sprintf( 'Upgrading batch of %d subscriptions', $batch_size ) );

		$upgraded_subscription_count = 0;

		foreach ( self::get_subscriptions( $batch_size ) as $original_order_item_id => $old_subscription ) {

			try {

				// don't allow data to be half upgraded on a subscription (but we need the subscription to be the atomic level, not the whole batch, to ensure that resubscribe and switch updates in the same batch have the new subscription available)
				$wpdb->query( 'START TRANSACTION' );

				WCS_Upgrade_Logger::add( sprintf( 'For order %d: beginning subscription upgrade process', $old_subscription['order_id'] ) );

				$original_order = wc_get_order( $old_subscription['order_id'] );

				// If we're still in a prepaid term, the new subscription has the new pending cancellation status
				if ( 'cancelled' == $old_subscription['status'] && false != wc_next_scheduled_action( 'subscription_end_of_prepaid_term', array( 'user_id' => $old_subscription['user_id'], 'subscription_key' => $old_subscription['subscription_key'] ) ) ) {
					$subscription_status = 'pending-cancel';
				} elseif ( 'trash' == $old_subscription['status'] ) {
					$subscription_status = 'cancelled'; // we'll trash it properly after migrating it
				} else {
					$subscription_status = $old_subscription['status'];
				}

				// Create a new subscription for this user
				$new_subscription = wcs_create_subscription( array(
					'status'           => $subscription_status,
					'order_id'         => $old_subscription['order_id'],
					'customer_id'      => $old_subscription['user_id'],
					'start_date'       => $old_subscription['start_date'],
					'billing_period'   => $old_subscription['period'],
					'billing_interval' => $old_subscription['interval'],
					'order_version'    => ( ! empty( $original_order->order_version ) ) ? $original_order->order_version : '', // Subscriptions will default to WC_Version if $original_order->order_version is not set, but we want the version set at the time of the order
				) );

				if ( ! is_wp_error( $new_subscription ) ) {

					WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: post created', $new_subscription->id ) );

					// If the subscription was in the trash, now that we've set on the meta on it, we need to trash it
					if ( 'trash' == $old_subscription['status'] ) {
						wp_trash_post( $new_subscription->id );
					}

					WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: upgrade complete', $new_subscription->id ) );

				} else {

					WCS_Upgrade_Logger::add( sprintf( '!!! For order %d: unable to create subscription. Error: %s', $old_subscription['order_id'], $new_subscription->get_error_message() ) );

				}

				// If we got here, the batch was upgraded without problems
				$wpdb->query( 'COMMIT' );

				$upgraded_subscription_count++;

			} catch ( Exception $e ) {

				// we couldn't upgrade this subscription don't commit the query
				$wpdb->query( 'ROLLBACK' );

				throw $e;
			}

			if ( $upgraded_subscription_count >= $batch_size ) {
				break;
			}
		}

		WCS_Upgrade_Logger::add( sprintf( 'Upgraded batch of %d subscriptions', $upgraded_subscription_count ) );

		return $upgraded_subscription_count;
	}

	/**
	 * Gets an array of subscriptions from the v1.5 database structure and returns them in the in the v1.5 structure of
	 * 'order_item_id' => subscripton details array().
	 *
	 * The subscription will be orders from oldest to newest, which is important because self::migrate_resubscribe_orders()
	 * method expects a subscription to exist in order to migrate the resubscribe meta data correctly.
	 *
	 * @param int $batch_size The number of subscriptions to return.
	 * @return array Subscription details in the v1.5 structure of 'order_item_id' => array()
	 * @since 2.0
	 */
	private static function get_subscriptions( $batch_size ) {
		global $wpdb;

		$query = sprintf(
			"SELECT meta.*, items.* FROM `{$wpdb->prefix}woocommerce_order_itemmeta` AS meta
			LEFT JOIN `{$wpdb->prefix}woocommerce_order_items` AS items USING (order_item_id)
			LEFT JOIN (
				SELECT a.order_item_id FROM `{$wpdb->prefix}woocommerce_order_itemmeta` AS a
				LEFT JOIN (
					SELECT `{$wpdb->prefix}woocommerce_order_itemmeta`.order_item_id FROM `{$wpdb->prefix}woocommerce_order_itemmeta`
					WHERE `{$wpdb->prefix}woocommerce_order_itemmeta`.meta_key = '_subscription_status'
				) AS s
				USING (order_item_id)
				WHERE 1=1
				AND a.order_item_id = s.order_item_id
				AND a.meta_key = '_subscription_start_date'
				ORDER BY CASE WHEN CAST(a.meta_value AS DATETIME) IS NULL THEN 1 ELSE 0 END, CAST(a.meta_value AS DATETIME) ASC
				LIMIT 0, %s
			) AS a3 USING (order_item_id)
			WHERE meta.meta_key REGEXP '_subscription_(.*)|_product_id|_variation_id'
			AND meta.order_item_id = a3.order_item_id", $batch_size );

		$wpdb->query( 'SET SQL_BIG_SELECTS = 1;' );

		$raw_subscriptions = $wpdb->get_results( $query );

		$subscriptions = array();

		// Create a backward compatible structure
		foreach ( $raw_subscriptions as $raw_subscription ) {

			if ( ! isset( $raw_subscription->order_item_id ) ) {
				continue;
			}

			if ( ! array_key_exists( $raw_subscription->order_item_id, $subscriptions ) ) {
				$subscriptions[ $raw_subscription->order_item_id ] = array(
					'order_id' => $raw_subscription->order_id,
					'name'     => $raw_subscription->order_item_name,
				);

				$subscriptions[ $raw_subscription->order_item_id ]['user_id'] = get_post_meta( $raw_subscription->order_id, '_customer_user', true );
			}

			$meta_key = str_replace( '_subscription', '', $raw_subscription->meta_key );
			$meta_key = substr( $meta_key, 0, 1 ) == '_' ? substr( $meta_key, 1 ) : $meta_key;

			if ( 'product_id' === $meta_key ) {
				$subscriptions[ $raw_subscription->order_item_id ]['subscription_key'] = $subscriptions[ $raw_subscription->order_item_id ]['order_id'] . '_' . $raw_subscription->meta_value;
			}

			$subscriptions[ $raw_subscription->order_item_id ][ $meta_key ] = maybe_unserialize( $raw_subscription->meta_value );
		}

		return $subscriptions;
	}

}