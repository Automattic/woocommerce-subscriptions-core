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

		// Update customer's address on the subscription if it is changed during renewal
		add_filter( 'woocommerce_checkout_update_customer_data', array( &$this, 'maybe_update_subscription_customer_data' ), 10, 2 );

		// When a failed renewal order is paid for via checkout, make sure WC_Checkout::create_order() preserves its "failed" status until it is paid
		add_filter( 'woocommerce_default_order_status', array( &$this, 'maybe_preserve_order_status' ) );
	}

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 2.0
	 */
	public function setup_hooks() {

		// Make sure renewal meta data persists between sessions
		add_filter( 'woocommerce_get_cart_item_from_session', array( &$this, 'get_cart_item_from_session' ), 10, 3 );
		add_action( 'woocommerce_cart_loaded_from_session', array( &$this, 'cart_items_loaded_from_session' ), 10 );

		// Allow renewal of limited subscriptions
		add_filter( 'woocommerce_subscription_is_purchasable', array( &$this, 'is_purchasable' ), 12, 2 );
		add_filter( 'woocommerce_subscription_variation_is_purchasable', array( &$this, 'is_purchasable' ), 12, 2 );

		// Check if a user is requesting to create a renewal order for a subscription, needs to happen after $wp->query_vars are set
		add_action( 'template_redirect', array( &$this, 'maybe_setup_cart' ), 100 );

		add_action( 'woocommerce_remove_cart_item', array( &$this, 'maybe_remove_items' ), 10, 1 );
		add_action( 'woocommerce_before_cart_item_quantity_zero', array( &$this, 'maybe_remove_items' ), 10, 1 );

		add_filter( 'woocommerce_cart_item_removed_title', array( &$this, 'items_removed_title' ), 10, 2 );

		add_action( 'woocommerce_cart_item_restored', array( &$this, 'maybe_restore_items' ), 10, 1 );
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
			$order_key = $_GET['key'];
			$order_id  = ( isset( $wp->query_vars['order-pay'] ) ) ? $wp->query_vars['order-pay'] : absint( $_GET['order_id'] );
			$order     = wc_get_order( $wp->query_vars['order-pay'] );

			if ( $order->order_key == $order_key && $order->has_status( array( 'pending', 'failed' ) ) && wcs_order_contains_renewal( $order ) ) {

				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );

				foreach ( $subscriptions as $subscription ) {
					$this->setup_cart( $subscription, array(
						'subscription_id'  => $subscription->id,
						'renewal_order_id' => $order_id,
					) );
				}

				if ( WC()->cart->cart_contents_count != 0 ) {
					// Store renewal order's ID in session so it can be re-used after payment
					WC()->session->set( 'order_awaiting_payment', $order_id );
				}

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
		$success = true;

		foreach ( $subscription->get_items() as $item_id => $line_item ) {
			// Load all product info including variation data
			$product_id   = (int) apply_filters( 'woocommerce_add_to_cart_product_id', $line_item['product_id'] );
			$quantity     = (int) $line_item['qty'];
			$variation_id = (int) $line_item['variation_id'];
			$variations   = array();

			foreach ( $line_item['item_meta'] as $meta_name => $meta_value ) {
				if ( taxonomy_is_product_attribute( $meta_name ) ) {
					$variations[ $meta_name ] = $meta_value[0];
				} elseif ( meta_is_product_attribute( $meta_name, $meta_value[0], $product_id ) ) {
					$variations[ $meta_name ] = $meta_value[0];
				}
			}

			$product = get_product( $line_item['product_id'] );

			// The notice displayed when a subscription product has been deleted and the custoemr attempts to manually renew or make a renewal payment for a failed recurring payment for that product/subscription
			// translators: placeholder is an item name
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

			if ( wcs_is_subscription( $subscription ) ) {
				$cart_item_data['subscription_line_item_id'] = $item_id;
			}

			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations, apply_filters( 'woocommerce_order_again_cart_item_data', array( $this->cart_item_key => $cart_item_data ), $line_item, $subscription ) );
			$success       = $success && (bool) $cart_item_key;
		}

		// If a product linked to a subscription failed to be added to the cart prevent partially paying for the order by removing all cart items.
		if ( ! $success && wcs_is_subscription( $subscription ) ) {
			wc_add_notice( sprintf( esc_html__( 'Subscription #%d has not been added to the cart.', 'woocommerce-subscriptions' ), $subscription->id ) , 'error' );
			WC()->cart->empty_cart( true );
		}

		do_action( 'woocommerce_setup_cart_for_' . $this->cart_item_key, $subscription, $cart_item_data );
	}

	/**
	 * Does some housekeeping. Fires after the items have been passed through the get items from session filter. Because
	 * that filter is not good for removing cart items, we need to work around that by doing it later, in the cart
	 * loaded from session action.
	 *
	 * This checks cart items whether underlying subscriptions / renewal orders they depend exist. If not, they are
	 * removed from the cart.
	 *
	 * @param $cart WC_Cart the one we got from session
	 */
	public function cart_items_loaded_from_session( $cart ) {
		$removed_count_subscription = $removed_count_order = 0;

		foreach ( $cart->cart_contents as $key => $item ) {
			if ( isset( $item[ $this->cart_item_key ]['subscription_id'] ) && ! wcs_is_subscription( $item[ $this->cart_item_key ]['subscription_id'] ) ) {
				$cart->remove_cart_item( $key );
				$removed_count_subscription++;
				continue;
			}

			if ( isset( $item[ $this->cart_item_key ]['renewal_order_id'] ) && ! 'shop_order' == get_post_type( $item[ $this->cart_item_key ]['renewal_order_id'] ) ) {
				$cart->remove_cart_item( $key );
				$removed_count_order++;
				continue;
			}
		}

		if ( $removed_count_subscription ) {
			$error_message = esc_html( _n( 'We couldn\'t find the original subscription for an item in your cart. The item was removed.', 'We couldn\'t find the original subscriptions for items in your cart. The items were removed.', $removed_count_subscription, 'woocommerce-subscriptions' ) );
			if ( ! wc_has_notice( $error_message, 'notice' ) ) {
				wc_add_notice( $error_message, 'notice' );
			}
		}

		if ( $removed_count_order ) {
			$error_message = esc_html( _n( 'We couldn\'t find the original renewal order for an item in your cart. The item was removed.', 'We couldn\'t find the original renewal orders for items in your cart. The items were removed.', $removed_count_order, 'woocommerce-subscriptions' ) );
			if ( ! wc_has_notice( $error_message, 'notice' ) ) {
				wc_add_notice( $error_message, 'notice' );
			}
		}
	}

	/**
	 * Restore renewal flag when cart is reset and modify Product object with renewal order related info
	 *
	 * @since 2.0
	 */
	public function get_cart_item_from_session( $cart_item_session_data, $cart_item, $key ) {

		if ( isset( $cart_item[ $this->cart_item_key ]['subscription_id'] ) ) {
			$cart_item_session_data[ $this->cart_item_key ] = $cart_item[ $this->cart_item_key ];

			$_product = $cart_item_session_data['data'];

			// Need to get the original subscription price, not the current price
			$subscription       = wcs_get_subscription( $cart_item[ $this->cart_item_key ]['subscription_id'] );

			if ( $subscription ) {
				$subscription_items = $subscription->get_items();
				$item_to_renew      = $subscription_items[ $cart_item_session_data[ $this->cart_item_key ]['subscription_line_item_id'] ];

				$price = $item_to_renew['line_subtotal'];

				if ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) ) {
					$price += $item_to_renew['line_subtotal_tax'];
				}

				$_product->price = $price / $item_to_renew['qty'];

				// Don't carry over any sign up fee
				$_product->subscription_sign_up_fee = 0;

				$_product->post->post_title = apply_filters( 'woocommerce_subscriptions_renewal_product_title', $_product->get_title(), $_product );

				// Make sure the same quantity is renewed
				$cart_item_session_data['quantity'] = $item_to_renew['qty'];
			}
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
	 * When completing checkout for a subscription renewal, update the address on the subscription to use
	 * the shipping/billing address entered in case it has changed since the subscription was first created.
	 *
	 * @since 2.0
	 */
	public function maybe_update_subscription_customer_data( $update_customer_data, $checkout_object ) {

		$cart_renewal_item = wcs_cart_contains_renewal();

		if ( false !== $cart_renewal_item ) {

			$subscription = wcs_get_subscription( $cart_renewal_item[ $this->cart_item_key ]['subscription_id'] );

			$billing_address = array();
			if ( $checkout_object->checkout_fields['billing'] ) {
				foreach ( array_keys( $checkout_object->checkout_fields['billing'] ) as $field ) {
					$field_name = str_replace( 'billing_', '', $field );
					$billing_address[ $field_name ] = $checkout_object->get_posted_address_data( $field_name );
				}
			}

			$shipping_address = array();
			if ( $checkout_object->checkout_fields['shipping'] ) {
				foreach ( array_keys( $checkout_object->checkout_fields['shipping'] ) as $field ) {
					$field_name = str_replace( 'shipping_', '', $field );
					$shipping_address[ $field_name ] = $checkout_object->get_posted_address_data( $field_name, 'shipping' );
				}
			}

			$subscription->set_address( $billing_address, 'billing' );
			$subscription->set_address( $shipping_address, 'shipping' );
		}

		return $update_customer_data;
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

			// Adding to cart from the product page or paying for a renewal
			if ( isset( $_GET[ $this->cart_item_key ] ) || isset( $_GET['subscription_renewal'] ) || wcs_cart_contains_renewal() ) {

				$is_purchasable = true;

			} else if ( WC()->session->cart ) {

				foreach ( WC()->session->cart as $cart_item_key => $cart_item ) {

					if ( $product->id == $cart_item['product_id'] && isset( $cart_item['subscription_renewal'] ) ) {
						$is_purchasable = true;
						break;
					}
				}
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

		if ( wcs_order_contains_renewal( $order ) ) {
			$pay_url = add_query_arg( array( $this->cart_item_key => 'true' ), $pay_url );
		}

		return $pay_url;
	}

	/**
	 * Customise which actions are shown against a subscription renewal order on the My Account page.
	 *
	 * @since 2.0
	 */
	public function filter_my_account_my_orders_actions( $actions, $order ) {

		if ( wcs_order_contains_renewal( $order ) ) {

			unset( $actions['cancel'] );

			// If the subscription has been deleted or reactivated some other way, don't support payment on the order
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );

			foreach ( $subscriptions as $subscription ) {
				if ( empty( $subscription ) || ! $subscription->has_status( array( 'on-hold', 'pending' ) ) ) {
					unset( $actions['pay'] );
					break;
				}
			}
		}

		return $actions;
	}

	/**
	 * When a failed renewal order is being paid for via checkout, make sure WC_Checkout::create_order() preserves its
	 * status as 'failed' until it is paid. By default, it will always set it to 'pending', but we need it left as 'failed'
	 * so that we can correctly identify the status change in @see self::maybe_change_subscription_status().
	 *
	 * @param string Default order status for orders paid for via checkout. Default 'pending'
	 * @since 2.0
	 */
	public function maybe_preserve_order_status( $order_status ) {

		if ( null !== WC()->session ) {

			$order_id = absint( WC()->session->order_awaiting_payment );

			if ( $order_id > 0 && ( $order = wc_get_order( $order_id ) ) && wcs_order_contains_renewal( $order ) && $order->has_status( 'failed' ) ) {
				$order_status = 'failed';
			}
		}

		return $order_status;
	}

	/**
	 * Removes all the linked renewal/resubscribe items from the cart if a renewal/resubscribe item is removed.
	 *
	 * @param string $cart_item_key The cart item key of the item removed from the cart.
	 * @since 2.0
	 */
	public function maybe_remove_items( $cart_item_key ) {

		if ( isset( WC()->cart->cart_contents[ $cart_item_key ][ $this->cart_item_key ]['subscription_id'] ) ) {

			$removed_item_count = 0;
			$subscription_id    = WC()->cart->cart_contents[ $cart_item_key ][ $this->cart_item_key ]['subscription_id'];

			foreach ( WC()->cart->cart_contents as $key => $cart_item ) {

				if ( isset( $cart_item[ $this->cart_item_key ] ) && $subscription_id == $cart_item[ $this->cart_item_key ]['subscription_id'] ) {
					WC()->cart->removed_cart_contents[ $key ] = WC()->cart->cart_contents[ $key ];
					unset( WC()->cart->cart_contents[ $key ] );
					$removed_item_count++;
				}
			}

			//remove the renewal order flag
			unset( WC()->session->order_awaiting_payment );

			if ( $removed_item_count > 1 && 'woocommerce_before_cart_item_quantity_zero' == current_filter() ) {
				wc_add_notice( esc_html__( 'All linked subscription items have been removed from the cart.', 'woocommerce-subscriptions' ), 'notice' );
			}
		}
	}

	/**
	 * Formats the title of the product removed from the cart. Because we have removed all
	 * linked renewal/resubscribe items from the cart we need a product title to reflect that.
	 *
	 * @param string $product_title
	 * @param $cart_item
	 * @return string $product_title
	 * @since 2.0
	 */
	public function items_removed_title( $product_title, $cart_item ) {

		if ( isset( $cart_item[ $this->cart_item_key ]['subscription_id'] ) ) {
			$subscription  = wcs_get_subscription( absint( $cart_item[ $this->cart_item_key ]['subscription_id'] ) );
			$product_title = ( count( $subscription->get_items() ) > 1 ) ? esc_html__( 'All linked subscription items were', 'woocommerce-subscriptions' ) : $product_title;
		}

		return $product_title;
	}

	/**
	 * Restores all linked renewal/resubscribe items to the cart if the customer has restored one.
	 *
	 * @param string $cart_item_key The cart item key of the item being restored to the cart.
	 * @since 2.0
	 */
	public function maybe_restore_items( $cart_item_key ) {

		if ( isset( WC()->cart->cart_contents[ $cart_item_key ][ $this->cart_item_key ]['subscription_id'] ) ) {

			$subscription_id = WC()->cart->cart_contents[ $cart_item_key ][ $this->cart_item_key ]['subscription_id'];

			foreach ( WC()->cart->removed_cart_contents as $key => $cart_item ) {

				if ( isset( $cart_item[ $this->cart_item_key ] ) && $key != $cart_item_key && $cart_item[ $this->cart_item_key ]['subscription_id'] == $subscription_id ) {
					WC()->cart->cart_contents[ $key ] = WC()->cart->removed_cart_contents[ $key ];
					unset( WC()->cart->removed_cart_contents[ $key ] );
				}
			}

			//restore the renewal order flag
			if ( isset( WC()->cart->cart_contents[ $cart_item_key ][ $this->cart_item_key ]['renewal_order_id'] ) ) {
				WC()->session->set( 'order_awaiting_payment', WC()->cart->cart_contents[ $cart_item_key ][ $this->cart_item_key ]['renewal_order_id'] );
			}
		}
	}
}
new WCS_Cart_Renewal();
