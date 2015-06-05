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