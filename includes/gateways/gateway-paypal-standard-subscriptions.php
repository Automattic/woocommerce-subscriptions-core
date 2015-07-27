<?php
/**
 * PayPal Standard Subscription Class.
 *
 * Filters necessary functions in the WC_Paypal class to allow for subscriptions.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_PayPal_Standard_Subscriptions
 * @category	Class
 * @author		Brent Shepherd
 * @since		1.0
 */

/**
 * Needs to be called after init so that $woocommerce global is setup
 **/
function create_paypal_standard_subscriptions() {
	WC_PayPal_Standard_Subscriptions::init();
}
add_action( 'init', 'create_paypal_standard_subscriptions', 10 );


class WC_PayPal_Standard_Subscriptions {

	public static $api_username;
	public static $api_password;
	public static $api_signature;

	public static $api_endpoint;

	private static $invoice_prefix;

	private static $paypal_settings;

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 1.0
	 */
	public static function init() {
		self::$paypal_settings = self::get_wc_paypal_settings();

		// Set creds
		self::$api_username  = ( isset( self::$paypal_settings['api_username'] ) ) ? self::$paypal_settings['api_username'] : '';
		self::$api_password  = ( isset( self::$paypal_settings['api_password'] ) ) ? self::$paypal_settings['api_password'] : '';
		self::$api_signature = ( isset( self::$paypal_settings['api_signature'] ) ) ? self::$paypal_settings['api_signature'] : '';

		// Invoice prefix added in WC 1.6.3
		self::$invoice_prefix = ( isset( self::$paypal_settings['invoice_prefix'] ) ) ? self::$paypal_settings['invoice_prefix'] : '';

		self::$api_endpoint = ( isset( self::$paypal_settings['testmode'] ) && 'no' == self::$paypal_settings['testmode'] ) ? 'https://api-3t.paypal.com/nvp' :  'https://api-3t.sandbox.paypal.com/nvp';

		// When necessary, set the PayPal args to be for a subscription instead of shopping cart
		add_filter( 'woocommerce_paypal_args', __CLASS__ . '::paypal_standard_subscription_args' );

		// Check a valid PayPal IPN request to see if it's a subscription *before* WC_Paypal::successful_request()
		add_action( 'valid-paypal-standard-ipn-request', __CLASS__ . '::process_paypal_ipn_request', 0 );

		// Set the PayPal Standard gateway to support subscriptions after it is added to the woocommerce_payment_gateways array
		add_filter( 'woocommerce_payment_gateway_supports', __CLASS__ . '::add_paypal_standard_subscription_support', 10, 3 );

		// Add PayPal API fields to PayPal form fields as required
		add_action( 'woocommerce_settings_start', __CLASS__ . '::add_subscription_form_fields', 100 );
		add_action( 'woocommerce_api_wc_gateway_paypal', __CLASS__ . '::add_subscription_form_fields', 100 );

		// When a subscriber or store manager changes a subscription's status in the store, change the status with PayPal
		add_action( 'woocommerce_subscription_cancelled_paypal', __CLASS__ . '::cancel_subscription' );
		add_action( 'woocommerce_subscription_pending-cancel_paypal', __CLASS__ . '::cancel_subscription' );
		add_action( 'woocommerce_subscription_expired_paypal', __CLASS__ . '::cancel_subscription' );
		add_action( 'woocommerce_subscription_on-hold_paypal', __CLASS__ . '::suspend_subscription' );
		add_action( 'woocommerce_subscription_activated_paypal', __CLASS__ . '::reactivate_subscription' );

		// Don't copy over PayPal details to Resubscribe Orders
		add_filter( 'wcs_resubscribe_order_created', __CLASS__ . '::remove_resubscribe_order_meta', 10, 2 );

		// Maybe show notice to enter PayPal API credentials
		add_action( 'admin_notices', __CLASS__ . '::maybe_show_admin_notice' );

		// When a payment is due, schedule a special check in one days time to make sure the payment went through
		add_action( 'woocommerce_scheduled_subscription_payment_paypal', __CLASS__ . '::schedule_payment_check' );

		// Don't automatically cancel a subscription with PayPal on payment method change - we'll cancel it ourselves
		add_action( 'woocommerce_subscriptions_pre_update_payment_method', __CLASS__ . '::maybe_remove_subscription_cancelled_callback', 10, 3 );
		add_action( 'woocommerce_subscription_payment_method_updated', __CLASS__ . '::maybe_reattach_subscription_cancelled_callback', 10, 3 );

		// Don't update payment methods immediately when changing to PayPal - wait for the IPN notification
		add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', __CLASS__ . '::maybe_dont_update_payment_method', 10, 2 );
	}

	/**
	 * Checks if the PayPal API credentials are set.
	 *
	 * @since 1.0
	 */
	public static function are_credentials_set() {

		$credentials_are_set = false;

		if ( ! empty( self::$api_username ) && ! empty( self::$api_password ) && ! empty( self::$api_signature ) ) {
			$credentials_are_set = true;
		}

		return apply_filters( 'wooocommerce_paypal_credentials_are_set', $credentials_are_set );
	}

	/**
	 * Add subscription support to the PayPal Standard gateway only when credentials are set
	 *
	 * @since 1.0
	 */
	public static function add_paypal_standard_subscription_support( $is_supported, $feature, $gateway ) {

		$supported_features = array(
			'subscriptions',
			'gateway_scheduled_payments',
			'subscription_payment_method_change_customer',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
		);

		if ( 'paypal' == $gateway->id && in_array( $feature, $supported_features ) && self::are_credentials_set() ) {
			$is_supported = true;
		}

		return $is_supported;
	}

