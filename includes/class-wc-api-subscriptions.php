<?php
/**
 * WooCommerce Subscriptions API
 *
 * Handles WC-API endpoint requests related to Subscriptions
 *
 * @author   Prospress
 * @since    2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Subscriptions_API {

	public static function init() {
		add_filter( 'woocommerce_api_classes', __CLASS__ . '::includes' );

	}

	/**
	 * Include the required files for the REST API and add register the subscription
	 * API class in the WC_API_Server.
	 *
	 * @since 2.0
	 * @param Array $wc_api_classes WC_API::registered_resources list of api_classes
	 * @return array
	 */
	public static function includes( $wc_api_classes ) {
		// include the subscription api classes
		require_once( 'api/class-wc-subscriptions-api-orders.php' );
		require_once( 'api/class-wc-subscriptions-api-customers.php' );

		array_push( $wc_api_classes, 'WC_API_Subscriptions' );
		array_push( $wc_api_classes, 'WC_Subscriptions_API_Customers' );

		return $wc_api_classes;
	}

}

WC_Subscriptions_API::init();
