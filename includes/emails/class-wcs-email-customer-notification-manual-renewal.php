<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Customer Notification: Manual Subscription Renewal.
 *
 * An email sent to the customer when a subscription needs to be renewed manually.
 *
 * @class WCS_Email_Customer_Notification_Manual_Renewal
 * @version 1.0.0
 * @package WooCommerce_Subscriptions/Classes/Emails
 * @extends WC_Email
 */
class WCS_Email_Customer_Notification_Manual_Renewal extends WCS_Email_Customer_Notification {

	/**
	 * Create an instance of the class.
	 */
	public function __construct() {
		$this->plugin_id = 'woocommerce-subscriptions_';

		$this->id          = 'customer_notification_manual_renewal';
		$this->title       = __( 'Customer Notification: Manual Renewal for Subscription', 'woocommerce-subscriptions' );
		$this->description = __( 'Customer Notification: Manual Renewal emails are sent when a customer\'s subscription needs to be manually renewed.', 'woocommerce-subscriptions' );

		$this->heading = __( 'Subscription needs to be renewed manually', 'woocommerce-subscriptions' );
		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
		$this->subject = sprintf( _x( '[%s] Subscription Needs Manual Renewal', 'default email subject for cancelled emails sent to the admin', 'woocommerce-subscriptions' ), '{blogname}' );

		$this->template_html  = 'emails/customer-notification-manual-renewal.php';
		$this->template_plain = 'emails/plain/customer-notification-manual-renewal.php';
		$this->template_base  = WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/' );

		$this->customer_email = true;

		parent::__construct();

		$this->enabled = WC_Subscriptions_Email_Notifications::should_send_notification();
	}
}
