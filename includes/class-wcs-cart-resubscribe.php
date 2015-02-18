<?php
/**
 * Implement resubscribing to a subscription via the cart.
 *
 * Resubscribing is a similar process to renewal via checkout (which is why this class extends WCS_Cart_Renewal), only it:
 * - creates a new subscription with similar terms to the existing subscription, where as a renewal resumes the existing subscription
 * - is for an expired or cancelled subscription only.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WCS_Cart_Resubscribe
 * @category	Class
 * @author		Prospress
 * @since		2.0
 */

class WCS_Cart_Resubscribe extends WCS_Cart_Renewal {

	/* The flag used to indicate if a cart item is a renewal */
	public $cart_item_key = 'subscription_resubscribe';

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->setup_hooks();
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

			// Validating when restoring cart from session
			if ( false !== wcs_cart_contains_resubscribe() ) {

				$is_purchasable = true;

			// Restoring cart from session, so need to check the cart in the session (wcs_cart_contains_renewal() only checks the cart)
			} elseif ( isset( WC()->session->cart ) ) {

				foreach ( WC()->session->cart as $cart_item_key => $cart_item ) {
					if ( $product->id == $cart_item['product_id'] && isset( $cart_item[ $this->cart_item_key ] ) ) {
						$is_purchasable = true;
						break;
					}
				}

			}
		}

		return $is_purchasable;
	}
}
new WCS_Cart_Resubscribe();
