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

		// Check if a user is requesting to create a renewal order for a subscription, needs to happen after $wp->query_vars are set
		add_action( 'template_redirect', array( &$this, 'maybe_setup_cart' ), 100 );
	}

	/**
	 * Check if a payment is being made on a renewal order from 'My Account'. If so,
	 * redirect the order into a cart/checkout payment flow so that the customer can
	 * choose payment method, apply discounts set shipping and pay for the order.
	 *
	 * @since 2.0
	 */
	public function maybe_setup_cart() {
		global $wp;

		if ( isset( $_GET['pay_for_order'] ) && isset( $_GET['key'] ) && isset( $wp->query_vars['order-pay'] ) ) {

			// Pay for existing order
			$order_key = $_GET[ 'key' ];
			$order_id  = ( isset( $wp->query_vars['order-pay'] ) ) ? $wp->query_vars['order-pay'] : absint( $_GET['order_id'] );
			$order     = wc_get_order( $wp->query_vars['order-pay'] );

			if ( $order->order_key == $order_key && $order->has_status( array( 'pending', 'failed' ) ) && wcs_is_renewal_order( $order ) ) {

				$subscription = wcs_get_subscription_for_renewal_order( $order );

				$this->setup_cart( $subscription, array(
					'subscription_id'  => $subscription->id,
					'renewal_order_id' => $order_id,
				) );

				// Store renewal order's ID in session so it can be re-used after payment
				WC()->session->set( 'order_awaiting_payment', $order_id );

				wp_safe_redirect( WC()->cart->get_checkout_url() );
				exit;
			}
		}
	}

	/**
	 * Set up cart item meta data for a to complete a subscription renewal via the cart.
	 *
	 * @since 2.0
	 */
	protected function setup_cart( $subscription, $cart_item_data ) {

		WC()->cart->empty_cart( true );

		foreach ( $subscription->get_items() as $line_item ) {

			// Load all product info including variation data
			$product_id   = (int) apply_filters( 'woocommerce_add_to_cart_product_id', $line_item['product_id'] );
			$quantity     = (int) $line_item['qty'];
			$variation_id = (int) $line_item['variation_id'];
			$variations   = array();

			foreach ( $line_item['item_meta'] as $meta_name => $meta_value ) {
				if ( taxonomy_is_product_attribute( $meta_name ) ) {
					$variations[ $meta_name ] = $meta_value[0];
				} elseif ( meta_is_product_attribute( $meta_name, $meta_value, $product_id ) ) {
					$variations[ $meta_name ] = $meta_value[0];
				}
			}

			$product = get_product( $line_item['product_id'] );

			// The notice displayed when a subscription product has been deleted and the custoemr attempts to manually renew or make a renewal payment for a failed recurring payment for that product/subscription
			$product_deleted_error_message = apply_filters( 'woocommerce_subscriptions_renew_deleted_product_error_message', __( 'The %s product has been deleted and can no longer be renewed. Please choose a new product or contact us for assistance.', 'woocommerce-subscriptions' ) );

			// Display error message for deleted products
			if ( false === $product ) {

				wc_add_notice( sprintf( $product_deleted_error_message, $line_item['name'] ), 'error' );

			// Make sure we don't actually need the variation ID (if the product was a variation, it will have a variation ID; however, if the product has changed from a simple subscription to a variable subscription, there will be no variation_id)
			} elseif ( $product->is_type( array( 'variable-subscription' ) ) && ! empty( $line_item['variation_id'] ) ) {

				$variation = get_product( $variation_id );

				// Display error message for deleted product variations
				if ( false === $variation ) {
					wc_add_notice( sprintf( $product_deleted_error_message, $line_item['name'] ), 'error' );
				}
			}

			$cart_item_data = apply_filters( 'woocommerce_order_again_cart_item_data', array( $this->cart_item_key => $cart_item_data ), $line_item, $subscription );

			WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations, $cart_item_data );
		}

		do_action( 'woocommerce_setup_cart_for_' . $this->cart_item_key, $subscription, $cart_item_data );
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
