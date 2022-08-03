<?php
/**
 * WC Subscriptions Custom Order Tables Compatibility Class
 *
 * @since 2.3
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\OrderUtil;

class WC_Subscriptions_COT_Compatibility {

	/**
	 * Initialise subscription COT compat hooks.
	 *
	 * @since 2.3
	 */
	public static function init() {
		add_filter( 'woocommerce_order_class', [ __CLASS__, 'get_subscription_classname_with_cot' ], 10, 3 );
	}

	/**
	 * When custom order tables is enabled, the Order_Factory::get_order_class_name_by_id()
	 * is unable to get the WC Subscriptions classname because the $order_type param is empty due to
	 * the OrderTableDataStore data store being loaded.
	 *
	 * While WC Subscriptions remain a custom order type that is found in the Posts table, this
	 * function will serve as a compatibility layer to allow using order functions to get a
	 * subscription object like wc_get_order( $sub_id ) and WC()->order_factory->get_order( $sub_id ).
	 *
	 * @since 2.3
	 *
	 * @param string $classname
	 * @param string $order_type
	 * @param int    $order_id
	 *
	 * @return string
	 */
	public static function get_subscription_classname_with_cot( $classname, $order_type, $order_id ) {
		// if classname and order type wasn't found (due to the COT data store loaded), manually check if it's a subscription
		if ( empty( $classname ) && empty( $order_type ) ) {
			$order_type      = WC_Data_Store::load( 'subscription' )->get_order_type( $order_id );
			$order_type_data = wc_get_order_type( $order_type );

			if ( 'shop_subscription' === $order_type && $order_type_data ) {
				$classname = $order_type_data['class_name'];
			}
		}

		return $classname;
	}
}
