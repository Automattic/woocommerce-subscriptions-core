<?php
/**
 *
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

		add_action( 'wp_ajax_wcs_disable_auto_renew', __CLASS__ . '::disable_auto_renew' );
		add_action( 'wp_ajax_wcs_enable_auto_renew', __CLASS__ . '::enable_auto_renew' );

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

		$subscription->set_requires_manual_renewal( true );
		$subscription->save();
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

		if ( false !== ( $payment_gateway = wc_get_payment_gateway_by_order( $subscription ) ) ) {
			$subscription->set_requires_manual_renewal( false );
			$subscription->save();
		}
	}


}