	/**
	 * When a PayPal IPN messaged is received for a subscription transaction,
	 * check the transaction details and
	 *
	 * @since 1.0
	 */
	public static function process_paypal_ipn_request( $transaction_details ) {
		global $wpdb;

		$transaction_details = stripslashes_deep( $transaction_details );

		if ( ! in_array( $transaction_details['txn_type'], array( 'subscr_signup', 'subscr_payment', 'subscr_cancel', 'subscr_eot', 'subscr_failed', 'subscr_modify', 'recurring_payment_suspended', 'recurring_payment_suspended_due_to_max_failed_payment' ) ) ) {
			return;
		}

		if ( 'recurring_payment_suspended_due_to_max_failed_payment' == $transaction_details['txn_type'] && isset( $transaction_details['rp_invoice_id'] ) ) {
			WC_Gateway_Paypal::log( 'Returning as "recurring_payment_suspended_due_to_max_failed_payment" transaction is for a subscription created with Express Checkout' );
			return;
		}

		$transaction_details['txn_type'] = strtolower( $transaction_details['txn_type'] );

		WC_Gateway_Paypal::log( 'Subscription Transaction Type: ' . $transaction_details['txn_type'] );
		WC_Gateway_Paypal::log( 'Subscription transaction details: ' . print_r( $transaction_details, true ) );

		// Get the subscription ID and order_key with backward compatibility
		$subscription_id_and_key = self::get_order_id_and_key( $transaction_details, 'shop_subscription' );
		$subscription            = wcs_get_subscription( $subscription_id_and_key['order_id'] );
		$subscription_key        = $subscription_id_and_key['order_key'];

		// We have an invalid $subscription, probably because invoice_prefix has changed since the subscription was first created, so get the subscription by order key
		if ( ! isset( $subscription->id ) ) {
			$subscription = wcs_get_subscription( wc_get_order_id_by_order_key( $subscription_key ) );
		}

		if ( $subscription->order_key !== $subscription_key ) {
			WC_Gateway_Paypal::log( 'Subscription IPN Error: Subscription Key does not match invoice.' );
			exit;
		}

		$is_renewal_sign_up_after_failure = false;

		// If the invoice ID doesn't match the default invoice ID and contains the string '-wcsfrp-', the IPN is for a subscription payment to fix up a failed payment
		if ( false !== strpos( $transaction_details['invoice'], '-wcsfrp-' ) && in_array( $transaction_details['txn_type'], array( 'subscr_signup', 'subscr_payment' ) ) ) {

			$renewal_order = wc_get_order( substr( $transaction_details['invoice'], strrpos( $transaction_details['invoice'], '-' ) + 1 ) );

			// check if the failed signup has been previously recorded
			if ( $renewal_order->id != get_post_meta( $subscription->id, '_paypal_failed_sign_up_recorded', true ) ) {

				$is_renewal_sign_up_after_failure = true;

			}
		}

		// If the invoice ID doesn't match the default invoice ID and contains the string '-wcscpm-', the IPN is for a subscription payment method change
		if ( false !== strpos( $transaction_details['invoice'], '-wcscpm-' ) && 'subscr_signup' == $transaction_details['txn_type'] ) {
			$is_payment_change = true;
		} else {
			$is_payment_change = false;
		}

		if ( $is_renewal_sign_up_after_failure || $is_payment_change ) {

			// Store the old profile ID on the order (for the first IPN message that comes through)
			$existing_profile_id = self::get_subscriptions_paypal_id( $subscription );

			if ( empty( $existing_profile_id ) || $existing_profile_id !== $transaction_details['subscr_id'] ) {
				update_post_meta( $subscription->id, '_old_paypal_subscriber_id', $existing_profile_id );
				update_post_meta( $subscription->id, '_old_payment_method', $subscription->payment_method );
			}
		}

		// Ignore IPN messages when the payment method isn't PayPal
		if ( 'paypal' != $subscription->payment_method ) {

			// The 'recurring_payment_suspended' transaction is actually an Express Checkout transaction type, but PayPal also send it for PayPal Standard Subscriptions suspended by admins at PayPal, so we need to handle it *if* the subscription has PayPal as the payment method, or leave it if the subscription is using a different payment method (because it might be using PayPal Express Checkout or PayPal Digital Goods)
			if ( 'recurring_payment_suspended' == $transaction_details['txn_type'] ) {

				WC_Gateway_Paypal::log( '"recurring_payment_suspended" IPN ignored: recurring payment method is not "PayPal". Returning to allow another extension to process the IPN, like PayPal Digital Goods.' );
				return;

			} elseif ( false === $is_renewal_sign_up_after_failure && false === $is_payment_change ) {

				WC_Gateway_Paypal::log( 'IPN ignored, recurring payment method has changed.' );
				exit;

			}
		}

		if ( isset( $transaction_details['ipn_track_id'] ) ) {

			// Make sure the IPN request has not already been handled
			$handled_ipn_requests = get_post_meta( $subscription->id, '_paypal_ipn_tracking_ids', true );

			if ( empty( $handled_ipn_requests ) ) {
				$handled_ipn_requests = array();
			}

			// The 'ipn_track_id' is not a unique ID and is shared between different transaction types, so create a unique ID by prepending the transaction type
			$ipn_id = $transaction_details['txn_type'] . '_' . $transaction_details['ipn_track_id'];

			if ( in_array( $ipn_id, $handled_ipn_requests ) ) {
				WC_Gateway_Paypal::log( 'Subscription IPN Error: IPN ' . $ipn_id . ' message has already been correctly handled.' );
				exit;
			}
		}

		if ( isset( $transaction_details['txn_id'] ) ) {

			// Make sure the IPN request has not already been handled
			$handled_transactions = get_post_meta( $subscription->id, '_paypal_transaction_ids', true );

			if ( empty( $handled_transactions ) ) {
				$handled_transactions = array();
			}

			$transaction_id = $transaction_details['txn_id'];

			if ( isset( $transaction_details['txn_type'] ) ) {
				$transaction_id .= '_' . $transaction_details['txn_type'];
			}

			// The same transaction ID is used for different payment statuses, so make sure we handle it only once. See: http://stackoverflow.com/questions/9240235/paypal-ipn-unique-identifier
			if ( isset( $transaction_details['payment_status'] ) ) {
				$transaction_id .= '_' . $transaction_details['payment_status'];
			}

			if ( in_array( $transaction_id, $handled_transactions ) ) {
				WC_Gateway_Paypal::log( 'Subscription IPN Error: transaction ' . $transaction_id . ' has already been correctly handled.' );
				exit;
			}
		}

		// Save the profile ID if it's not a cancellation/expiration request
		if ( isset( $transaction_details['subscr_id'] ) && ! in_array( $transaction_details['txn_type'], array( 'subscr_cancel', 'subscr_eot' ) ) ) {
			update_post_meta( $subscription->id, 'PayPal Subscriber ID', $transaction_details['subscr_id'] );

			if ( 'S-' == substr( $transaction_details['subscr_id'], 0, 2 ) && 'disabled' != get_option( 'wcs_paypal_invalid_profile_id' ) ) {
				update_option( 'wcs_paypal_invalid_profile_id', 'yes' );
			}
		}

		$is_first_payment = ( $subscription->get_completed_payment_count() < 1 ) ? true : false;

		if ( $subscription->has_status( 'switched' ) ) {
			WC_Gateway_Paypal::log( 'IPN ignored, subscription has been switched.' );
			exit;
		}

		switch ( $transaction_details['txn_type'] ) {
			case 'subscr_signup':

				// Store PayPal Details on Subscription and Order
				$paypal_details_to_store = array(
					'payer_email' => 'Payer PayPal address',
					'first_name'  => 'Payer PayPal first name',
					'last_name'   => 'Payer PayPal last name',
				);

				foreach ( $paypal_details_to_store as $ipn_key => $post_meta_key ) {
					update_post_meta( $subscription->id, $post_meta_key, $transaction_details[ $ipn_key ] );
					update_post_meta( $subscription->order->id, $post_meta_key, $transaction_details[ $ipn_key ] );
				}

				// When there is a free trial & no initial payment amount, we need to mark the order as paid and activate the subscription
				if ( ! $is_payment_change && ! $is_renewal_sign_up_after_failure && 0 == $subscription->order->get_total() ) {
					// Safe to assume the subscription has an order here because otherwise we wouldn't get a 'subscr_signup' IPN
					$subscription->order->payment_complete(); // No 'txn_id' value for 'subscr_signup' IPN messages
					update_post_meta( $subscription->id, '_paypal_first_ipn_ignored_for_pdt', 'true' );
				}

				// Payment completed
				if ( $is_payment_change ) {

					// Set PayPal as the new payment method
					WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $subscription, 'paypal' );

					// We need to cancel the subscription now that the method has been changed successfully
					if ( 'paypal' == get_post_meta( $subscription->id, '_old_payment_method', true ) ) {
						self::cancel_subscription( $subscription, get_post_meta( $subscription->id, '_old_paypal_subscriber_id', true ) );
					}

					$subscription->add_order_note( __( 'IPN subscription payment method changed to PayPal.', 'woocommerce-subscriptions' ) );

				} else {

					$subscription->add_order_note( __( 'IPN subscription sign up completed.', 'woocommerce-subscriptions' ) );

				}

				if ( $is_payment_change ) {
					WC_Gateway_Paypal::log( 'IPN subscription payment method changed for subscription ' . $subscription->id );
				} else {
					WC_Gateway_Paypal::log( 'IPN subscription sign up completed for subscription ' . $subscription->id );
				}

				break;

			case 'subscr_payment':

				if ( ! $is_first_payment && ! $is_renewal_sign_up_after_failure ) {
					// Generate a renewal order to record the payment (and determine how much is due)
					$renewal_order = wcs_create_renewal_order( $subscription );

					// Set PayPal as the payment method (we can't use $renewal_order->set_payment_method() here as it requires an object we don't have)
					$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
					$renewal_order->set_payment_method( $available_gateways['paypal'] );
				}

				if ( 'completed' == strtolower( $transaction_details['payment_status'] ) ) {
					// Store PayPal Details
					update_post_meta( $subscription->id, 'PayPal Transaction ID', $transaction_details['txn_id'] );
					update_post_meta( $subscription->id, 'Payer PayPal first name', $transaction_details['first_name'] );
					update_post_meta( $subscription->id, 'Payer PayPal last name', $transaction_details['last_name'] );
					update_post_meta( $subscription->id, 'PayPal Payment type', $transaction_details['payment_type'] );

					// Subscription Payment completed
					$subscription->add_order_note( __( 'IPN subscription payment completed.', 'woocommerce-subscriptions' ) );

					WC_Gateway_Paypal::log( 'IPN subscription payment completed for subscription ' . $subscription->id );

					// First payment on order, process payment & activate subscription
					if ( $is_first_payment ) {

						$subscription->order->payment_complete( $transaction_details['txn_id'] );

						// Store PayPal Details on Order
						update_post_meta( $subscription->order->id, 'PayPal Payment type', $transaction_details['payment_type'] );

						// IPN got here first or PDT will never arrive. Normally PDT would have arrived, so the first IPN would not be the first payment. In case the the first payment is an IPN, we need to make sure to not ignore the second one
						update_post_meta( $subscription->id, '_paypal_first_ipn_ignored_for_pdt', 'true' );

					// Ignore the first IPN message if the PDT should have handled it (if it didn't handle it, it will have been dealt with as first payment), but set a flag to make sure we only ignore it once
					} elseif ( $subscription->get_completed_payment_count() == 1 && ! empty( self::$paypal_settings['identity_token'] ) && 'true' != get_post_meta( $subscription->id, '_paypal_first_ipn_ignored_for_pdt', true ) && false === $is_renewal_sign_up_after_failure ) {

						WC_Gateway_Paypal::log( 'IPN subscription payment ignored for subscription ' . $subscription->id . ' due to PDT previously handling the payment.' );

						update_post_meta( $subscription->id, '_paypal_first_ipn_ignored_for_pdt', 'true' );

					// Process the payment if the subscription is active
					} elseif ( ! $subscription->has_status( array( 'cancelled', 'expired', 'switched', 'trash' ) ) ) {

						// We don't need to reactivate the subscription because Subs didn't suspend it
						remove_action( 'woocommerce_subscription_activated_paypal', __CLASS__ . '::reactivate_subscription' );

						if ( true === $is_renewal_sign_up_after_failure && is_object( $renewal_order ) ) {

							update_post_meta( $subscription->id, '_paypal_failed_sign_up_recorded', $renewal_order->id );

							// We need to cancel the old subscription now that the method has been changed successfully
							if ( 'paypal' == get_post_meta( $subscription->id, '_old_payment_method', true ) ) {

								$profile_id = get_post_meta( $subscription->id, '_old_paypal_subscriber_id', true );

								// Make sure we don't cancel the current profile
								if ( $profile_id !== $transaction_details['subscr_id'] ) {
									self::cancel_subscription( $subscription, $profile_id );
								}

								$subscription->add_order_note( __( 'IPN subscription failing payment method changed.', 'woocommerce-subscriptions' ) );
							}
						}

						$renewal_order->payment_complete( $transaction_details['txn_id'] );

						$renewal_order->add_order_note( __( 'IPN subscription payment completed.', 'woocommerce-subscriptions' ) );

						update_post_meta( $renewal_order->id, 'PayPal Subscriber ID', $transaction_details['subscr_id'] );

						add_action( 'woocommerce_subscription_activated_paypal', __CLASS__ . '::reactivate_subscription' );

					}
				} elseif ( in_array( strtolower( $transaction_details['payment_status'] ), array( 'pending', 'failed' ) ) ) {

					// Subscription Payment completed
					$subscription->add_order_note( sprintf( __( 'IPN subscription payment %s.', 'woocommerce-subscriptions' ), $transaction_details['payment_status'] ) );

					if ( ! $is_first_payment ) {

						update_post_meta( $renewal_order->id, '_transaction_id', $transaction_details['txn_id'] );

						$renewal_order->add_order_note( sprintf( __( 'IPN subscription payment %s.', 'woocommerce-subscriptions' ), $transaction_details['payment_status'] ) );

						$subscription->payment_failed();
					}

					WC_Gateway_Paypal::log( 'IPN subscription payment failed for subscription ' . $subscription->id );

				} else {

					WC_Gateway_Paypal::log( 'IPN subscription payment notification received for subscription ' . $subscription->id  . ' with status ' . $transaction_details['payment_status'] );

				}

				break;

			// Admins can suspend subscription at PayPal triggering this IPN
			case 'recurring_payment_suspended':

				if ( ! $subscription->has_status( 'on-hold' ) ) {

					// We don't need to suspend the subscription at PayPal because it's already on-hold there
					remove_action( 'woocommerce_subscription_on-hold_paypal', __CLASS__ . '::suspend_subscription' );

					$subscription->update_status( 'on-hold', __( 'IPN subscription suspended.', 'woocommerce-subscriptions' ) );

					add_action( 'woocommerce_subscription_activated_paypal', __CLASS__ . '::reactivate_subscription' );

					WC_Gateway_Paypal::log( 'IPN subscription suspended for subscription ' . $subscription->id );

				} else {

					WC_Gateway_Paypal::log( sprintf( 'IPN "recurring_payment_suspended" ignored for subscription %d. Subscription already on-hold.', $subscription->id ) );

				}

				break;

			case 'subscr_cancel':

				// Make sure the subscription hasn't been linked to a new payment method
				if ( self::get_subscriptions_paypal_id( $subscription ) != $transaction_details['subscr_id'] ) {

					WC_Gateway_Paypal::log( 'IPN subscription cancellation request ignored - new PayPal Profile ID linked to this subscription, for subscription ' . $subscription->id );

				} else {

					$subscription->cancel_order( __( 'IPN subscription cancelled.', 'woocommerce-subscriptions' ) );

					WC_Gateway_Paypal::log( 'IPN subscription cancelled for subscription ' . $subscription->id );

				}

				break;

			case 'subscr_eot': // Subscription ended, either due to failed payments or expiration

				WC_Gateway_Paypal::log( 'IPN EOT request ignored for subscription ' . $subscription->id );
				break;

			case 'subscr_failed': // Subscription sign up failed
			case 'recurring_payment_suspended_due_to_max_failed_payment': // Subscription sign up failed

				WC_Gateway_Paypal::log( 'IPN subscription payment failure for subscription ' . $subscription->id );

				// Subscription Payment completed
				$subscription->add_order_note( __( 'IPN subscription payment failure.', 'woocommerce-subscriptions' ) );

				$subscription->payment_failed();

				break;
		}

