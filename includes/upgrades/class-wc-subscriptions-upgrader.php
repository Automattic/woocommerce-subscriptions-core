<?php
/**
 * A timeout resistant, single-serve upgrader for WC Subscriptions.
 *
 * This class is used to make all reasonable attempts to neatly upgrade data between versions of Subscriptions.
 *
 * For example, the way subscription data is stored changed significantly between v1.n and v2.0. It was imperative
 * the data be upgraded to the new schema without hassle. A hassle could easily occur if 100,000 orders were being
 * modified - memory exhaustion, script time out etc.
 *
 * @author		Prospress
 * @category	Admin
 * @package		WooCommerce Subscriptions/Admin/Upgrades
 * @version		2.0.0
 * @since		1.2
 */
class WC_Subscriptions_Upgrader {

	private static $active_version;

	private static $upgrade_limit_hooks;

	private static $about_page_url;


	public static $is_wc_version_2 = false;

	public static $updated_to_wc_2_0;

	/**
	 * Hooks upgrade function to init.
	 *
	 * @since 1.2
	 */
	public static function init() {

		self::$active_version = get_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', '0' );

		self::$is_wc_version_2 = version_compare( get_option( 'woocommerce_db_version' ), '2.0', '>=' );

		self::$upgrade_limit_hooks = apply_filters( 'woocommerce_subscriptions_hooks_to_upgrade', 250 );

		self::$about_page_url = admin_url( 'index.php?page=wcs-about&wcs-updated=true' );

		if ( isset( $_POST['action'] ) && 'wcs_upgrade' == $_POST['action'] ) {

			add_action( 'wp_ajax_wcs_upgrade', __CLASS__ . '::ajax_upgrade', 10 );

		} elseif ( @current_user_can( 'activate_plugins' ) ) {

			if ( 'true' == get_transient( 'wc_subscriptions_is_upgrading' ) ) {

				self::upgrade_in_progress_notice();

			} elseif ( isset( $_GET['wcs_upgrade_step'] ) || version_compare( self::$active_version, WC_Subscriptions::$version, '<' ) ) {

				// Run upgrades as soon as admin hits site
				add_action( 'init', __CLASS__ . '::upgrade', 11 );

			} elseif ( is_admin() && isset( $_GET['page'] ) && 'wcs-about' == $_GET['page'] ) {

				add_action( 'admin_menu', __CLASS__ . '::updated_welcome_page' );

			}
		}
	}

	/**
	 * Checks which upgrades need to run and calls the necessary functions for that upgrade.
	 *
	 * @since 1.2
	 */
	public static function upgrade(){
		global $wpdb;

		update_option( WC_Subscriptions_Admin::$option_prefix . '_previous_version', self::$active_version );

		// Update the hold stock notification to be one week (if it's still at the default 60 minutes) to prevent cancelling subscriptions using manual renewals and payment methods that can take more than 1 hour (i.e. PayPal eCheck)
		if ( '0' == self::$active_version || version_compare( self::$active_version, '1.4', '<' ) ) {

			$hold_stock_duration = get_option( 'woocommerce_hold_stock_minutes' );

			if ( 60 == $hold_stock_duration ) {
				update_option( 'woocommerce_hold_stock_minutes', 60 * 24 * 7 );
			}

			// Allow products & subscriptions to be purchased in the same transaction
			update_option( 'woocommerce_subscriptions_multiple_purchase', 'yes' );

		}

		// Keep track of site url to prevent duplicate payments from staging sites, first added in 1.3.8 & updated with 1.4.2 to work with WP Engine staging sites
		if ( '0' == self::$active_version || version_compare( self::$active_version, '1.4.2', '<' ) ) {
			WC_Subscriptions::set_duplicate_site_url_lock();
		}

		// Don't autoload cron locks
		if ( '0' != self::$active_version && version_compare( self::$active_version, '1.4.3', '<' ) ) {
			$wpdb->query(
				"UPDATE $wpdb->options
				SET autoload = 'no'
				WHERE option_name LIKE 'wcs_blocker_%'"
			);
		}

		// Add support for quantities  & migrate wp_cron schedules to the new action-scheduler system.
		if ( '0' != self::$active_version && version_compare( self::$active_version, '1.5', '<' ) ) {
			self::upgrade_to_version_1_5();
		}

		self::upgrade_complete();
	}

