<?php
/**
 * Tracker for Subscriptions usage.
 *
 * @class     WC_Subscriptions_Tracker
 * @version   3.0.0
 * @package   WooCommerce Subscriptions/Classes
 * @category  Class
 * @author    Automattic
 */

defined( 'ABSPATH' ) || exit;

class WC_Subscriptions_Tracker {

	public static function init() {
		add_filter( 'woocommerce_tracker_data', array( __CLASS__, 'add_subscriptions_tracking_data' ), 10, 1 );
	}

	public static function add_subscriptions_tracking_data( $data ) {
		$data['extensions']['wc_subscriptions']['settings'] = self::get_subscriptions_options();
		$data['extensions']['wc_subscriptions']['subscriptions'] = self::get_subscriptions();
		return $data;
	}

	private static function get_subscriptions_options() {
		$subs_data = array(
			// Staging and live site
			'wc_subscriptions_staging'    => WC_Subscriptions::is_duplicate_site() ? 'staging' : 'live',
			'wc_subscriptions_live_url'   => esc_url( WC_Subscriptions::get_site_url_from_source( 'subscriptions_install' ) ),

			// Button text, roles, and renewals
			'add_to_cart_button_text'     => get_option( WC_Subscriptions_Admin::$option_prefix . '_add_to_cart_button_text' ),
			'order_button_text'           => get_option( WC_Subscriptions_Admin::$option_prefix . '_order_button_text' ),
			'subscriber_role'             => get_option( WC_Subscriptions_Admin::$option_prefix . '_subscriber_role' ),
			'cancelled_role'              => get_option( WC_Subscriptions_Admin::$option_prefix . '_cancelled_role' ),
			'accept_manual_renewals'      => get_option( WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals' ),
			'enable_auto_renewal_toggle'  => get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_auto_renewal_toggle' ),
			'enable_early_renewal'        => get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_early_renewal' ),

			// Switching
			'allow_switching'             => get_option( WC_Subscriptions_Admin::$option_prefix . '_allow_switching' ),

			// Synchronization
			'sync_payments'               => get_option( WC_Subscriptions_Admin::$option_prefix . '_sync_payments' ),

			// Miscellaneous
			'max_customer_suspensions'              => get_option( WC_Subscriptions_Admin::$option_prefix . '_max_customer_suspensions' ),
			'multiple_purchase'                     => get_option( WC_Subscriptions_Admin::$option_prefix . '_multiple_purchase' ),
			'zero_initial_payment_requires_payment' => get_option( WC_Subscriptions_Admin::$option_prefix . '_zero_initial_payment_requires_payment' ),
			'drip_downloadable_content_on_renewal'  => get_option( WC_Subscriptions_Admin::$option_prefix . '_drip_downloadable_content_on_renewal' ),
			'enable_retry' => get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_retry' ),
		);

		// Turn off automatic renewals
		if ( 'no' != get_option( WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals' ) ) {
			$subs_data['turn_off_automatic_payments'] = get_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments' );
		}

		// Enable early renewal
		if ( 'no' != get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_early_renewal' ) ) {
			$subs_data['enable_early_renewal_via_modal'] = get_option( WC_Subscriptions_Admin::$option_prefix . '_enable_early_renewal_via_modal' );
		}

		// Switching Options
		if ( 'no' != get_option( WC_Subscriptions_Admin::$option_prefix . '_allow_switching' ) ) {
			$subs_data['apportion_recurring_price'] = get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_recurring_price' );
			$subs_data['apportion_sign_up_fee']     = get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_sign_up_fee' );
			$subs_data['apportion_length']          = get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_length' );
			$subs_data['switch_button_text']        = get_option( WC_Subscriptions_Admin::$option_prefix . '_switch_button_text' );
		}

		// Synchronization options
		if ( 'no' != get_option( WC_Subscriptions_Admin::$option_prefix . '_sync_payments' ) ) {
			$subs_data['prorate_synced_payments'] = get_option( WC_Subscriptions_Admin::$option_prefix . '_prorate_synced_payments' );
			$subs_data['days_no_fee']             = get_option( WC_Subscriptions_Admin::$option_prefix . '_days_no_fee' );
		}

		return $subs_data;
	}

	/**
	 * Combine all subscription data.
	 *
	 * @return array
	 */
	private static function get_subscriptions() {
		$subscription_dates  = self::get_subscription_dates();
		$subscription_counts = self::get_subscription_counts();
		$subscription_totals = self::get_subscription_totals();

		return array_merge( $subscription_dates, $subscription_counts, $subscription_totals );
	}

	/**
	 * Get subscription counts
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
	 * Get order totals
	 *
	 * @return array
	 */
	private static function get_subscription_totals() {
		global $wpdb;

		$gross_totals   = array();
		$relation_types = array(
			'switch',
			'renewal',
			'resubscribe',
		);

		foreach ( $relation_types as $relation_type ) {

			$gross_total = $wpdb->get_var( sprintf(
				"
				SELECT
					SUM( order_total.meta_value ) AS 'gross_total'
				FROM {$wpdb->prefix}posts AS orders
					LEFT JOIN {$wpdb->prefix}postmeta AS order_relation ON order_relation.post_id = orders.ID
					LEFT JOIN {$wpdb->prefix}postmeta AS order_total ON order_total.post_id = orders.ID
				WHERE order_relation.meta_key =  '_subscription_%s'
					AND orders.post_status in ( 'wc-completed', 'wc-refunded' )
					AND order_total.meta_key = '_order_total'
				GROUP BY order_total.meta_key
			", $relation_type
			);

			if ( is_null( $gross_total ) ) {
				$gross_total = 0;
			}

			$gross_totals[ $relation_type ] = $gross_total;
		}

		// Finally get the initial revenue
		$gross_total = $wpdb->get_var( sprintf(
			"
			SELECT
				SUM( order_total.meta_value ) AS 'gross_total'
			FROM {$wpdb->prefix}posts AS orders
				LEFT JOIN {$wpdb->prefix}posts AS subscriptions ON subscriptions.post_parent = orders.ID
				LEFT JOIN {$wpdb->prefix}postmeta AS order_total ON order_total.post_id = orders.ID
			WHERE orders.post_status in ( 'wc-completed', 'wc-refunded' )
				AND subscriptions.post_type = 'shop_subscription'
				AND orders.post_type = 'shop_order'
				AND order_total.meta_key = '_order_total'
			GROUP BY order_total.meta_key
		", $relation_type
		);

		if ( is_null( $gross_total ) ) {
			$gross_total = 0;
		}

		// Don't double count resubscribe revenue
		$gross_totals['initial'] = $gross_total - $gross_totals['resubscribe'];

		return $gross_totals;
	}

	/**
	 * Get last order date
	 *
	 * @return string
	 */
	private static function get_subscription_dates() {
		global $wpdb;

		$min_max = $wpdb->get_row(
			"
			SELECT
				MIN( post_date_gmt ) as 'first', MAX( post_date_gmt ) as 'last'
			FROM {$wpdb->prefix}posts
			WHERE post_type = 'shop_subscription'
			AND post_status NOT IN ( 'trash', 'auto-draft' )
		",
			ARRAY_A
		);

		if ( is_null( $min_max ) ) {
			$min_max = array(
				'first' => '-',
				'last'  => '-',
			);
		}

		return $min_max;
	}
}
