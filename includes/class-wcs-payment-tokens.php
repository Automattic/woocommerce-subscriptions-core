<?php
/**
 * WooCommerce Subscriptions Payment Tokens
 *
 * An API for storing and managing tokens for subscriptions.
 *
 * @package  WooCommerce Subscriptions
 * @category Class
 * @author   Prospress
 * @since    2.5.0
 */

class WCS_Payment_Tokens extends WC_Payment_Tokens {

	// A cache of a customer's payment tokens to avoid running multiple queries in the same request.
	protected static $customer_tokens = array();

	/**
	 * Update the subscription payment meta to change from an old payment token to a new one.
	 *
	 * @param  WC_Subscription $subscription The subscription to update.
	 * @param  WC_Payment_Token $new_token   The new payment token.
	 * @param  WC_Payment_Token $old_token   The old payment token.
	 * @return bool Whether the subscription was updated or not.
	 */
	public static function update_subscription_token( $subscription, $new_token, $old_token ) {
		$payment_method_meta   = apply_filters( 'woocommerce_subscription_payment_meta', array(), $subscription );
		$token_payment_gateway = $old_token->get_gateway_id();
		$token_meta_key        = '';

		// Attempt to find the token meta key from the subscription payment meta and the old token.
		if ( is_array( $payment_method_meta ) && isset( $payment_method_meta[ $token_payment_gateway ] ) && is_array( $payment_method_meta[ $token_payment_gateway ] ) ) {
			foreach ( $payment_method_meta[ $token_payment_gateway ] as $meta_table => $meta ) {
				foreach ( $meta as $meta_key => $meta_data ) {
					if ( $old_token->get_token() === $meta_data['value'] ) {
						$token_meta_key = $meta_key;
						break 2;
					}
				}
			}
		}

		$updated = update_post_meta( $subscription->get_id(), $token_meta_key, $new_token->get_token(), $old_token->get_token() );

		if ( $updated ) {
			do_action( 'woocommerce_subscription_token_changed', $subscription, $new_token, $old_token );
		}

		return $updated;
	}

	/**
	 * Update all the customer's subscription tokens to one token.
	 *
<<<<<<< HEAD
	 * @param string $token_id The token ID to assign to all subscriptions.
=======
	 * @param string $token_id The token ID to assign to all subscriptions 
>>>>>>> 0f3bda701d423d1659823a72680fa867f92fed30
	 * @since 2.5.0
	 */
	public static function update_all_subscription_tokens( $token_id ) {

		// init payment gateways
		WC()->payment_gateways();

		$default_token = self::get( $token_id );

		if ( ! $default_token ) {
			return;
		}

		$tokens = self::get_customer_tokens( $default_token->get_gateway_id(), $default_token->get_user_id() );
		unset( $tokens[ $default_token_id ] );

		foreach ( $tokens as $old_token ) {
			foreach ( self::get_subscriptions_by_token( $old_token ) as $subscription ) {
				$subscription = wcs_get_subscription( $subscription );

				if ( ! empty( $subscription ) && self::update_subscription_token( $subscription, $default_token, $old_token ) ) {
					do_action( 'woocommerce_subscription_token_changed', $subscription, $old_token, $default_token );
				}
			}
		}
	}

	/**
	 * Get subscriptions by a WC_Payment_Token. All automatic subscriptions with the token's payment method,
	 * customer id and token value stored in post meta will be returned.
	 *
<<<<<<< HEAD
	 * @param  WC_Payment_Token $payment_token Payment token object.
=======
	 * @param  WC_Payment_Token payment token object
>>>>>>> 0f3bda701d423d1659823a72680fa867f92fed30
	 * @return array subscription posts
	 * @since  2.2.7
	 */
	public static function get_subscriptions_by_token( $payment_token ) {

<<<<<<< HEAD
		$meta_query = array(
			array(
				'key'   => '_payment_method',
				'value' => $payment_token->get_gateway_id(),
			),
			array(
				'key'   => '_requires_manual_renewal',
				'value' => 'false',
			),
			array(
				'value' => $payment_token->get_token(),
			),
		);
		$user_subscriptions = get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => array( 'wc-pending', 'wc-active', 'wc-on-hold' ),
			'meta_query'     => $meta_query,
			'posts_per_page' => -1,
			'post__in'       => WCS_Customer_Store::instance()->get_users_subscription_ids( $payment_token->get_user_id() ),
		) );
=======
		$user_subscriptions = self::get_subscription_posts(
			$payment_token->get_gateway_id(),
			$payment_token->get_token(),
			WCS_Customer_Store::instance()->get_users_subscription_ids( $payment_token->get_user_id() )
		);
