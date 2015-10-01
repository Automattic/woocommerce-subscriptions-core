<?php
/**
 * Handles the initial payment for a pending subscription via the cart.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WCS_Cart_Initial_Payment
 * @category	Class
 * @author		Prospress
 * @since		2.0
 */

class WCS_Cart_Initial_Payment extends WCS_Cart_Renewal {

	/* The flag used to indicate if a cart item is for a initial payment */
	public $cart_item_key = 'subscription_initial_payment';

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 2.0
	 */
	public function __construct() {

		$this->setup_hooks();
	}

	/**
	 * Setup the cart for paying for a delayed initial payment for a subscription.
	 *
	 * @since 2.0
	 */
	public function maybe_setup_cart() {
		global $wp;

		if ( isset( $_GET['pay_for_order'] ) && isset( $_GET['key'] ) && isset( $wp->query_vars['order-pay'] ) ) {

			// Pay for existing order
			$order_key    = $_GET['key'];
			$order_id     = ( isset( $wp->query_vars['order-pay'] ) ) ? $wp->query_vars['order-pay'] : absint( $_GET['order_id'] );
			$order        = wc_get_order( $wp->query_vars['order-pay'] );

			if ( $order->order_key == $order_key && $order->has_status( array( 'pending', 'failed' ) ) && ! wcs_order_contains_renewal( $order ) ) {

				$subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'parent' ) );

				if ( get_current_user_id() !== $order->get_user_id() ) {
					wc_add_notice( __( 'That doesn\'t appear to be your order.', 'woocommerce-subscriptions' ), 'error' );

					wp_safe_redirect( get_permalink( wc_get_page_id( 'myaccount' ) ) );
					exit;

				} elseif ( ! empty( $subscriptions ) ) {

					// Setup cart with all the original order's line items
					$this->setup_cart( $order, array() );

					WC()->session->set( 'order_awaiting_payment', $order_id );

					wp_safe_redirect( WC()->cart->get_checkout_url() );
					exit;
				}
			}
		}
	}
}
new WCS_Cart_Initial_Payment();
