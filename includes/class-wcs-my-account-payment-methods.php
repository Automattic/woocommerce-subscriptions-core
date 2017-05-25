<?php
/**
 * Manage the process of deleting, adding, assigning default payment tokens associated with automatic subscriptions
 *
 * @package  WooCommerce Subscriptions
 * @category Class
 * @author   Prospress
 * @since    2.1.4
 */
class WCS_My_Account_Payment_Methods {

	/**
	 * Initialize filters and hooks for class.
	 *
	 * @since 2.1.4
	 */
	public static function init() {

		// Only hook class functions if the payment token object exists
		if ( class_exists( 'WC_Payment_Token' ) ) {
			add_filter( 'woocommerce_payment_methods_list_item', __CLASS__ . '::flag_subscription_payment_token_deletions', 10, 2 );
			add_action( 'woocommerce_payment_token_deleted', __CLASS__ . '::maybe_update_subscriptions_payment_meta', 10, 2 );
		}
	}

	/**
	 * Add additional query args to delete token URLs which are being used for subscription automatic payments.
	 *
	 * @param  array data about the token including a list of actions which can be triggered by the customer from their my account page
	 * @param  WC_Payment_Token payment token object
	 * @return array payment token data
	 * @since  2.1.4
	 */
	public static function flag_subscription_payment_token_deletions( $payment_token_data, $payment_token ) {

		if ( $payment_token instanceof WC_Payment_Token && isset( $payment_token_data['actions']['delete']['url'] ) ) {

			$user_subscriptions = self::get_subscriptions_by_token( $payment_token );

			if ( 0 < count( $user_subscriptions ) ) {
				$delete_subscription_token_args = array(
					'delete_subscription_token' => $payment_token->get_id(),
					'wcs_nonce'                 => wp_create_nonce( 'delete_subscription_token_' . $payment_token->get_id() ),
					'delete_url'                => $payment_token_data['actions']['delete']['url'],
				);

				$payment_token_data['actions']['delete']['url'] = add_query_arg( $delete_subscription_token_args, wc_get_account_endpoint_url( 'payment-methods' ) );
			}
		}

		return $payment_token_data;
	}

	/**
	 * Update subscriptions using a deleted token to use a new token. Subscriptions with the
	 * old token value stored in post meta will be updated using the same meta key to use the
	 * new token value.
	 *
	 * @param int The deleted token id
	 * @param WC_Payment_Token The deleted token object
	 * @since 2.1.4
	 */
	public static function maybe_update_subscriptions_payment_meta( $deleted_token_id, $deleted_token ) {

		if ( isset( $_GET['delete_and_update_subscription_token'] ) ) {

			// init payment gateways
			WC()->payment_gateways();

			$new_token = WC_Payment_Tokens::get( $_GET['delete_and_update_subscription_token'] );

			if ( empty( $new_token ) ) {
				return;
			}

			$subscriptions  = self::get_subscriptions_by_token( $deleted_token );
			$token_meta_key = '';

			foreach ( $subscriptions as $subscription ) {
				$subscription = wcs_get_subscription( $subscription );

				if ( empty( $subscription ) ) {
					continue;
				}

				// Attempt to find the token meta key if we haven't already found it.
				if ( empty( $token_meta_key ) ) {
					$payment_method_meta = apply_filters( 'woocommerce_subscription_payment_meta', array(), $subscription );

					if ( is_array( $payment_method_meta ) && isset( $payment_method_meta[ $deleted_token->get_gateway_id() ] ) && is_array( $payment_method_meta[ $deleted_token->get_gateway_id() ] ) ) {
						foreach ( $payment_method_meta[ $deleted_token->get_gateway_id() ] as $meta_table => $meta ) {
							foreach ( $meta as $meta_key => $meta_data ) {
								if ( $deleted_token->get_token() === $meta_data['value'] ) {
									$token_meta_key = $meta_key;
									break 2;
								}
							}
						}
					}
				}

				$updated = update_post_meta( $subscription->get_id(), $token_meta_key, $new_token->get_token(), $deleted_token->get_token() );

				if ( $updated ) {
					$subscription->add_order_note( sprintf( _x( 'Payment method meta updated after customer deleted a token from their My Account page. Payment meta changed from %1$s to %2$s', 'used in subscription note', 'woocommerce-subscriptions' ), $deleted_token->get_token(), $new_token->get_token() ) );
					do_action( 'woocommerce_subscription_token_changed', $subscription, $new_token, $deleted_token );
				}
			}
		}
	}

	/**
	 * Get subscriptions by a WC_Payment_Token. All automatic subscriptions with the token's payment method,
	 * customer id and token value stored in post meta will be returned.
	 *
	 * @param  WC_Payment_Token payment token object
	 * @return array subscription posts
	 * @since  2.1.4
	 */
	public static function get_subscriptions_by_token( $payment_token ) {

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
				'key'   => '_customer_user',
				'value' => $payment_token->get_user_id(),
				'type'  => 'numeric',
			),
			array(
				'value' => $payment_token->get_token(),
			),
		);

		$user_subscriptions = get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => 'any',
			'meta_query'     => $meta_query,
			'posts_per_page' => -1,
		) );

		return apply_filters( 'woocommerce_subscriptions_by_payment_token', $user_subscriptions, $payment_token );
	}

	/**
	 * Get a WC_Payment_Token label. eg Visa ending in 1234
	 *
	 * @param  WC_Payment_Token payment token object
	 * @return string WC_Payment_Token label
	 * @since  2.1.4
	 */
	public static function get_token_label( $token ) {

		if ( method_exists( $token, 'get_last4' ) && $token->get_last4() ) {
			$label = sprintf( __( '%s ending in %s', 'woocommerce-subscriptions' ), esc_html( wc_get_credit_card_type_label( $token->get_card_type() ) ), esc_html( $token->get_last4() ) );
		} else {
			$label = esc_html( wc_get_credit_card_type_label( $token->get_card_type() ) );
		}

		return $label;
	}
}
WCS_My_Account_Payment_Methods::init();
