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

		// Set correct discounts on renewal orders
		add_action( 'woocommerce_before_calculate_totals', array( &$this, 'set_renewal_discounts' ), 10 );
		add_filter( 'woocommerce_get_discounted_price', array( &$this, 'get_discounted_price_for_renewal' ), 10, 3 );

		// Remove order action buttons from the My Account page
		add_filter( 'woocommerce_my_account_my_orders_actions', array( &$this, 'filter_my_account_my_orders_actions' ), 10, 2 );

		// When a renewal order's status changes, check if a corresponding subscription's status should be changed accordingly
		add_filter( 'woocommerce_order_status_changed', array( &$this, 'maybe_change_subscription_status' ), 10, 3 );
	}

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 2.0
	 */
	public function setup_hooks() {

		// Make sure renewal meta data persists between sessions
		add_filter( 'woocommerce_get_cart_item_from_session', array( &$this, 'get_cart_item_from_session' ), 10, 3 );

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
	 * Restore renewal flag when cart is reset and modify Product object with renewal order related info
	 *
	 * @since 2.0
	 */
	public function get_cart_item_from_session( $cart_item_session_data, $cart_item, $key ) {

		if ( isset( $cart_item[ $this->cart_item_key ] ) ) {

			$cart_item_session_data[ $this->cart_item_key ] = $cart_item[ $this->cart_item_key ];

			$_product = $cart_item_session_data['data'];

			// Need to get the original subscription price, not the current price
			$subscription = wcs_get_subscription( $cart_item[ $this->cart_item_key ]['subscription_id'] );

			foreach ( $subscription->get_items() as $item_id => $item ) {
				if ( $_product->id == $item['product_id'] && ( ! isset( $_product->variation_id ) || $item['variation_id'] == $order_item['variation_id']) ) {
					$item_to_renew = $item;
				}
			}

			$_product->price = $item_to_renew['line_subtotal'] / $item_to_renew['qty'];

			// Don't carry over any sign up fee
			$_product->subscription_sign_up_fee = 0;

			$_product->post->post_title = apply_filters( 'woocommerce_subscriptions_renewal_product_title', $_product->get_title(), $_product );

			// Make sure the same quantity is renewed
			$cart_item_session_data['quantity'] = $item_to_renew['qty'];
		}

		return $cart_item_session_data;
	}

	/**
	 * For subscription renewal via cart, use original order discount
	 *
	 * @since 2.0
	 */
	public function set_renewal_discounts( $cart ) {

		$cart_item = wcs_cart_contains_renewal();

		if ( $cart_item ) {

			$subscription = wcs_get_subscription( $cart_item[ $this->cart_item_key ]['subscription_id'] );

			$cart->discount_cart     = $subscription->cart_discount;
			$cart->discount_cart_tax = $subscription->cart_discount_tax;
		}
	}

	/**
	 * For subscription renewal via cart, previously adjust item price by original order discount
	 *
	 * No longer required as of 1.3.5 as totals are calculated correctly internally.
	 *
	 * @since 2.0
	 */
	public function get_discounted_price_for_renewal( $price, $cart_item, $cart ) {

		$cart_item = wcs_cart_contains_renewal();

		if ( $cart_item ) {
			$original_order_id = $cart_item[ $this->cart_item_key ]['subscription_id'];
			$price -= WC_Subscriptions_Order::get_meta( $original_order_id, '_order_recurring_discount_cart', 0 );
		}

		return $price;
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

	/**
	 * Process a renewal payment completed via checkout.
	 *
	 * This function is hooked to 'woocommerce_order_status_changed', rather than 'woocommerce_payment_complete', to ensure
	 * subscriptions are updated even if payment is processed by a manual payment gateways (which would never trigger the
	 * 'woocommerce_payment_complete' hook) or by some other means that circumvents that hook.
	 *
	 * @since 2.0
	 */
	public function maybe_change_subscription_status( $order_id, $orders_old_status, $orders_new_status ) {

		if ( ! wcs_is_renewal_order( $order_id ) ) {
			return;
		}

		$subscription = wcs_get_subscription_for_renewal_order( $order_id );

		// Do we need to activate a subscription?
		if ( in_array( $orders_new_status, array( 'processing', 'completed' ) ) ) {

			if ( $subscription->is_manual() && in_array( $orders_old_status, array( 'pending', 'on-hold' ) ) ) {

				$this->process_payment( $subscription );

			} elseif ( 'failed' === $orders_old_status ) {

				add_action( 'reactivated_subscription', 'WC_Subscriptions_Renewal_Order::trigger_processed_failed_renewal_order_payment_hook', 10, 2 );

				$this->process_payment( $subscription );

				remove_action( 'reactivated_subscription', 'WC_Subscriptions_Renewal_Order::trigger_processed_failed_renewal_order_payment_hook', 10, 2 );

				do_action( 'woocommerce_subscriptions_paid_for_failed_renewal_order', wc_get_order( $order_id ), $subscription );

			}

		} elseif ( 'failed' == $orders_new_status ) {

			// Don't duplicate renewal order
			remove_action( 'woocommerce_subscription_renewal_payment_failed', __CLASS__ . '::create_failed_payment_renewal_order' );

			$subscription->payment_failed();

			// But make sure orders are still generated for other payments in the same request
			add_action( 'woocommerce_subscription_renewal_payment_failed', __CLASS__ . '::create_failed_payment_renewal_order' );

		}
	}

	/**
	 * Customise which actions are shown against a subscription renewal order on the My Account page.
	 *
	 * @since 2.0
	 */
	public function filter_my_account_my_orders_actions( $actions, $order ) {

		if ( wcs_is_renewal_order( $order ) ) {

			unset( $actions['cancel'] );

			// If the subscription has been deleted or reactivated some other way, don't support payment on the order
			$subscription = wcs_get_subscription_for_renewal_order( $order );
			if ( empty( $subscription ) || ! $subscription->has_status( array( 'on-hold', 'pending' ) ) ) {
				unset( $actions['pay'] );
			}
		}

		return $actions;
	}

	/**
	 * Once payment is completed on a renewal order, process the payment on the subscription and update it to active.
	 *
	 * @param WC_Subscription $subscription A WC_Subscription object
	 * @since 2.0
	 */
	protected function process_payment( $subscription ) {

		// Don't duplicate the renewal order
		remove_action( 'woocommerce_subscription_renewal_payment_complete', 'WC_Subscriptions_Renewal_Order::create_paid_renewal_order', 10, 2 );

		$subscription->payment_complete();

		// But make sure orders are still generated for other payments in the same request
		add_action( 'woocommerce_subscription_renewal_payment_complete', 'WC_Subscriptions_Renewal_Order::create_paid_renewal_order', 10, 2 );

		$subscription->update_status( 'active' );
	}

}
new WCS_Cart_Renewal();
