<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Customer Notification: Free Trial Expiring Subscription Email
 *
 * An email sent to the customer when a free trial is about to end.
 *
 * @class WCS_Email_Customer_Notification_Free_Trial_Expiry
 * @version 1.0.0
 * @package WooCommerce_Subscriptions/Classes/Emails
 * @extends WC_Email
 */
class WCS_Email_Customer_Notification_Free_Trial_Expiration extends WCS_Email_Customer_Notification {

	/**
	 * Create an instance of the class.
	 */
	public function __construct() {
		parent::__construct();

		$this->plugin_id = 'woocommerce-subscriptions_';

		$this->id          = 'customer_notification_free_trial_expiry';
		$this->title       = __( 'Customer Notification: Free trial expiring', 'woocommerce-subscriptions' );
		$this->description = __( 'Free trial expiry notification emails are sent when customer\'s free trial is about to expire.', 'woocommerce-subscriptions' );

		$this->heading = __( 'Free Trial Expiring', 'woocommerce-subscriptions' );
		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
		$this->subject = sprintf( _x( '[%s] Your Free Trial Is About To End', 'default email subject for free trial expiry notification emails sent to the customer', 'woocommerce-subscriptions' ), '{blogname}' );

		$this->template_html  = 'emails/customer-notification-trial-ending.php';
		$this->template_plain = 'emails/plain/customer-notification-trial-ending.php';
		$this->template_base  = WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/' );

		$this->customer_email = true;
	}
}
