<?php
/**
 * A timeout resistant, single-serve upgrader for WC Subscriptions.
 *
 * This class is used to make all reasonable attempts to neatly upgrade data between versions of Subscriptions.
 *
 * For example, the subscription meta data associated with an order significantly changed between 1.1.n and 1.2.
 * It was imperative the data be upgraded to the new schema without hassle. A hassle could easily occur if 100,000
 * orders were being modified - memory exhaustion, script time out etc.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_Subscriptions_Checkout
 * @category	Class
 * @author		Brent Shepherd
 * @since		1.2
 */
class WC_Subscriptions_Upgrader {

	private static $active_version;

	private static $upgrade_limit;

	private static $about_page_url;

	private static $last_upgraded_user_id = false;

	/**
	 * Hooks upgrade function to init.
	 *
	 * @since 1.2
	 */
	public static function init() {

		self::$active_version = get_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', '0' );

		self::$upgrade_limit = apply_filters( 'woocommerce_subscriptions_hooks_to_upgrade', 250 );

		self::$about_page_url = admin_url( 'index.php?page=wcs-about&wcs-updated=true' );

		if ( isset( $_POST['action'] ) && 'wcs_upgrade' == $_POST['action'] ) {

			add_action( 'wp_ajax_wcs_upgrade', __CLASS__ . '::ajax_upgrade', 10 );

		} elseif ( @current_user_can( 'activate_plugins' ) ) {

			if ( 'true' == get_transient( 'wc_subscriptions_is_upgrading' ) ) {

				self::upgrade_in_progress_notice();

			} elseif ( isset( $_GET['wcs_upgrade_step'] ) || version_compare( self::$active_version, WC_Subscriptions::$version, '<' ) ) {

				// Run updates as soon as admin hits site
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

		// Update to new system to limit subscriptions by status rather than in a binary way
		if ( '0' != self::$active_version && version_compare( self::$active_version, '1.5.4', '<' ) ) {
			$wpdb->query(
				"UPDATE $wpdb->postmeta
				SET meta_value = 'any'
				WHERE meta_key LIKE '_subscription_limit'
				AND meta_value LIKE 'yes'"
			);
		}

		self::upgrade_complete();
	}

	/**
	 * When an upgrade is complete, set the active version, delete the transient locking upgrade and fire a hook.
	 *
	 * @since 1.2
	 */
	public static function upgrade_complete() {
		// Set the new version now that all upgrade routines have completed
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

		if ( 'really_old_version' == $_POST['upgrade_step'] ) {

			$database_updates = '';

			if ( '0' != self::$active_version && version_compare( self::$active_version, '1.2', '<' ) ) {
				self::upgrade_database_to_1_2();
				self::generate_renewal_orders();
				update_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', '1.2' );
				$database_updates = '1.2, ';
			}

			// Add Variable Subscription product type term
			if ( '0' != self::$active_version && version_compare( self::$active_version, '1.3', '<' ) ) {
				self::upgrade_database_to_1_3();
				update_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', '1.3' );
				$database_updates .= '1.3 & ';
			}

			// Moving subscription meta out of user meta and into item meta
			if ( '0' != self::$active_version && version_compare( self::$active_version, '1.4', '<' ) ) {
				self::upgrade_database_to_1_4();
				update_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', '1.4' );
				$database_updates .= '1.4.';
			}

			$results = array(
				'message' => sprintf( __( 'Database updated to version %s', 'woocommerce-subscriptions' ), $database_updates )
			);

		} elseif ( 'products' == $_POST['upgrade_step'] ) {

			// Set status to 'sold individually' for all existing subscriptions that haven't already been updated
			$sql = "SELECT DISTINCT ID FROM {$wpdb->posts} as posts
				JOIN {$wpdb->postmeta} as postmeta
					ON posts.ID = postmeta.post_id
					AND (postmeta.meta_key LIKE '_subscription%')
				JOIN  {$wpdb->postmeta} AS soldindividually
					ON posts.ID = soldindividually.post_id
					AND ( soldindividually.meta_key LIKE '_sold_individually' AND soldindividually.meta_value !=  'yes' )
				WHERE posts.post_type = 'product'";

			$subscription_product_ids = $wpdb->get_results( $sql );

			foreach ( $subscription_product_ids as $product_id ) {
				update_post_meta( $product_id->ID, '_sold_individually', 'yes' );
			}

			$results = array(
				'message' => sprintf( __( 'Marked %s subscription products as "sold individually".', 'woocommerce-subscriptions' ), count( $subscription_product_ids ) )
			);

		} else {

			$counter  = 0;

			$before_cron_update = microtime( true );

			// update all of the current Subscription cron tasks to the new Action Scheduler
			$cron = _get_cron_array();

			foreach ( $cron as $timestamp => $actions ) {
				foreach ( $actions as $hook => $details ) {
					if ( 'scheduled_subscription_payment' == $hook || 'scheduled_subscription_expiration' == $hook || 'scheduled_subscription_end_of_prepaid_term' == $hook || 'scheduled_subscription_trial_end' == $hook || 'paypal_check_subscription_payment' == $hook ) {
						foreach ( $details as $hook_key => $values ) {

							if ( ! wc_next_scheduled_action( $hook, $values['args'] ) ) {
								wc_schedule_single_action( $timestamp, $hook, $values['args'] );
								unset( $cron[ $timestamp ][ $hook ][ $hook_key ] );
								$counter++;
							}

							if ( $counter >= self::$upgrade_limit ) {
								break;
							}
						}

						// If there are no other jobs scheduled for this hook at this timestamp, remove the entire hook
						if ( 0 == count( $cron[ $timestamp ][ $hook ] ) ) {
							unset( $cron[ $timestamp ][ $hook ] );
						}
						if ( $counter >= self::$upgrade_limit ) {
							break;
						}
					}
				}

				// If there are no actions schedued for this timestamp, remove the entire schedule
				if ( 0 == count( $cron[ $timestamp ] ) ) {
					unset( $cron[ $timestamp ] );
				}
				if ( $counter >= self::$upgrade_limit ) {
					break;
				}
			}

			// Set the cron with the removed schedule
			_set_cron_array( $cron );

			$results = array(
				'upgraded_count' => $counter,
				'message'        => sprintf( __( 'Migrated %s subscription related hooks to the new scheduler (in {execution_time} seconds).', 'woocommerce-subscriptions' ), $counter )
			);

		}

		if ( isset( $counter ) && $counter < self::$upgrade_limit ) {
			self::upgrade_complete();
		}

		delete_transient( 'wc_subscriptions_is_upgrading' );

		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( $results );
		exit();
	}

	/**
	 * Version 1.2 introduced a massive change to the order meta data schema. This function goes
	 * through and upgrades the existing data on all orders to the new schema.
	 *
	 * The upgrade process is timeout safe as it keeps a record of the orders upgraded and only
	 * deletes this record once all orders have been upgraded successfully. If operating on a huge
	 * number of orders and the upgrade process times out, only the orders not already upgraded
	 * will be upgraded in future requests that trigger this function.
	 *
	 * @since 1.2
	 */
	private static function upgrade_database_to_1_2() {
		include_once( 'upgrade-1-2.php' );
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
	 * Upgrade cron lock values to be options rather than transients to work around potential early deletion by W3TC
	 * and other caching plugins. Also add the Variable Subscription product type (if it doesn't exist).
	 *
	 * @since 1.3
	 */
	private static function upgrade_database_to_1_3() {
		global $wpdb;

		// Change transient timeout entries to be a vanilla option
		$wpdb->query( " UPDATE $wpdb->options
						SET option_name = TRIM(LEADING '_transient_timeout_' FROM option_name)
						WHERE option_name LIKE '_transient_timeout_wcs_blocker_%'" );

		// Change transient keys from the < 1.1.5 format to new format
		$wpdb->query( " UPDATE $wpdb->options
						SET option_name = CONCAT('wcs_blocker_', TRIM(LEADING '_transient_timeout_block_scheduled_subscription_payments_' FROM option_name))
						WHERE option_name LIKE '_transient_timeout_block_scheduled_subscription_payments_%'" );

		// Delete old transient values
		$wpdb->query( " DELETE FROM $wpdb->options
						WHERE option_name LIKE '_transient_wcs_blocker_%'
						OR option_name LIKE '_transient_block_scheduled_subscription_payments_%'" );

	}

	/**
	 * Version 1.4 moved subscription meta out of usermeta and into the new WC2.0 order item meta
	 * table.
	 *
	 * @since 1.4
	 */
	private static function upgrade_database_to_1_4() {
		global $wpdb;

		$subscriptions_meta_key = $wpdb->get_blog_prefix() . 'woocommerce_subscriptions';
		$order_items_table      = $wpdb->get_blog_prefix() . 'woocommerce_order_items';
		$order_item_meta_table  = $wpdb->get_blog_prefix() . 'woocommerce_order_itemmeta';

		// Get the IDs of all users who have a subscription
		$users_to_upgrade = get_users( array(
			'meta_key' => $subscriptions_meta_key,
			'fields'   => 'ID',
			'orderby'  => 'ID',
			)
		);

		$users_to_upgrade = array_filter( $users_to_upgrade, __CLASS__ . '::is_user_upgraded_to_1_4' );

		foreach ( $users_to_upgrade as $user_to_upgrade ) {

			// Can't use WC_Subscriptions_Manager::get_users_subscriptions() because it relies on the new structure
			$users_old_subscriptions = get_user_option( $subscriptions_meta_key, $user_to_upgrade );

			foreach ( $users_old_subscriptions as $subscription_key => $subscription ) {

				if ( ! isset( $subscription['order_id'] ) ) { // Subscription created incorrectly with v1.1.2
					continue;
				}

				$order_item_id = WC_Subscriptions_Order::get_item_id_by_subscription_key( $subscription_key );

				if ( empty( $order_item_id ) ) { // Subscription created incorrectly with v1.1.2
					continue;
				}

				if ( ! isset( $subscription['trial_expiry_date'] ) ) {
					$subscription['trial_expiry_date'] = '';
				}

				// Set defaults
				$failed_payments    = isset( $subscription['failed_payments'] ) ? $subscription['failed_payments'] : 0;
				$completed_payments = isset( $subscription['completed_payments'] ) ? $subscription['completed_payments'] : array();
				$suspension_count   = isset( $subscription['suspension_count'] ) ? $subscription['suspension_count'] : 0;
				$trial_expiry_date  = isset( $subscription['trial_expiry_date'] ) ? $subscription['trial_expiry_date'] : '';

				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO $order_item_meta_table (order_item_id, meta_key, meta_value)
						VALUES
						(%d,%s,%s),
						(%d,%s,%s),
						(%d,%s,%s),
						(%d,%s,%s),
						(%d,%s,%s),
						(%d,%s,%s),
						(%d,%s,%s),
						(%d,%s,%s)",
						$order_item_id, '_subscription_status', $subscription['status'],
						$order_item_id, '_subscription_start_date', $subscription['start_date'],
						$order_item_id, '_subscription_expiry_date', $subscription['expiry_date'],
						$order_item_id, '_subscription_end_date', $subscription['end_date'],
						$order_item_id, '_subscription_trial_expiry_date', $trial_expiry_date,
						$order_item_id, '_subscription_failed_payments', $failed_payments,
						$order_item_id, '_subscription_completed_payments', serialize( $completed_payments ),
						$order_item_id, '_subscription_suspension_count', $suspension_count
					)
				);

			}

			update_option( 'wcs_1_4_last_upgraded_user_id', $user_to_upgrade );
			self::$last_upgraded_user_id = $user_to_upgrade;

		}

		// Add an underscore prefix to usermeta key to deprecate, but not delete, subscriptions in user meta
		$wpdb->update(
			$wpdb->usermeta,
			array( 'meta_key' => '_' . $subscriptions_meta_key ),
			array( 'meta_key' => $subscriptions_meta_key )
		);

		// Now set the recurring shipping & payment method on all subscription orders
		$wpdb->query(
			"INSERT INTO $wpdb->postmeta (`post_id`, `meta_key`, `meta_value`)
			SELECT `post_id`, CONCAT('_recurring',`meta_key`), `meta_value`
			FROM $wpdb->postmeta
			WHERE `meta_key` IN ('_shipping_method','_shipping_method_title','_payment_method','_payment_method_title')
			AND `post_id` IN (
				SELECT `post_id` FROM $wpdb->postmeta WHERE `meta_key` = '_order_recurring_total'
			)"
		);

		// Set the recurring shipping total on all subscription orders
		$wpdb->query(
			"INSERT INTO $wpdb->postmeta (`post_id`, `meta_key`, `meta_value`)
			SELECT `post_id`, '_order_recurring_shipping_total', `meta_value`
			FROM $wpdb->postmeta
			WHERE `meta_key` = '_order_shipping'
			AND `post_id` IN (
				SELECT `post_id` FROM $wpdb->postmeta WHERE `meta_key` = '_order_recurring_total'
			)"
		);

		// Get the ID of all orders for a subscription with a free trial and no sign-up fee
		$order_ids = $wpdb->get_col(
			"SELECT order_items.order_id FROM $order_items_table AS order_items
				LEFT JOIN $order_item_meta_table AS itemmeta USING (order_item_id)
				LEFT JOIN $order_item_meta_table AS itemmeta2 USING (order_item_id)
			WHERE itemmeta.meta_key = '_subscription_trial_length'
			AND itemmeta.meta_value > 0
			AND itemmeta2.meta_key = '_subscription_sign_up_fee'
			AND itemmeta2.meta_value > 0"
		);

		$order_ids = implode( ',', $order_ids );

		// Now set the order totals to $0 (can't use $wpdb->update as it only allows joining WHERE clauses with AND)
		if ( ! empty ( $order_ids ) ) {
			$wpdb->query(
				"UPDATE $wpdb->postmeta
				SET `meta_value` = 0
				WHERE `meta_key` IN ( '_order_total', '_order_tax', '_order_shipping_tax', '_order_shipping', '_order_discount', '_cart_discount' )
				AND `post_id` IN ( $order_ids )"
			);

			// Now set the line totals to $0
			$wpdb->query(
				"UPDATE $order_item_meta_table
				 SET `meta_value` = 0
				 WHERE `meta_key` IN ( '_line_subtotal', '_line_subtotal_tax', '_line_total', '_line_tax', 'tax_amount', 'shipping_tax_amount' )
				 AND `order_item_id` IN (
					SELECT `order_item_id` FROM $order_items_table
					WHERE `order_item_type` IN ('tax','line_item')
					AND `order_id` IN ( $order_ids )
				)"
			);
		}

		update_option( 'wcs_1_4_upgraded_order_ids', explode( ',', $order_ids ) );
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

		if ( false === self::$last_upgraded_user_id ) {
			self::$last_upgraded_user_id = get_option( 'wcs_1_4_last_upgraded_user_id', 0 );
		}

		return ( $user_id > self::$last_upgraded_user_id ) ? true : false;
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
			$subscription_count = WC_Subscriptions::get_total_subscription_count();
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
}
add_action( 'after_setup_theme', 'WC_Subscriptions_Upgrader::init', 11 );