	/**
	 * When an upgrade is complete, set the active version, delete the transient locking upgrade and fire a hook.
	 *
	 * @since 1.2
	 */
	public static function upgrade_complete() {

		update_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', WC_Subscriptions::$version );

		do_action( 'woocommerce_subscriptions_upgraded', WC_Subscriptions::$version );
	}

	/**
	 * Add support for quantities for subscriptions.
	 * Update all current subscription wp_cron tasks to the new action-scheduler system.
	 *
	 * @since 1.5
	 */
	private static function upgrade_to_version_1_5() {

		$_GET['wcs_upgrade_step'] = ( ! isset( $_GET['wcs_upgrade_step'] ) ) ? 0 : $_GET['wcs_upgrade_step'];

		switch ( (int)$_GET['wcs_upgrade_step'] ) {
			case 1:
				self::display_database_upgrade_helper();
				break;
			case 3: // keep a way to circumvent the upgrade routine just in case
				self::upgrade_complete();
				wp_safe_redirect( self::$about_page_url );
				break;
			case 0:
			default:
				wp_safe_redirect( admin_url( 'admin.php?wcs_upgrade_step=1' ) );
				break;
		}

		exit();
	}

	/**
	 * Move scheduled subscription hooks out of wp-cron and into the new Action Scheduler.
	 *
	 * Also set all existing subscriptions to "sold individually" to maintain previous behavior
	 * for existing subscription products before the subscription quantities feature was enabled..
	 *
	 * @since 1.5
	 */
	public static function ajax_upgrade() {
		global $wpdb;

		@set_time_limit( 600 );
		@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );

		set_transient( 'wc_subscriptions_is_upgrading', 'true', 60 * 2 );

		switch ( $_POST['upgrade_step'] ) {

			case 'really_old_version':
				$upgraded_versions = self::upgrade_really_old_versions();
				$results = array(
					'message' => sprintf( __( 'Database updated to version %s', 'woocommerce-subscriptions' ), $upgraded_versions ),
				);
				break;

			case 'products':

				require_once( 'class-wcs-upgrade-1-5.php' );

				$upgraded_product_count = WCS_Upgrade_1_5::upgrade_products();
				$results = array(
					'message' => sprintf( __( 'Marked %s subscription products as "sold individually".', 'woocommerce-subscriptions' ), $upgraded_product_count ),
				);
				break;

			case 'hooks':

				require_once( 'class-wcs-upgrade-1-5.php' );

				$upgraded_hook_count = WCS_Upgrade_1_5::upgrade_hooks( self::$upgrade_limit_hooks );
				$results = array(
					'upgraded_count' => $upgraded_hook_count,
					'message'        => sprintf( __( 'Migrated %s subscription related hooks to the new scheduler (in {execution_time} seconds).', 'woocommerce-subscriptions' ), $upgraded_hook_count ),
				);
				break;
		}

		if ( isset( $upgraded_subscriptions ) && $upgraded_subscriptions < self::$upgrade_limit_subscriptions ) {
			self::upgrade_complete();
		}

		delete_transient( 'wc_subscriptions_is_upgrading' );

