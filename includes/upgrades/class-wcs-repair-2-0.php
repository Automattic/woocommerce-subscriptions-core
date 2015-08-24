<?php
/**
 * Repair subscriptions data to v2.0
 *
 * @author		Prospress
 * @category	Admin
 * @package		WooCommerce Subscriptions/Admin/Upgrades
 * @version		2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Repair_2_0 {
	/**
	 * Takes care of undefine notices in the upgrade process
	 *
	 * @param  array $order_item item meta
	 * @return array             repaired item meta
	 */
	public static function maybe_repair_order_item( $order_item ) {
		foreach ( array( 'qty', 'tax_class', 'product_id', 'variation_id', 'recurring_line_subtotal', 'recurring_line_total', 'recurring_line_subtotal_tax', 'recurring_line_tax' ) as $key ) {
			if ( ! array_key_exists( $key, $order_item ) ) {
				$order_item[ $key ] = '';
			}
		}

		return $order_item;
	}

	/**
	 * Does sanity check on every subscription, and repairs them as needed
	 *
	 * @param  array $subscription subscription data to be upgraded
	 * @param  integer $item_id      id of order item meta
	 * @return array               a repaired subscription array
	 */
	public static function maybe_repair_subscription( $subscription, $item_id ) {
		global $wpdb;

		$item_meta = get_metadata( 'order_item', $item_id );

		foreach ( self::integrity_check( $subscription ) as $function ) {
			$subscription = call_user_func( 'WCS_Repair_2_0::repair_' . $function, $subscription, $item_id, $item_meta );
		}

		return $subscription;
	}

	/**
	 * Checks for missing data on a subscription
	 *
	 * @param  array $subscription data about the subscription
	 * @return array               a list of repair functions to run on the subscription
	 */
	public static function integrity_check( $subscription ) {
		$repairs_needed = array();

		foreach ( array(
			'order_id',
			'product_id',
			'variation_id',
			'status',
			'period',
			'interval',
			'length',
			'start_date',
			'trial_expiry_date',
			'expiry_date',
			'end_date',
			'recurring_line_total',
			'recurring_line_tax',
			'recurring_line_subtotal',
			'recurring_line_subtotal_tax',
			'subscription_key',
			) as $meta ) {
			if ( ! array_key_exists( $meta, $subscription ) || empty( $subscription[ $meta ] ) ) {
				$repairs_needed[] = $meta;
			}
		}

		return $repairs_needed;
	}

	/**
	 * 'order_id': a subscription can exist without an original order in v2.0, so technically the order ID is no longer required.
	 * However, if some or all order item meta data that constitutes a subscription exists without a corresponding parent order,
	 * we can deem the issue to be that the subscription meta data was not deleted, not that the subscription should exist. Meta
	 * data could be orphaned in v1.n if the order row in the wp_posts table was deleted directly in the database, or the
	 * subscription/order were for a customer that was deleted in WordPress administration interface prior to Subscriptions v1.3.8.
	 * In both cases, the subscription, including meta data, should have been permanently deleted. However, deleting data is not a
	 * good idea during an upgrade. So I propose instead that we create a subscription without a parent order, but move it to the trash.
	 *
	 * Additional idea was to check whether the given order_id exists, but since that's another database read, it would slow down a lot of things.
	 *
	 * A subscription will not make it to this point if it doesn't have an order id, so this function will practically never be run
	 *
	 * @param  array $subscription data about the subscription
	 * @return array               repaired data about the subscription
	 */
	public static function repair_order_id( $subscription ) {
		WCS_Upgrade_Logger::add( sprintf( 'Repairing order_id for subscription %d: Status changed to trash', $subscription['order_id'] ) );

		$subscription['status'] = 'trash';

		return $subscription;
	}

	/**
	 * Combined functionality for the following functions:
	 * - repair_product_id
	 * - repair_variation_id
	 * - repair_recurring_line_total
	 * - repair_recurring_line_tax
	 * - repair_recurring_line_subtotal
	 * - repair_recurring_line_subtotal_tax
	 *
	 * @param  array   $subscription          data about the subscription
	 * @param  numeric $item_id               the id of the product we're missing the id for
	 * @param  array   $item_meta             meta data about the product
	 * @param  string  $item_meta_key         the meta key for the data on the item meta
	 * @param  string  $subscription_meta_key the meta key for the data on the subscription
	 * @return array                          repaired data about the subscription
	 */
	public static function repair_from_item_meta( $subscription, $item_id, $item_meta, $subscription_meta_key = null, $item_meta_key = null, $default_value = '' ) {
		if ( ! is_string( $subscription_meta_key ) || ! is_string( $item_meta_key ) || ( ! is_string( $default_value ) && ! is_numeric( $default_value ) ) ) {
			return $subscription;
		}

		WCS_Upgrade_Logger::add( sprintf( 'Repairing %s for subscription %d.', $subscription_meta_key, $subscription['order_id'] ) );

		if ( array_key_exists( $item_meta_key, $item_meta ) && ! empty( $item_meta[ $item_meta_key ] ) ) {
			WCS_Upgrade_Logger::add( sprintf( '-- Copying %s from item_meta to %s on subscription', $item_meta_key, $subscription_meta_key ) );
			$subscription[ $subscription_meta_key ] = $item_meta[ $item_meta_key ][0];
		} elseif ( ! array_key_exists( $subscription_meta_key, $subscription ) ) {
			WCS_Upgrade_Logger::add( sprintf( '-- Setting an empty %s on old subscription, item meta was not helpful.', $subscription_meta_key ) );
			$subscription[ $subscription_meta_key ] = $default_value;
		}

		return $subscription;
	}

	/**
	 * '_product_id': the only way to derive a order item's product ID would be to match the order item's name to a product name/title.
	 * This is quite hacky, so we may be better copying the empty product ID to the new subscription. A subscription to a deleted
	 * produced should be able to exist.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing the id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_product_id( $subscription, $item_id, $item_meta ) {
		return self::repair_from_item_meta( $subscription, $item_id, $item_meta, 'product_id', 'product_id' );
	}

	/**
	 * '_variation_id': the only way to derive a order item's product ID would be to match the order item's name to a product name/title.
	 * This is quite hacky, so we may be better copying the empty product ID to the new subscription. A subscription to a deleted produced
	 * should be able to exist.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_variation_id( $subscription, $item_id, $item_meta ) {
		return self::repair_from_item_meta( $subscription, $item_id, $item_meta, 'variation_id', 'variation_id' );
	}

	/**
	 * '_subscription_status': we could default to cancelled (and then potentially trash) if no status exists because the cancelled status
	 * is irreversible. But we can also take this a step further. If the subscription has a '_subscription_expiry_date' value and a
	 * '_subscription_end_date' value, and they are within a few minutes of each other, we can assume the subscription's status should be
	 * expired. If there is a '_subscription_end_date' value that is different to the '_subscription_expiry_date' value (either because the
	 * expiration value is 0 or some other date), then we can assume the status should be cancelled). If there is no end date value, we're
	 * a bit lost as technically the subscription hasn't ended, but we should make sure it is not active, so cancelled is still the best
	 * default.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_status( $subscription, $item_id, $item_meta ) {
		WCS_Upgrade_Logger::add( sprintf( 'Repairing status for subscription %d.', $subscription['order_id'] ) );

		// only reset this if we didn't repair the order_id
		if ( ! array_key_exists( 'order_id', $subscription ) || empty( $subscription['order_id'] ) ) {
			WCS_Upgrade_Logger::add( '-- Previously set it to trash with order_id missing, bailing.' );
			return $subscription;
		}

		// if expiry_date and end_date are within 4 minutes (arbitrary), let it be expired
		if ( array_key_exists( 'expiry_date' ) && ! empty( $subscription['expiry_date'] ) && array_key_exists( 'end_date', $subscription ) && ! empty( $subscription['end_date'] ) && ( 4 * MINUTE_IN_SECONDS ) > self::time_diff( $subscription['expiry_date'], $subscription['end_date'] ) ) {
			WCS_Upgrade_Logger::add( '-- There are end dates and expiry dates, they are close to each other, setting status to "expired" and returning.' );

			$subscription['status'] = 'expired';
		} else {
			// default to cancelled
			WCS_Upgrade_Logger::add( '-- Setting the default to "cancelled".' );
			$subscription['status'] = 'cancelled';
		}

		WCS_Upgrade_Logger::add( sprintf( '-- Returning the status with %s', $subscription['status'] ) );
		return $subscription;
	}

	/**
	 * '_subscription_period': we can attempt to derive this from the time between renewal orders. For example, if there are two renewal
	 * orders found 3 months apart, the billing period would be month. If there are not two or more renewal orders (we can't use a single
	 * renewal order because that would account for the free trial) and a _product_id value , if the product still exists, we can use the
	 * current value set on that product. It won't always be correct, but it's the closest we can get to an accurate estimate.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_period( $subscription, $item_id, $item_meta ) {
		WCS_Upgrade_Logger::add( sprintf( 'Repairing period for subscription %d.', $subscription['order_id'] ) );

		// Get info from the product
		if ( array_key_exists( '_subscription_period', $item_meta ) && ! empty( $item_meta['_subscription_period'] ) ) {
			WCS_Upgrade_Logger::add( '-- Getting info from item meta and returning.' );

			$subscription['period'] = $item_meta['_subscription_period'][0];
			return $subscription;
		}

		// let's get the renewal orders
		$renewal_orders = self::get_renewal_orders( $subscription );

		if ( count( $renewal_orders ) < 2 ) {
			// default to month
			$subscription['period'] = 'month';
			return $subscription;
		}

		// let's get the last 2 renewal orders
		$last_renewal_order = array_shift( $renewal_orders );
		$last_renewal_date = $last_renewal_order->order_date;
		$last_renewal_timestamp = strtotime( $last_renewal_date );

		$second_renewal_order = array_shift( $renewal_orders );
		$second_renewal_date = $second_renewal_order->order_date;
		$second_renewal_timestamp = strtotime( $second_renewal_date );

		$interval = 1;

		// if we have an interval, let's pass this along too, because then it's a known variable
		if ( array_key_exists( 'interval', $subscription ) && ! empty( $subscription['interval'] ) ) {
			$interval = $subscription['interval'];
		}

		WCS_Upgrade_Logger::add( '-- Passing info to maybe_get_period...' );
		$period = self::maybe_get_period( $last_renewal_date, $second_renewal_date, $interval );

		// if we have 3 renewal orders, do a double check
		if ( ! empty( $renewal_orders ) ) {
			WCS_Upgrade_Logger::add( '-- We have 3 renewal orders, trying to make sure we are right.' );

			$third_renewal_order = array_shift( $renewal_orders );
			$third_renewal_date = $third_renewal_order->order_date;

			$period2 = self::maybe_get_period( $second_renewal_date, $third_renewal_date, $interval );

			if ( $period == $period2 ) {
				WCS_Upgrade_Logger::add( sprintf( '-- Second check confirmed, we are very confident period is %s', $period ) );
				$subscription['period'] = $period;
			}
		}

		$subscription['period'] = $period;

		return $subscription;
	}

	/**
	 * '_subscription_interval': we can attempt to derive this from the time between renewal orders. For example, if there are two renewal
	 * orders found 3 months apart, the billing period would be month. If there are not two or more renewal orders (we can't use a single
	 * renewal order because that would account for the free trial) and a _product_id value , if the product still exists, we can use the
	 * current value set on that product. It won't always be correct, but it's the closest we can get to an accurate estimate.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_interval( $subscription, $item_id, $item_meta ) {
		// let's see if we can have info from the product
		// Get info from the product
		if ( array_key_exists( '_subscription_interval', $item_meta ) && ! empty( $item_meta['_subscription_interval'] ) ) {
			WCS_Upgrade_Logger::add( '-- Getting info from item meta and returning.' );

			$subscription['interval'] = $item_meta['_subscription_interval'][0];
			return $subscription;
		}

		// by this time we already have a period on our hand
		// let's get the renewal orders
		$renewal_orders = self::get_renewal_orders( $subscription );

		if ( count( $renewal_orders ) < 2 ) {
			// default to month
			$subscription['interval'] = 1;
			return $subscription;
		}

		// let's get the last 2 renewal orders
		$last_renewal_order = array_shift( $renewal_orders );
		$last_renewal_date = $last_renewal_order->order_date;
		$last_renewal_timestamp = strtotime( $last_renewal_date );

		$second_renewal_order = array_shift( $renewal_orders );
		$second_renewal_date = $second_renewal_order->order_date;
		$second_renewal_timestamp = strtotime( $second_renewal_date );

		$subscription['interval'] = wcs_estimate_periods_between( $second_renewal_timestamp, $last_renewal_timestamp, $subscription['period'] );

		return $subscription;
	}

	/**
	 * '_subscription_length': if there is are '_subscription_expiry_date' and '_subscription_start_date' values, we can use those to
	 * determine how many billing periods fall between them, and therefore, the length of the subscription. This data is low value however as
	 * it is no longer stored in v2.0 and mainly used to determine the expiration date.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_length( $subscription, $item_id, $item_meta ) {
		// Set a default
		$subscription['length'] = 0;

		// Let's see if the item meta has that
		if ( array_key_exists( '_subscription_length', $item_meta ) && ! empty( $item_meta['_subscription_length'] ) ) {
			WCS_Upgrade_Logger::add( '-- Copying subscription_length from item_meta' );
			$subscription['length'] = $item_meta['_subscription_length'][0];
			return $subscription;
		}

		// If we can calculate it from start date and expiry date
		if ( 'expired' == $subscription['status'] && array_key_exists( 'expiry_date', $susbcription ) && ! empty( $subscription['expiry_date'] ) && array_key_exists( 'start_date', $subscription ) && ! empty( $subscription['start_date'] ) && array_key_exists( 'period', $subscription ) && ! empty( $subscription['period'] ) && array_key_exists( 'interval', $subscription ) && ! empty( $subscription['interval'] ) ) {
			$intervals = wcs_estimate_periods_between( strtotime( $subscription['start_date'] ), strtotime( $subscription['expiry_date'] ), $subscription['period'], 'floor' );

			$intervals = floor( $intervals / $subscription['interval'] );

			$subscription['length'] = $intervals;
		}

		return $subscription;
	}


	/**
	 * '_subscription_start_date': the original order's '_paid_date' value (stored in post meta) can be used as the subscription's start date.
	 * If no '_paid_date' exists, because the order used a payment method that doesn't call $order->payment_complete(), like BACs or Cheque,
	 * then we can use the post_date_gmt column in the wp_posts table of the original order.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_start_date( $subscription, $item_id, $item_meta ) {
		global $wpdb;
		$start_date = get_post_meta( $subscription['order_id'], '_paid_date', true );

		if ( empty( $start_date ) ) {
			$start_date = $wpdb->get_var( $wpdb->prepare( "SELECT post_date_gmt FROM {$wpdb->posts} WHERE ID = %d", $subscription['order_id'] ) );
		}

		$subscription['start_date'] = $start_date;
		return $subscription;
	}


	/**
	 * '_subscription_trial_expiry_date': if the subscription has at least one renewal order, we can set the trial expiration date to the date
	 * of the first renewal order. However, this is generally safe to default to 0 if it is not set. Especially if the subscription is
	 * inactive and/or has 1 or more renewals (because its no longer used and is simply for record keeping).
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_trial_expiry_date( $subscription, $item_id, $item_meta ) {
		$subscription['trial_expiry_date'] = 0;
		return $subscription;
	}

	/**
	 * '_subscription_expiry_date': if the subscription has a '_subscription_length' value, that can be used to calculate the expiration date
	 * (from the '_subscription_start_date' or '_subscription_trial_expiry_date' if one is set). If no length is set, but the subscription has
	 * an expired status, the '_subscription_end_date' can be used. In most other cases, this is generally safe to default to 0 if the
	 * subscription is cancelled because its no longer used and is simply for record keeping.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_expiry_date( $subscription, $item_id, $item_meta ) {
		$subscription['expiry_date'] = 0;
		return $subscription;
	}

	/**
	 * '_subscription_end_date': if the subscription has a '_subscription_length' value and status of expired, the length can be used to
	 * calculate the end date as it will be the same as the expiration date. If no length is set, or the subscription has a cancelled status,
	 * some time within 24 hours after the last renewal order's date can be used to provide a rough estimate.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_end_date( $subscription, $item_id, $item_meta ) {
		if ( array_key_exists( '_subscription_end_date', $item_meta ) ) {
			WCS_Upgrade_Logger::add( '-- Copying end date from item_meta' );
			$subscription['end_date'] = $item_meta['_subscription_end_date'][0];
			return $subscription;
		}

		if ( 'expired' == $subscription['status'] && array_key_exists( 'expiry_date', $subscription ) && ! empty( $subscription['expiry_date'] ) ) {
			$subscription['end_date'] = $subscription['expiry_date'];
			return $subscription;
		}

		if ( 'cancelled' == $subscription['status'] || ! array_key_exists( 'length', $subscription ) || empty( $subscription['length'] ) ) {
			// get renewal orders
			$renewal_orders = self::get_renewal_orders( $subscription );
			$last_order = array_shift( $renewal_orders );

			$subscription['end_date'] = wcs_add_time( 5, 'hours', strtotime( $last_order->order_date ) );
			return $subscription;
		}

		// if everything failed, let's have an empty one
		$subscription['end_date'] = 0;

		return $subscription;
	}

	/**
	 * _recurring_line_total': if the subscription has at least one renewal order, this value can be derived from the '_line_total' value of
	 * that order. If no renewal orders exist, it can be derived roughly by deducting the '_subscription_sign_up_fee' value from the original
	 * order's total if there is no trial expiration date.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_recurring_line_total( $subscription, $item_id, $item_meta ) {
		return self::repair_from_item_meta( $subscription, $item_id, $item_meta, 'recurring_line_total', '_line_total', 0 );
	}

	/**
	 * _recurring_line_total': if the subscription has at least one renewal order, this value can be derived from the '_line_total' value
	 * of that order. If no renewal orders exist, it can be derived roughly by deducting the '_subscription_sign_up_fee' value from the
	 * original order's total if there is no trial expiration date.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_recurring_line_tax( $subscription, $item_id, $item_meta ) {
		return self::repair_from_item_meta( $subscription, $item_id, $item_meta, 'recurring_line_tax', '_line_tax', 0 );
	}

	/**
	 * _recurring_line_total': if the subscription has at least one renewal order, this value can be derived from the '_line_total' value of
	 * that order. If no renewal orders exist, it can be derived roughly by deducting the '_subscription_sign_up_fee' value from the original
	 * order's total if there is no trial expiration date
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_recurring_line_subtotal( $subscription, $item_id, $item_meta ) {
		return self::repair_from_item_meta( $subscription, $item_id, $item_meta, 'recurring_line_subtotal', '_line_subtotal', 0 );
	}

	/**
	 * _recurring_line_total': if the subscription has at least one renewal order, this value can be derived from the '_line_total' value of
	 * that order. If no renewal orders exist, it can be derived roughly by deducting the '_subscription_sign_up_fee' value from the original
	 * order's total if there is no trial expiration date.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_recurring_line_subtotal_tax( $subscription, $item_id, $item_meta ) {
		return self::repair_from_item_meta( $subscription, $item_id, $item_meta, 'recurring_line_subtotal_tax', '_line_subtotal_tax', 0 );
	}

	/**
	 * If the subscription does not have a subscription key for whatever reason (probably becuase the product_id was missing), then this one
	 * fills in the blank.
	 *
	 * @param  array $subscription data about the subscription
	 * @param  numeric $item_id    the id of the product we're missing variation id for
	 * @param  array $item_meta    meta data about the product
	 * @return array               repaired data about the subscription
	 */
	public static function repair_subscription_key( $subscription, $item_id, $item_meta ) {
		$subscription['subscription_key'] = $subscription['order_id'] . '_' . $item_id;

		return $subscription;
	}

	/**
	 * Utility function to calculate the seconds between two timestamps. Order is not important, it's just the difference.
	 *
	 * @param  string $to   mysql timestamp
	 * @param  string $from mysql timestamp
	 * @return integer       number of seconds between the two
	 */
	private static function time_diff( $to, $from ) {
		$to = strtotime( $to );
		$from = strtotime( $from );

		return abs( $to - $from );
	}

	/**
	 * Utility function to get all renewal orders in the old structure.
	 * @param  array $subscription the sub we're looking for the renewal orders
	 * @return array               of WC_Orders
	 */
	private static function get_renewal_orders( $subscription ) {
		$related_orders = array();

		$related_post_ids = get_posts( array(
			'posts_per_page' => -1,
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_parent'    => $subscription['order_id'],
		) );

		foreach ( $related_post_ids as $post_id ) {
			$related_orders[ $post_id ] = wc_get_order( $post_id );
		}

		return $related_orders;
	}

	/**
	 * Method to try to determine the period of subscriptions if data is missing. It tries the following, in order:
	 *
	 * - defaults to month
	 * - comes up with an array of possible values given the standard time spans (day / week / month / year)
	 * - ranks them
	 * - discards 0 interval values
	 * - discards high deviation values
	 * - tries to match with passed in interval
	 * - if all else fails, sorts by interval and returns the one having the lowest interval, or the first, if equal (that should
	 *   not happen though)
	 * @param  string  $last_date   mysql date string
	 * @param  string  $second_date mysql date string
	 * @param  integer $interval    potential interval
	 * @return string               period string
	 */
	private static function maybe_get_period( $last_date, $second_date, $interval = 1 ) {

		if ( ! is_int( $interval ) ) {
			$interval = 1;
		}

		$last_timestamp = strtotime( $last_date );
		$second_timestamp = strtotime( $second_date );

		$earlier_timestamp = min( $last_timestamp, $second_timestamp );
		$days_in_month = date( 't', $earlier_timestamp );

		$difference = absint( $last_timestamp - $second_timestamp );

		$period_in_seconds = round( $difference / $interval );

		$possible_periods = array();

		// check for different time spans
		foreach ( array( 'year' => YEAR_IN_SECONDS, 'month' => $days_in_month * DAY_IN_SECONDS, 'week' => WEEK_IN_SECONDS, 'day' => DAY_IN_SECONDS ) as $time => $seconds ) {
			$possible_periods[ $time ] = array(
				'intervals' => floor( $period_in_seconds / $seconds ),
				'remainder' => $remainder = $period_in_seconds % $seconds,
				'fraction' => $remainder / $seconds,
				'period' => $time,
				'days_in_month' => $days_in_month,
				'original_interval' => $interval,
			);
		}

		// filter out ones that are less than one period
		$possible_periods = array_filter( $possible_periods, 'self::discard_zero_intervals' );

		// filter out ones that have too high of a deviation
		$possible_periods_no_hd = array_filter( $possible_periods, 'self::discard_high_deviations' );

		if ( count( $possible_periods_no_hd ) == 1 ) {
			WCS_Upgrade_Logger::add( sprintf( '---- There is only one period left with no high deviation, returning with %s.', $possible_periods_no_hd['period'] ) );
			// only one matched, let's return that as our best guess
			return $possible_periods_no_hd['period'];
		} elseif ( count( $possible_periods_no_hd > 1 ) ) {
			WCS_Upgrade_Logger::add( '---- More than 1 periods with high deviation left.' );
			$possible_periods = $possible_periods_no_hd;
		}

		// check for interval equality
		$possible_periods_interval_match = array_filter( $possible_periods, 'self::match_intervals' );

		if ( count( $possible_periods_interval_match ) == 1 ) {
			foreach ( $possible_periods_interval_match as $period_data ) {
				WCS_Upgrade_Logger::add( sprintf( '---- Checking for interval matching, only one found, returning with %s.', $period_data['period'] ) );

				// only one matched the interval as our best guess
				return $period_data['period'];
			}
		} elseif ( count( $possible_periods_interval_match ) > 1 ) {
			WCS_Upgrade_Logger::add( '---- More than 1 periods with matching intervals left.' );
			$possible_periods = $possible_periods_interval_match;
		}

		// order by number of intervals and return the lowest

		usort( $possible_periods, 'self::sort_by_intervals' );

		$least_interval = array_shift( $possible_periods );

		WCS_Upgrade_Logger::add( sprintf( '---- Sorting by intervals and returning the first member of the array: %s', $least_interval['period'] ) );

		return $least_interval['period'];
	}

	/**
	 * Used in an array_filter, removes elements where intervals are less than 0
	 * @param  array $array elements of an array
	 * @return bool        true if at least 1 interval
	 */
	private static function discard_zero_intervals( $array ) {
		return $array['intervals'] > 0;
	}

	/**
	 * Used in an array_filter, discards high deviation elements.
	 * - for days it's 1/24th
	 * - for week it's 1/7th
	 * - for year it's 1/300th
	 * - for month it's 1/($days_in_months-2)
	 * @param  array $array elements of the filtered array
	 * @return bool        true if value is within deviation limit
	 */
	private static function discard_high_deviations( $array ) {
		switch ( $array['period'] ) {
			case 'year':
				return $array['fraction'] < ( 1 / 300 );
				break;
			case 'month':
				return $array['fraction'] < ( 1 / ( $array['days_in_month'] - 2 ) );
				break;
			case 'week':
				return $array['fraction'] < ( 1 / 7 );
				break;
			case 'day':
				return $array['fraction'] < ( 1 / 24 );
				break;
			default:
				return false;
		}
	}

	/**
	 * Used in an array_filter, tries to match intervals against passed in interval
	 * @param  array $array elements of filtered array
	 * @return bool        true if intervals match
	 */
	private static function match_intervals( $array ) {
		return $array['intervals'] == $array['original_interval'];
	}

	/**
	 * Used in a usort, responsible for making sure the array is sorted in ascending order by intervals
	 * @param  array $a one element of the sorted array
	 * @param  array $b different element of the sorted array
	 * @return int    0 if equal, -1 if $b is larger, 1 if $a is larger
	 */
	private static function sort_by_intervals( $a, $b ) {
		if ( $a['intervals'] == $b['intervals'] ) {
			return 0;
		}
		// return ( $a['intervals'] > $b['intervals'] ) ? -1 : 1;
		return ( $a['intervals'] < $b['intervals'] ) ? -1 : 1;
	}
}
