<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Customer Notification: Subscription Expiring email
 *
 * An email sent to the customer when a subscription is about to expire.
 *
 * @class WCS_Email_Customer_Notification_Subscription_Expiring
 * @version 1.0.0
 * @package WooCommerce_Subscriptions/Classes/Emails
 * @extends WC_Email
 */
class WCS_Email_Customer_Notification_Subscription_Expiration extends WCS_Email_Customer_Notification {

	/**
	 * Create an instance of the class.
	 */
	public function __construct() {
		$this->plugin_id = 'woocommerce-subscriptions_';

		$this->id          = 'customer_notification_subscription_expiry';
		$this->title       = __( 'Customer Notification: Subscription Is About To Expire', 'woocommerce-subscriptions' );
		$this->description = __( 'Subscription Expiting Notification emails are sent when a customer\'s subscription is about to expire.', 'woocommerce-subscriptions' );

		$this->heading = __( 'Subscription Expiring Notification', 'woocommerce-subscriptions' );
		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
		$this->subject = sprintf( _x( '[%s] Subscription is about to expire', 'default email subject for cancelled emails sent to the admin', 'woocommerce-subscriptions' ), '{blogname}' );

		$this->template_html  = 'emails/customer-expiring-subscription.php';
		$this->template_plain = 'emails/plain/customer-expiring-subscription.php';
		$this->template_base  = WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/' );

		$this->customer_email = true;

		parent::__construct();

		$this->enabled = WC_Subscriptions_Email_Notifications::should_send_notification();
	}
}
