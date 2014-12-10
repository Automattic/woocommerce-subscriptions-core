<?php
/**
 * WooCommerce Subscriptions Order Functions
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
 * A wrapper for @see wcs_get_subscriptions() which accepts simply an order ID
 *
 * @param int|WC_Order $order_id The post_id of a shop_order post or an intsance of a WC_Order object
 * @return array Subscription details in post_id => WC_Subscription form.
 * @since  2.0
 */
function wcs_get_subscriptions_for_order( $order_id ) {

	if ( is_object( $order_id ) ) {
		$order_id = $order_id->id;
	}

	return wcs_get_subscriptions( array( 'order_id' => $order_id ) );;
}

/**
 * Copy the billing, shipping or all addresses from one order to another (including custom order types, like the
 * WC_Subscription order type).
 *
 * @param WC_Order $to_order The WC_Order object to copy the address to.
 * @param WC_Order $from_order The WC_Order object to copy the address from.
 * @param string $address_type The address type to copy, can be 'shipping', 'billing' or 'all'
 * @return WC_Order The WC_Order object with the new address set.
 * @since  2.0
 */
function wcs_copy_order_address( $from_order, $to_order, $address_type = 'all' ) {

	if ( in_array( $address_type, array( 'shipping', 'all' ) ) ) {
		$to_order->set_address( array(
			'first_name' => $from_order->shipping_first_name,
			'last_name'  => $from_order->shipping_last_name,
			'company'    => $from_order->shipping_company,
			'address_1'  => $from_order->shipping_address_1,
			'address_2'  => $from_order->shipping_address_2,
			'city'       => $from_order->shipping_city,
			'state'      => $from_order->shipping_state,
			'postcode'   => $from_order->shipping_postcode,
			'country'    => $from_order->shipping_country
		), 'shipping' );
	}

	if ( in_array( $address_type, array( 'billing', 'all' ) ) ) {
		$to_order->set_address( array(
			'first_name' => $from_order->billing_first_name,
			'last_name'  => $from_order->billing_last_name,
			'company'    => $from_order->billing_company,
			'address_1'  => $from_order->billing_address_1,
			'address_2'  => $from_order->billing_address_2,
			'city'       => $from_order->billing_city,
			'state'      => $from_order->billing_state,
			'postcode'   => $from_order->billing_postcode,
			'country'    => $from_order->billing_country
		), 'billing' );
	}

	return apply_filters( 'woocommerce_subscriptions_copy_order_address', $to_order, $from_order, $address_type );
}
