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
	 * Attaches all IPN failure handler related hooks and filters and also sets logging to enabled.
	 *
	 * @since 2.0.6
	 * @param array $transaction_details
	 */
	public static function attach( $transaction_details ) {
		self::$transaction_details = $transaction_details;

		WC_Gateway_Paypal::$log_enabled = true;

		add_action( 'wcs_paypal_ipn_process_failure', __CLASS__ . '::log_ipn_errors', 10, 2 );
		add_action( 'shutdown', __CLASS__ . '::catch_unexpected_shutdown' );
	}

	/**
	 * Close up loose ends and maybe log an error to paypal logs
	 *
	 * @since 2.0.6
	 * @param $message
	 */
	public static function detach( $message = '' ) {
		remove_action( 'wcs_paypal_ipn_process_failure', __CLASS__ . '::log_ipn_errors' );
		remove_action( 'shutdown', __CLASS__ . '::catch_unexpected_shutdown' );

		if ( ! empty( $message ) ) {
			WC_Gateway_Paypal::log( $message );
		}

		self::$transaction_details = null;
	}

	/**
	 * On PHP shutdown log any unexpected failures from PayPal IPN processing
	 *
	 * @since 2.0.6
	 */
	public static function catch_unexpected_shutdown() {

		if ( ! empty( self::$transaction_details ) && $error = error_get_last() ) {
			if ( E_ERROR == $error['type'] ) {
				do_action( 'wcs_paypal_ipn_process_failure', self::$transaction_details, $error );
			}
		}

		self::$transaction_details = null;
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

		self::log_to_failure( sprintf( 'Subscription transaction details: %s', print_r( $transaction_details, true ) ) );

		if ( ! empty( $error ) ) {
			update_option( 'wcs_fatal_error_handling_ipn', $error['message'] );
			self::log_to_failure( sprintf( __( 'Unexcepted shutdown when processing subscription IPN messages. PHP Fatal error %s in %s on line %s.', 'woocommerce-subscriptions' ), $error['message'], $error['file'], $error['line'] ) );
		}

		set_transient( 'wcs_paypal_ipn_error_occurred', WCS_PayPal::get_option( 'api_username' ), WEEK_IN_SECONDS );
	}

	/**
	 * Log any unexpected fatal errors to wcs-ipn-failures log file
	 *
	 * @since 2.0.6
	 * @param string $message
	 */
	public static function log_to_failure( $message ) {

		if ( empty( self::$log ) ) {
			self::$log = new WC_Logger();
		}

		self::$log->add( 'wcs-ipn-failures', $message );
	}
}
