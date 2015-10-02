<?php
/**
 * Subscriptions Email Class
 *
 * Modifies the base WooCommerce email class and extends it to send subscription emails.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_Subscriptions_Email
 * @category	Class
 * @author		Brent Shepherd
 */
class WC_Subscriptions_Email {

	private static $woocommerce_email;

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 1.0
	 */
	public static function init() {

		add_action( 'woocommerce_email_classes', __CLASS__ . '::add_emails', 10, 1 );

		add_action( 'woocommerce_init', __CLASS__ . '::hook_transactional_emails' );

		add_filter( 'woocommerce_resend_order_emails_available', __CLASS__ . '::renewal_order_emails_available', -1 ); // run before other plugins so we don't remove their emails

	}

	/**
	 * Add Subscriptions' email classes.
	 *
	 * @since 1.4
	 */
	public static function add_emails( $email_classes ) {

		require_once( 'emails/class-wcs-email-new-renewal-order.php' );
		require_once( 'emails/class-wcs-email-new-switch-order.php' );
		require_once( 'emails/class-wcs-email-customer-processing-renewal-order.php' );
		require_once( 'emails/class-wcs-email-customer-completed-renewal-order.php' );
		require_once( 'emails/class-wcs-email-customer-completed-switch-order.php' );
		require_once( 'emails/class-wcs-email-customer-renewal-invoice.php' );
		require_once( 'emails/class-wcs-email-cancelled-subscription.php' );

		$email_classes['WCS_Email_New_Renewal_Order']        = new WCS_Email_New_Renewal_Order();
		$email_classes['WCS_Email_New_Switch_Order']         = new WCS_Email_New_Switch_Order();
		$email_classes['WCS_Email_Processing_Renewal_Order'] = new WCS_Email_Processing_Renewal_Order();
		$email_classes['WCS_Email_Completed_Renewal_Order']  = new WCS_Email_Completed_Renewal_Order();
		$email_classes['WCS_Email_Completed_Switch_Order']   = new WCS_Email_Completed_Switch_Order();
		$email_classes['WCS_Email_Customer_Renewal_Invoice'] = new WCS_Email_Customer_Renewal_Invoice();
		$email_classes['WCS_Email_Cancelled_Subscription']   = new WCS_Email_Cancelled_Subscription();

		return $email_classes;
	}

	/**
	 * Hooks up all of Subscription's transaction emails after the WooCommerce object is constructed.
	 *
	 * @since 1.4
	 */
	public static function hook_transactional_emails() {

		// Don't send subscription
		if ( WC_Subscriptions::is_duplicate_site() && ! defined( 'WCS_FORCE_EMAIL' ) ) {
			return;
		}

		add_action( 'woocommerce_subscription_status_updated', __CLASS__ . '::send_cancelled_email', 10, 2 );

		$order_email_actions = array(
			'woocommerce_order_status_pending_to_processing',
			'woocommerce_order_status_pending_to_completed',
			'woocommerce_order_status_pending_to_on-hold',
			'woocommerce_order_status_failed_to_processing_notification',
			'woocommerce_order_status_failed_to_completed_notification',
			'woocommerce_order_status_failed_to_on-hold_notification',
			'woocommerce_order_status_completed',
			'woocommerce_generated_manual_renewal_order',
			'woocommerce_order_status_failed',
		);

		foreach ( $order_email_actions as $action ) {
			add_action( $action, __CLASS__ . '::maybe_remove_woocommerce_email', 9 );
			add_action( $action, __CLASS__ . '::send_renewal_order_email', 10 );
			add_action( $action, __CLASS__ . '::send_switch_order_email', 10 );
			add_action( $action, __CLASS__ . '::maybe_reattach_woocommerce_email', 11 );
		}
	}

	/**
	 * Init the mailer and call for the cancelled email notification hook.
	 *
	 * @param $subscription WC Subscription
	 * @since 2.0
	 */
	public static function send_cancelled_email( $subscription ) {
		WC()->mailer();

		if ( $subscription->has_status( array( 'pending-cancel', 'cancelled' ) ) && 'true' !== get_post_meta( $subscription->id, '_cancelled_email_sent', true ) ) {
			do_action( 'cancelled_subscription_notification', $subscription );
		}
	}

	/**
	 * Init the mailer and call the notifications for the renewal orders.
	 *
	 * @param int $user_id The ID of the user who the subscription belongs to
	 * @param string $subscription_key A subscription key of the form created by @see self::get_subscription_key()
	 * @return void
	 */
	public static function send_renewal_order_email( $order_id ) {
		WC()->mailer();

		if ( wcs_order_contains_renewal( $order_id ) ) {
			do_action( current_filter() . '_renewal_notification', $order_id );
		}
	}

	/**
	 * If the order is a renewal order, don't send core emails.
	 *
	 * @param int $user_id The ID of the user who the subscription belongs to
	 * @param string $subscription_key A subscription key of the form created by @see self::get_subscription_key()
	 * @return void
	 */
	public static function maybe_remove_woocommerce_email( $order_id ) {
		if ( wcs_order_contains_renewal( $order_id ) || wcs_order_contains_switch( $order_id ) ) {
			remove_action( current_filter(), array( 'WC_Emails', 'send_transactional_email' ) );
		}
	}

	/**
	 * If the order is a renewal order, don't send core emails.
	 *
	 * @param int $user_id The ID of the user who the subscription belongs to
	 * @param string $subscription_key A subscription key of the form created by @see self::get_subscription_key()
	 * @return void
	 */
	public static function maybe_reattach_woocommerce_email( $order_id ) {
		if ( wcs_order_contains_renewal( $order_id ) || wcs_order_contains_switch( $order_id ) ) {
			add_action( current_filter(), array( 'WC_Emails', 'send_transactional_email' ) );
		}
	}

	/**
	 * If viewing a renewal order on the the Edit Order screen, set the available email actions for the order to use
	 * renewal order emails, not core WooCommerce order emails.
	 *
	 * @param int $user_id The ID of the user who the subscription belongs to
	 * @param string $subscription_key A subscription key of the form created by @see self::get_subscription_key()
	 * @return void
	 */
	public static function renewal_order_emails_available( $available_emails ) {
		global $theorder;

		if ( wcs_order_contains_renewal( $theorder->id ) ) {
			$available_emails = array(
				'new_renewal_order',
				'customer_processing_renewal_order',
				'customer_completed_renewal_order',
				'customer_renewal_invoice',
			);
		}

		return $available_emails;
	}

	/**
	 * Init the mailer and call the notifications for subscription switch orders.
	 *
	 * @param int $user_id The ID of the user who the subscription belongs to
	 * @param string $subscription_key A subscription key of the form created by @see self::get_subscription_key()
	 * @return void
	 */
	public static function send_switch_order_email( $order_id ) {
		WC()->mailer();

		if ( wcs_order_contains_switch( $order_id ) ) {
			do_action( current_filter() . '_switch_notification', $order_id );
		}
	}

	/**
	 * Init the mailer and call the notifications for the current filter.
	 *
	 * @param int $user_id The ID of the user who the subscription belongs to
	 * @param string $subscription_key A subscription key of the form created by @see self::get_subscription_key()
	 * @return void
	 * @deprecated 2.0
	 */
	public static function send_subscription_email( $user_id, $subscription_key ) {
		_deprecated_function( __FUNCTION__, '2.0' );
	}
}

WC_Subscriptions_Email::init();
