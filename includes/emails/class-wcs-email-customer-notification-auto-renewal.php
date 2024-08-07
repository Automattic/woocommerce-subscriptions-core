<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Customer Notification: Automated Subscription Renewal.
 *
 * An email sent to the customer when a subscription will be renewed automatically.
 *
 * @class WCS_Email_Customer_Notification_Auto_Renewal
 * @version 1.0.0
 * @package WooCommerce_Subscriptions/Classes/Emails
 * @extends WC_Email
 */
class WCS_Email_Customer_Notification_Auto_Renewal extends WCS_Email_Customer_Notification {

	/**
	 * Create an instance of the class.
	 */
	public function __construct() {
		$this->plugin_id = 'woocommerce-subscriptions_';

		$this->id          = 'customer_notification_auto_renewal';
		$this->title       = __( 'Customer Notification: Automatic Renewal for Subscription', 'woocommerce-subscriptions' );
		$this->description = __( 'Customer Notification: Automatic Renewal emails are sent when a customer\'s subscription is about to be renewed automatically.', 'woocommerce-subscriptions' );

		$this->heading = __( 'Subscription will be renewed automatically', 'woocommerce-subscriptions' );
		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
		$this->subject = sprintf( _x( '[%s] Subscription Will Be Auto Renewed', 'default email subject for cancelled emails sent to the admin', 'woocommerce-subscriptions' ), '{blogname}' );

		$this->template_html  = 'emails/customer-notification-auto-renewal.php';
		$this->template_plain = 'emails/plain/customer-notification-auto-renewal.php';
		$this->template_base  = WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/' );

		$this->customer_email = true;

		parent::__construct();

		// TODO: check if this interferes with the UX via option setting.
		$this->enabled = WC_Subscriptions_Email_Notifications::should_send_notification();
	}
}
