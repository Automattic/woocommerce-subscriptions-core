<?php
/**
 * A class to make it possible to limit a subscription product.
 *
 * @package		WooCommerce Subscriptions
 * @category	Class
 * @since		2.1
 */
class WCS_Limiter {

	/* cache whether a given product is purchasable or not to save running lots of queries for the same product in the same request */
	protected static $is_purchasable_cache = array();

	/* cache the check on whether the session has an order awaiting payment for a given product */
	protected static $order_awaiting_payment_for_product = array();

	public static function init() {

		//Add limiting subscriptions options on edit product page
		add_action( 'woocommerce_product_options_reviews', __CLASS__ . '::admin_edit_product_fields' );

		add_filter( 'woocommerce_subscription_is_purchasable', __CLASS__ . '::is_purchasable_switch', 12, 2 );

		add_filter( 'woocommerce_subscription_variation_is_purchasable', __CLASS__ . '::is_purchasable_switch', 12, 2 );

		add_filter( 'woocommerce_subscription_is_purchasable', __CLASS__ . '::is_purchasable_renewal', 12, 2 );

		add_filter( 'woocommerce_subscription_variation_is_purchasable', __CLASS__ . '::is_purchasable_renewal', 12, 2 );

		add_filter( 'woocommerce_subscriptions_recurring_cart_key', __CLASS__ . '::get_recurring_cart_key', 10, 2 );

		add_filter( 'wcs_recurring_cart_next_payment_date', __CLASS__ . '::recurring_cart_next_payment_date', 100, 2 );

	}

	/**
	 * Adds limit options to 'Edit Product' screen.
	 *
	 * @since 2.1 Moved from WC_Subscriptions_Admin
	 */
	public static function admin_edit_product_fields() {
		global $post;

		echo '</div>';
		echo '<div class="options_group limit_subscription show_if_subscription show_if_variable-subscription">';

		// Only one Subscription per customer
		woocommerce_wp_select( array(
			'id'          => '_subscription_limit',
			'label'       => __( 'Limit Subscription', 'woocommerce-subscriptions' ),
			// translators: placeholders are opening and closing link tags
			'description' => sprintf( __( 'Only allow a customer to have one subscription to this product. %sLearn more%s.', 'woocommerce-subscriptions' ), '<a href="http://docs.woothemes.com/document/subscriptions/store-manager-guide/#limit-subscription">', '</a>' ),
			'options'     => array(
				'no'      => __( 'Do not limit', 'woocommerce-subscriptions' ),
				'active'  => __( 'Limit to one active subscription', 'woocommerce-subscriptions' ),
				'any'     => __( 'Limit to one of any status', 'woocommerce-subscriptions' ),
			),
		) );

		do_action( 'woocommerce_subscriptions_product_options_advanced' );
	}

	/**
	 * Canonical is_purchasable method to be called by product classes.
	 *
	 * @since 2.1
	 * @param bool $purchasable Whether the product is purchasable as determined by parent class
	 * @param mixed $product The product in question to be checked if it is purchasable.
	 * @param string $product_class Determines the subscription type of the product. Controls switch logic.
	 *
	 * @return bool
	 */
	public static function is_purchasable( $purchasable, $product ) {
		switch ( $product->get_type() ) {
			case 'subscription' :
			case 'variable-subscription' :
				if ( true === $purchasable && false === self::is_purchasable_product( $purchasable, $product ) ) {
					$purchasable = false;
				}
				break;
			case 'subscription_variation' :
				if ( 'no' != wcs_get_product_limitation( $product->parent ) && ! empty( WC()->cart->cart_contents ) && ! wcs_is_order_received_page() && ! wcs_is_paypal_api_page() ) {
					foreach ( WC()->cart->cart_contents as $cart_item ) {
						if ( $product->id == $cart_item['data']->id && $product->variation_id != $cart_item['data']->variation_id ) {
							$purchasable = false;
							break;
						}
					}
				}
				break;
		}
		return $purchasable;
	}


