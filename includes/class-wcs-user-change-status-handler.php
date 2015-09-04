<?php
/**
 * WooCommerce Subscriptions User Change Status Handler Class
 *
 * @author      Prospress
 * @since       2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_User_Change_Status_Handler {

	public static function init() {
		// Check if a user is requesting to cancel their subscription
		add_action( 'wp_loaded', __CLASS__ . '::maybe_change_users_subscription', 100 );
	}

	/**
	 * Checks if the current request is by a user to change the status of their subscription, and if it is,
	 * validate the request and proceed to change to the subscription.
	 *
	 * @since 2.0
	 */
	public static function maybe_change_users_subscription() {

		if ( isset( $_GET['change_subscription_to'] ) && isset( $_GET['subscription_id'] ) && isset( $_GET['_wpnonce'] )  ) {

			$user_id      = get_current_user_id();
			$subscription = wcs_get_subscription( $_GET['subscription_id'] );
			$new_status   = $_GET['change_subscription_to'];

			if ( self::validate_request( $user_id, $subscription, $new_status, $_GET['_wpnonce'] ) ) {
				self::change_users_subscription( $subscription, $new_status );

				wp_safe_redirect( $subscription->get_view_order_url() );
				exit;
			}
		}
	}

	/**
	 * Change the status of a subscription and show a notice to the user if there was an issue.
	 *
	 * @since 2.0
	 */
	public static function change_users_subscription( $subscription, $new_status ) {
		$subscription = ( ! is_object( $subscription ) ) ? wcs_get_subscription( $subscription ) : $subscription;

		switch ( $new_status ) {
			case 'active' :
				if ( $subscription->needs_payment() ) {
					WC_Subscriptions::add_notice( sprintf( __( 'You can not reactivate that subscription until paying to renew it. Please contact us if you need assistance.', 'woocommerce-subscriptions' ), $new_status ), 'error' );
				} else {
					$subscription->update_status( $new_status );
					$status_message = __( 'reactivated', 'woocommerce-subscriptions' );
				}
				break;
			case 'on-hold' :
				if ( wcs_can_user_put_subscription_on_hold( $subscription ) ) {
					$subscription->update_status( $new_status );
					$status_message = __( 'put on hold', 'woocommerce-subscriptions' );
				} else {
					WC_Subscriptions::add_notice( __( 'You can not suspend that subscription - the suspension limit has been reached. Please contact us if you need assistance.', 'woocommerce-subscriptions' ), 'error' );
				}
				break;
			case 'cancelled' :
				$subscription->cancel_order();
				$status_message = __( 'cancelled', 'woocommerce-subscriptions' );
				break;
		}

		if ( isset( $status_message ) ) {
			// translators: placeholder is status (e.g. "reactivated", "cancelled")
			$subscription->add_order_note( sprintf( __( 'Subscription %s by the subscriber from their account page.', 'woocommerce-subscriptions' ), $status_message ) );
			// translators: placeholder is status (e.g. "reactivated", "cancelled")
			WC_Subscriptions::add_notice( sprintf( __( 'Your subscription has been %s.', 'woocommerce-subscriptions' ), $status_message ), 'success' );

			do_action( 'woocommerce_customer_changed_subscription_to_' . $new_status, $subscription );
		}
	}

	/**
	 * Checks if the user's current request to change the status of their subscription is valid.
	 *
	 * @since 2.0
	 */
	public static function validate_request( $user_id, $subscription, $new_status, $wpnonce = '' ) {
		$subscription = ( ! is_object( $subscription ) ) ? wcs_get_subscription( $subscription ) : $subscription;

		if ( ! wcs_is_subscription( $subscription ) ) {
			WC_Subscriptions::add_notice( __( 'That subscription does not exist. Please contact us if you need assistance.', 'woocommerce-subscriptions' ), 'error' );
			return false;

		} elseif ( ! empty( $wpnonce ) && wp_verify_nonce( $wpnonce, $subscription->id ) === false ) {
			WC_Subscriptions::add_notice( __( 'Security error. Please contact us if you need assistance.', 'woocommerce-subscriptions' ), 'error' );
			return false;

		} elseif ( ! user_can( $user_id, 'edit_shop_subscription_status', $subscription->id ) ) {
			WC_Subscriptions::add_notice( __( 'That doesn\'t appear to be one of your subscriptions.', 'woocommerce-subscriptions' ), 'error' );
			return false;

		} elseif ( ! $subscription->can_be_updated_to( $new_status ) ) {
			WC_Subscriptions::add_notice( sprintf( __( 'That subscription can not be changed to %s. Please contact us if you need assistance.', 'woocommerce-subscriptions' ), $new_status ), 'error' );
			return false;
		}

		return true;
	}
}
WCS_User_Change_Status_Handler::init();
