<?php
/**
 * WooCommerce Subscriptions Renewal Functions
 *
 * Functions for managing renewal of a subscription.
 *
 * @author 		Prospress
 * @category 	Core
 * @package 	WooCommerce Subscriptions/Functions
 * @version     2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Create a renewal order to record a scheduled subscription payment.
 *
 * This method simply creates an order with the same post meta, order items and order item meta as the subscription
 * passed to it.
 *
 * @param  int | WC_Subscription $subscription Post ID of a 'shop_subscription' post, or instance of a WC_Subscription object
 * @return WC_Subscription
 * @since  2.0
 */
function wcs_create_renewal_order( $subscription ) {
	global $wpdb;

	try {

		$wpdb->query( 'START TRANSACTION' );

		if ( ! is_object( $subscription ) ) {
			$subscription = wcs_get_subscription( $subscription );
		}

		$renewal_order = wc_create_order( array(
			'customer_id'   => $subscription->get_user_id(),
			'customer_note' => __( '', 'woocommerce-subscriptions' ),
		) );

		$renewal_order->post->post_title = sprintf( __( 'Subscription Renewal Order &ndash; %s', 'woocommerce-subscriptions' ), strftime( _x( '%b %d, %Y @ %I:%M %p', 'Order date parsed by strftime', 'woocommerce-subscriptions' ) ) );

		// Keep a record of the subscription to which the renewal order relates
		$renewal_order->post->post_parent = $subscription->id;

		wp_update_post( $renewal_order->post );

		$order_meta_query = $wpdb->prepare(
			"SELECT `meta_key`, `meta_value`
			 FROM {$wpdb->postmeta}
			 WHERE `post_id` = %d
			 AND `meta_key` NOT LIKE '_schedule_%%'
			 AND `meta_key` NOT IN (
				 '_paid_date',
				 '_completed_date',
				 '_order_key',
				 '_edit_lock',
				 '_original_order',
				 '_wc_points_earned',
				 '_transaction_id',
				 '_billing_interval',
				 '_billing_period',
				 '_payment_method',
				 '_payment_method_title'
			 )",
			 $subscription->id
		 );

		// Allow extensions to add/remove order meta
		$order_meta_query = apply_filters( 'wcs_renewal_order_meta_query', $order_meta_query, $subscription );
		$order_meta       = $wpdb->get_results( $order_meta_query, 'ARRAY_A' );
		$order_meta       = apply_filters( 'wcs_renewal_order_meta', $order_meta, $subscription );

		foreach( $order_meta as $meta_item ) {
			add_post_meta( $renewal_order->id, $meta_item['meta_key'], maybe_unserialize( $meta_item['meta_value'] ), true );
		}

		// Keep a record of the original order's ID on the renewal order
		update_post_meta( $renewal_order->id, '_original_order', $subscription->id, true );

		// Copy over line items and allow extensions to add/remove items or item meta
		$items = apply_filters( 'wcs_renewal_order_items', $subscription->get_items( array( 'line_item', 'fee', 'shipping', 'tax' ) ), $subscription );

		foreach ( $items as $item_index => $item ) {

			$item_name = apply_filters( 'wcs_renewal_order_item_name', $item['name'], $item, $subscription );

			// Create order line item on the renewal order
			$recurring_item_id = wc_add_order_item( $renewal_order->id, array(
				'order_item_name' => $item_name,
				'order_item_type' => $item['type'],
			) );

			// Remove recurring line items and set item totals based on recurring line totals
			foreach ( $item['item_meta'] as $meta_key => $meta_value ) {
				wc_add_order_item_meta( $recurring_item_id, $meta_key, maybe_unserialize( $meta_value[0] ) );
			}

		}

		// If we got here, the subscription was created without problems
		$wpdb->query( 'COMMIT' );

		return apply_filters( 'wcs_renewal_order_created', $renewal_order, $subscription );

	} catch ( Exception $e ) {
		// There was an error adding the subscription
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'renewal-order-error', $e->getMessage() );
	}
}
