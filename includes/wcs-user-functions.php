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

