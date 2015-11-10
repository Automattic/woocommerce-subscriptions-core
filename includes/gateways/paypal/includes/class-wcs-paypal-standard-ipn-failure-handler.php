<?php
/**
 * PayPal Standard IPN Failure Handler
 *
 * Introduces a new handler to take care of failing IPN requests 
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	Gateways/PayPal
 * @category	Class
 * @author		Prospress
 * @since		2.0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_PayPal_Standard_IPN_Failure_Handler {

	private static $transaction_details = null;

	public static $log = null;

	/**
	 * Initialise fialure handler related admin hooks and filters
	 *
	 * @since 2.0.6
	 */
	public static function init() {

	}

	/**
	 * Log any fatal errors occurred while Subscriptions is trying to process IPN messages
	 *
	 * @since 2.0.6
	 * @param array $transaction_details the current IPN message being processed when the fatal error occurred
	 * @param array $error
	 */
	public static function log_ipn_errors( $transaction_details, $error = '' ) {
		// we want to make sure the ipn error admin notice is always displayed when a new error occurs
		delete_option( 'wcs_fatal_error_handling_ipn_ignored' );

		if ( empty( self::$log ) ) {
			self::$log = new WC_Logger();
		}

		self::$log->add( 'wcs-paypal', sprintf( 'Subscription transaction details: %s', print_r( $transaction_details, true ) ) );
		$message = get_option( 'wcs_fatal_error_handling_ipn', '' );

		if ( ! empty( $message ) ) {
			self::$log->add( 'wcs-paypal', sprintf( 'Subscription exception caught: %s', $message ) );
			WC_Gateway_PayPal::log( $message );
		}

		if ( ! empty( $error ) ) {
			self::$log->add( 'wcs-paypal', sprintf( __( 'Unexcepted shutdown when processing subscription IPN messages. PHP Fatal error %s in %s on line %s.', 'woocommerce-subscriptions' ), $error['message'], $error['file'], $error['line'] ) );
		}
	}
}

WCS_PayPal_Standard_IPN_Failure_Handler::init();
