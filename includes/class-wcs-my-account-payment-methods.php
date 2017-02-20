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
		if ( class_exists( 'WC_Payment_Token_CC' ) ) {
			add_filter( 'woocommerce_payment_methods_list_item', __CLASS__ . '::flag_subscription_payment_token_deletions', 10, 2 );
			add_action( 'woocommerce_before_account_payment_methods', __CLASS__ . '::display_delete_token_warning', 10, 1 );
		}
	}

	/**
	 * Add additional query args to delete token URLs which are currently being used for subscription automatic payments.
	 *
	 * @param  array data about the token including a list of actions which can be triggered by the customer from their my account page
	 * @param  WC_Payment_Token_CC payment token object
	 * @return array payment token data
	 */
	public static function flag_subscription_payment_token_deletions( $payment_token_data, $payment_token ) {

		if ( $payment_token instanceof WC_Payment_Token_CC && isset( $payment_token_data['actions']['delete']['url'] ) ) {

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
				$notice = esc_html__( 'We couldn\'t find the token you are trying to delete. Please try again.', 'woocommerce-subscriptions' );
				wc_print_notice( $notice, 'error' );
				return;
			}

			$notice  = esc_html__( 'You have just attempted to delete a token which is used for automatic subscription payments. Deleting this payment token may mean future subscription renewals will require manual payments.', 'woocommerce-subscriptions' );
			$actions = array();

			// If the customer is deleting the default token --- if there is just one other token we can probably add the option to switch to that
			if ( $token->is_default() ) {
				$notice .= esc_html__( ' Do you wish to delete the payment method?', 'woocommerce-subscriptions' );
				$actions = array(
					'yes' => sprintf( '<a href="' . $_GET['delete_url'] . '">%s</a>', esc_html_x( 'Yes', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ) ),
					'no'  => sprintf( '<a href="' . wc_get_account_endpoint_url( 'payment-methods' ) . '">%s</a>', esc_html_x( 'No', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ) ),
				);
			} else {
				$notice .= esc_html__( ' How would you like to proceed?', 'woocommerce-subscriptions' );
				$actions = array(
					'delete'            => sprintf( '<a href="' . $_GET['delete_url'] . '">%s</a>', esc_html_x( 'Delete token', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ) ),
					'delete_and_update' => sprintf( '<a href="' . add_query_arg( 'delete_and_update_subscription_token', 'true', $_GET['delete_url'] ) . '">%s</a>', esc_html_x( 'Delete and set subscriptions to default', 'user option when deleting a payment token from their my account page', 'woocommerce-subscriptions' ) ),
				);
			}

			$notice .= '</br>';
			$notice .= implode( ' | ', $actions );

			wc_print_notice( $notice, 'notice' );
		}
	}
}
WCS_My_Account_Payment_Methods::init();
