<?php
/**
 * WooCommerce Subscriptions Product Functions
 *
 * Functions for managing renewal of a subscription.
 *
 * @author 		Prospress
 * @category 	Core
 * @package 	WooCommerce Subscriptions/Functions
 * @version     2.1.4
 */

/**
 * For a given product, and optionally price/qty, work out the sign-up with tax included, based on store settings.
 *
 * @since  2.1.4
 * @param  WC_Product $product
 * @param  array $args
 * @return float
 */
function wcs_get_price_including_tax( $product, $args = array() ) {

	$args = wp_parse_args( $args, array(
		'qty'   => 1,
		'price' => $product->get_price(),
	) );

	if ( function_exists( 'wc_get_price_including_tax' ) ) { // WC 2.7+
		$price = wc_get_price_including_tax( $product, $args );
	} else { // WC < 2.7
		$price = $product->get_price_including_tax( $args['qty'], $args['price'] );
	}

	return $price;
}

/**
 * For a given product, and optionally price/qty, work out the sign-up fee with tax excluded, based on store settings.
 *
 * @since  2.1.4
 * @param  WC_Product $product
 * @param  array $args
 * @return float
 */
function wcs_get_price_excluding_tax( $product, $args = array() ) {

	$args = wp_parse_args( $args, array(
		'qty'   => 1,
		'price' => $product->get_price(),
	) );

	if ( function_exists( 'wc_get_price_excluding_tax' ) ) { // WC 2.7+
		$price = wc_get_price_excluding_tax( $product, $args );
	} else { // WC < 2.7
		$price = $product->get_price_excluding_tax( $args['qty'], $args['price'] );
	}

	return $price;
}

/**
 * Returns a 'from' prefix if you want to show where prices start at.
 *
 * @since  2.1.4
 * @return string
 */
function wcs_get_price_html_from_text( $product = '' ) {

	if ( function_exists( 'wc_get_price_html_from_text' ) ) { // WC 2.7+
		$price_html_from_text = wc_get_price_html_from_text();
	} else { // WC < 2.7
		$price_html_from_text = $product->get_price_html_from_text();
	}

	return $price_html_from_text;
}