		// Store the transaction IDs to avoid handling requests duplicated by PayPal
		if ( isset( $transaction_details['ipn_track_id'] ) ) {
			$handled_ipn_requests[] = $ipn_id;
			update_post_meta( $subscription->id, '_paypal_ipn_tracking_ids', $handled_ipn_requests );
		}

		if ( isset( $transaction_details['txn_id'] ) ) {
			$handled_transactions[] = $transaction_id;
			update_post_meta( $subscription->id, '_paypal_transaction_ids', $handled_transactions );
		}

		// Prevent default IPN handling for subscription txn_types
		exit;
	}

	/**
	 * Override the default PayPal standard args in WooCommerce for subscription purchases when
	 * automatic payments are enabled and when the recurring order totals is over $0.00 (because
	 * PayPal doesn't support subscriptions with a $0 recurring total, we need to circumvent it and
	 * manage it entirely ourselves.)
	 *
	 * Based on the HTML Variables documented here: https://developer.paypal.com/webapps/developer/docs/classic/paypal-payments-standard/integration-guide/Appx_websitestandard_htmlvariables/#id08A6HI00JQU
	 *
	 * @since 1.0
	 */
	public static function paypal_standard_subscription_args( $paypal_args ) {

		$is_payment_change = WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment;
		$order_contains_failed_renewal = false;

		extract( self::get_order_id_and_key( $paypal_args, 'shop_order' ) );

		// Payment method changes act on the subscription not the original order
		if ( $is_payment_change ) {

			$subscriptions = array( wcs_get_subscription( $order_id ) );
			$subscription  = array_pop( $subscriptions );
			$order         = $subscription->order;

			// We need the subscription's total
			remove_filter( 'woocommerce_order_amount_total', 'WC_Subscriptions_Change_Payment_Gateway::maybe_zero_total', 11, 2 );

		} else {

			// Otherwise the order is the order
			$order = wc_get_order( $order_id );

			if ( $cart_item = wcs_cart_contains_failed_renewal_order_payment() || false !== WC_Subscriptions_Renewal_Order::get_failed_order_replaced_by( $order_id ) ) {
				$subscriptions                 = wcs_get_subscriptions_for_renewal_order( $order );
				$order_contains_failed_renewal = true;
			} else {
				$subscriptions                 = wcs_get_subscriptions_for_order( $order );
			}

			// Only one subscription allowed per order with PayPal
			$subscription = array_pop( $subscriptions );
		}

		if ( $order_contains_failed_renewal || ( ! empty( $subscription ) && $subscription->get_total() > 0 && 'yes' !== get_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments', 'no' ) ) ) {

			// It's a subscription
			$paypal_args['cmd'] = '_xclick-subscriptions';

			// Store the subscription ID in the args sent to PayPal so we can access them later
			$paypal_args['custom'] = json_encode( array( 'order_id' => $order_id, 'order_key' => $order_key, 'subscription_id' => $subscription->id, 'subscription_key' => $subscription->order_key ) );

			foreach ( $subscription->get_items() as $item ) {
				if ( $item['qty'] > 1 ) {
					$item_names[] = $item['qty'] . ' x ' . self::paypal_item_name( $item['name'] );
				} elseif ( $item['qty'] > 0 ) {
					$item_names[] = self::paypal_item_name( $item['name'] );
				}
			}

			$paypal_args['item_name'] = self::paypal_item_name( sprintf( __( 'Subscription %s (Order %s) - %s', 'woocommerce-subscriptions' ), $subscription->get_order_number(), $order->get_order_number(), implode( ', ', $item_names ) ) );

			$unconverted_periods = array(
				'billing_period' => $subscription->billing_period,
				'trial_period'   => $subscription->trial_period,
			);

			$converted_periods = array();

			// Convert period strings into PayPay's format
			foreach ( $unconverted_periods as $key => $period ) {
				switch ( strtolower( $period ) ) {
					case 'day':
						$converted_periods[ $key ] = 'D';
						break;
					case 'week':
						$converted_periods[ $key ] = 'W';
						break;
					case 'year':
						$converted_periods[ $key ] = 'Y';
						break;
					case 'month':
					default:
						$converted_periods[ $key ] = 'M';
						break;
				}
			}

			$price_per_period      = $subscription->get_total();
			$subscription_interval = $subscription->billing_interval;
			$start_timestamp       = $subscription->get_time( 'start' );
			$trial_end_timestamp   = $subscription->get_time( 'trial_end' );
			$end_timestamp         = $subscription->get_time( 'end' );

			if ( $trial_end_timestamp > 0 ) {
				$subscription_length = wcs_estimate_periods_between( $trial_end_timestamp, $subscription->get_time( 'end' ), $subscription->billing_period );
			} else {
				$subscription_length = wcs_estimate_periods_between( $start_timestamp, $subscription->get_time( 'end' ), $subscription->billing_period );
			}

			$subscription_installments = $subscription_length / $subscription_interval;

			$is_synced_subscription = WC_Subscriptions_Synchroniser::subscription_contains_synced_product( $subscription->id );

			$initial_payment = ( $is_payment_change ) ? 0 : $order->get_total();

			if ( $order_contains_failed_renewal || $is_payment_change ) {

				if ( $is_payment_change ) {
					// Add a nonce to the order ID to avoid "This invoice has already been paid" error when changing payment method to PayPal when it was previously PayPal
					$suffix = '-wcscpm-' . wp_create_nonce();
				} else {
					// Failed renewal order, append a descriptor and renewal order's ID
					$suffix = '-wcsfrp-' . $order->id;
				}

				// Change the 'invoice' and the 'custom' values to be for the original order (if there is one)
				if ( false === $subscription->order ) {
					// No original order so we need to use the subscriptions values instead
					$order_number = ltrim( $subscription->get_order_number(), '#' ). '-subscription';
					$order_id_key = array( 'order_id' => $subscription->id, 'order_key' => $subscription->order_key );
				} else {
					$order_number = ltrim( $subscription->order->get_order_number(), '#' );
					$order_id_key = array( 'order_id' => $subscription->order->id, 'order_key' => $subscription->order->order_key );
				}

				$order_details = ( false !== $subscription->order ) ? $subscription->order : $subscription;

				// Set the invoice details to the original order's invoice but also append a special string and this renewal orders ID so that we can match it up as a failed renewal order payment later
				$paypal_args['invoice'] = self::$invoice_prefix . $order_number . $suffix;
				$paypal_args['custom']  = json_encode( array_merge( $order_id_key, array( 'subscription_id' => $subscription->id, 'subscription_key' => $subscription->order_key ) ) );

			}

			if ( $order_contains_failed_renewal ) {

				$subscription_trial_length = 0;
				$subscription_installments = max( $subscription_installments - $subscription->get_completed_payment_count(), 0 );

			// If we're changing the payment date or switching subs, we need to set the trial period to the next payment date & installments to be the number of installments left
			} elseif ( $is_payment_change || $is_synced_subscription ) {

				$next_payment_timestamp = $subscription->get_time( 'next_payment' );

				// When the subscription is on hold
				if ( false != $next_payment_timestamp && ! empty( $next_payment_timestamp ) ) {

					$trial_until = self::calculate_trial_periods_until( $next_payment_timestamp );

					$subscription_trial_length = $trial_until['first_trial_length'];
					$converted_periods['trial_period'] = $trial_until['first_trial_period'];

					$second_trial_length = $trial_until['second_trial_length'];
					$second_trial_period = $trial_until['second_trial_period'];

				} else {

					$subscription_trial_length = 0;

				}

				// If this is a payment change, we need to account for completed payments on the number of installments owing
				if ( $is_payment_change && $subscription_length > 0 ) {
					$subscription_installments = max( $subscription_installments - $subscription->get_completed_payment_count(), 0 );
				}
			} else {

				$subscription_trial_length = wcs_estimate_periods_between( $start_timestamp, $trial_end_timestamp, $subscription->trial_period );

			}

			if ( $subscription_trial_length > 0 ) { // Specify a free trial period

				$paypal_args['a1'] = ( $initial_payment > 0 ) ? $initial_payment : 0;

				// Trial period length
				$paypal_args['p1'] = $subscription_trial_length;

				// Trial period
				$paypal_args['t1'] = $converted_periods['trial_period'];

				// We need to use a second trial period before we have more than 90 days until the next payment
				if ( isset( $second_trial_length ) && $second_trial_length > 0 ) {
					$paypal_args['a2'] = 0.01; // Alas, although it's undocumented, PayPal appears to require a non-zero value in order to allow a second trial period
					$paypal_args['p2'] = $second_trial_length;
					$paypal_args['t2'] = $second_trial_period;
				}
			} elseif ( $initial_payment != $price_per_period ) { // No trial period, but initial amount includes a sign-up fee and/or other items, so charge it as a separate period

				if ( 1 == $subscription_installments ) {
					$param_number = 3;
				} else {
					$param_number = 1;
				}

				$paypal_args[ 'a' . $param_number ] = $initial_payment;

				// Sign Up interval
				$paypal_args[ 'p' . $param_number ] = $subscription_interval;

				// Sign Up unit of duration
				$paypal_args[ 't' . $param_number ] = $converted_periods['billing_period'];

			}

			// We have a recurring payment
			if ( ! isset( $param_number ) || 1 == $param_number ) {

				// Subscription price
				$paypal_args['a3'] = $price_per_period;

				// Subscription duration
				$paypal_args['p3'] = $subscription_interval;

				// Subscription period
				$paypal_args['t3'] = $converted_periods['billing_period'];

			}

			// Recurring payments
			if ( 1 == $subscription_installments || ( $initial_payment != $price_per_period && 0 == $subscription_trial_length && 2 == $subscription_installments ) ) {

				// Non-recurring payments
				$paypal_args['src'] = 0;

			} else {

				$paypal_args['src'] = 1;

				if ( $subscription_installments > 0 ) {

					// An initial period is being used to charge a sign-up fee
					if ( $initial_payment != $price_per_period && 0 == $subscription_trial_length ) {
						$subscription_installments--;
					}

					$paypal_args['srt'] = $subscription_installments;

				}
			}

			// Don't reattempt failed payments, instead let Subscriptions handle the failed payment
			$paypal_args['sra'] = 0;

			// Force return URL so that order description & instructions display
			$paypal_args['rm'] = 2;

			// Reattach the filter we removed earlier
			if ( $is_payment_change ) {
				add_filter( 'woocommerce_order_amount_total', 'WC_Subscriptions_Change_Payment_Gateway::maybe_zero_total', 11, 2 );
			}
		}

		return $paypal_args;
	}

	/**
	 * Adds extra PayPal credential fields required to manage subscriptions.
	 *
	 * @since 1.0
	 */
	public static function add_subscription_form_fields() {
		foreach ( WC()->payment_gateways->payment_gateways as $key => $gateway ) {

			if ( WC()->payment_gateways->payment_gateways[ $key ]->id !== 'paypal' ) {
				continue;
			}

			// Warn store managers not to change their PayPal Email address as it can break existing Subscriptions in WC2.0+
			WC()->payment_gateways->payment_gateways[ $key ]->form_fields['receiver_email']['desc_tip'] = false;
			WC()->payment_gateways->payment_gateways[ $key ]->form_fields['receiver_email']['description'] .= ' </p><p class="description">' . __( 'It is <strong>strongly recommended you do not change the Receiver Email address</strong> if you have active subscriptions with PayPal. Doing so can break existing subscriptions.', 'woocommerce-subscriptions' );

		}

	}

	/**
	 * When a store manager or user cancels a subscription in the store, also cancel the subscription with PayPal.
	 *
	 * @since 2.0
	 */
	public static function cancel_subscription( $subscription, $profile_id = '' ) {

		if ( empty( $profile_id ) ) {
			$profile_id = self::get_subscriptions_paypal_id( $subscription );
		}

		// Make sure a subscriptions status is active with PayPal
		$response = self::change_subscription_status( $profile_id, 'Cancel', $subscription );

		if ( isset( $response['ACK'] ) && 'Success' == $response['ACK'] ) {
			$subscription->add_order_note( __( 'Subscription cancelled with PayPal', 'woocommerce-subscriptions' ) );
		}
	}

	/**
	 * When a store manager or user suspends a subscription in the store, also suspend the subscription with PayPal.
	 *
	 * @since 2.0
	 */
	public static function suspend_subscription( $subscription ) {

		$profile_id = self::get_subscriptions_paypal_id( $subscription );

		// Make sure a subscriptions status is active with PayPal
		$response = self::change_subscription_status( $profile_id, 'Suspend', $subscription );

		if ( isset( $response['ACK'] ) && 'Success' == $response['ACK'] ) {
			$subscription->add_order_note( __( 'Subscription suspended with PayPal', 'woocommerce-subscriptions' ) );
		}
	}

	/**
	 * When a store manager or user reactivates a subscription in the store, also reactivate the subscription with PayPal.
	 *
	 * How PayPal Handles suspension is discussed here: https://www.x.com/developers/paypal/forums/nvp/reactivate-recurring-profile
	 *
	 * @since 2.0
	 */
	public static function reactivate_subscription( $subscription ) {

		$profile_id = self::get_subscriptions_paypal_id( $subscription );

		// Make sure a subscriptions status is active with PayPal
		$response = self::change_subscription_status( $profile_id, 'Reactivate', $subscription );

		if ( isset( $response['ACK'] ) && 'Success' == $response['ACK'] ) {
			$subscription->add_order_note( __( 'Subscription reactivated with PayPal', 'woocommerce-subscriptions' ) );
		}
	}

	/**
	 * Returns a PayPal Subscription ID/Recurring Payment Profile ID based on a user ID and subscription key
	 *
	 * @param WC_Order|WC_Subscription A WC_Order object or child object (i.e. WC_Subscription)
	 * @since 1.1
	 */
	public static function get_subscriptions_paypal_id( $order, $deprecated = null ) {

		if ( null != $deprecated ) {
			_deprecated_argument( __METHOD__, '2.0', 'Second parameter is deprecated' );
		}

		$profile_id = get_post_meta( $order->id, 'PayPal Subscriber ID', true );

		return $profile_id;
	}

	/**
	 * Performs an Express Checkout NVP API operation as passed in $api_method.
	 *
	 * Although the PayPal Standard API provides no facility for cancelling a subscription, the PayPal
	 * Express Checkout NVP API can be used.
	 *
	 * @since 1.1
	 */
	public static function change_subscription_status( $profile_id, $new_status, $order = null ) {

		switch ( $new_status ) {
			case 'Cancel' :
				$new_status_string = __( 'cancelled', 'woocommerce-subscriptions' );
				break;
			case 'Suspend' :
				$new_status_string = __( 'suspended', 'woocommerce-subscriptions' );
				break;
			case 'Reactivate' :
				$new_status_string = __( 'reactivated', 'woocommerce-subscriptions' );
				break;
		}

		$post_data = array(
			'VERSION'   => '76.0',
			'USER'      => self::$api_username,
			'PWD'       => self::$api_password,
			'SIGNATURE' => self::$api_signature,
			'METHOD'    => 'ManageRecurringPaymentsProfileStatus',
			'PROFILEID' => $profile_id,
			'ACTION'    => $new_status,
			'NOTE'      => html_entity_decode( sprintf( __( 'Subscription %s at %s', 'woocommerce-subscriptions' ), $new_status_string, get_bloginfo( 'name' ) ), ENT_NOQUOTES, 'UTF-8' ),
		);

		$post_data = apply_filters( 'woocommerce_subscriptions_paypal_change_status_data', $post_data, $new_status, $order, $profile_id );

		$response = wp_remote_post( self::$api_endpoint, array(
			'method'      => 'POST',
			'body'        => $post_data,
			'timeout'     => 70,
			'user-agent'  => 'WooCommerce',
			'httpversion' => '1.1',
			)
		);

		if ( is_wp_error( $response ) ) {
			WC_Gateway_Paypal::log( 'Calling PayPal to change_subscription_status for ' . $profile_id . ' has failed: ' . $response->get_error_message() . '(' . $response->get_error_code() . ')' );
		}

		if ( empty( $response['body'] ) ) {
			WC_Gateway_Paypal::log( 'Calling PayPal to change_subscription_status failed: Empty Paypal response.' );
		}

		// An associative array is more usable than a parameter string
		parse_str( $response['body'], $parsed_response );

		if ( ( 0 == sizeof( $parsed_response ) || ! array_key_exists( 'ACK', $parsed_response ) ) ) {
			WC_Gateway_Paypal::log( 'Invalid HTTP Response for change_subscription_status to ' . self::$api_endpoint . ' with $post_data = ' . print_r( $post_data, true ) );
		}

		if ( 'Failure' == $parsed_response['ACK'] ) {

			WC_Gateway_Paypal::log( "Calling PayPal to change_subscription_status for $profile_id has Failed: " . $parsed_response['L_LONGMESSAGE0'] );

			if ( 10002 == (int) $parsed_response['L_ERRORCODE0'] ) {

				// Store the profile IDs affected
				$profile_ids   = get_option( 'wcs_paypal_credentials_error_affected_profiles', '' );

				if ( ! empty( $profile_ids ) ) {
					$profile_ids .= ', ';
				}
				$profile_ids .= $profile_id;
				update_option( 'wcs_paypal_credentials_error_affected_profiles', $profile_ids );

				// And set a flag to display notice
				update_option( 'wcs_paypal_credentials_error', 'yes' );
			}
		}

		return $parsed_response;
	}

	/**
	 * Checks a set of args and derives an Order ID with backward compatibility for WC < 1.7 where 'custom' was the Order ID.
	 *
	 * @since 1.2
	 */
	private static function get_order_id_and_key( $args, $order_type = 'shop_order' ) {

		// First try and get the order ID by the subscr_id
		if ( isset( $args['subscr_id'] ) ) {
			$posts = get_posts( array(
				'numberposts'      => 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'post_type'        => $order_type,
				'post_status'      => 'any',
				'suppress_filters' => true,
				'meta_query'       => array(
					array(
						'key'     => 'PayPal Subscriber ID',
						'compare' => '=',
						'value'   => $args['subscr_id'],
						'type'    => 'CHAR',
					),
					array(
						'key'     => '_subscription_renewal',
						'compare' => 'NOT EXISTS',
					),
				),
			));

			if ( ! empty( $posts ) ) {
				$order_id  = $posts[0]->ID;
				$order_key = get_post_meta( $order_id, '_order_key', true );
			}
		}

		// Couldn't find the order ID by subscr_id, so it's either not set on the order yet or the $args doesn't have a subscr_id, either way, let's get it from the args
		if ( ! isset( $order_id ) ) {

			// WC < 1.6.5
			if ( is_numeric( $args['custom'] ) && 'shop_order' == $order_type ) {

				$order_id  = $args['custom'];
				$order_key = $args['invoice'];

			} else {

				$order_details = json_decode( $args['custom'] );

				if ( is_object( $order_details ) ) { // WC 2.3.11+ converted the custom value to JSON, if we have an object, we've got valid JSON

					if ( 'shop_order' == $order_type ) {
						$order_id  = $order_details->order_id;
						$order_key = $order_details->order_key;
					} else {
						// Subscription
						$order_id  = $order_details->subscription_id;
						$order_key = $order_details->subscription_key;
					}
				} elseif ( preg_match( '/^a:2:{/', $args['custom'] ) && ! preg_match( '/[CO]:\+?[0-9]+:"/', $args['custom'] ) && ( $order_details = maybe_unserialize( $args['custom'] ) ) ) {  // WC 2.0 - WC 2.3.11, only allow serialized data in the expected format, do not allow objects or anything nasty to sneak in

					if ( 'shop_order' == $order_type ) {
						$order_id  = $order_details[0];
						$order_key = $order_details[1];
					} else {
						// Subscription, but we didn't have the subscription data in old, serialized value
						$order_id  = '';
						$order_key = '';
					}
				} else { // WC 1.6.5 - WC 2.0 or invalid data

					$order_id  = str_replace( self::$invoice_prefix, '', $args['invoice'] );
					$order_key = $args['custom'];

				}
			}
		}

		return array( 'order_id' => (int) $order_id, 'order_key' => $order_key );
	}

	/**
	 * Return the default WC PayPal gateway's settings.
	 *
	 * @since 1.2
	 */
	private static function get_wc_paypal_settings() {

		if ( ! isset( self::$paypal_settings ) ) {
			self::$paypal_settings = get_option( 'woocommerce_paypal_settings' );
		}

		return self::$paypal_settings;
	}

	/**
	 * Don't transfer PayPal meta to resubscribe orders.
	 *
	 * @param object $resubscribe_order The order created for resubscribing the subscription
	 * @param object $subscription The subscription to which the resubscribe order relates
	 * @return object
	 */
	public static function remove_resubscribe_order_meta( $resubscribe_order, $subscription ) {

		$post_meta_keys = array(
			'Transaction ID',
			'Payer first name',
			'Payer last name',
			'Payer PayPal address',
			'Payer PayPal first name',
			'Payer PayPal last name',
			'PayPal Subscriber ID',
			'Payment type',
		);

		foreach ( $post_meta_keys as $post_meta_key ) {
			delete_post_meta( $resubscribe_order->id, $post_meta_key );
		}

		return $resubscribe_order;
	}

	/**
	 * Prompt the store manager to enter their PayPal API credentials if they are using
	 * PayPal and have yet not entered their API credentials.
	 *
	 * @return void
	 */
	public static function maybe_show_admin_notice() {

		if ( isset( $_GET['wcs_disable_paypal_invalid_profile_id_notice'] ) ) {
			update_option( 'wcs_paypal_invalid_profile_id', 'disabled' );
		}

		// Check if the API credentials are being saved - we can't do this on the 'woocommerce_update_options_payment_gateways_paypal' hook because it is triggered after 'admin_notices'
		if ( ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'woocommerce-settings' ) && isset( $_POST['woocommerce_paypal_api_username'] ) || isset( $_POST['woocommerce_paypal_api_password'] ) || isset( $_POST['woocommerce_paypal_api_signature'] ) ) {

			$credentials_updated = false;

			if ( isset( $_POST['woocommerce_paypal_api_username'] ) && $_POST['woocommerce_paypal_api_username'] != self::$api_username ) {
				$credentials_updated = true;
			} elseif ( isset( $_POST['woocommerce_paypal_api_password'] ) && $_POST['woocommerce_paypal_api_password'] != self::$api_password ) {
				$credentials_updated = true;
			} elseif ( isset( $_POST['woocommerce_paypal_api_signature'] ) && $_POST['woocommerce_paypal_api_signature'] != self::$api_signature ) {
				$credentials_updated = true;
			}

			if ( $credentials_updated ) {
				delete_option( 'wcs_paypal_credentials_error' );
			}
		}

		if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_paypal_supported_currencies', array( 'AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP', 'RMB' ) ) ) ) {
			$valid_for_use = false;
		} else {
			$valid_for_use = true;
		}

		$payment_gateway_tab_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_paypal' );

		if ( ! self::are_credentials_set() && $valid_for_use && ( isset( self::$paypal_settings['enabled'] ) && 'yes' == self::$paypal_settings['enabled'] ) && ! has_action( 'admin_notices', 'WC_Subscriptions_Admin::admin_installed_notice' ) && current_user_can( 'manage_options' ) ) : ?>
