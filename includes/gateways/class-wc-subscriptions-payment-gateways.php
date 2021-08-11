<?php
/**
 * Subscriptions Payment Gateways
 *
 * Hooks into the WooCommerce payment gateways class to add subscription specific functionality.
 *
 * @package    WooCommerce Subscriptions
 * @subpackage WC_Subscriptions_Payment_Gateways
 * @category   Class
 * @author     Brent Shepherd
 * @since      1.0
 */
class WC_Subscriptions_Payment_Gateways extends WC_Subscriptions_Base_Payment_Gateways {

	/**
	 * Display the gateways which support subscriptions if manual payments are not allowed.
	 *
	 * @since 1.0
	 */
	public static function get_available_payment_gateways( $available_gateways ) {
		// We don't want to filter the available payment methods while the customer is paying for a standard order via the order-pay screen.
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			return $available_gateways;
		}

		if ( ! WC_Subscriptions_Cart::cart_contains_subscription() && ( ! isset( $_GET['order_id'] ) || ! wcs_order_contains_subscription( $_GET['order_id'] ) ) ) {
			return $available_gateways;
		}

		$accept_manual_renewals = wcs_is_manual_renewal_enabled();
		$subscriptions_in_cart  = is_array( WC()->cart->recurring_carts ) ? count( WC()->cart->recurring_carts ) : 0;

		foreach ( $available_gateways as $gateway_id => $gateway ) {

			$supports_subscriptions = $gateway->supports( 'subscriptions' );

			// Remove the payment gateway if there are multiple subscriptions in the cart and this gateway either doesn't support multiple subscriptions or isn't manual (all manual gateways support multiple subscriptions)
			if ( $subscriptions_in_cart > 1 && $gateway->supports( 'multiple_subscriptions' ) !== true && ( $supports_subscriptions || ! $accept_manual_renewals ) ) {
				unset( $available_gateways[ $gateway_id ] );

			// If there is just the one subscription the cart, remove the payment gateway if manual renewals are disabled and this gateway doesn't support automatic payments
			} elseif ( ! $supports_subscriptions && ! $accept_manual_renewals ) {
				unset( $available_gateways[ $gateway_id ] );
			}
		}

		return $available_gateways;
	}
}
