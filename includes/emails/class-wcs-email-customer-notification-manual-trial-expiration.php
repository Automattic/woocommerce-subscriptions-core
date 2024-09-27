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
class WCS_Email_Customer_Notification_Manual_Trial_Expiration extends WCS_Email_Customer_Notification {

	/**
	 * Create an instance of the class.
	 */
	public function __construct() {

		$this->plugin_id = 'woocommerce-subscriptions_';

		$this->id          = 'customer_notification_manual_trial_expiry';
		$this->title       = __( 'Customer Notification: Free trial expiration: manual payment required', 'woocommerce-subscriptions' );
		$this->description = __( 'Free trial expiry notification emails are sent when customer\'s free trial for a manually renewed subscription is about to expire.', 'woocommerce-subscriptions' );

		$this->heading = __( 'Free trial expiration: manual payment required', 'woocommerce-subscriptions' );
		// translators: placeholder is {customers_first_name}, a variable that will be substituted when email is sent out
		$this->subject = sprintf( _x( 'Your free trial is almost up, %s ⏳', 'default email subject for an email notification for a manually renewed subscription with free trial expiry emails sent to the customer', 'woocommerce-subscriptions' ), '{customers_first_name}' );

		$this->template_html  = 'emails/customer-notification-manual-trial-ending.php';
		$this->template_plain = 'emails/plain/customer-notification-manual-trial-ending.php';
		$this->template_base  = WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/' );

		$this->customer_email = true;

		// Constructor in parent uses the values above in the initialization.
		parent::__construct();
	}

	public function get_relevant_date_type() {
		return 'trial_end';
	}
}
