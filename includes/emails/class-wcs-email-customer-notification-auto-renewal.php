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
		$this->title       = __( 'Customer Notification: Automatic renewal notice', 'woocommerce-subscriptions' );
		$this->description = __( 'Customer Notification: Automatic renewal notice emails are sent when customer\'s subscription is about to be renewed automatically.', 'woocommerce-subscriptions' );

		$this->heading = __( 'Automatic renewal notice', 'woocommerce-subscriptions' );

		$this->subject = sprintf(
			// translators: %1$s: number of days until renewal, %2$s: customer's first name.
			_x( 'Your subscription automatically renews in %1$s days, %2$s ♻️', 'default email subject for subscription\'s automatic renewal notice', 'woocommerce-subscriptions' ),
			'{days_until_renewal}',
			'{customers_first_name}'
		);

		$this->template_html  = 'emails/customer-notification-auto-renewal.php';
		$this->template_plain = 'emails/plain/customer-notification-auto-renewal.php';
		$this->template_base  = WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/' );

		$this->customer_email = true;

		// Constructor in parent uses the values above in the initialization.
		parent::__construct();
	}

	public function get_relevant_date_type() {
		return 'next_payment';
	}

	/**
	 * Default content to show below main email content.
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return __( 'Thank you for being a loyal customer, {customers_first_name} — we appreciate your business.', 'woocommerce-subscriptions' );
	}
}
