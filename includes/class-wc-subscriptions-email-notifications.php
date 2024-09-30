<?php

/**
 * Subscriptions Email Notifications Class
 *
 *
 * @package    WooCommerce Subscriptions
 * @subpackage WC_Subscriptions_Email
 * @category   Class
 */
class WC_Subscriptions_Email_Notifications {

	/**
	 * @var string Offset setting option identifier.
	 */
	public static $offset_setting_string = '_customer_notifications_offset';

	/**
	 * @var string Enabled/disabled setting option identifier.
	 */
	public static $switch_setting_string = '_customer_notifications_enabled';

	/**
	 * Init.
	 */
	public static function init() {

		add_action( 'woocommerce_email_classes', __CLASS__ . '::add_emails', 10, 1 );

		add_action( 'woocommerce_init', __CLASS__ . '::hook_notification_emails' );

		// Add notification actions to the admin edit subscriptions page.
		add_filter( 'woocommerce_order_actions', [ __CLASS__, 'add_notification_actions' ], 10, 1 );

		// Trigger actions from Edit order screen.
		add_action( 'woocommerce_order_action_wcs_customer_notification_free_trial_expiration', [ __CLASS__, 'forward_action' ], 10, 1 );
		add_action( 'woocommerce_order_action_wcs_customer_notification_subscription_expiration', [ __CLASS__, 'forward_action' ], 10, 1 );
		add_action( 'woocommerce_order_action_wcs_customer_notification_renewal', [ __CLASS__, 'forward_action' ], 10, 1 );

		// Add settings UI.
		add_filter( 'woocommerce_subscription_settings', [ __CLASS__, 'add_settings' ], 20 );

		add_action( 'update_option_' . WC_Subscriptions_Admin::$option_prefix . self::$offset_setting_string, [ 'WC_Subscriptions_Email_Notifications', 'set_notification_settings_update_time' ] );
		add_action( 'update_option_' . WC_Subscriptions_Admin::$option_prefix . self::$switch_setting_string, [ 'WC_Subscriptions_Email_Notifications', 'set_notification_settings_update_time' ] );
		add_action( 'add_option_' . WC_Subscriptions_Admin::$option_prefix . self::$offset_setting_string, [ 'WC_Subscriptions_Email_Notifications', 'set_notification_settings_update_time' ] );
		add_action( 'add_option_' . WC_Subscriptions_Admin::$option_prefix . self::$switch_setting_string, [ 'WC_Subscriptions_Email_Notifications', 'set_notification_settings_update_time' ] );
	}

	/**
	 * Map and forward Edit order screen action to the correct reminder.
	 *
	 * @param $order
	 *
	 * @return void
	 */
	public static function forward_action( $order ) {
		$trigger_action = '';
		$current_action = current_action();
		switch ( $current_action ) {
			case 'woocommerce_order_action_wcs_customer_notification_free_trial_expiration':
				$trigger_action = 'woocommerce_scheduled_subscription_customer_notification_trial_expiration';
				break;
			case 'woocommerce_order_action_wcs_customer_notification_subscription_expiration':
				$trigger_action = 'woocommerce_scheduled_subscription_customer_notification_expiration';
				break;
			case 'woocommerce_order_action_wcs_customer_notification_renewal':
				$trigger_action = 'woocommerce_scheduled_subscription_customer_notification_renewal';
				break;
		}

		if ( $trigger_action ) {
			do_action( $trigger_action, $order->get_id() );
		}
	}

	/**
	 * Sets the update time when any of the settings that affect notifications change and triggers update of subscriptions.
	 *
	 * When time offset or global on/off switch change values, this method gets triggered and it:
	 * 1. Updates the wcs_notification_settings_update_time option so that the code knows which subscriptions to update
	 * 2. Triggers rescheduling/unscheduling of existing notifications.
	 * 3. Adds a notice with info about the actions that got triggered to the store manager.
	 *
	 * @return void
	 */
	public static function set_notification_settings_update_time() {
		update_option( 'wcs_notification_settings_update_time', time() );

		// Shortcut to unschedule all notifications more efficiently instead of processing them subscription by subscription.
		if ( ! self::notifications_globally_enabled() ) {
			\WC_Subscriptions_Core_Plugin::instance()->notifications_scheduler->unschedule_all_notifications();

			$message = __( 'Unscheduling all notifications now.', 'woocommerce-subscriptions' );
		} else {
			$message = WCS_Notifications_Batch_Processor::enqueue();
		}

		wc_add_notice( $message, 'notice' );
	}

