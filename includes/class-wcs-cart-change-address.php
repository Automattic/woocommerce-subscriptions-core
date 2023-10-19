<?php
/**
 * Implement resubscribing to a subscription via the cart.
 *
 * Resubscribing is a similar process to renewal via checkout (which is why this class extends WCS_Cart_Renewal), only it:
 * - creates a new subscription with similar terms to the existing subscription, where as a renewal resumes the existing subscription
 * - is for an expired or cancelled subscription only.
 *
 * @package WooCommerce Subscriptions
 * @subpackage WCS_Cart_Resubscribe
 * @category Class
 * @author Prospress
 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
 */

class WCS_Cart_Change_Address extends WCS_Cart_Renewal {

	/* The flag used to indicate if a cart item is a renewal */
	public $cart_item_key = 'subscription_change_address';

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $subscription
	 * @return void
	 */
	public function add_subscription_to_cart( $subscription ) {
		$this->setup_cart( $subscription, array(
			'subscription_id' => $subscription->get_id(),
		), 'all_items_required' );
	}

	/**
	 * Checks the cart to see if it contains a subscription resubscribe item.
	 *
	 * @see wcs_cart_contains_resubscribe()
	 * @param WC_Cart $cart The cart object to search in.
	 * @return bool | Array The cart item containing the renewal, else false.
	 * @since  1.0.0 - Migrated from WooCommerce Subscriptions v2.0.10
	 */
	protected function cart_contains( $cart = '' ) {
		$contains_resubscribe = false;

		if ( empty( $cart ) ) {
			$cart = WC()->cart;
		}

		if ( ! empty( $cart->cart_contents ) ) {
			foreach ( $cart->cart_contents as $cart_item ) {
				if ( isset( $cart_item[ $this->cart_item_key ] ) ) {
					$contains_resubscribe = $cart_item;
					break;
				}
			}
		}

		return $contains_resubscribe;
	}

	/**
	 * Get the subscription object used to construct the resubscribe cart.
	 *
	 * @param Array The resubscribe cart item.
	 * @return WC_Subscription | The subscription object.
	 * @since  1.0.0 - Migrated from WooCommerce Subscriptions v2.0.13
	 */
	protected function get_order( $cart_item = '' ) {
		$subscription = false;

		if ( empty( $cart_item ) ) {
			$cart_item = $this->cart_contains();
		}

		if ( false !== $cart_item && isset( $cart_item[ $this->cart_item_key ] ) ) {
			$subscription = wcs_get_subscription( $cart_item[ $this->cart_item_key ]['subscription_id'] );
		}

		return $subscription;
	}
}
