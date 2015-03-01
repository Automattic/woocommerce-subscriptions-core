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
			'customer_note' => $subscription->customer_note,
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

/**
 * Check if a given order is a subscription renewal order.
 *
 * @param WC_Order|int $order The WC_Order object or ID of a WC_Order order.
 * @since 2.0
 */
function wcs_is_renewal_order( $order ) {

	if ( ! is_object( $order ) ) {
		$order = wc_get_order( $order );
	}

	if ( 0 == $order->post->post_parent ) { // It's a parent order or original order

		$is_renewal = false;

	} else {

		$subscription = wcs_get_subscription( $order->post->post_parent );

		if ( false === $subscription ) { // It's parent is something other than a subscription
			$is_renewal = false;
		} else {
			$is_renewal = true;
		}

	}

	return apply_filters( 'woocommerce_subscriptions_is_renewal_order', $is_renewal, $order );
}

/**
 * Checks the cart to see if it contains a subscription product renewal.
 *
 * @param  bool | Array The cart item containing the renewal, else false.
 * @return string
 * @since  2.0
 */
function wcs_cart_contains_renewal() {

	$contains_renewal = false;

	if ( ! empty( WC()->cart->cart_contents ) ) {
		foreach ( WC()->cart->cart_contents as $cart_item ) {
			if ( isset( $cart_item['subscription_renewal'] ) ) {
				$contains_renewal = $cart_item;
				break;
			}
		}
	}

	return $contains_renewal;
}

/**
 * Checks the cart to see if it contains a subscription product renewal for a failed renewal payment.
 *
 * @param  bool | Array The cart item containing the renewal, else false.
 * @return string
 * @since  2.0
 */
function wcs_cart_contains_failed_renewal_order_payment() {

	$contains_renewal = false;
	$cart_item        = wcs_cart_contains_renewal();

	if ( false !== $cart_item && isset( $cart_item['subscription_renewal']['renewal_order_id'] ) ) {
		$renewal_order = wc_get_order( $cart_item['subscription_renewal']['renewal_order_id'] );
		if ( $renewal_order->has_status( 'failed' ) ) {
			$contains_renewal = $cart_item;
		}
	}

	return $contains_renewal;
}

/**
 * Get the subscription to which a renewal order relates.
 *
 * @param WC_Order|int $order The WC_Order object or ID of a WC_Order order.
 * @since 2.0
 */
function wcs_get_subscription_for_renewal_order( $renewal_order ) {

	if ( ! is_object( $renewal_order ) ) {
		$renewal_order = new WC_Order( $renewal_order );
	}

	if ( ! wcs_is_renewal_order( $renewal_order ) ) {
		throw new InvalidArgumentException( __( __METHOD__ . '() expects parameter one to be a child renewal order.', 'woocommerce-subscriptions' ) );
	}

	return apply_filters( 'woocommerce_subscription_for_renewal_order', wcs_get_subscription( $renewal_order->post->post_parent ), $renewal_order );
}
