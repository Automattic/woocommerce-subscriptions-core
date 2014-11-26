<?php
/**
 * WooCommerce User Functions
 *
 * Functions for managing user
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
 * Give a user the Subscription's default subscriber role
 *
 * @since 2.0
 */
function wcs_make_user_active( $user_id ) {
	wcs_update_users_role( $user_id, 'default_subscriber_role' );
}

/**
 * Give a user the Subscription's default subscriber's inactive role
 *
 * @since 2.0
 */
function wcs_make_user_inactive( $user_id ) {
	wcs_update_users_role( $user_id, 'default_inactive_role' );
}

/**
 * Give a user the Subscription's default subscriber's inactive role if they do not have an active subscription
 *
 * @since 2.0
 */
function wcs_maybe_make_user_inactive( $user_id ) {
	if ( ! wcs_user_has_subscription( $user_id, '', 'active' ) ) {
		wcs_update_users_role( $user_id, 'default_inactive_role' );
	}
}

/**
 * Update a user's role to a special subscription's role
 *
 * @param int The ID of a user
 * @param string The special name assigned to the role by Subscriptions, one of 'default_subscriber_role', 'default_inactive_role' or 'default_cancelled_role'
 * @since 2.0
 */
function wcs_update_users_role( $user_id, $role_name ) {

	$user = new WP_User( $user_id );

	// Never change an admin's role to avoid locking out admins testing the plugin
	if ( ! empty( $user->roles ) && in_array( 'administrator', $user->roles ) ) {
		return;
	}

	// Allow plugins to prevent Subscriptions from handling roles
	if ( ! apply_filters( 'woocommerce_subscriptions_update_users_role', true, $user, $role_name ) ) {
		return;
	}

	if ( $role_name == 'default_subscriber_role' ) {
		$role_name = get_option( WC_Subscriptions_Admin::$option_prefix . '_subscriber_role' );
	} elseif ( in_array( $role_name, array( 'default_inactive_role', 'default_cancelled_role' ) ) ) {
		$role_name = get_option( WC_Subscriptions_Admin::$option_prefix . '_cancelled_role' );
	}

	$user->set_role( $role_name );

	do_action( 'woocommerce_subscriptions_updated_users_role', $role_name, $user );
}

/**
 * Check if a user has a subscription, optionally to a specific product and/or with a certain status.
 *
 * @param int (optional) The ID of a user in the store. If left empty, the current user's ID will be used.
 * @param int (optional) The ID of a product in the store. If left empty, the function will see if the user has any subscription.
 * @param string (optional) A valid subscription status. If left empty, the function will see if the user has a subscription of any status.
 * @since 2.0
 */
function wcs_user_has_subscription( $user_id = 0, $product_id = '', $status = 'any' ) {

	$subscriptions = wcs_get_users_subscriptions( $user_id );

	$has_subscription = false;

	if ( empty( $product_id ) ) { // Any subscription

		if ( ! empty( $status ) && 'any' != $status ) { // We need to check for a specific status
			foreach ( $subscriptions as $subscription ) {
				if ( $subscription->get_status() == $status ) {
					$has_subscription = true;
					break;
				}
			}
		} elseif ( ! empty( $subscriptions ) ) {
			$has_subscription = true;
		}

	} else {

		foreach ( $subscriptions as $subscription ) {
			$subscriptions_product_ids = wp_list_pluck( $subscription->get_items(), 'product_id' );
			if ( in_array( $product_id, $subscriptions_product_ids ) && ( empty( $status ) || 'any' == $status || $subscription->get_status() == $status ) ) {
				$has_subscription = true;
				break;
			}
		}

	}

	return apply_filters( 'woocommerce_user_has_subscription', $has_subscription, $user_id, $product_id, $status );
}

/**
 * Gets all the active and inactive subscriptions for a user, as specified by $user_id
 *
 * @param int $user_id (optional) The id of the user whose subscriptions you want. Defaults to the currently logged in user.
 * @since 2.0
 */
function wcs_get_users_subscriptions( $user_id = 0 ) {

	if ( 0 === $user_id || empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$post_ids = get_posts( array(
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'post_type'      => 'shop_subscription',
		'order'          => 'desc',
		'meta_key'       => '_customer_user',
		'meta_value'     => $user_id,
		'meta_compare'   => '=',
	) );

	$subscriptions = array();

	foreach ( $post_ids as $post_id ) {
		$subscriptions[ $post_id ] = wcs_get_subscription( $post_id );
	}

	return apply_filters( 'woocommerce_get_users_subscriptions', $subscriptions, $user_id );
}

/**
 * Return a link for subscribers to change the status of their subscription, as specified with $status parameter
 *
 * @param int $subscription_id A subscription's post ID
 * @param string $status A subscription's post ID
 * @since 1.0
 */
function wcs_get_users_change_status_link( $subscription_id, $status ) {

	$action_link = add_query_arg( array( 'subscription_id' => $subscription_id, 'change_subscription_to' => $status ) );
	$action_link = wp_nonce_url( $action_link, $subscription_id );

	return apply_filters( 'woocommerce_subscriptions_users_change_status_link', $action_link, $subscription_id, $status );
}