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
class WC_Subscriptions_Payment_Gateways extends WC_Subscriptions_Core_Payment_Gateways {

	/**
	 * Init WC_Subscriptions_Payment_Gateways actions & filters.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		parent::init();
		// Trigger a hook for gateways to charge recurring payments.
		add_action( 'woocommerce_scheduled_subscription_payment', array( __CLASS__, 'gateway_scheduled_subscription_payment' ), 10, 1 );
	}

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

	/**
	 * Fire a gateway specific hook for when a subscription payment is due.
	 *
	 * @since 1.0
	 */
	public static function gateway_scheduled_subscription_payment( $subscription_id, $deprecated = null ) {
		if ( ! is_object( $subscription_id ) ) {
			$subscription = wcs_get_subscription( $subscription_id );
		} else {
			$subscription = $subscription_id;
		}

		if ( false === $subscription ) {
			// translators: %d: subscription ID.
			throw new InvalidArgumentException( sprintf( __( 'Subscription doesn\'t exist in scheduled action: %d', 'woocommerce-subscriptions' ), $subscription_id ) );
		}

		if ( ! $subscription->is_manual() && ! $subscription->has_status( wcs_get_subscription_ended_statuses() ) ) {
			self::trigger_gateway_renewal_payment_hook( $subscription->get_last_order( 'all', 'renewal' ) );
		}
	}

	/**
	 * Fire a gateway specific hook for when a subscription renewal payment is due.
	 *
	 * @param WC_Order $renewal_order The renewal order to trigger the payment gateway hook for.
	 * @since 2.1.0
	 */
	public static function trigger_gateway_renewal_payment_hook( $renewal_order ) {
		if ( ! empty( $renewal_order ) && $renewal_order->get_total() > 0 && $renewal_order->get_payment_method() ) {

			// Make sure gateways are setup
			WC()->payment_gateways();

			do_action( 'woocommerce_scheduled_subscription_payment_' . $renewal_order->get_payment_method(), $renewal_order->get_total(), $renewal_order );
		}
	}
}
