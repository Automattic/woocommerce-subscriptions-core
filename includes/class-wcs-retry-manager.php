<?php
/**
 * Manage the process of retrying a failed renewal payment that previously failed.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WCS_Retry_Manager
 * @category	Class
 * @author		Prospress
 * @since		2.1
 */
require_once( 'payment-retry/class-wcs-retry-admin.php' );

class WCS_Retry_Manager {

	/* the rules that control the retry schedule and behaviour of each retry */
	protected static $retry_rules = array();

	/* the setting ID for enabling/disabling the automatic retry system */
	protected static $setting_id;

	/* property to store the instance of WCS_Retry_Admin */
	protected static $admin;

	/**
	 * Attach callbacks and set the retry rules
	 *
	 * @since 2.1
	 */
	public static function init() {

		self::$setting_id = WC_Subscriptions_Admin::$option_prefix . '_enable_retry';
		self::$admin      = new WCS_Retry_Admin( self::$setting_id );

		if ( self::is_retry_enabled() ) {

			self::load_classes();

			add_filter( 'init', array( self::store(), 'init' ) );

			add_filter( 'woocommerce_subscription_dates', __CLASS__ . '::add_retry_date_type' );

			add_action( 'woocommerce_subscription_status_updated', __CLASS__ . '::delete_retry_payment_date', 0, 3 );

			add_action( 'woocommerce_subscription_renewal_payment_failed', __CLASS__ . '::maybe_apply_retry_rule', 10 );

			add_action( 'woocommerce_scheduled_subscription_payment_retry', __CLASS__ . '::maybe_retry_payment' );
		}
	}

	/**
	 * A helper function to check if the retry system has been enabled or not
	 *
	 * @since 2.1
	 */
	public static function is_retry_enabled() {
		return ( 'yes' == get_option( self::$setting_id, 'no' ) ) ? true : false;
	}

	/**
	 * Load all the retry classes if the retry system is enabled
	 *
	 * @since 2.1
	 */
	protected static function load_classes() {

		require_once( 'abstracts/abstract-wcs-retry-store.php' );

		require_once( 'payment-retry/class-wcs-retry.php' );

		require_once( 'payment-retry/class-wcs-retry-rule.php' );

		require_once( 'payment-retry/class-wcs-retry-rules.php' );

		require_once( 'payment-retry/class-wcs-retry-post-store.php' );

		require_once( 'payment-retry/class-wcs-retry-email.php' );

		require_once( 'admin/meta-boxes/class-wcs-meta-box-payment-retries.php' );
	}

	/**
	 * Add a renewal retry date type to Subscriptions date types
	 *
	 * @since 2.1
	 */
	public static function add_retry_date_type( $subscription_date_types ) {

		$subscription_date_types['payment_retry'] = _x( 'Renewal Payment Retry', 'table heading', 'woocommerce-subscriptions' );

		return $subscription_date_types;
	}

	/**
	 * When a subscription's status is updated, if it's being changed to active or an inactive status, delete the retry date
	 *
	 * @param object $subscription An instance of a WC_Subscription object
	 * @param string $new_status A valid subscription status
	 * @param string $old_status A valid subscription status
	 */
	public static function delete_retry_payment_date( $subscription, $new_status, $old_status ) {
		if ( in_array( $new_status, array( 'active', 'pending-cancel', 'cancelled', 'switched', 'expired' ) ) ) {
			$subscription->delete_date( 'payment_retry' );
		}
	}

	/**
	 * When a payment fails, apply a retry rule, if one exists that applies to this failure.
	 *
	 * @param WC_Subscription The subscription on which the payment failed
	 * @since 2.1
	 */
	public static function maybe_apply_retry_rule( $subscription ) {

		if ( $subscription->is_manual() || $subscription->payment_method_supports( 'gateway_scheduled_payments' ) ) {
			return;
		}

		$last_order  = $subscription->get_last_order( 'all' );
		$retry_count = self::store()->get_retry_count_for_order( $last_order->id );

		if ( wcs_order_contains_renewal( $last_order ) && self::rules()->has_rule( $retry_count, $last_order->id ) ) {

			$retry_rule = self::rules()->get_rule( $retry_count, $last_order->id );

			do_action( 'woocommerce_subscriptions_before_apply_retry_rule', $retry_rule, $last_order, $subscription );

			$retry_id = self::store()->save( new WCS_Retry( array(
				'status'   => 'pending',
				'order_id' => $last_order->id,
				'date_gmt' => date( 'Y-m-d H:i:s', gmdate( 'U' ) + $retry_rule->get_retry_interval() ),
				'rule_raw' => $retry_rule->get_raw_data(),
			) ) );

			foreach ( array( 'order' => $last_order, 'subscription' => $subscription ) as $object_key => $object ) {

				$new_status = $retry_rule->get_status_to_apply( $object_key );

				if ( '' !== $new_status && ! $object->has_status( $new_status ) ) {
					$object->update_status( $new_status, _x( 'Retry rule applied:', 'used in order note as reason for why status changed', 'woocommerce-subscriptions' ) );
				}
			}

			if ( $retry_rule->get_retry_interval() > 0 ) {
				// by calling this after changing the status, this will also schedule the 'woocommerce_scheduled_subscription_payment_retry' action
				$subscription->update_dates( array( 'payment_retry' => date( 'Y-m-d H:i:s', gmdate( 'U' ) + $retry_rule->get_retry_interval( $retry_count ) ) ) );
			}

			// maybe send emails about the renewal payment failure
			foreach ( array( 'customer', 'admin' ) as $recipient ) {
				if ( $retry_rule->has_email_template( $recipient ) ) {
					$email_class = $retry_rule->get_email_template( $recipient );
					$email = new $email_class();
					$email->trigger( $last_order, self::store()->get_retry( $retry_id ) );
				}
			}

			do_action( 'woocommerce_subscriptions_after_apply_retry_rule', $retry_rule, $last_order, $subscription );
		}
	}

