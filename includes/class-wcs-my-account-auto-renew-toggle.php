<?php
/**
 * Class for managing Auto Renew Toggle on View Subscription page of My Account
 *
 * @package  WooCommerce Subscriptions
 * @category Class
 * @author   Prospress
 * @since    2.5.0
 */
class WCS_My_Account_Auto_Renew_Toggle {

	/**
	 * Initialize filters and hooks for class.
	 *
	 * @since 2.5.0
	 */
	public static function init() {
		add_action( 'wp_ajax_wcs_disable_auto_renew', array( __CLASS__, 'disable_auto_renew' ) );
		add_action( 'wp_ajax_wcs_enable_auto_renew', array( __CLASS__, 'enable_auto_renew' ) );
	}


	/**
	 * Check all conditions for whether auto-renewal can be changed is possible
	 *
	 * @param WC_Subscription $subscription The subscription for which the checks for auto-renewal needs to be made
	 * @return boolean
	 * @since 2.5.0
	 */
	public static function can_subscription_auto_renewal_be_changed( $subscription ) {
		// Cannot change to auto-renewal for a subscription with status other than active
		if ( ! $subscription->has_status( 'active' ) ) {
			return false;
		}
		// Cannot change to auto-renewal for a subscription with 0 total
		if ( 0 == $subscription->get_total() ) { // Not using strict comparison intentionally
			return false;
		}
		// Cannot change to auto-renewal for a subscription in the final billing period. No next renewal date.
		if ( 0 == $subscription->get_date( 'next_payment' ) ) { // Not using strict comparison intentionally
			return false;
		}
		// If it is not a manual subscription, and the payment gateway is Paypal Standard
		if ( ! $subscription->is_manual() && $subscription->payment_method_supports( 'gateway_scheduled_payments' ) ) {
			return false;
		}
		// If the store accepts manual renewals, but automatic payments are turned off, not possible to change to auto-renewal
		if ( 'yes' === get_option( WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals' ) && 'yes' === get_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments', 'no' ) ) {
			return false;
		}

		// Looks like changing to auto-renewal is indeed possible
		return true;
	}

	/**
	 * Disable auto renewal of subscription
	 *
	 * @since 2.5.0
	 */
	public static function disable_auto_renew() {
		check_ajax_referer( 'toggle-auto-renew', 'security' );
		if ( ! isset( $_POST['subscription_id'] ) ) {
			return -1;
		}
		$subscription = wcs_get_subscription( $_POST['subscription_id'] );

		if ( $subscription ) {
			$subscription->set_requires_manual_renewal( true );
			$subscription->save();
		}
	}

	/**
	 * Enable auto renewal of subscription
	 *
	 * @since 2.5.0
	 */
	public static function enable_auto_renew() {
		check_ajax_referer( 'toggle-auto-renew', 'security' );
		if ( ! isset( $_POST['subscription_id'] ) ) {
			return -1;
		}
		$subscription = wcs_get_subscription( $_POST['subscription_id'] );

		if ( wc_get_payment_gateway_by_order( $subscription ) ) {
			$subscription->set_requires_manual_renewal( false );
			$subscription->save();
		}
	}
}
