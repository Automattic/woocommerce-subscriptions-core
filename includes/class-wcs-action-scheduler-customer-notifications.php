<?php

/**
 * Scheduler for subscription events that uses the Action Scheduler
 *
 * @class     WCS_Action_Scheduler
 * @version   1.0.0 - Migrated from WooCommerce Subscriptions v2.0.0
 * @package   WooCommerce Subscriptions/Classes
 * @category  Class
 */
class WCS_Action_Scheduler_Customer_Notifications extends WCS_Scheduler {

	/**
	 * @var int Time offset (in whole hours) between the notification and the action it's notifying about.
	 */
	protected int $hours_offset;

	/**
	 * @var DateInterval Time offset between the notification and the action it's notifying about.
	 */
	protected DateInterval $time_delta;

	/**
	 * @var array|string[] Notifications scheduled by this class.
	 *
	 * Just for reference.
	 */
	protected array $notification_actions = array(
		'woocommerce_scheduled_subscription_customer_notification_trial_expiring',
		'woocommerce_scheduled_subscription_customer_notification_expiration',
		'woocommerce_scheduled_subscription_customer_notification_manual_renewal',
		'woocommerce_scheduled_subscription_customer_notification_auto_renewal',
	);

	public function __construct() {
		parent::__construct();

		//TODO: make number of hours configurable.
		$this->hours_offset = 3 * DAY_IN_SECONDS / HOUR_IN_SECONDS; // aka 3 days in hours.

		$this->time_delta = new DateInterval( "PT{$this->hours_offset}H" );
	}

	public function set_date_types_to_schedule() {
		$date_types_to_schedule = wcs_get_subscription_date_types();
		unset( $date_types_to_schedule['start'] );

		//TODO: filter?
		$this->date_types_to_schedule = array_keys( $date_types_to_schedule );
	}

	protected function schedule_notification( $subscription, $action, $timestamp ) {
		if (
			! (
				$subscription->has_status( 'active' )
				|| $subscription->has_status( 'pending-cancelled' ) //TODO: do we want to create notifications when user cancelled the subscription?
			)
		) {
			return;
		}

		$action_args = $this->get_action_args( $subscription );

		$next_scheduled = as_next_scheduled_action( $action, $action_args );

		if ( $timestamp === $next_scheduled ) {
			return;
		}

		$this->unschedule_actions( $action, $action_args );

		// Only reschedule if it's in the future
		if ( $timestamp <= time() ) {
			return;
		}

		as_schedule_single_action( $timestamp, $action, $action_args );
	}

	//TODO: check timezones
	/*
	 * @param string $datetime
	 * @return int
	 */
	protected function sub_time_offset( $datetime ) {
		$dt = new DateTime( $datetime, new DateTimeZone( 'UTC' ) );
		$dt->sub( $this->time_delta );

		return $dt->getTimestamp();
	}

	public function schedule_trial_ending_notification( $subscription ) {
		$trial_end = $subscription->get_date( 'trial_end' );
		$timestamp = $this->sub_time_offset( $trial_end );

		$this->schedule_notification(
			$subscription,
			'woocommerce_scheduled_subscription_customer_notification_trial_expiring',
			$timestamp
		);
	}

	public function schedule_expiry_notification( $subscription ) {
		$subscription_end = $subscription->get_date( 'end' );
		$timestamp        = $this->sub_time_offset( $subscription_end );

		$this->schedule_notification(
			$subscription,
			'woocommerce_scheduled_subscription_customer_notification_expiration',
			$timestamp
		);
	}

	public function schedule_payment_notification( $subscription ) {

		//TODO: For end of trial, should we schedule payment notification? Let's say no.
		if ( $subscription->get_date( 'trial_end' ) ) {
			return;
		}

		$next_payment = $subscription->get_date( 'next_payment' );
		$timestamp    = $this->sub_time_offset( $next_payment );

		// Can manual vs automatic payment change until it runs?
		if ( $subscription->is_manual() ) {
			$this->schedule_notification(
				$subscription,
				'woocommerce_scheduled_subscription_customer_notification_manual_renewal',
				$timestamp
			);
		} else {
			$this->schedule_notification(
				$subscription,
				'woocommerce_scheduled_subscription_customer_notification_auto_renewal',
				$timestamp
			);
		}
	}