	/**
	 * Add Subscriptions notifications' email classes.
	 */
	public static function add_emails( $email_classes ) {

		$email_classes['WCS_Email_Customer_Notification_Free_Trial_Expiration']   = new WCS_Email_Customer_Notification_Free_Trial_Expiration();
		$email_classes['WCS_Email_Customer_Notification_Subscription_Expiration'] = new WCS_Email_Customer_Notification_Subscription_Expiration();
		$email_classes['WCS_Email_Customer_Notification_Manual_Renewal']          = new WCS_Email_Customer_Notification_Manual_Renewal();
		$email_classes['WCS_Email_Customer_Notification_Auto_Renewal']            = new WCS_Email_Customer_Notification_Auto_Renewal();

		return $email_classes;
	}

	/**
	 * Hook the notification emails with our custom trigger.
	 */
	public static function hook_notification_emails() {
		add_action( 'woocommerce_scheduled_subscription_customer_notification_renewal', [ __CLASS__, 'send_notification' ] );
		add_action( 'woocommerce_scheduled_subscription_customer_notification_trial_expiration', [ __CLASS__, 'send_notification' ] );
		add_action( 'woocommerce_scheduled_subscription_customer_notification_expiration', [ __CLASS__, 'send_notification' ] );
	}

	/**
	 * Send the notification emails.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public static function send_notification( $subscription_id ) {

		// Init email classes.
		$emails = WC()->mailer()->get_emails();

		if ( ! ( $emails['WCS_Email_Customer_Notification_Auto_Renewal'] instanceof WCS_Email_Customer_Notification_Auto_Renewal
				&& $emails['WCS_Email_Customer_Notification_Manual_Renewal'] instanceof WCS_Email_Customer_Notification_Manual_Renewal
				&& $emails['WCS_Email_Customer_Notification_Subscription_Expiration'] instanceof WCS_Email_Customer_Notification_Subscription_Expiration
				&& $emails['WCS_Email_Customer_Notification_Free_Trial_Expiration'] instanceof WCS_Email_Customer_Notification_Free_Trial_Expiration
			)
		) {
			return;
		}
		$notification = null;
		switch ( current_action() ) {
			case 'woocommerce_scheduled_subscription_customer_notification_renewal':
				$subscription = wcs_get_subscription( $subscription_id );
				if ( $subscription->is_manual() ) {
					$notification = $emails['WCS_Email_Customer_Notification_Manual_Renewal'];
				} else {
					$notification = $emails['WCS_Email_Customer_Notification_Auto_Renewal'];
				}
				break;
			case 'woocommerce_scheduled_subscription_customer_notification_trial_expiration':
				$notification = $emails['WCS_Email_Customer_Notification_Free_Trial_Expiration'];
				break;
			case 'woocommerce_scheduled_subscription_customer_notification_expiration':
				$notification = $emails['WCS_Email_Customer_Notification_Subscription_Expiration'];
				break;
		}

		if ( $notification ) {
			$notification->trigger( $subscription_id );
		}
	}

	/**
	 * Is the notifications feature enabled?
	 *
	 * @return bool
	 */
	public static function notifications_globally_enabled() {
		return ( 'yes' === get_option( WC_Subscriptions_Admin::$option_prefix . self::$switch_setting_string )
				&& get_option( WC_Subscriptions_Admin::$option_prefix . self::$offset_setting_string ) );
	}

	/**
	 * Should the emails be sent out?
	 *
	 * @return bool
	 */
	public static function should_send_notification() {
		$notification_enabled = true;

		if ( WCS_Staging::is_duplicate_site() ) {
			$notification_enabled = false;
		}

		$allowed_env_types = [
			'production',
		];
		if ( ! in_array( wp_get_environment_type(), $allowed_env_types, true ) ) {
			$notification_enabled = false;
		}

		// If Customer notifications are disabled in the settings by a global switch, or there is no offset set, don't send notifications.
		if ( ! self::notifications_globally_enabled() ) {
			$notification_enabled = false;
		}

		/**
		 * Enables/disables all customer subscription notifications.
		 *
		 * Values 'yes' or 'no' expected, since it works with WC_Settings_API.
		 *
		 * @since x.x.x
		 *
		 * @param string $notification_enabled
		 */
		return apply_filters( 'wcs_customer_email_notifications_enabled', $notification_enabled );
	}

