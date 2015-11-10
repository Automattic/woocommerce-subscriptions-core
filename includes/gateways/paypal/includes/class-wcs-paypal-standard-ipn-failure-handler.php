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
}

WCS_PayPal_Standard_IPN_Failure_Handler::init();
