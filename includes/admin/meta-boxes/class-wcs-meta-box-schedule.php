<?php
/**
 * Subscription Billing Schedule
 *
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

		include dirname( __FILE__ ) . '/views/html-subscription-schedule.php';
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

		if ( ! wcs_is_subscription( $order_id ) ) {
			return;
		}

		if ( empty( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( wc_clean( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		$subscription = wcs_get_subscription( $order );

		if ( isset( $_POST['_billing_interval'] ) ) {
			$subscription->set_billing_interval( wc_clean( wp_unslash( $_POST['_billing_interval'] ) ) );
		}

		if ( ! empty( $_POST['_billing_period'] ) ) {
			$subscription->set_billing_period( wc_clean( wp_unslash( $_POST['_billing_period'] ) ) );
		}

		$dates = array();

		foreach ( wcs_get_subscription_date_types() as $date_type => $date_label ) {
			$date_key = wcs_normalise_date_type_key( $date_type );

			if ( 'last_order_date_created' === $date_key ) {
				continue;
			}

			$utc_timestamp_key = $date_type . '_timestamp_utc';

			// A subscription needs a created date, even if it wasn't set or is empty
			if ( 'date_created' === $date_key && empty( $_POST[ $utc_timestamp_key ] ) ) {
				$datetime = time();
			} elseif ( isset( $_POST[ $utc_timestamp_key ] ) ) {
				$datetime = wc_clean( wp_unslash( $_POST[ $utc_timestamp_key ] ) );
			} else { // No date to set
				continue;
			}

			$dates[ $date_key ] = gmdate( 'Y-m-d H:i:s', $datetime );
		}

		try {
			$subscription->update_dates( $dates, 'gmt' );

			// Clear the posts cache for non-HPOS stores.
			if ( ! wcs_is_custom_order_tables_usage_enabled() ) {
				wp_cache_delete( $order_id, 'posts' );
			}
		} catch ( Exception $e ) {
			wcs_add_admin_notice( $e->getMessage(), 'error' );
		}

		$subscription->save();
	}
}