	/**
	 * Check if the subscription period is too short to send a renewal notification.
	 *
	 * @param $subscription
	 * @return bool
	 */
	public static function subscription_period_too_short( $subscription ) {
		$period   = $subscription->get_billing_period();
		$interval = $subscription->get_billing_interval();

		// By default, there are no shorter periods than days in WCS, so we ignore hours, minutes, etc.
		if ( $interval <= 2 && 'day' === $period ) {
			return true;
		}

		return false;
	}

	/**
	 * Adds actions to the admin edit subscriptions page.
	 *
	 * @param array $actions An array of available actions
	 * @return array An array of updated actions
	 */
	public static function add_notification_actions( $actions ) {
		global $theorder;

		if ( wcs_is_subscription( $theorder ) ) {
			$subscription     = $theorder;
			$allowed_statuses = [
				'active',
				'on-hold',
				'pending-cancel',
			];

			if ( ! $subscription->has_status( $allowed_statuses ) ) {
				return $actions;
			}

			if ( $subscription->get_date( 'trial_end' ) ) {
				$actions['wcs_customer_notification_free_trial_expiration'] = esc_html__( 'Send Free Trial Expiration notification', 'woocommerce-subscriptions' );
			}

			if ( $subscription->get_date( 'end' ) ) {
				$actions['wcs_customer_notification_subscription_expiration'] = esc_html__( 'Send Subscription Expiration notification', 'woocommerce-subscriptions' );
			}

			if ( $subscription->get_date( 'next_payment' ) ) {
				if ( $subscription->is_manual() ) {
					$actions['wcs_customer_notification_renewal'] = esc_html__( 'Send Manual Renewal notification', 'woocommerce-subscriptions' );
				} else {
					$actions['wcs_customer_notification_renewal'] = esc_html__( 'Send Automatic Renewal notification', 'woocommerce-subscriptions' );
				}
			}
		}

		return $actions;
	}

	/**
	 * Adds the subscription notification setting.
	 *
	 * @param  array $settings Subscriptions settings.
	 * @return array Subscriptions settings.
	 */
	public static function add_settings( $settings ) {
		$notification_settings = [
			[
				'name' => __( 'Customer Notifications', 'woocommerce-subscriptions' ),
				'type' => 'title',
				'id'   => WC_Subscriptions_Admin::$option_prefix . '_customer_notifications',
				/* translators: Link to WC Settings > Email. */
				'desc' => sprintf( __( 'To enable and disable individual notifications, visit the <a href="%s">Email settings</a>.', 'woocommerce-subscriptions' ), admin_url( 'admin.php?page=wc-settings&tab=email' ) ),
			],
			[
				'name'     => __( 'Enable Renewal Reminders', 'woocommerce-subscriptions' ),
				'desc'     => __( 'Enable customer renewal reminder notification emails.', 'woocommerce-subscriptions' ),
				'tip'      => '',
				'id'       => WC_Subscriptions_Admin::$option_prefix . self::$switch_setting_string,
				'desc_tip' => false,
				'type'     => 'checkbox',
				'default'  => 'no',
				'autoload' => false,
			],
			[
				'name'        => __( 'Renewal Reminder Timing', 'woocommerce-subscriptions' ),
				'desc'        => __( 'How long before the event should the notification be sent.', 'woocommerce-subscriptions' ),
				'tip'         => '',
				'id'          => WC_Subscriptions_Admin::$option_prefix . self::$offset_setting_string,
				'desc_tip'    => true,
				'type'        => 'relative_date_selector',
				'placeholder' => __( 'N/A', 'woocommerce-subscriptions' ),
				'default'     => [
					'number' => '3',
					'unit'   => 'days',
				],
				'autoload'    => false,
			],
			[
				'type' => 'sectionend',
				'id'   => WC_Subscriptions_Admin::$option_prefix . '_customer_notifications',
			],
		];

		WC_Subscriptions_Admin::insert_setting_after( $settings, WC_Subscriptions_Admin::$option_prefix . '_miscellaneous', $notification_settings, 'multiple_settings', 'sectionend' );
		return $settings;
	}
}