<div id="message" class="updated error">
	<p>
		<?php
		printf( esc_html__( 'PayPal is inactive for subscription transactions. Please %sset up the PayPal IPN%s and %senter your API credentials%s to enable PayPal for Subscriptions.', 'woocommerce-subscriptions' ),
			'<a href="http://docs.woothemes.com/document/subscriptions/store-manager-guide/#section-4" target="_blank">',
			'</a>',
			'<a href="' . esc_attr__( $payment_gateway_tab_url ) . '">',
			'</a>'
		); ?>
	</p>
</div>
<?php 	endif;

		if ( false !== get_option( 'wcs_paypal_credentials_error' ) ) : ?>
<div id="message" class="updated error">
	<p>
		<?php
		printf( esc_html__( 'There is a problem with PayPal. Your API credentials may be incorrect. Please update your %sAPI credentials%s. %sLearn more%s.', 'woocommerce-subscriptions' ),
			'<a href="' . esc_attr__( $payment_gateway_tab_url ) . '">',
			'</a>',
			'<a href="https://support.woothemes.com/hc/en-us/articles/202882473#paypal-credentials" target="_blank">',
			'</a>'
		);
		?>
	</p>
</div>
<?php 	endif;

		if ( 'yes' == get_option( 'wcs_paypal_invalid_profile_id' ) ) : ?>
