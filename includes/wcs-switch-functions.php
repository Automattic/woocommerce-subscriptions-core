<?php
/**
 * WooCommerce Subscriptions Switch Functions
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
 * Check if a given order was to switch a subscription
 *
 * @param WC_Order|int $order The WC_Order object or ID of a WC_Order order.
 * @since 2.0
 */
function wcs_order_contains_switch( $order ) {

	if ( ! is_object( $order ) ) {
		$order = wc_get_order( $order );
	}

	if ( 'simple' != $order->order_type || isset( $order->subscription_renewal ) ) { // It's a parent order or renewal order

		$is_switch_order = false;

	} else {

		$subscription_ids = get_post_meta( $order->id, '_subscription_switch_order', false );

		if ( ! empty( $subscription_ids ) ) {
			$is_switch_order = true;
		} else {
			$is_switch_order = false;
		}

	}

	return apply_filters( 'woocommerce_subscriptions_is_switch_order', $is_switch_order, $order );
}

/**
 * Get the subscriptions that had an item switch for a given order (if any).
 *
 * @param int|WC_Order $order_id The post_id of a shop_order post or an intsance of a WC_Order object
 * @return array Subscription details in post_id => WC_Subscription form.
 * @since  2.0
 */
function wcs_get_subscriptions_for_switch_order( $order_id ) {

	if ( is_object( $order_id ) ) {
		$order_id = $order_id->id;
	}

	$subscriptions    = array();
	$subscription_ids = get_post_meta( $order_id, '_subscription_switch_order', false );

	foreach( $subscription_ids as $subscription_id ) {
		$subscriptions[] = wcs_get_subscriptions( $subscription_id );
	}

	return $subscriptions;
}
