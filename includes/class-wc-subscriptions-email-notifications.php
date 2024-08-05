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
}
