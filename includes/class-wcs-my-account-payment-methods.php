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
			add_action( 'woocommerce_before_account_payment_methods', __CLASS__ . '::display_delete_token_warning', 10, 1 );
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
	 * Before deleting a subscription payment token, display a warning with possible options.
	 *
	 * @param bool Whether the customer has saved payment tokens or not
	 * @since 2.1.4
	 */
	public static function display_delete_token_warning( $has_methods ) {

		if ( $has_methods && isset( $_GET['delete_subscription_token'] ) && ! empty( $_GET['wcs_nonce'] ) && wp_verify_nonce( $_GET['wcs_nonce'], 'delete_subscription_token_' . $_GET['delete_subscription_token'] ) ) {

			$token_id = $_GET['delete_subscription_token'];
			$token    = WC_Payment_Tokens::get( $token_id );

			if ( empty( $token ) ) {
				$notice = esc_html__( 'We couldn\'t find the payment method token you are trying to delete. Please try again.', 'woocommerce-subscriptions' );
				wc_print_notice( $notice, 'error' );
				return;
			}

			$notice                 = esc_html__( 'This payment method is used for automatic subscription payments, deleting it may mean future subscription renewals will require manual payments.', 'woocommerce-subscriptions' );
			$actions                = array();
			$customer_tokens        = WC_Payment_Tokens::get_customer_tokens( $token->get_user_id(), $token->get_gateway_id() ); // Payment token objects only store 1 value, however, payment gateways will typically have 2 - the customer id and token. Because of this, we can only switch between the same gateway.
			$has_single_alternative = count( $customer_tokens ) == 2;
			$has_default_token      = false;
			$default_token          = null;

			foreach ( $customer_tokens as $payment_token ) {
				if ( $payment_token->is_default() ) {
					$has_default_token = true;
					$default_token     = $payment_token;
					break;
				}
			}

			// If the customer has a single alternative or a default payment method we can offer to set it to.
			if ( $has_single_alternative || ( $has_default_token && ! $token->is_default() ) ) {

				$notice .= esc_html__( ' How would you like to proceed?', 'woocommerce-subscriptions' );
				$actions['delete'] = array(
					'url'  => $_GET['delete_url'],
					'text' => esc_html_x( 'Delete token', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ),
				);

				// If the customer has a default token and we're not deleting it, offer to switch to that
				if ( $has_default_token && ! $token->is_default() ) {

					$new_token = $default_token;
					$actions['delete_and_update']['text'] = esc_html_x( 'Delete and update subscriptions to use default', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' );
				} else {

					// Get the only other token object (alternative)
					$alternative_tokens           = array_diff_assoc( $customer_tokens, array( $token->get_id() => $token ) );
					$new_token                    = reset( $alternative_tokens );
					$actions['delete_and_update']['text'] = esc_html_x( 'Delete and update subscriptions to use alternative', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' );
				}

				$actions['delete_and_update']['url']   = add_query_arg( 'delete_and_update_subscription_token', $new_token->get_id(), $_GET['delete_url'] );
				$actions['delete_and_update']['text'] .= ' <small>(' . self::get_token_label( $new_token ) . ')</small>';
			} else {

				$notice .= esc_html__( ' Do you wish to delete the payment method?', 'woocommerce-subscriptions' );
				$actions = array(
					'yes' => array(
						'url' => $_GET['delete_url'],
						'text' => esc_html_x( 'Yes', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ),
					),
					'no'  => array(
						'url' => wc_get_account_endpoint_url( 'payment-methods' ),
						'text' => esc_html_x( 'No', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ),
					),
				);
			}

			$notice .= '</br>';
			$counter = count( $actions );

			foreach ( $actions as $action ) {
				$notice .= sprintf( '<a href="' . $action['url'] . '">%s</a>', $action['text'] );

				// is not the last action
				if ( 0 != --$counter ) {
					$notice .= ' | ';
				}
			}

			wc_print_notice( $notice, 'notice' );
		}
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

				// Attempt to find the token meta key if we haven't already found it.
				if ( empty( $token_meta_key ) ) {

					$payment_method_meta = apply_filters( 'woocommerce_subscription_payment_meta', array(), $subscription );
					if ( is_array( $payment_method_meta ) && isset( $payment_method_meta[ $deleted_token->get_gateway_id() ] ) && is_array( $payment_method_meta[ $deleted_token->get_gateway_id() ] ) ) {
						foreach ( $payment_method_meta[ $deleted_token->get_gateway_id() ] as $meta_table => $meta ) {
							foreach ( $meta as $meta_key => $meta_data ) {
								if ( $deleted_token->get_token() == $meta_data['value'] ) {
									$token_meta_key = $meta_key;
								}
							}
						}
					}
				}

				$updated = update_post_meta( $subscription->id, $token_meta_key, $new_token->get_token(), $deleted_token->get_token() );

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

		if ( method_exists( $token, 'get_last4' ) && ! empty( $token->get_last4() ) ) {
			$label = sprintf( __( '%s ending in %s', 'woocommerce-subscriptions' ), esc_html( wc_get_credit_card_type_label( $token->get_card_type() ) ), esc_html( $token->get_last4() ) );
		} else {
			$label = esc_html( wc_get_credit_card_type_label( $token->get_card_type() ) );
		}

		return $label;
	}
}
WCS_My_Account_Payment_Methods::init();
