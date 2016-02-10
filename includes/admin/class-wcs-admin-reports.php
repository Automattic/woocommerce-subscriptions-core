<?php
/**
 * Reports Admin
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin
 * @version  2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( class_exists( 'WCS_Admin_Reports' ) ) {
	return new WCS_Admin_Reports();
}

/**
 * WCS_Admin_Reports Class
 *
 * Handles the reports screen.
 */
class WCS_Admin_Reports {

	/**
	 * Constructor
	 */
	public function __construct() {

		// Add the reports layout to the WooCommerce -> Reports admin section
		add_filter( 'woocommerce_admin_reports',  __CLASS__ . '::initialize_reports', 12, 1 );

		// Add the reports layout to the WooCommerce -> Reports admin section
		add_filter( 'wc_admin_reports_path',  __CLASS__ . '::initialize_reports_path', 12, 3 );

		// Add any necessary scripts
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::reports_scripts' );

		// Track subscription cancellation dates
		add_action( 'woocommerce_subscription_status_updated', __CLASS__ . '::track_cancellation_dates', 12, 3 );
	}



	/**
	 * Add the 'Subscriptions' report type to the WooCommerce reports screen.
	 *
	 * @param array Array of Report types & their labels, excluding the Subscription product type.
	 * @return array Array of Report types & their labels, including the Subscription product type.
	 * @since 2.1
	 */
	public static function initialize_reports( $reports ) {

		$reports['subscriptions'] = array(
				'title'  => __( 'Subscriptions', 'woocommerce-subscriptions' ),
				'reports' => array(
					'subscription_events_by_date' => array(
						'title'       => __( 'Subscription Events by Date', 'woocommerce-subscriptions' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => array( 'WC_Admin_Reports', 'get_report' ),
					),
					'upcoming_recurring_revenue' => array(
						'title'       => __( 'Upcoming Recurring Revenue', 'woocommerce-subscriptions' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => array( 'WC_Admin_Reports', 'get_report' ),
					),
				),
			);

		return $reports;
	}

	/**
	 * If we hit one of our reports in the WC get_report function, change the path to our dir.
	 *
	 * @param report_path the parth to the report.
	 * @param name the name of the report.
	 * @param class the class of the report.
	 * @return string  path to the report template.
	 * @since 2.1
	 */
	public static function initialize_reports_path( $report_path, $name, $class ) {

		if ( 'WC_Report_subscription_events_by_date' == $class || 'WC_Report_upcoming_recurring_revenue' == $class ) {
			$report_path = dirname( __FILE__ ) . '/reports/class-wcs-report-' . $name . '.php';
		}

		return $report_path;

	}

	/**
	 * Add any subscriptions report javascript to the admin pages.
	 *
	 * @since 1.5
	 */
	public static function reports_scripts() {
		global $wp_query, $post;

		$suffix       = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$screen       = get_current_screen();
		$wc_screen_id = sanitize_title( __( 'WooCommerce', 'woocommerce' ) );

		// Reports Subscriptions Pages
		if ( in_array( $screen->id, apply_filters( 'woocommerce_reports_screen_ids', array( $wc_screen_id . '_page_wc-reports', 'dashboard' ) ) ) && isset( $_GET['tab'] ) && 'subscriptions' == $_GET['tab'] ) {
			wp_enqueue_script( 'wcs-reports', plugin_dir_url( WC_Subscriptions::$plugin_file ) . 'assets/js/admin/reports.js', array( 'jquery', 'jquery-ui-datepicker', 'wc-reports' ), WC_Subscriptions::$version );
			wp_enqueue_script( 'flot-order', plugin_dir_url( WC_Subscriptions::$plugin_file ) . 'assets/js/admin/jquery.flot.orderBars' . $suffix . '.js', array( 'jquery', 'flot' ), WC_Subscriptions::$version );
		}
	}

	/**
	 * Add postmeta whenever a subscription is set to cancelled
	 *
	 * @since 2.1
	 */
	public static function track_cancellation_dates( $subscription, $new_status, $old_status ) {

		if ( 'pending-cancel' == $new_status ) {
			update_post_meta( $subscription->id, '_subscription_cancelled_date', current_time( 'mysql', true ) );
		} elseif ( 'cancelled' == $new_status ) {
			add_post_meta( $subscription->id, '_subscription_cancelled_date', current_time( 'mysql', true ), true );
		}

	}
}

new WCS_Admin_Reports();