		WCS_Upgrade_Logger::add( sprintf( 'Completed upgrade step: %s', $_POST['upgrade_step'] ) );

		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( $results );
		exit();
	}

	/**
	 * Handle upgrades for really old versions.
	 *
	 * @since 2.0
	 */
	private static function upgrade_really_old_versions() {

		if ( '0' != self::$active_version && version_compare( self::$active_version, '1.2', '<' ) ) {
			include_once( 'class-wcs-upgrade-1-2.php' );
			self::generate_renewal_orders();
			update_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', '1.2' );
			$upgraded_versions = '1.2, ';
		}

		// Add Variable Subscription product type term
		if ( '0' != self::$active_version && version_compare( self::$active_version, '1.3', '<' ) ) {
			include_once( 'class-wcs-upgrade-1-3.php' );
			update_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', '1.3' );
			$upgraded_versions .= '1.3 & ';
		}

		// Moving subscription meta out of user meta and into item meta
		if ( '0' != self::$active_version && version_compare( self::$active_version, '1.4', '<' ) ) {
			include_once( 'class-wcs-upgrade-1-4.php' );
			update_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', '1.4' );
			$upgraded_versions .= '1.4.';
		}

		return $upgraded_versions;
	}

	/**
	 * Version 1.2 introduced child renewal orders to keep a record of each completed subscription
	 * payment. Before 1.2, these orders did not exist, so this function creates them.
	 *
	 * @since 1.2
	 */
	private static function generate_renewal_orders() {
		global $wpdb;
		$woocommerce = WC();

		$subscriptions_grouped_by_user = WC_Subscriptions_Manager::get_all_users_subscriptions();

		// Don't send any order emails
		$email_actions = array( 'woocommerce_low_stock', 'woocommerce_no_stock', 'woocommerce_product_on_backorder', 'woocommerce_order_status_pending_to_processing', 'woocommerce_order_status_pending_to_completed', 'woocommerce_order_status_pending_to_on-hold', 'woocommerce_order_status_failed_to_processing', 'woocommerce_order_status_failed_to_completed', 'woocommerce_order_status_pending_to_processing', 'woocommerce_order_status_pending_to_on-hold', 'woocommerce_order_status_completed', 'woocommerce_new_customer_note' );
		foreach ( $email_actions as $action ) {
			remove_action( $action, array( &$woocommerce, 'send_transactional_email') );
		}

		remove_action( 'woocommerce_payment_complete', 'WC_Subscriptions_Renewal_Order::maybe_record_renewal_order_payment', 10, 1 );

		foreach ( $subscriptions_grouped_by_user as $user_id => $users_subscriptions ) {
			foreach ( $users_subscriptions as $subscription_key => $subscription ) {
				$order_post = get_post( $subscription['order_id'] );

				if ( isset( $subscription['completed_payments'] ) && count( $subscription['completed_payments'] ) > 0 && null != $order_post ) {
					foreach ( $subscription['completed_payments'] as $payment_date ) {

						$existing_renewal_order = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_date_gmt = %s AND post_parent = %d AND post_type = 'shop_order'", $payment_date, $subscription['order_id'] ) );

						// If a renewal order exists on this date, don't generate another one
						if ( null !== $existing_renewal_order ) {
							continue;
						}

						$renewal_order_id = WC_Subscriptions_Renewal_Order::generate_renewal_order( $subscription['order_id'], $subscription['product_id'], array( 'new_order_role' => 'child' ) );

						if ( $renewal_order_id ) {

							// Mark the order as paid
							$renewal_order = new WC_Order( $renewal_order_id );

							$renewal_order->payment_complete();

							// Avoid creating 100s "processing" orders
							$renewal_order->update_status( 'completed' );

							// Set correct dates on the order
							$renewal_order = array(
								'ID'            => $renewal_order_id,
								'post_date'     => $payment_date,
								'post_date_gmt' => $payment_date,
							);
							wp_update_post( $renewal_order );

							update_post_meta( $renewal_order_id, '_paid_date', $payment_date );
							update_post_meta( $renewal_order_id, '_completed_date', $payment_date );

						}
					}
				}
			}
		}
	}

	/**
	 * Let the site administrator know we are upgrading the database and provide a confirmation is complete.
	 *
	 * This is important to avoid the possibility of a database not upgrading correctly, but the site continuing
	 * to function without any remedy.
	 *
	 * @since 1.2
	 */
	public static function display_database_upgrade_helper() {

		wp_register_style( 'wcs-upgrade', plugins_url( '/css/wcs-upgrade.css', WC_Subscriptions::$plugin_file ) );
		wp_register_script( 'wcs-upgrade', plugins_url( '/js/wcs-upgrade.js', WC_Subscriptions::$plugin_file ), 'jquery' );

		$script_data = array(
			'really_old_version' => ( version_compare( self::$active_version, '1.4', '<' ) ) ? 'true' : 'false',
			'hooks_per_request' => self::$upgrade_limit_hooks,
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		);

		wp_localize_script( 'wcs-upgrade', 'wcs_update_script_data', $script_data );

		// Can't get subscription count with database structure < 1.4
		if ( 'false' == $script_data['really_old_version'] ) {
			$subscription_count = self::get_total_subscription_count();
			$estimated_duration = ceil( $subscription_count / 500 );
		}

		$about_page_url = self::$about_page_url;

		@header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
		include_once( 'templates/wcs-upgrade.php' );
	}

	/**
	 * Let the site administrator know we are upgrading the database already to prevent duplicate processes running the
	 * upgrade. Also provides some useful diagnostic information, like how long before the site admin can restart the
	 * upgrade process, and how many subscriptions per request can typically be updated given the amount of memory
	 * allocated to PHP.
	 *
	 * @since 1.4
	 */
	public static function upgrade_in_progress_notice() {
		include_once( 'templates/wcs-upgrade-in-progress.php' );
	}

	/**
	 * Display the Subscriptions welcome/about page after successfully upgrading to the latest version.
	 *
	 * @since 1.4
	 */
	public static function updated_welcome_page() {
		$about_page = add_dashboard_page( __( 'Welcome to WooCommerce Subscriptions 1.5', 'woocommerce-subscriptions' ), __( 'About WooCommerce Subscriptions', 'woocommerce-subscriptions' ), 'manage_options', 'wcs-about', __CLASS__ . '::about_screen' );
		add_action( 'admin_print_styles-'. $about_page, __CLASS__ . '::admin_css' );
		add_action( 'admin_head',  __CLASS__ . '::admin_head' );
	}

	/**
	 * admin_css function.
	 *
	 * @access public
	 * @return void
	 */
	public static function admin_css() {
		wp_enqueue_style( 'woocommerce-subscriptions-about', plugins_url( '/css/about.css', WC_Subscriptions::$plugin_file ), array(), self::$active_version );
	}

	/**
	 * Add styles just for this page, and remove dashboard page links.
	 *
	 * @access public
	 * @return void
	 */
	public static function admin_head() {
		remove_submenu_page( 'index.php', 'wcs-about' );
	}

	/**
	 * Output the about screen.
	 */
	public static function about_screen() {
		$active_version = self::$active_version;
		include_once( 'templates/wcs-about.php' );
	}

	/**
	 * In v2.0 and newer, it's possible to simply use wp_count_posts( 'shop_subscription' ) to count subscriptions,
	 * but not in v1.5, because a subscription data is still stored in order item meta. This function queries the
	 * v1.5 database structure.
	 *
	 * @since 2.0
	 */
	private static function get_total_subscription_count() {
		global $wpdb;

		$query = "SELECT meta.order_item_id FROM `{$wpdb->prefix}woocommerce_order_itemmeta` AS meta
				  WHERE meta.meta_key = '_subscription_status'
				  AND meta.meta_value <> 'trash'
				  GROUP BY meta.order_item_id";

		$wpdb->get_results( $query );

		return $wpdb->num_rows;
	}

	/**
	 * Used to check if a user ID is greater than the last user upgraded to version 1.4.
	 *
	 * Needs to be a separate function so that it can use a static variable (and therefore avoid calling get_option() thousands
	 * of times when iterating over thousands of users).
	 *
	 * @since 1.4
	 */
	public static function is_user_upgraded_to_1_4( $user_id ) {
		_deprecated_function( __METHOD__, '2.0', 'WCS_Upgrade_1_4::is_user_upgraded( $user_id )' );
		return WCS_Upgrade_1_4::is_user_upgraded( $user_id );
	}
}
add_action( 'after_setup_theme', 'WC_Subscriptions_Upgrader::init', 11 );