	/**
	 * If a product is limited and the customer already has a subscription, mark it as not purchasable.
	 *
	 * @since 2.1 Moved from WC_Subscriptions_Product
	 * @return bool
	 */
	public static function is_purchasable_product( $is_purchasable, $product ) {

		//Set up cache
		if ( ! isset( self::$is_purchasable_cache[ $product->id ] ) ) {
			self::$is_purchasable_cache[ $product->id ] = array();
		}

		if ( ! isset( self::$is_purchasable_cache[ $product->id ]['standard'] ) ) {
			self::$is_purchasable_cache[ $product->id ]['standard'] = $is_purchasable;

			if ( WC_Subscriptions_Product::is_subscription( $product->id ) && 'no' != wcs_get_product_limitation( $product ) && ! wcs_is_order_received_page() && ! wcs_is_paypal_api_page() ) {

				if ( wcs_is_product_limited_for_user( $product ) && ! self::order_awaiting_payment_for_product( $product->id ) ) {
					self::$is_purchasable_cache[ $product->id ]['standard'] = false;
				}
			}
		}
		return self::$is_purchasable_cache[ $product->id ]['standard'];

	}

	/**
	 * If a product is being marked as not purchasable because it is limited and the customer has a subscription,
	 * but the current request is to switch the subscription, then mark it as purchasable.
	 *
	 * @since 2.1 Moved from WC_Subscriptions_Switcher::is_purchasable
	 * @return bool
	 */
	public static function is_purchasable_switch( $is_purchasable, $product ) {
		$product_key = ( ! empty( $product->variation_id ) ) ? $product->variation_id : $product->id;

		if ( ! isset( self::$is_purchasable_cache[ $product_key ] ) ) {
			self::$is_purchasable_cache[ $product_key ] = array();
		}

		if ( ! isset( self::$is_purchasable_cache[ $product_key ]['switch'] ) ) {

			if ( false === $is_purchasable && wcs_is_product_switchable_type( $product ) && WC_Subscriptions_Product::is_subscription( $product->id ) && 'no' != wcs_get_product_limitation( $product ) && is_user_logged_in() && wcs_user_has_subscription( 0, $product->id, wcs_get_product_limitation( $product ) ) ) {

				//Adding to cart
				if ( isset( $_GET['switch-subscription'] ) ) {
					$is_purchasable = true;

					//Validating when restring cart from session
				} elseif ( WC_Subscriptions_Switcher::cart_contains_switches() ) {
					$is_purchasable = true;

				// Restoring cart from session, so need to check the cart in the session (WC_Subscriptions_Switcher::cart_contains_subscription_switch() only checks the cart)
				} elseif ( isset( WC()->session->cart ) ) {

					foreach ( WC()->session->cart as $cart_item_key => $cart_item ) {
						if ( $product->id == $cart_item['product_id'] && isset( $cart_item['subscription_switch'] ) ) {
							$is_purchasable = true;
							break;
						}
					}
				}
			}
			self::$is_purchasable_cache[ $product_key ]['switch'] = $is_purchasable;
		}
		return self::$is_purchasable_cache[ $product_key ]['switch'];
	}

	/**
	 * Determines whether a product is purchasable based on whether the cart is to resubscribe or renew.
	 *
	 * @since 2.1 Combines WCS_Cart_Renewal::is_purchasable and WCS_Cart_Resubscribe::is_purchasable
	 * @return bool
	 */
	public static function is_purchasable_renewal( $is_purchasable, $product ) {
		if ( false === $is_purchasable && false === self::is_purchasable_product( $is_purchasable, $product ) ) {

			// Resubscribe logic
			if ( isset( $_GET['resubscribe'] ) || false !== ( $resubscribe_cart_item = wcs_cart_contains_resubscribe() ) ) {
				$subscription_id       = ( isset( $_GET['resubscribe'] ) ) ? absint( $_GET['resubscribe'] ) : $resubscribe_cart_item['subscription_resubscribe']['subscription_id'];
				$subscription          = wcs_get_subscription( $subscription_id );

				if ( false != $subscription && $subscription->has_product( $product->id ) && wcs_can_user_resubscribe_to( $subscription ) ) {
					$is_purchasable = true;
				}

			// Renewal logic
			} elseif ( isset( $_GET['subscription_renewal'] ) || wcs_cart_contains_renewal() ) {
				$is_purchasable = true;

			// Restoring cart from session, so need to check the cart in the session (wcs_cart_contains_renewal() only checks the cart)
			} elseif ( WC()->session->cart ) {
				foreach ( WC()->session->cart as $cart_item_key => $cart_item ) {
					if ( $product->id == $cart_item['product_id'] && ( isset( $cart_item['subscription_renewal'] ) || isset( $cart_item['subscription_resubscribe'] ) ) ) {
						$is_purchasable = true;
						break;
					}
				}
			}
		}
		return $is_purchasable;
	}

