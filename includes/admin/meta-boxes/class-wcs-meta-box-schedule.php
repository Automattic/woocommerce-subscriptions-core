<?php
/**
 * Subscription Billing Schedule
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin/Meta Boxes
 * @version  1.0.0 - Migrated from WooCommerce Subscriptions v2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WCS_Meta_Box_Schedule
 */
class WCS_Meta_Box_Schedule {

	/**
	 * Output the metabox
	 */
	public static function output( $post ) {
		global $post, $the_subscription;

		if ( empty( $the_subscription ) ) {
			$the_subscription = wcs_get_subscription( $post->ID );
		}

		include( dirname( __FILE__ ) . '/views/html-subscription-schedule.php' );
	}

	/**
	 * Save meta box data
	 *
	 * @see woocommerce_process_shop_order_meta
	 *
	 * @param int $order_id
	 * @param WC_Order $order
	 */
	public static function save( $order_id, $order ) {

		if ( 'shop_subscription' === $order->get_type() && ! empty( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( $_POST['woocommerce_meta_nonce'], 'woocommerce_save_data' ) ) {

			if ( isset( $_POST['_billing_interval'] ) ) {
				update_post_meta( $post_id, '_billing_interval', $_POST['_billing_interval'] );
			}

			if ( ! empty( $_POST['_billing_period'] ) ) {
				update_post_meta( $post_id, '_billing_period', $_POST['_billing_period'] );
			}

			$subscription = wcs_get_subscription( $order );

			$dates = array();

			foreach ( wcs_get_subscription_date_types() as $date_type => $date_label ) {
				$date_key = wcs_normalise_date_type_key( $date_type );

				if ( 'last_order_date_created' == $date_key ) {
					continue;
				}

				$utc_timestamp_key = $date_type . '_timestamp_utc';

				// A subscription needs a created date, even if it wasn't set or is empty
				if ( 'date_created' === $date_key && empty( $_POST[ $utc_timestamp_key ] ) ) {
					$datetime = time();
				} elseif ( isset( $_POST[ $utc_timestamp_key ] ) ) {
					$datetime = $_POST[ $utc_timestamp_key ];
				} else { // No date to set
					continue;
				}

				$dates[ $date_key ] = gmdate( 'Y-m-d H:i:s', $datetime );
			}

			try {
				$subscription->update_dates( $dates, 'gmt' );

				wp_cache_delete( $order_id, 'posts' );
			} catch ( Exception $e ) {
				wcs_add_admin_notice( $e->getMessage(), 'error' );
			}

			$subscription->save();
		}
	}
}
