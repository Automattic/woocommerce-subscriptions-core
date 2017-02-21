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
		}
	}

	/**
	 * Add additional query args to delete token URLs which are currently being used for subscription automatic payments.
	 *
	 * @param  array data about the token including a list of actions which can be triggered by the customer from their my account page
	 * @param  WC_Payment_Token payment token object
	 * @return array payment token data
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
	 * @param  bool Whether the customer has saved payment tokens or not
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

			foreach ( $customer_tokens as $payment_token ) {
				if ( $payment_token->is_default() ) {
					$has_default_token = true;
					break;
				}
			}

			// If the customer has a single alternative or a default payment method we can offer to set it to that.
			if ( $has_single_alternative || ( $has_default_token && ! $token->is_default() ) ) {
				$notice .= esc_html__( ' How would you like to proceed?', 'woocommerce-subscriptions' );
				$actions['delete'] = array(
					'href' => $_GET['delete_url'],
					'text' => esc_html_x( 'Delete token', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ),
				);

				if ( $token->is_default() ) {
					$actions['delete_and_update'] = array(
						'href' => add_query_arg( 'delete_and_update_subscription_token', 'true', $_GET['delete_url'] ),
						'text' => esc_html_x( 'Delete and update subscriptions payment method', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ),
					);
				} else {
					$actions['delete_and_set_default'] = array(
						'href' => add_query_arg( 'delete_and_update_subscription_token', 'true', $_GET['delete_url'] ),
						'text' => esc_html_x( 'Delete and update subscriptions to use default', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ),
					);
				}
			} else {

				$notice .= esc_html__( ' Do you wish to delete the payment method?', 'woocommerce-subscriptions' );
				$actions = array(
					'yes' => array(
						'href' => $_GET['delete_url'],
						'text' => esc_html_x( 'Yes', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ),
					),
					'no'  => array(
						'href' => wc_get_account_endpoint_url( 'payment-methods' ),
						'text' => esc_html_x( 'No', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ),
					),
				);
			}

			$notice .= '</br>';
			$counter = count( $actions );

			foreach ( $actions as $action ) {
				$notice .= sprintf( '<a href="' . $action['href'] . '">%s</a>', $action['text'] );

				// is not the last action
				if ( 0 != --$counter ) {
					$notice .= ' | ';
				}
			}

			wc_print_notice( $notice, 'notice' );
		}
	}

	/**
	 * Get subscriptions by a WC_Payment_Token.
	 *
	 * @param  WC_Payment_Token payment token object
	 * @return array subscriptions
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
			'post_type'   => 'shop_subscription',
			'post_status' => 'any',
			'meta_query'  => $meta_query,
		) );

		return $user_subscriptions;
	}
}
WCS_My_Account_Payment_Methods::init();
