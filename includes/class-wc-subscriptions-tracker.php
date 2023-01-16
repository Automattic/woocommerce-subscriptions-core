<?php
/**
 * Tracker for Subscriptions usage.
 *
 * @class     WC_Subscriptions_Tracker
 * @version   1.0.0 - Migrated from WooCommerce Subscriptions v2.6.4
 * @package   WooCommerce Subscriptions/Classes
 * @category  Class
 * @author    WooCommerce
 */

defined( 'ABSPATH' ) || exit;

class WC_Subscriptions_Tracker {

	/**
	 * Initialize the Tracker.
	 */
	public static function init() {
		// Only add data if Tracker enabled
		if ( 'yes' === get_option( 'woocommerce_allow_tracking', 'no' ) ) {
			add_filter( 'woocommerce_tracker_data', array( __CLASS__, 'add_subscriptions_tracking_data' ), 10, 1 );
		}
	}

	/**
	 * Adds Subscriptions data to the WC tracked data.
	 *
	 * @param array $data
	 * @return array all the tracking data.
	 */
	public static function add_subscriptions_tracking_data( $data ) {
		$data['extensions']['wc_subscriptions']['settings']            = self::get_subscriptions_options();
		$data['extensions']['wc_subscriptions']['subscriptions']       = self::get_subscriptions();
		$data['extensions']['wc_subscriptions']['subscription_orders'] = self::get_subscription_orders();
		return $data;
	}

	/**
	 * Gets the tracked Subscriptions options data.
	 *
	 * @return array Subscriptions options data.
	 */
	private static function get_subscriptions_options() {
		return array(
			// Staging and live site
			'wc_subscriptions_staging'             => WCS_Staging::is_duplicate_site() ? 'staging' : 'live',
			'wc_subscriptions_live_url'            => esc_url( WCS_Staging::get_site_url_from_source( 'subscriptions_install' ) ),

			// Button text, roles
			'add_to_cart_button_text'              => get_option( WC_Subscriptions_Admin::$option_prefix . '_add_to_cart_button_text' ),
			'order_button_text'                    => get_option( WC_Subscriptions_Admin::$option_prefix . '_order_button_text' ),
			'subscriber_role'                      => get_option( WC_Subscriptions_Admin::$option_prefix . '_subscriber_role' ),
			'cancelled_role'                       => get_option( WC_Subscriptions_Admin::$option_prefix . '_cancelled_role' ),

			// Renewals
			'accept_manual_renewals'               => get_option( WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals' ),
			'turn_off_automatic_payments'          => 'no' == get_option( WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals' ) ? 'none' : get_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments', 'none' ),
			'enable_auto_renewal_toggle'           => get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_auto_renewal_toggle' ),

			// Early renewal
			'enable_early_renewal'                 => get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_early_renewal' ),
			'enable_early_renewal_via_modal'       => 'no' == get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_early_renewal' ) ? 'none' : get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_early_renewal_via_modal', 'none' ),

			// Switching
			'allow_switching'                      => get_option( WC_Subscriptions_Admin::$option_prefix . '_allow_switching' ),
			'apportion_recurring_price'            => get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_recurring_price', 'none' ),
			'apportion_sign_up_fee'                => get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_sign_up_fee', 'none' ),
			'apportion_length'                     => get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_length', 'none' ),
			'switch_button_text'                   => get_option( WC_Subscriptions_Admin::$option_prefix . '_switch_button_text', 'none' ),

			// Synchronization
			'sync_payments'                        => get_option( WC_Subscriptions_Admin::$option_prefix . '_sync_payments' ),
			'prorate_synced_payments'              => $prorate_synced_payments = ( 'no' == get_option( WC_Subscriptions_Admin::$option_prefix . '_sync_payments' ) ? 'none' : get_option( WC_Subscriptions_Admin::$option_prefix . '_prorate_synced_payments', 'none' ) ),
			'days_no_fee'                          => 'recurring' == $prorate_synced_payments ? get_option( WC_Subscriptions_Admin::$option_prefix . '_days_no_fee', 'none' ) : 'none',

			// Miscellaneous
			'max_customer_suspensions'             => get_option( WC_Subscriptions_Admin::$option_prefix . '_max_customer_suspensions' ),
			'multiple_purchase'                    => get_option( WC_Subscriptions_Admin::$option_prefix . '_multiple_purchase' ),
			'allow_zero_initial_order_without_payment_method' => get_option( WC_Subscriptions_Admin::$option_prefix . '_zero_initial_payment_requires_payment' ),
			'drip_downloadable_content_on_renewal' => get_option( WC_Subscriptions_Admin::$option_prefix . '_drip_downloadable_content_on_renewal' ),
			'enable_retry'                         => get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_retry' ),
		);
	}

