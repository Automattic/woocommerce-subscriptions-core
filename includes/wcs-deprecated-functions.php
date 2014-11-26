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

	$order_and_product_id = explode( '_', $subscription_key );

	$subscription_ids = get_posts( array(
		'posts_per_page' => 1,
		'post_parent'    => $order_and_product_ids[0],
		'post_status'    => 'any',
		'post_type'      => 'shop_subscription',
		'fields'         => 'ids',
	) );

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

	$subscription_id = self::get_subscription_id_from_key( $subscription_key );

	if ( null !== $subscription_id && is_int( $subscription_id ) ) {
		$subscription = wcs_get_subscription( $subscription_id );
	} else {
		$subscription = null;
	}

	return $subscription;
}