	/**
	 * Check if the current session has an order awaiting payment for a subscription to a specific product line item.
	 *
	 * @since 2.1 Moved from WC_Subscriptions_Product
	 * @return bool
	 **/
	protected static function order_awaiting_payment_for_product( $product_id ) {
		global $wp;

		if ( ! isset( self::$order_awaiting_payment_for_product[ $product_id ] ) ) {

			self::$order_awaiting_payment_for_product[ $product_id ] = false;

			if ( ! empty( WC()->session->order_awaiting_payment ) || isset( $_GET['pay_for_order'] ) ) {

				$order_id = ! empty( WC()->session->order_awaiting_payment ) ? WC()->session->order_awaiting_payment : $wp->query_vars['order-pay'];
				$order    = wc_get_order( absint( $order_id ) );

				if ( is_object( $order ) && $order->has_status( array( 'pending', 'failed' ) ) ) {
					foreach ( $order->get_items() as $item ) {
						if ( $item['product_id'] == $product_id || $item['variation_id'] == $product_id ) {

							$subscriptions = wcs_get_subscriptions( array(
								'order_id'   => $order->id,
								'product_id' => $product_id,
							) );

							if ( ! empty( $subscriptions ) ) {
								$subscription = array_pop( $subscriptions );

								if ( $subscription->has_status( array( 'pending', 'on-hold' ) ) ) {
									self::$order_awaiting_payment_for_product[ $product_id ] = true;
								}
							}
							break;
						}
					}
				}
			}
		}

		return self::$order_awaiting_payment_for_product[ $product_id ];
	}

	public static function get_recurring_cart_key( $cart_key, $cart_item ) {
		if ( 'no' !== wcs_get_product_limitation( $cart_item['data'] ) && wcs_user_has_subscription( 0, $cart_item['product_id'] ) ) {
			$subscriptions = wcs_get_users_subscriptions();

			foreach( $subscriptions as $subscription ) {
				if ( $subscription->has_product( $cart_item['product_id'] ) && $subscription->has_status( 'pending-cancel' ) ) {
					remove_filter( 'woocommerce_subscriptions_recurring_cart_key', __METHOD__, 10, 2 );
					$cart_key = WC_Subscriptions_Cart::get_recurring_cart_key( $cart_item, $subscription->get_time( 'end' ) );
					add_filter( 'woocommerce_subscriptions_recurring_cart_key', __METHOD__, 10, 2 );
					break;
				}
			}
		}
		return $cart_key;
	}

	public static function recurring_cart_next_payment_date( $first_renewal_date, $cart ) {

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {

			if ( 'no' !== wcs_get_product_limitation( $cart_item['data'] ) && wcs_user_has_subscription( 0, $cart_item['product_id'] ) ) {
				$subscriptions = wcs_get_users_subscriptions();

				foreach( $subscriptions as $subscription ) {
					if ( $subscription->has_product( $cart_item['product_id'] ) && $subscription->has_status( 'pending-cancel' ) ) {
						$first_renewal_date = ( '1' != $cart_item['data']->subscription_length ) ? $subscription->get_date( 'end' ) : 0;
						break;
					}
				}
			}
		}
		return $first_renewal_date;
	}

}
WCS_Limiter::init();
