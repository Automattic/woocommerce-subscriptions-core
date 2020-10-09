<?php
/**
 * WooCommerce Subscriptions Extend Store API.
 *
 * A class to extend the store public API with subscription related data
 * for each subscription item
 *
 * @package WooCommerce Subscriptions
 * @author  WooCommerce
 * @since   3.0.9
 */
class WC_Subscriptions_Extend_Store_Endpoint {

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 3.0.9
	 */
	public static function init() {
		// This is going to be changed in the future to a dedicated register
		// function and not a filter, once we go public and stable, I will update this.
		add_filter( '__internal_woocommerce_blocks_cart_item', __CLASS__ . '::extend_cart_item', 10, 4 );
	}

	public static function extend_cart_item( $item_data, $cart_item, $product, $response_object ) {

		if ( $product->get_type() === 'subscription' ) {
			$item_data['extensions']['subscriptions'] = [
				"period"          => WC_Subscriptions_Product::get_period( $product ),
				"interval"        => WC_Subscriptions_Product::get_interval( $product ),
				"length"          => WC_Subscriptions_Product::get_length( $product ),
				"trial_length"    => WC_Subscriptions_Product::get_trial_length( $product ),
				"trial_period"    => WC_Subscriptions_Product::get_trial_period( $product ),
				"first_renewal"   => WC_Subscriptions_Product::get_first_renewal_payment_date( $product ),
				"fees"            => WC_Subscriptions_Product::get_sign_up_fee( $product ),
			];
		}
		return $item_data;
	}
}
