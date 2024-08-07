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
	public static function init() {

		add_action( 'woocommerce_email_classes', __CLASS__ . '::add_emails', 10, 1 );

		add_action( 'woocommerce_init', __CLASS__ . '::hook_notification_emails' );

		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'add_notification_actions' ), 10, 1 );

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
	 * @return string yes|no
	 */
	public static function should_send_notification() {
		$notification_enabled = 'yes';

		if ( WCS_Staging::is_duplicate_site() ) {
			$notification_enabled = 'no';
		}

		$allowed_env_types = array(
			'production',
		);
		if ( ! in_array( wp_get_environment_type(), $allowed_env_types, true ) ) {
			$notification_enabled = 'no';
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
	 * Adds actions to the admin edit subscriptions page, if the subscription hasn't ended and the payment method supports them.
	 *
	 * @param array $actions An array of available actions
	 * @return array An array of updated actions
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public static function add_notification_actions( $actions ) {
		global $theorder;

		if ( wcs_is_subscription( $theorder ) ) {
			//TODO maybe send only for active, on hold subscriptions?
			//
			$actions['wcs_customer_notification_free_trial_expiration']   = esc_html__( 'Send Free Trial Expiration notification', 'woocommerce-subscriptions' );
			$actions['wcs_customer_notification_subscription_expiration'] = esc_html__( 'Send Subscription Expiration notification', 'woocommerce-subscriptions' );
			$actions['wcs_customer_notification_manual_renewal']          = esc_html__( 'Send Manual Renewal notification', 'woocommerce-subscriptions' );
			$actions['wcs_customer_notification_auto_renewal']            = esc_html__( 'Send Automatic Renewal notification', 'woocommerce-subscriptions' );
		}

		return $actions;
	}
}
