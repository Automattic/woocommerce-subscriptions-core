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
 * Display a recurring cart's subtotal
 *
 * @access public
 * @return string
 */
function wcs_cart_totals_subtotal_html( $cart ) {
	echo wcs_cart_price_string( $cart->get_cart_subtotal(), $cart );
}

/**
 * Display a recurring shipping methods price
 * @param  object $method
 * @return string
 */
function wcs_cart_totals_shipping_method( $method, $cart ) {

	if ( $method->cost > 0 ) {

		if ( WC()->cart->tax_display_cart == 'excl' ) {
			$label = wcs_cart_price_string( $method->cost, $cart );
			if ( $method->get_shipping_tax() > 0 && $cart->prices_include_tax ) {
				$label .= ' <small>' . WC()->countries->ex_tax_or_vat() . '</small>';
			}
		} else {
			$label = wcs_cart_price_string( $method->cost + $method->get_shipping_tax(), $cart );
			if ( $method->get_shipping_tax() > 0 && ! $cart->prices_include_tax ) {
				$label .= ' <small>' . WC()->countries->inc_tax_or_vat() . '</small>';
			}
		}

	} else {
		$label = __( 'Free', 'woocommerce-subscriptions' );
	}

	return apply_filters( 'wcs_cart_totals_shipping_method', $label, $method, $cart );
}

/**
 * Display recurring taxes total
 *
 * @access public
 * @return void
 */
function wcs_cart_totals_taxes_total_html( $cart ) {
	$value = apply_filters( 'woocommerce_cart_totals_taxes_total_html', $cart->get_taxes_total() );
	echo apply_filters( 'wcs_cart_totals_taxes_total_html', wcs_cart_price_string( $value, $cart ), $cart );
}

/**
 * Display a recurring coupon's value
 *
 * @access public
 * @param string $coupon
 * @return void
 */
function wcs_cart_totals_coupon_html( $coupon, $cart ) {
	if ( is_string( $coupon ) ) {
		$coupon = new WC_Coupon( $coupon );
	}

	$value  = array();

	if ( $amount = $cart->get_coupon_discount_amount( $coupon->code, $cart->display_cart_ex_tax ) ) {
		$discount_html = '-' . wc_price( $amount );
	} else {
		$discount_html = '';
	}

	$value[] = apply_filters( 'woocommerce_coupon_discount_amount_html', $discount_html, $coupon );

	if ( $coupon->enable_free_shipping() ) {
		$value[] = __( 'Free shipping coupon', 'woocommerce' );
	}

	// get rid of empty array elements
	$value = implode( ', ', array_filter( $value ) );

	// Apply WooCommerce core filter
	$value = apply_filters( 'woocommerce_cart_totals_coupon_html', $value, $coupon );

	echo apply_filters( 'wcs_cart_totals_coupon_html', wcs_cart_price_string( $value, $cart ), $coupon, $cart );
}

/**
 * Get recurring total html including inc tax if needed
 *
 * @access public
 * @return void
 */
function wcs_cart_totals_order_total_html( $cart ) {
	$value = '<strong>' . $cart->get_total() . '</strong> ';

	// If prices are tax inclusive, show taxes here
	if ( wc_tax_enabled() && $cart->tax_display_cart == 'incl' ) {
		$tax_string_array = array();

		if ( get_option( 'woocommerce_tax_total_display' ) == 'itemized' ) {
			foreach ( $cart->get_tax_totals() as $code => $tax )
				$tax_string_array[] = sprintf( '%s %s', $tax->formatted_amount, $tax->label );
		} else {
			$tax_string_array[] = sprintf( '%s %s', wc_price( $cart->get_taxes_total( true, true ) ), WC()->countries->tax_or_vat() );
		}

		if ( ! empty( $tax_string_array ) ) {
			$value .= '<small class="includes_tax">' . sprintf( __( '(Includes %s)', 'woocommerce' ), implode( ', ', $tax_string_array ) ) . '</small>';
		}
	}

	// Apply WooCommerce core filter
	$value = apply_filters( 'woocommerce_cart_totals_order_total_html', $value );

	echo apply_filters( 'wcs_cart_totals_order_total_html', wcs_cart_price_string( $value, $cart ), $cart );
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