<?php
/**
 * Subscription Billing Schedule
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin/Meta Boxes
 * @version  2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

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

		include( 'views/html-subscription-schedule.php' );
	}

	/**
	 * Save meta box data
	 */
	public static function save( $post_id, $post ) {

		if ( 'shop_subscription' == $post->post_type ) {

			update_post_meta( $post_id, '_billing_interval', $_POST['_billing_interval'] );
			update_post_meta( $post_id, '_billing_period', $_POST['_billing_period'] );

			$subscription = wcs_get_subscription( $post_id );

			foreach ( wcs_get_subscription_date_types() as $date_key => $date_label ) {

				$utc_timestamp_key = $date_key . '_timestamp_utc';

				// A subscription needs a start date, even if it wasn't set
				if ( isset( $_POST[ $utc_timestamp_key ] ) ) {
					$datetime = $_POST[ $utc_timestamp_key ];
				} elseif ( 'start' === $date_key ) {
					$datetime = current_time( 'timestamp', true );
				} else { // No date to set
					continue;
				}

				if ( 0 != $datetime ) {
					$datetime = date( 'Y-m-d H:i:s', $datetime );
				}

				try {
					$subscription->update_date( $date_key, $datetime, 'gmt' );
				} catch ( Exception $e ) {
					wcs_add_admin_notice( $e->getMessage(), 'error' );
				}
			}
		}
	}
}