<div id="message" class="updated error">
	<p>
		<?php
		printf( esc_html__( 'There is a problem with PayPal. Your PayPal account is issuing out-of-date subscription IDs. %sLearn more%s. %sDismiss%s.', 'woocommerce-subscriptions' ),
			'<a href="https://support.woothemes.com/hc/en-us/articles/202882473#old-paypal-account" target="_blank">',
			'</a>',
			'<a href="' . esc_url( add_query_arg( 'wcs_disable_paypal_invalid_profile_id_notice', 'true' ) ) . '">',
			'</a>'
		);
		?>
	</p>
</div>
<?php 	endif;
	}

	/**
	 * Takes a timestamp for a date in the future and calculates the number of days between now and then
	 *
	 * @since 1.4
	 */
	public static function calculate_trial_periods_until( $future_timestamp ) {

		$seconds_until_next_payment = $future_timestamp - gmdate( 'U' );
		$days_until_next_payment    = ceil( $seconds_until_next_payment / ( 60 * 60 * 24 ) );

		if ( $days_until_next_payment <= 90 ) { // Can't be more than 90 days free trial

			$first_trial_length = $days_until_next_payment;
			$first_trial_period = 'D';

			$second_trial_length = 0;
			$second_trial_period = 'D';

		} else { // We need to use a second trial period

			if ( $days_until_next_payment > 365 * 2 ) { // We need to use years because PayPal has a maximum of 24 months

				$first_trial_length = floor( $days_until_next_payment / 365 );
				$first_trial_period = 'Y';

				$days_remaining = $days_until_next_payment % 365;

				if ( $days_remaining <= 90 ) { // We can use days
					$second_trial_length = $days_remaining;
					$second_trial_period = 'D';
				} else { // We need to use weeks
					$second_trial_length = floor( $days_remaining / 7 );
					$second_trial_period = 'W';
				}
			} elseif ( $days_until_next_payment > 365 ) { // Less than two years but more than one, use months

				$first_trial_length = floor( $days_until_next_payment / 30 );
				$first_trial_period = 'M';

				$days_remaining = $days_until_next_payment % 30;

				if ( $days_remaining <= 90 ) { // We can use days
					$second_trial_length = $days_remaining;
					$second_trial_period = 'D';
				} else { // We need to use weeks
					$second_trial_length = floor( $days_remaining / 7 );
					$second_trial_period = 'W';
				}
			} else {  // We need to use weeks

				$first_trial_length = floor( $days_until_next_payment / 7 );
				$first_trial_period = 'W';

				$second_trial_length = $days_until_next_payment % 7;
				$second_trial_period = 'D';

			}
		}

		return array(
			'first_trial_length'  => $first_trial_length,
			'first_trial_period'  => $first_trial_period,
			'second_trial_length' => $second_trial_length,
			'second_trial_period' => $second_trial_period,
		);
	}

	/**
	 * PayPal make no guarantee about when a recurring payment will be charged. This creates issues for
	 * suspending a subscription until the payment is processed. Specifically, if PayPal processed a payment
	 * *before* it was due, we can't suspend the subscription when it is due because it will remain suspended
	 * until the next payment. As a result, subscriptions for PayPal are not suspended. However, if there was
	 * an issue with the subscription sign-up or payment that was not correctly reported to the store, then the
	 * subscription would remain active. No renewal order would be generated, because no payments are completed,
	 * so physical subscriptions would not be affected, however, subscriptions to digital goods would be affected.
	 *
	 * @since 2.0
	 */
	public static function schedule_payment_check( $subscription_id ) {
		if ( 'paypal' == get_post_meta( $subscription_id, '_payment_method' ) ) {
			wc_schedule_single_action( 2 * DAY_IN_SECONDS + gmdate( 'U' ), 'paypal_check_subscription_payment', array( 'subscription_id' => $subscription_id ) );
		}
	}

	/**
	 * If changing a subscriptions payment method from and to PayPal, wait until an appropriate IPN message
	 * has come in before deciding to cancel the old subscription.
	 *
	 * @since 2.0
	 */
	public static function maybe_remove_subscription_cancelled_callback( $subscription, $new_payment_method, $old_payment_method ) {
		if ( 'paypal' == $new_payment_method && 'paypal' == $old_payment_method ) {
			remove_action( 'woocommerce_subscription_cancelled_paypal', __CLASS__ . '::cancel_subscription' );
		}
	}

	/**
	 * If changing a subscriptions payment method from and to PayPal, the cancelled subscription hook was removed in
	 * @see self::maybe_remove_cancelled_subscription_hook() so we want to add it again for other subscriptions.
	 *
	 * @since 2.0
	 */
	public static function maybe_reattach_subscription_cancelled_callback( $subscription, $new_payment_method, $old_payment_method ) {
		if ( 'paypal' == $new_payment_method && 'paypal' == $old_payment_method ) {
			add_action( 'woocommerce_subscription_cancelled_paypal', __CLASS__ . '::cancel_subscription' );
		}
	}

	/**
	 * Limit the length of item names
	 *
	 * @param  string $item_name
	 * @return string
	 * @since 1.5.14
	 */
	protected static function paypal_item_name( $item_name ) {

		if ( strlen( $item_name ) > 127 ) {
			$item_name = substr( $item_name, 0, 124 ) . '...';
		}
		return html_entity_decode( $item_name, ENT_NOQUOTES, 'UTF-8' );
	}

	/**
	 * Don't update the payment method on checkout when switching to PayPal - wait until we have the IPN message.
	 *
	 * @param  string $item_name
	 * @return string
	 * @since 1.5.14
	 */
	public static function maybe_dont_update_payment_method( $update, $new_payment_method ) {

		if ( 'paypal' == $new_payment_method ) {
			$update = false;
		}

		return $update;
	}

	/**
	 * In typical PayPal style, there are a couple of important limitations we need to work around:
	 *
	 * @since 1.4.3
	 */
	public static function scheduled_subscription_payment() {
		_deprecated_function( __METHOD__, '2.0' );
	}

	/**
	 * When a store manager or user cancels a subscription in the store, also cancel the subscription with PayPal.
	 *
	 * @since 1.1
	 */
	public static function cancel_subscription_with_paypal( $order, $product_id = '', $profile_id = '' ) {
		_deprecated_function( __METHOD__, '2.0', __CLASS__ . '::cancel_subscription( $subscription )' );
		foreach ( wcs_get_subscriptions_for_order( $order ) as $subscription ) {
			self::cancel_subscription( $subscription, $profile_id );
		}
	}

	/**
	 * When a store manager or user suspends a subscription in the store, also suspend the subscription with PayPal.
	 *
	 * @since 1.1
	 */
	public static function suspend_subscription_with_paypal( $order, $product_id ) {
		_deprecated_function( __METHOD__, '2.0', __CLASS__ . '::suspend_subscription( $subscription )' );
		foreach ( wcs_get_subscriptions_for_order( $order ) as $subscription ) {
			self::suspend_subscription( $subscription );
		}
	}

	/**
	 * When a store manager or user reactivates a subscription in the store, also reactivate the subscription with PayPal.
	 *
	 * How PayPal Handles suspension is discussed here: https://www.x.com/developers/paypal/forums/nvp/reactivate-recurring-profile
	 *
	 * @since 1.1
	 */
	public static function reactivate_subscription_with_paypal( $order, $product_id ) {
		_deprecated_function( __METHOD__, '2.0', __CLASS__ . '::reactivate_subscription( $subscription )' );
		foreach ( wcs_get_subscriptions_for_order( $order ) as $subscription ) {
			self::reactivate_subscription( $subscription );
		}
	}

	/**
	 * Don't transfer PayPal customer/token meta when creating a parent renewal order.
	 *
	 * @access public
	 * @param array $order_meta_query MySQL query for pulling the metadata
	 * @param int $original_order_id Post ID of the order being used to purchased the subscription being renewed
	 * @param int $renewal_order_id Post ID of the order created for renewing the subscription
	 * @param string $new_order_role The role the renewal order is taking, one of 'parent' or 'child'
	 * @return void
	 */
	public static function remove_renewal_order_meta( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {
		_deprecated_function( __METHOD__, '2.0' );

		if ( 'parent' == $new_order_role ) {
			$order_meta_query .= ' AND `meta_key` NOT IN ('
							  .		"'Transaction ID', "
							  .		"'Payer first name', "
							  .		"'Payer last name', "
							  .		"'Payment type', "
							  .		"'Payer PayPal address', "
							  .		"'Payer PayPal first name', "
							  .		"'Payer PayPal last name', "
							  .		"'PayPal Subscriber ID' )";
		}

		return $order_meta_query;
	}
	/**
	 * If changing a subscriptions payment method from and to PayPal, wait until an appropriate IPN message
	 * has come in before deciding to cancel the old subscription.
	 *
	 * @since 1.4.6
	 */
	public static function maybe_remove_cancelled_subscription_hook( $order, $subscription_key, $new_payment_method, $old_payment_method ) {
		_deprecated_function( __METHOD__, '2.0' );
	}

	/**
	 * If changing a subscriptions payment method from and to PayPal, the cancelled subscription hook was removed in
	 * @see self::maybe_remove_cancelled_subscription_hook() so we want to add it again for other subscriptions.
	 *
	 * @since 1.4.6
	 */
	public static function maybe_readd_cancelled_subscription_hook( $order, $subscription_key, $new_payment_method, $old_payment_method ) {
		_deprecated_function( __METHOD__, '2.0' );
	}
}
