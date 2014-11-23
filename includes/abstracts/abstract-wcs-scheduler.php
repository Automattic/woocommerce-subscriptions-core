<?php
/**
 * Base class for creating a scheduler
 *
 * Schedulers are responsible for triggering subscription events/action, like when a payment is due
 * or subscription expires.
 *
 * @class     WCS_Scheduler
 * @version   2.0.0
 * @package   WooCommerce Subscriptions/Abstracts
 * @category  Abstract Class
 * @author    Prospress
 */
abstract class WCS_Scheduler {

	/** @protected array The types of dates which this class should schedule */
	protected static $date_types_to_schedule;

	public function __construct() {

		$date_types_to_schedule = apply_filters( 'woocommerce_subscriptions_date_types_to_schedule', array_keys( wcs_subscription_dates() ) );

		add_filter( 'woocommerce_subscription_updated_date', array( __CLASS__, 'update_date' ), 10, 4 );

		add_filter( 'woocommerce_subscription_updated_status', array( __CLASS__, 'update_status' ), 10, 3 );
	}

	/**
	 * When a subscription's date is updated, maybe schedule an event
	 */
	abstract public function update_date( $subscription_id, $date_type, $datetime, $timezone );

	/**
	 * When a subscription's status is updated, maybe schedule an event
	 */
	abstract public function update_status( $subscription_id, $old_status, $new_status );
}