	/**
	 * When a retry hook is triggered, check if the rules for that retry are still valid
	 * and if so, retry the payment.
	 *
	 * @param WC_Subscription The subscription on which the payment failed
	 * @since 2.1
	 */
	public static function maybe_retry_payment( $subscription_id ) {

		$subscription = wcs_get_subscription( $subscription_id );
		$last_order   = $subscription->get_last_order( 'all' );
		$last_retry   = self::store()->get_last_retry_for_order( $last_order->id );

		// we only need to retry the payment if we have applied a retry rule for the order and it still needs payment
		if ( null !== $last_retry && 'pending' === $last_retry->get_status() ) {

			do_action( 'woocommerce_subscriptions_before_payment_retry', $last_retry, $last_order, $subscription );

			if ( $last_order->needs_payment() ) {

				self::update_retry_status( $last_retry, 'processing', $last_retry->get_date_gmt() );

				$expected_order_status        = $last_retry->get_rule()->get_status_to_apply( 'order' );
				$expected_subscription_status = $last_retry->get_rule()->get_status_to_apply( 'subscription' );

				$valid_order_status        = ( '' == $expected_order_status || $last_order->has_status( $expected_order_status ) ) ? true : false;
				$valid_subscription_status = ( '' == $expected_subscription_status || $subscription->has_status( $expected_subscription_status ) ) ? true : false;

				// if both statuses are still the same or there no special status was applied and the order still needs payment (i.e. there has been no manual intervention), trigger the payment hook
				if ( $valid_order_status && $valid_order_status ) {

					// Make sure the subscription is on hold in case something goes wrong while trying to process renewal and in case gateways expect the subscription to be on-hold, which is normally the case with a renewal payment
					$subscription->update_status( 'on-hold', _x( 'Subscription renewal payment retry:', 'used in order note as reason for why subscription status changed', 'woocommerce-subscriptions' ) );

					WC_Subscriptions_Payment_Gateways::gateway_scheduled_subscription_payment( $subscription_id );

					// Now that we've attempted to process the payment, refresh the order
					$last_order = wc_get_order( $last_order->id );

					// if the order still needs payment, payment failed
					if ( $last_order->needs_payment() ) {
						self::update_retry_status( $last_retry, 'failed', $last_retry->get_date_gmt() );
					} else {
						self::update_retry_status( $last_retry, 'complete', $last_retry->get_date_gmt() );
					}
				} else {
					// order or subscription statuses have been manually updated, so we'll cancel the retry
					self::update_retry_status( $last_retry, 'cancelled', $last_retry->get_date_gmt() );
				}
			} else {
				// last order must have been paid for some other way, so we'll cancel the retry
				self::update_retry_status( $last_retry, 'cancelled', $last_retry->get_date_gmt() );
			}

			do_action( 'woocommerce_subscriptions_after_payment_retry', $last_retry, $last_order, $subscription );
		}
	}

	/**
	 * Update the status of a retry and set the date to reflect that
	 *
	 * @since 2.1
	 */
	protected static function update_retry_status( $retry, $new_status, $new_date ) {
		self::store()->save( new WCS_Retry( array(
			'id'       => $retry->get_id(),
			'order_id' => $retry->get_order_id(),
			'date_gmt' => $new_date,
			'status'   => $new_status,
			'rule_raw' => $retry->get_rule()->get_raw_data(),
		) ) );
	}

	/**
	 * Access the object used to interface with the database
	 *
	 * @since 2.1
	 */
	public static function store() {
		return WCS_Retry_Store::instance();
	}

	/**
	 * Setup and access the object used to interface with retry rules
	 *
	 * @since 2.1
	 */
	public static function rules() {
		if ( empty( self::$retry_rules ) ) {
			$class = apply_filters( 'wcs_retry_rules_class', 'WCS_Retry_Rules' );
			self::$retry_rules = new $class();
		}
		return self::$retry_rules;
	}
}
WCS_Retry_Manager::init();
