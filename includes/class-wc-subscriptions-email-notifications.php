<?php

/**
 * Subscriptions Email Notifications Class
 *
 * Some details to enlighten your exploration of this code.
 *
 * @package    WooCommerce Subscriptions
 * @subpackage WC_Subscriptions_Email
 * @category   Class
 */
class WC_Subscriptions_Email_Notifications {

	public static string $offset_setting_string = '_customer_notifications_offset';

	public static function init() {

		add_action( 'woocommerce_email_classes', __CLASS__ . '::add_emails', 10, 1 );

		add_action( 'woocommerce_init', __CLASS__ . '::hook_notification_emails' );

		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'add_notification_actions' ), 10, 1 );

		// TODO this is a bit ugly...
		add_action(
			'woocommerce_order_action_wcs_customer_notification_free_trial_expiration',
			function ( $order ) {
				/**
				 * Send Trial expiration notification to the customer.
				 *
				 * @since 8.0.0
				 *
				 * @param int $subscription_id
				 */
				do_action( 'woocommerce_scheduled_subscription_customer_notification_trial_expiration', $order->get_id() );
			},
			10,
			1
		);
		add_action(
			'woocommerce_order_action_wcs_customer_notification_subscription_expiration',
			function ( $order ) {
				/**
				 * Send Subscription expiration notification to the customer.
				 *
				 * @since 8.0.0
				 *
				 * @param int $subscription_id
				 */
				do_action( 'woocommerce_scheduled_subscription_customer_notification_expiration', $order->get_id() );
			},
			10,
			1
		);
		add_action(
			'woocommerce_order_action_wcs_customer_notification_manual_renewal',
			function ( $order ) {
				/**
				 * Send Manual renewal notification to the customer.
				 *
				 * @since 8.0.0
				 *
				 * @param int $subscription_id
				 */
				do_action( 'woocommerce_scheduled_subscription_customer_notification_manual_renewal', $order->get_id() );
			},
			10,
			1
		);
		add_action(
			'woocommerce_order_action_wcs_customer_notification_auto_renewal',
			function ( $order ) {
				/**
				 * Send Automatic Renewal notification to the customer.
				 *
				 * @since 8.0.0
				 *
				 * @param int $subscription_id
				 */
				do_action( 'woocommerce_scheduled_subscription_customer_notification_auto_renewal', $order->get_id() );
			},
			10,
			1
		);

		add_filter( 'woocommerce_subscription_settings', array( __CLASS__, 'add_settings' ), 20 );
	}

	/**
	 * Add Subscriptions notifications' email classes.
	 */
	public static function add_emails( $email_classes ) {

		// Customer notifications.
		$email_classes['WCS_Email_Customer_Notification_Free_Trial_Expiration']   = new WCS_Email_Customer_Notification_Free_Trial_Expiration();
		$email_classes['WCS_Email_Customer_Notification_Subscription_Expiration'] = new WCS_Email_Customer_Notification_Subscription_Expiration();
		$email_classes['WCS_Email_Customer_Notification_Manual_Renewal']          = new WCS_Email_Customer_Notification_Manual_Renewal();
		$email_classes['WCS_Email_Customer_Notification_Auto_Renewal']            = new WCS_Email_Customer_Notification_Auto_Renewal();

		return $email_classes;
	}

	public static function hook_notification_emails() {
		add_action( 'woocommerce_scheduled_subscription_customer_notification_auto_renewal', array( __CLASS__, 'send_notification' ) );
		add_action( 'woocommerce_scheduled_subscription_customer_notification_manual_renewal', array( __CLASS__, 'send_notification' ) );
		add_action( 'woocommerce_scheduled_subscription_customer_notification_trial_expiration', array( __CLASS__, 'send_notification' ) );
		add_action( 'woocommerce_scheduled_subscription_customer_notification_expiration', array( __CLASS__, 'send_notification' ) );
	}

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
			case 'woocommerce_scheduled_subscription_customer_notification_auto_renewal':
				$notification = $emails['WCS_Email_Customer_Notification_Auto_Renewal'];
				break;
			case 'woocommerce_scheduled_subscription_customer_notification_manual_renewal':
				$notification = $emails['WCS_Email_Customer_Notification_Manual_Renewal'];
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
	 * Should the emails be sent out?
	 *
	 * @return bool
	 */
	public static function should_send_notification() {
		$notification_enabled = true;

		if ( WCS_Staging::is_duplicate_site() ) {
			$notification_enabled = false;
		}

		$allowed_env_types = array(
			'production',
		);
		if ( ! in_array( wp_get_environment_type(), $allowed_env_types, true ) ) {
			$notification_enabled = false;
		}

		/**
		 * Enables/disables all customer subscription notifications.
		 *
		 * Values 'yes' or 'no' expected, since it works with WC_Settings_API.
		 *
		 * @since 8.0.0
		 *
		 * @param string $notification_enabled
		 */
		return apply_filters( 'wcs_customer_email_notifications_enabled', $notification_enabled );
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
			$subscription = $theorder;
			//TODO: confirm if these statuses make sense.
			$allowed_statuses = array(
				'active',
				'on-hold',
				'pending-cancellation',
			);

			if ( ! in_array( $subscription->get_status(), $allowed_statuses, true ) ) {
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
					$actions['wcs_customer_notification_manual_renewal'] = esc_html__( 'Send Manual Renewal notification', 'woocommerce-subscriptions' );
				} else {
					$actions['wcs_customer_notification_auto_renewal'] = esc_html__( 'Send Automatic Renewal notification', 'woocommerce-subscriptions' );
				}
			}
		}

		return $actions;
	}

	/**
	 * Adds the subscription notification setting.
	 *
	 * @since 8.0.0
	 *
	 * @param  array $settings Subscriptions settings.
	 * @return array Subscriptions settings.
	 */
	public static function add_settings( $settings ) {
		$notification_settings = array(
			array(
				'name' => __( 'Customer Notifications', 'woocommerce-subscriptions' ),
				'type' => 'title',
				'id'   => WC_Subscriptions_Admin::$option_prefix . '_customer_notifications',
				/* translators: Link to WC Settings > Email. */
				'desc' => sprintf( __( 'To enable and disable individual notifications, visit the <a href="%s">Email settings</a>.', 'woocommerce-subscriptions' ), admin_url( 'admin.php?page=wc-settings&tab=email' ) ),
			),
			array(
				'name'        => __( 'Time Offset', 'woocommerce-subscriptions' ),
				'desc'        => __( 'How long before the event should the notification be sent.', 'woocommerce-subscriptions' ),
				'tip'         => '',
				'id'          => WC_Subscriptions_Admin::$option_prefix . self::$offset_setting_string,
				'desc_tip'    => true,
				'type'        => 'relative_date_selector',
				'placeholder' => __( 'N/A', 'woocommerce-subscriptions' ),
				'default'     => array(
					'number' => '3',
					'unit'   => 'days',
				),
				'autoload'    => false,
			),
			array(
				'type' => 'sectionend',
				'id'   => WC_Subscriptions_Admin::$option_prefix . '_customer_notifications',
			),
		);

		WC_Subscriptions_Admin::insert_setting_after( $settings, WC_Subscriptions_Admin::$option_prefix . '_miscellaneous', $notification_settings, 'multiple_settings', 'sectionend' );
		return $settings;
	}
}
