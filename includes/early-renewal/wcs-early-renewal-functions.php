<?php
/**
 * WooCommerce Subscriptions Early Renewal functions.
 *
 * @author   Prospress
 * @category Core
 * @package  WooCommerce Subscriptions/Functions
 * @since    2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Checks the cart to see if it contains an early subscription renewal.
 *
 * @return bool|array The cart item containing the early renewal, else false.
 * @since  2.3.0
 */
function wcs_cart_contains_early_renewal() {

	$cart_item = wcs_cart_contains_renewal();

	if ( $cart_item && ! empty( $cart_item['subscription_renewal']['subscription_renewal_early'] ) ) {
		return $cart_item;
	}

	return false;
}

/**
 * Checks if a user can renew an active subscription early.
 *
 * @param int|WC_Subscription $subscription Post ID of a 'shop_subscription' post, or instance of a WC_Subscription object.
 * @param int $user_id The ID of a user.
 * @since 2.3.0
 * @return bool Whether the user can renew a subscription early.
 */
function wcs_can_user_renew_early( $subscription, $user_id = 0 ) {

	if ( ! is_object( $subscription ) ) {
		$subscription = wcs_get_subscription( $subscription );
	}

	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	if ( ! $subscription ) {
		$can_renew_early = false;
	} elseif ( ! $subscription->has_status( array( 'active' ) ) ) {
		$can_renew_early = false;
	} elseif ( 0 === $subscription->get_total() ) {
		$can_renew_early = false;
	} elseif ( WC_Subscriptions_Synchroniser::subscription_contains_synced_product( $subscription ) ) {
		$can_renew_early = false;
	} elseif ( ! $subscription->payment_method_supports( 'subscription_date_changes' ) ) {
		$can_renew_early = false;
	} else {
		// Make sure all line items still exist.
		$all_line_items_exist = true;

		foreach ( $subscription->get_items() as $line_item ) {
			$product = ( ! empty( $line_item['variation_id'] ) ) ? wc_get_product( $line_item['variation_id'] ) : wc_get_product( $line_item['product_id'] );

			if ( false === $product ) {
				$all_line_items_exist = false;
				break;
			}
		}

		if ( true === $all_line_items_exist ) {
			$can_renew_early = true;
		} else {
			$can_renew_early = false;
		}
	}

	return apply_filters( 'wcs_can_user_renew_early', $can_renew_early, $subscription, $user_id );
}

/**
 * Check if a given order is a subscription renewal order.
 *
 * @param WC_Order|int $order The WC_Order object or ID of a WC_Order order.
 * @since 2.3.0
 * @return bool True if the order contains an early renewal, otherwise false.
 */
function wcs_order_contains_early_renewal( $order ) {

	if ( ! is_object( $order ) ) {
		$order = wc_get_order( $order );
	}

	$subscription_id = absint( wcs_get_objects_property( $order, 'subscription_renewal_early' ) );

	if ( wcs_is_order( $order ) && $subscription_id > 0 ) {
		$is_renewal = true;
	} else {
		$is_renewal = false;
	}

	return apply_filters( 'woocommerce_subscriptions_is_early_renewal_order', $is_renewal, $order );
}

/**
 * Returns a URL for early renewal of a subscription
 * @param  int|WC_Subscription $subscription Post ID of a 'shop_subscription' post, or instance of a WC_Subscription object
 * @return string
 * @since  2.3.0
 */
function wcs_get_early_renewal_link( $subscription ) {
	$subscription_id = is_object( $subscription ) ? $subscription->get_id() : absint( $subscription );
	$link = add_query_arg( array( 'subscription_renewal_early' => $subscription_id, 'subscription_renewal' => 'true' ), get_permalink( wc_get_page_id( 'myaccount' ) ) );
	$link = wp_nonce_url( $link, $subscription_id );

	return apply_filters( 'wcs_get_early_renewal_link', $link, $subscription_id );
}
