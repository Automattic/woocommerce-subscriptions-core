<?php
/**
 * Tracker for Subscriptions usage.
 *
 * @class     WC_Subscriptions_Tracker
 * @version   3.0.0
 * @package   WooCommerce Subscriptions/Classes
 * @category  Class
 * @author    Automattic
 */

defined( 'ABSPATH' ) || exit;

class WC_Subscriptions_Tracker {

	public static function init() {
		add_filter( 'woocommerce_tracker_data', array( __CLASS__, 'add_subscriptions_tracking_data' ), 10, 1 );
	}

	public static function add_subscriptions_tracking_data( $data ) {
		$data['extensions']['wc_subscriptions']['settings'] = self::get_subscriptions_options();
		$data['extensions']['wc_subscriptions']['subscriptions'] = self::get_subscriptions();
		return $data;
	}

	private static function get_subscriptions_options() {
		$subs_data = array(
			// Staging and live site
			'wc_subscriptions_staging'    => WC_Subscriptions::is_duplicate_site() ? 'staging' : 'live',
			'wc_subscriptions_live_url'   => esc_url( WC_Subscriptions::get_site_url_from_source( 'subscriptions_install' ) ),

			// Button text, roles, and renewals
			'add_to_cart_button_text'     => get_option( WC_Subscriptions_Admin::$option_prefix . '_add_to_cart_button_text' ),
			'order_button_text'           => get_option( WC_Subscriptions_Admin::$option_prefix . '_order_button_text' ),
			'subscriber_role'             => get_option( WC_Subscriptions_Admin::$option_prefix . '_subscriber_role' ),
			'cancelled_role'              => get_option( WC_Subscriptions_Admin::$option_prefix . '_cancelled_role' ),
			'accept_manual_renewals'      => get_option( WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals' ),
			'enable_auto_renewal_toggle'  => get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_auto_renewal_toggle' ),
			'enable_early_renewal'        => get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_early_renewal' ),

			// Switching
			'allow_switching'             => get_option( WC_Subscriptions_Admin::$option_prefix . '_allow_switching' ),

			// Synchronization
			'sync_payments'               => get_option( WC_Subscriptions_Admin::$option_prefix . '_sync_payments' ),

			// Miscellaneous
			'max_customer_suspensions'              => get_option( WC_Subscriptions_Admin::$option_prefix . '_max_customer_suspensions' ),
			'multiple_purchase'                     => get_option( WC_Subscriptions_Admin::$option_prefix . '_multiple_purchase' ),
			'zero_initial_payment_requires_payment' => get_option( WC_Subscriptions_Admin::$option_prefix . '_zero_initial_payment_requires_payment' ),
			'drip_downloadable_content_on_renewal'  => get_option( WC_Subscriptions_Admin::$option_prefix . '_drip_downloadable_content_on_renewal' ),
			'enable_retry' => get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_retry' ),
		);

		// Turn off automatic renewals
		if ( 'no' != get_option( WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals' ) ) {
			$subs_data['turn_off_automatic_payments'] = get_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments' );
		}

		// Enable early renewal
		if ( 'no' != get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_early_renewal' ) ) {
			$subs_data['enable_early_renewal_via_modal'] = get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_early_renewal_via_modal' );
		}

		// Switching Options
		if ( 'no' != get_option( WC_Subscriptions_Admin::$option_prefix . '_allow_switching' ) ) {
			$subs_data['apportion_recurring_price'] = get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_recurring_price' );
			$subs_data['apportion_sign_up_fee']     = get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_sign_up_fee' );
			$subs_data['apportion_length']          = get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_length' );
			$subs_data['switch_button_text']        = get_option( WC_Subscriptions_Admin::$option_prefix . '_switch_button_text' );
		}

		// Synchronization options
		if ( 'no' != get_option( WC_Subscriptions_Admin::$option_prefix . '_sync_payments' ) ) {
			$subs_data['prorate_synced_payments'] = get_option( WC_Subscriptions_Admin::$option_prefix . '_prorate_synced_payments' );
			$subs_data['days_no_fee']             = get_option( WC_Subscriptions_Admin::$option_prefix . '_days_no_fee' );
		}

		return $subs_data;
	}

	/**
	 * Combine all subscription data.
	 *
	 * @return array
	 */
	private static function get_subscriptions() {
		return array();
	}
}
