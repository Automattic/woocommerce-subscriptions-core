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
class WCS_Email_Customer_Notification_Free_Trial_Expiration extends WC_Email {

	/**
	 * Create an instance of the class.
	 */
	public function __construct() {

		$this->id          = 'customer_notification_free_trial_expiry';
		$this->title       = __( 'Free trial expiring', 'woocommerce-subscriptions' );
		$this->description = __( 'Free trial expiry notification emails are sent when customer\'s free trial is about to expire.', 'woocommerce-subscriptions' );

		$this->heading = __( 'Free Trial Expiring', 'woocommerce-subscriptions' );
		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
		$this->subject = sprintf( _x( '[%s] Your Free Trial Is About To End', 'default email subject for free trial expiry notification emails sent to the customer', 'woocommerce-subscriptions' ), '{blogname}' );

		$this->template_html  = 'emails/customer-notification-trial-ending.php';
		$this->template_plain = 'emails/plain/customer-notification-trial-ending.php';
		$this->template_base  = WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/' );

		$this->customer_email = true;

		parent::__construct();

		$this->enabled = WC_Subscriptions_Email_Notifications::should_send_notification();

		add_action( 'woocommerce_scheduled_subscription_customer_notification_trial_expiration', array( $this, 'trigger' ) );
	}

	/**
	 * Get the default e-mail heading.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_default_heading() {
		return $this->heading;
	}

	/**
	 * Get the default e-mail subject.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_default_subject() {
		return $this->subject;
	}

	/**
	 * trigger function.
	 *
	 * @return void
	 */
	public function trigger( $subscription ) {

		$this->object    = $subscription;
		$this->recipient = $this->object->get_billing_email();

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
				'sent_to_admin'      => false,
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
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
}
