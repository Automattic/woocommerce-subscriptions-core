<?php
/**
 * WooCommerce Subscriptions Cart Functions
 *
 * Functions for cart specific things, based on wc-cart-functions.php but overloaded
 * for use with recurring carts.
 *
 * @author 		WooThemes
 * @category 	Core
 * @package 	WooCommerce/Functions
 * @version     2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Return a formatted price string for a given cart object
 *
 * @access public
 * @return void
 */
function wcs_cart_price_string( $recurring_amount, $cart ) {

	return wcs_price_string( apply_filters( 'woocommerce_cart_subscription_string_details', array(
		'recurring_amount'      => $recurring_amount,

		// Schedule details
		'subscription_interval' => wcs_cart_pluck( $cart, 'subscription_period_interval' ),
		'subscription_period'   => wcs_cart_pluck( $cart, 'subscription_period', '' ),
		'subscription_length'   => wcs_cart_pluck( $cart, 'subscription_length' ),
	)));
}

/**
 * Return a given piece of meta data from the cart
 *
 * The data can exist on the cart object, a cart item, or product data on a cart item.
 * The first piece of data with a matching key (in that order) will be returned if it
 * is found, otherwise, the value specified with $default, will be returned.
 *
 * @access public
 * @return string
 */
function wcs_cart_pluck( $cart, $field, $default = 0 ) {

	$value = $default;

	if ( isset( $cart->$field ) ) {
		$value = $cart->$field;
	} else {
		foreach( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item[ $field ] ) ) {
				$value = $cart_item[ $field ];
			} elseif ( $cart_item['data']->$field ) {
				$value = $cart_item['data']->$field;
			}
		}
	}

	return $value;
}