	/**
	 * Maybe set a schedule action if the new date is in the future
	 *
	 * @param object $subscription An instance of a WC_Subscription object
	 * @param string $date_type Can be 'trial_end', 'next_payment', 'payment_retry', 'end', 'end_of_prepaid_term' or a custom date type
	 * @param string $datetime A MySQL formatted date/time string in the GMT/UTC timezone.
	 */
	public function update_date( $subscription, $date_type, $datetime ) {

		if ( in_array( $date_type, $this->get_date_types_to_schedule(), true ) ) {

			if ( $subscription->get_date( 'trial_end' ) ) {
				$this->schedule_trial_ending_notification( $subscription );
			}

			if ( $subscription->get_date( 'end' ) ) {
				$this->schedule_expiry_notification( $subscription );
			}

			if ( $subscription->get_date( 'next_payment' ) ) {
				$this->schedule_payment_notification( $subscription );
			}
		}
	}

	/**
	 * Delete a date from the action scheduler queue
	 *
	 * @param object $subscription An instance of a WC_Subscription object
	 * @param string $date_type Can be 'trial_end', 'next_payment', 'end', 'end_of_prepaid_term' or a custom date type
	 */
	public function delete_date( $subscription, $date_type ) {
		$this->update_date( $subscription, $date_type, '0' );
	}

	/**
	 * When a subscription's status is updated, maybe schedule an event
	 *
	 * @param object $subscription An instance of a WC_Subscription object
	 * @param string $date_type Can be 'trial_end', 'next_payment', 'end', 'end_of_prepaid_term' or a custom date type
	 * @param string $datetime A MySQL formatted date/time string in the GMT/UTC timezone.
	 */
	public function update_status( $subscription, $new_status, $old_status ) {

		switch ( $new_status ) {
			case 'active':
				if ( $subscription->get_date( 'trial_end' ) ) {
					$this->schedule_trial_ending_notification( $subscription );
				}

				if ( $subscription->get_date( 'end' ) ) {
					$this->schedule_expiry_notification( $subscription );
				}

				if ( $subscription->get_date( 'next_payment' ) ) {
					$this->schedule_payment_notification( $subscription );
				}

				break;
			case 'pending-cancel':
				// Unschedule all notifications?
				foreach ( $this->notification_actions as $action ) {
					$this->unschedule_actions( $action, $this->get_action_args( $subscription ) );
				}
				break;
			case 'on-hold':
			case 'cancelled':
			case 'switched':
			case 'expired':
			case 'trash':
				// Unschedule all
				foreach ( $this->notification_actions as $action ) {
					$this->unschedule_actions( $action, $this->get_action_args( $subscription ) );
				}
				break;
		}
	}

	/**
	 * Get the args to set on the scheduled action.
	 *
	 * @param string $date_type Can be 'trial_end', 'next_payment', 'expiration', 'end_of_prepaid_term' or a custom date type
	 * @param object $subscription An instance of WC_Subscription to get the hook for
	 *
	 * @return array Array of name => value pairs stored against the scheduled action.
	 */
	protected function get_action_args( $subscription ) {

		$action_args = array( 'subscription_id' => $subscription->get_id() );

		// TODO maybe filter
		return $action_args;
	}

	/**
	 * Get the args to set on the scheduled action.
	 *
	 * @param string $$action_hook Name of event used as the hook for the scheduled action.
	 * @param array $action_args Array of name => value pairs stored against the scheduled action.
	 */
	protected function unschedule_actions( $action_hook, $action_args ) {
		as_unschedule_all_actions( $action_hook, $action_args );
	}
}
