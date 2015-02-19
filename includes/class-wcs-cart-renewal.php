<?php
/**
 * Implement renewing to a subscription via the cart.
 *
 * For manual renewals and the renewal of a subscription after a failed automatic payment, the customer must complete
 * the renewal via checkout in order to pay for the renewal. This class handles that.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WCS_Cart_Renewal
 * @category	Class
 * @author		Prospress
 * @since		2.0
 */

class WCS_Cart_Renewal {

	/* The flag used to indicate if a cart item is a renewal */
	public $cart_item_key = 'subscription_renewal';

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 2.0
	 */
	public function __construct() {

		$this->setup_hooks();

		// Set URL parameter for manual subscription renewals
		add_filter( 'woocommerce_get_checkout_payment_url', array( &$this, 'get_checkout_payment_url' ), 10, 2 );
	}

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 2.0
	 */
	public function setup_hooks() {

		// Allow renewal of limited subscriptions
		add_filter( 'woocommerce_subscription_is_purchasable', array( &$this, 'is_purchasable' ), 12, 2 );
		add_filter( 'woocommerce_subscription_variation_is_purchasable', array( &$this, 'is_purchasable' ), 12, 2 );
	}

	/**
	 * If a product is being marked as not purchasable because it is limited and the customer has a subscription,
	 * but the current request is to resubscribe to the subscription, then mark it as purchasable.
	 *
	 * @since 2.0
	 * @return bool
	 */
	public function is_purchasable( $is_purchasable, $product ) {

		// If the product is being set as not-purchasable by Subscriptions (due to limiting)
		if ( false === $is_purchasable && false === WC_Subscriptions_Product::is_purchasable( $is_purchasable, $product ) ) {

			// Adding to cart from the product page
			if ( isset( $_GET[ $this->cart_item_key ] ) ) {

				$is_purchasable = true;

			}
		}

		return $is_purchasable;
	}

	/**
	 * Flag payment of manual renewal orders via an extra URL param.
	 *
	 * This is particularly important to ensure renewals of limited subscriptions can be completed.
	 *
	 * @since 2.0
	 */
	public function get_checkout_payment_url( $pay_url, $order ) {

		if ( wcs_is_renewal_order( $order ) ) {
			$pay_url = add_query_arg( array( $this->cart_item_key => 'true' ), $pay_url );
		}

		return $pay_url;
	}

}
new WCS_Cart_Renewal();