>>>>>>> 0f3bda701d423d1659823a72680fa867f92fed30

		return apply_filters( 'woocommerce_subscriptions_by_payment_token', $user_subscriptions, $payment_token );
	}

	/**
	 * Get WC_Payment_Token by a subscription.
	 *
<<<<<<< HEAD
	 * @param  WC_Subscription $subscription A subscription object.
	 * @param  string $payment_gateway_id The subscriptions payment gateway ID.
	 * @return WC_Payment_Token Subscription token.
=======
	 * @param WC_Subscription $subscription A subscription object
	 * @param string $payment_gateway_id The subscriptions payment gateway ID
	 * @return WC_Payment_Token subscription token
>>>>>>> 0f3bda701d423d1659823a72680fa867f92fed30
	 * @since  2.5.0
	 */
	public static function get_token_by_subscription( $subscription, $payment_gateway_id ) {

<<<<<<< HEAD
		$tokens = self::get_customer_tokens( $payment_gateway_id, $subscription->get_customer_id() );

		foreach( $tokens as $token ) {
			if ( $token->get_gateway_id() != $payment_gateway_id ) {
				continue;
			}
			$subs = self::get_subscriptions_by_token( $token );
			foreach( $subs as $sub ) {
				if ( $sub->ID == $subscription->get_id() ) {
					return $token;
				}
=======
		foreach( self::get_customer_tokens( $payment_gateway_id ) as $token ) {

			$posts = self::get_subscription_posts(
				$payment_gateway_id,
				$token->get_token(),
				array( $subscription->get_id() )
			);

			if ( count( $posts ) ) {
				return $token;
>>>>>>> 0f3bda701d423d1659823a72680fa867f92fed30
			}
		}

		return false;
	}

	/**
	 * Get subscription posts by gateway ID, payment token & post IDs.
	 *
<<<<<<< HEAD
	 * @param  string $payment_gateway_id The subscriptions payment gateway ID.
	 * @param  string $token A payment token.
	 * @param  array $post_ids List of subscription post IDs.
	 * @return array WP posts
	 * @since  2.5.0
	 */
	protected static function get_subscription_posts( $payment_gateway_id, $token, $post_ids ) {
=======
	 * @param string $payment_gateway_id The subscriptions payment gateway ID
	 * @param string $token A payment token
	 * @param array $post_ids List of subscription post IDs.
	 * @return array WP posts
	 * @since  2.5.0
	 */
	protected function get_subscription_posts( $payment_gateway_id, $token, $post_ids ) {
>>>>>>> 0f3bda701d423d1659823a72680fa867f92fed30

		$meta_query = array(
			array(
				'key'   => '_payment_method',
				'value' => $payment_gateway_id,
			),
			array(
				'key'   => '_requires_manual_renewal',
				'value' => 'false',
			),
			array(
				'value' => $token,
			),
		);

		return get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => array( 'wc-pending', 'wc-active', 'wc-on-hold' ),
			'meta_query'     => $meta_query,
			'posts_per_page' => -1,
			'post__in'       => $post_ids,
		) );
	}
	/**
	 * Get a list of customer payment tokens. Caches results to avoid multiple database queries per request
	 *
	 * @param  string (optional) Gateway ID for getting tokens for a specific gateway.
	 * @param  int (optional) The customer id - defaults to the current user.
	 * @return array of WC_Payment_Token objects.
	 * @since  2.2.7
	 */
	public static function get_customer_tokens( $gateway_id = '', $customer_id = '' ) {
		if ( '' === $customer_id ) {
			$customer_id = get_current_user_id();
		}

		if ( ! isset( self::$customer_tokens[ $customer_id ][ $gateway_id ] ) ) {
			self::$customer_tokens[ $customer_id ][ $gateway_id ] = parent::get_customer_tokens( $customer_id, $gateway_id );
		}

		return self::$customer_tokens[ $customer_id ][ $gateway_id ];
	}

	/**
	 * Get the customer's alternative token.
	 *
	 * @param  WC_Payment_Token $token The token to find an alternative for.
	 * @return WC_Payment_Token The customer's alternative token.
	 * @since  2.2.7
	 */
	public static function get_customers_alternative_token( $token ) {
		$payment_tokens    = self::get_customer_tokens( $token->get_gateway_id(), $token->get_user_id() );
		$alternative_token = null;

		// Remove the token we're trying to find an alternative for.
		unset( $payment_tokens[ $token->get_id() ] );

		if ( count( $payment_tokens ) === 1 ) {
			$alternative_token = reset( $payment_tokens );
		} else {
			foreach ( $payment_tokens as $payment_token ) {
				// If there is a default token we can use it as an alternative.
				if ( $payment_token->is_default() ) {
					$alternative_token = $payment_token;
					break;
				}
			}
		}

		return $alternative_token;
	}

	/**
	 * Determine if the customer has an alternative token.
	 *
	 * @param  WC_Payment_Token $token Payment token object.
	 * @return bool
	 * @since  2.2.7
	 */
	public static function customer_has_alternative_token( $token ) {
		return self::get_customers_alternative_token( $token ) !== null;
	}

}
