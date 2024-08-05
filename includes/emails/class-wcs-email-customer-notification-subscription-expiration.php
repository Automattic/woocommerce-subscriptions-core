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
class WCS_Email_Customer_Notification_Subscription_Expiration extends WC_Email {

	/**
	 * Create an instance of the class.
	 */
	public function __construct() {

		$this->id          = 'customer_notification_expiring_subscription';
		$this->title       = __( 'Subscription Is About To Expire', 'woocommerce-subscriptions' );
		$this->description = __( 'Subscription Expiting Notification emails are sent when a customer\'s subscription is about to expire.', 'woocommerce-subscriptions' );

		$this->heading = __( 'Subscription Expiring Notification', 'woocommerce-subscriptions' );
		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
		$this->subject = sprintf( _x( '[%s] Subscription is about to expire', 'default email subject for cancelled emails sent to the admin', 'woocommerce-subscriptions' ), '{blogname}' );

		$this->template_html  = 'emails/customer-expiring-subscription.php';
		$this->template_plain = 'emails/plain/customer-expiring-subscription.php';
		$this->template_base  = WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/' );

		$this->customer_email = true;

		parent::__construct();
	}

	/**
	 * Get the default e-mail subject.
	 *
	 * @return string
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.5.3
	 */
	public function get_default_subject() {
		return $this->subject;
	}

	/**
	 * Get the default e-mail heading.
	 *
	 * @return string
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.5.3
	 */
	public function get_default_heading() {
		return $this->heading;
	}

	//TODO: perhaps return bool for these so that AS knows whether the email sending succeeded?

	/**
	 * trigger function.
	 *
	 * @return void
	 */
	public function trigger( $subscription ) {
		$this->object    = $subscription;
		$this->recipient = $subscription->get_billing_email();

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * get_content_html function.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'subscription'       => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => is_callable(
					array(
						$this,
						'get_additional_content',
					)
				) ? $this->get_additional_content() : '',
				// WC 3.7 introduced an additional content field for all emails.
				'sent_to_admin'      => true,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * get_content_plain function.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'subscription'       => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => is_callable(
					array(
						$this,
						'get_additional_content',
					)
				) ? $this->get_additional_content() : '',
				// WC 3.7 introduced an additional content field for all emails.
				'sent_to_admin'      => true,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
}