	/**
	 * Gets the combined subscription dates, count, and totals data.
	 *
	 * @return array
	 */
	private static function get_subscriptions() {
		$subscription_dates  = self::get_subscription_dates();
		$subscription_counts = self::get_subscription_counts();

		return array_merge( $subscription_dates, $subscription_counts );
	}

	/**
	 * Gets subscription counts.
	 *
	 * @return array
	 */
	private static function get_subscription_counts() {
		$subscription_counts      = array();
		$subscription_counts_data = wp_count_posts( 'shop_subscription' );
		foreach ( wcs_get_subscription_statuses() as $status_slug => $status_name ) {
			$subscription_counts[ $status_slug ] = $subscription_counts_data->{ $status_slug };
		}
		return $subscription_counts;
	}

	/**
	 * Gets subscription order counts and totals.
	 *
	 * @return array
	 */
	private static function get_subscription_orders() {
		$order_totals   = [];
		$relation_types = [
			'switch',
			'renewal',
			'resubscribe',
		];

		// Get the subtotal and count for each subscription type.
		foreach ( $relation_types as $relation_type ) {

			// Prepare the handler for the query to get the orders for this relation type.
			$relation_type_query_handler = function( $query ) use ( $relation_type ) {
				$query['meta_query'][] = [
					'key'     => '_subscription_' . $relation_type,
					'compare' => 'EXISTS',
				];
				return $query;
			};
			add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $relation_type_query_handler, 10 );

			// Fetch the orders.
			$relation_orders = wc_get_orders(
				[
					'type'   => 'shop_order',
					'status' => [ 'wc-completed', 'wc-processing', 'wc-refunded' ],
					'limit'  => -1,
				]
			);

			remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $relation_type_query_handler, 10 );

			// Sum the totals and count the orders.
			$count = count( $relation_orders );
			$total = array_reduce(
				$relation_orders,
				function( $total, $order ) {
					return $total + $order->get_total();
				},
				0
			);

			$order_totals[ $relation_type . '_gross' ] = $total;
			$order_totals[ $relation_type . '_count' ] = $count;
		}

		// Finally, get the initial revenue and count.
		// Prepare the handler for the query to get the orders for all initial subscription orders (no switch, renewal or resubscribe meta key).
		$initial_type_query_handler = function( $query ) use ( $relation_types ) {
			foreach ( $relation_types as $relation_type ) {
				$query['meta_query'][] = [
					'key'     => '_subscription_' . $relation_type,
					'compare' => 'NOT EXISTS',
				];
			}
			return $query;
		};
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $initial_type_query_handler, 10 );

		// Fetch the orders.
		$initial_subscription_orders = wc_get_orders(
			[
				'type'   => 'shop_order',
				'status' => [ 'wc-completed', 'wc-processing', 'wc-refunded' ],
				'limit'  => -1,
			]
		);

		remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $initial_type_query_handler, 10 );

		// Sum the totals and count the orders.
		$initial_order_count = count( $initial_subscription_orders );
		$initial_order_total = array_reduce(
			$initial_subscription_orders,
			function( $total, $order ) {
				return $total + $order->get_total();
			},
			0
		);

		$order_totals['initial_gross'] = $initial_order_total;
		$order_totals['initial_count'] = $initial_order_count;

		return $order_totals;
	}

	/**
	 * Gets first and last subscription created dates.
	 *
	 * @return array 'first' and 'last' created subscription dates as a string in the date format 'Y-m-d H:i:s' or '-'.
	 */
	private static function get_subscription_dates() {
		// Ignore subscriptions with status 'trash'.
		$first = wcs_get_subscriptions(
			array(
				'subscriptions_per_page' => 1,
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'subscription_status'    => [ 'active', 'on-hold', 'pending', 'cancelled', 'expired' ],
			)
		);
		$last  = wcs_get_subscriptions(
			array(
				'subscriptions_per_page' => 1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'subscription_status'    => [ 'active', 'on-hold', 'pending', 'cancelled', 'expired' ],
			)
		);

		// Return each date in 'Y-m-d H:i:s' format or '-' if no subscriptions found.
		$min_max = array(
			'first' => count( $first ) ? array_shift( $first )->get_date( 'date_created' ) : '-',
			'last'  => count( $last ) ? array_shift( $last )->get_date( 'date_created' ) : '-',
		);

		return $min_max;
	}
}
