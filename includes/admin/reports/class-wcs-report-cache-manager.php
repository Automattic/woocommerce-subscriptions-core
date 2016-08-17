<?php
/**
 * Subscriptions Report Cache Manager
 *
 * Update report data caches on appropriate events, like renewal order payment.
 *
 * @class    WCS_Cache_Manager
 * @since    2.1
 * @package  WooCommerce Subscriptions/Classes
 * @category Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WCS_Report_Cache_Manager {

	/**
	 * Array of event => report classes to determine which reports need to be updated on certain events.
	 */
	private $update_events_and_classes = array(
		'woocommerce_subscription_payment_complete' => array( // this hook takes care of renewal, switch and initial payments
			'WC_Report_Subscription_Events_By_Date',
			'WC_Report_Subscription_By_Customer',
		),
		'woocommerce_subscriptions_switch_completed' => array(
			'WC_Report_Subscription_Events_By_Date',
		),
		'woocommerce_subscription_status_changed' => array(
			'WC_Report_Subscription_Events_By_Date', // we really only need cancelled, expired and active status here, but we'll use a more generic hook for convenience
			'WC_Report_Subscription_By_Customer',
		),
		'woocommerce_subscription_status_active' => array(
			'WC_Report_Upcoming_Recurring_Revenue',
		),
		'woocommerce_order_add_product' => array(
			'WC_Report_Subscription_By_Product',
		),
		'woocommerce_order_edit_product' => array(
			'WC_Report_Subscription_By_Product',
		),
	);

	/**
	 * Record of all the report calsses to need to have the cache updated during this request. Prevents duplicate updates in the same request for different events.
	 */
	private $reports_to_update = array();

	/**
	 * The hook name to use for our WP-Cron entry for updating report cache.
	 */
	private $cron_hook = 'wcs_report_update_cache';

	/**
	 * Attach callbacks to manage cache updates
	 *
	 * @since 2.1
	 * @return null
	 */
	public function __construct() {

		add_action( $this->cron_hook, array( $this, 'update_cache' ), 10, 1 );

		foreach ( $this->update_events_and_classes as $event_hook => $report_classes ) {
			add_action( $event_hook, array( $this, 'set_reports_to_update' ), 10 );
		}

		add_action( 'shutdown', array( $this, 'schedule_cache_updates' ), 10 );
	}

	/**
	 * Check if the given hook has reports associated with it, and if so, add them to our $this->reports_to_update
	 * property so we know to schedule an event to update their cache at the end of the request.
	 *
	 * This function is attached as a callback on the events in the $update_events_and_classes property.
	 *
	 * @since 2.1
	 * @return null
	 */
	public function set_reports_to_update() {
		if ( isset( $this->update_events_and_classes[ current_filter() ] ) ) {
			$this->reports_to_update = array_unique( array_merge( $this->reports_to_update, $this->update_events_and_classes[ current_filter() ] ) );
		}
	}

	/**
	 * At the end of the request, schedule cache updates for the near future for any events that occured during this request.
	 *
	 * This function is attached as a callback on 'shutdown' and will schedule cache updates for any reports found to need
	 * updates by @see $this->set_reports_to_update().
	 *
	 * @since 2.1
	 * @return null
	 */
	public function schedule_cache_updates() {

		// Schedule one update event for each class to avoid updating cache more than once for the same class for different events
		foreach ( $this->reports_to_update as $index => $report_class ) {

			$cron_args = array( 'report_class' => $report_class );

			if ( false !== ( $next_scheduled = wp_next_scheduled( $this->cron_hook, $cron_args ) ) ) {
				wp_unschedule_event( $next_scheduled, $this->cron_hook, $cron_args );
			}

			// Use the index to space out caching of each report to make them 3 minutes apart so that on large sites, where we assume they'll get a request at least once every 3 minutes, we don't try to update the caches of all reports in the same request
			wp_schedule_single_event( gmdate( 'U' ) + MINUTE_IN_SECONDS * ( $index + 1 ) * 3, $this->cron_hook, $cron_args );
		}
	}

	/**
	 * Update the cache data for a given report, as specified with $report_class, by call it's get_data() method.
	 *
	 * @since 2.1
	 * @return null
	 */
	public function update_cache( $report_class ) {

		// Validate the report class
		$valid_report_class = false;

		foreach ( $this->update_events_and_classes as $event_hook => $report_classes ) {
			if ( in_array( $report_class, $report_classes ) ) {
				$valid_report_class = true;
				break;
			}
		}

		if ( false === $valid_report_class ) {
			return;
		}

		// Load report class dependencies
		require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
		require_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );

		$report_name = strtolower( str_replace( '_', '-', str_replace( 'WC_Report_', '', $report_class ) ) );
		$report_path = WCS_Admin_Reports::initialize_reports_path( '', $report_name, $report_class );

		require_once( $report_path );

		$reflector = new ReflectionMethod( $report_class, 'get_data' );

		// Some report classes extend WP_List_Table which has a constructor using methods not available on WP-Cron (and unable to be loaded with a __doing_it_wrong() notice), so they have a static get_data() method and do not need to be instantiated
		if ( $reflector->isStatic() ) {

			call_user_func( array( $report_class, 'get_data' ), array( 'no_cache' => true ) );

		} else {

			$report = new $report_class();

			// Classes with a non-static get_data() method can be displayed for different time series, so we need to update the cache for each of those ranges
			foreach ( array( 'year', 'last_month', 'month', '7day' ) as $range ) {
				$report->calculate_current_range( $range );
				$report->get_data( array( 'no_cache' => true ) );
			}
		}
	}
}
return new WCS_Report_Cache_Manager();
