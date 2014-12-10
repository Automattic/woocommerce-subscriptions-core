<?php
/**
 * WooCommerce Subscriptions Deprecated Functions
 *
 * Functions for handling backward compatibility with the Subscription 1.n
 * data structure and reference system (i.e. $subscription_key instead of a
 * post ID)
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
 * Return the post ID of a WC_Subscription object for the given subscription key (if one exists).
 *
 * @param string $subscription_key A subscription key in the deprecated form created by @see WC_Subscriptions_Manager::get_subscription_key()
 * @return int|null The post ID for the subscription if it can be found (i.e. an order exists) or null if no order exists for the subscription.
 * @since 2.0
 */
function wcs_get_subscription_id_from_key( $subscription_key ) {
	global $wpdb;

	$order_and_product_id = explode( '_', $subscription_key );

	$subscription_ids = array();

	// If we have an order ID and product ID, query based on that
	if ( isset( $order_and_product_id[0] ) && isset( $order_and_product_id[1] ) ) {

		$subscription_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT DISTINCT order_items.order_id FROM {$wpdb->prefix}woocommerce_order_items as order_items
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta ON order_items.order_item_id = itemmeta.order_item_id
				LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
			WHERE posts.post_type = 'shop_subscription'
			WHERE posts.post_parent = %d
				AND itemmeta.meta_value %d
				AND itemmeta.meta_key IN ( '_variation_id', '_product_id' )"
		), $order_and_product_ids[0], $order_and_product_id[1] );

	} elseif ( isset( $order_and_product_id[0] ) ) {

		$subscription_ids = get_posts( array(
			'posts_per_page' => 1,
			'post_parent'    => $order_and_product_ids[0],
			'post_status'    => 'any',
			'post_type'      => 'shop_subscription',
			'fields'         => 'ids',
		) );

	}

	return ( ! empty( $subscription_ids ) ) ? $subscription_ids[0] : null;
}

/**
 * Return an instance of a WC_Subscription object for the given subscription key (if one exists).
 *
 * @param string $subscription_key A subscription key in the deprecated form created by @see self::get_subscription_key()
 * @return WC_Subscription|null The subscription object if it can be found (i.e. an order exists) or null if no order exists for the subscription (i.e. it was manually created).
 * @since 2.0
 */
function wcs_get_subscription_from_key( $subscription_key ) {

	$subscription_id = wcs_get_subscription_id_from_key( $subscription_key );

	if ( null !== $subscription_id && is_int( $subscription_id ) ) {
		$subscription = wcs_get_subscription( $subscription_id );
	} else {
		$subscription = null;
	}

	return $subscription;
}

/**
 * Return an associative array of a given subscriptions details (if it exists) in the pre v2.0 data structure.
 *
 * @param WC_Subscription $subscription An instance of WC_Subscription
 * @return array Subscription details
 * @since 2.0
 */
function wcs_get_subscription_in_deprecated_structure( WC_Subscription $subscription ) {

	$completed_payments = array();

	if ( $subscription->get_completed_payment_count() ) {
		if ( isset( $subscription->order->paid_date ) ) {
			$completed_payments[] = $subscription->order->paid_date;
		}

		$paid_renewal_order_ids = get_posts( array(
			'posts_per_page' => -1,
			'post_parent'    => $subscription->id,
			'post_status'    => 'any',
			'post_type'      => 'shop_order',
			'orderby'        => 'date',
			'order'          => 'desc',
			'fields'         => 'ids',
			'meta_key'       => '_paid_date',
			'meta_compare'   => 'EXISTS',
		) );

		foreach( $paid_renewal_order_ids as $paid_renewal_order_id ) {
			$completed_payments[] = get_post_meta( $paid_renewal_order_id, '_paid_date', true );
		}
	}

	$item = array_pop( $subscription->get_items() );

	if ( ! empty( $item ) ) {

		$deprecated_subscription_object = array(
			'order_id'           => $subscription->order->id,
			'product_id'         => isset( $item['product_id'] ) ? $item['product_id'] : 0,
			'variation_id'       => isset( $item['variation_id'] ) ? $item['variation_id'] : 0,
			'status'             => $subscription->get_status(),

			// Subscription billing details
			'period'             => $subscription->billing_period,
			'interval'           => $subscription->billing_interval,
			'length'             => null, // Subscriptions no longer have a length, just an expiration date

			// Subscription dates
			'start_date'         => $subscription->get_date( 'start' ),
			'expiry_date'        => $subscription->get_date( 'end' ),
			'end_date'           => $subscription->has_ended() ? $subscription->get_date( 'end' ) : 0,
			'trial_expiry_date'  => $subscription->get_date( 'trial_end' ),

			// Payment & status change history
			'failed_payments'    => $subscription->failed_payment_count,
			'completed_payments' => $completed_payments,
			'suspension_count'   => $subscription->suspension_count,
			'last_payment_date'  => $subscription->get_date( 'last_payment' ),
		);

	} else {

		$deprecated_subscription_object = array();

	}

	return $deprecated_subscription_object;
}
