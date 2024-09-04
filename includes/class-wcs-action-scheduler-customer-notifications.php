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
	 * @var int Time offset (in whole seconds) between the notification and the action it's notifying about.
	 */
	protected $time_offset;

	/**
	 * @var array|string[] Notifications scheduled by this class.
	 *
	 * Just for reference.
	 */
	protected $notification_actions = [
		'woocommerce_scheduled_subscription_customer_notification_trial_expiration',
		'woocommerce_scheduled_subscription_customer_notification_expiration',
		'woocommerce_scheduled_subscription_customer_notification_manual_renewal',
		'woocommerce_scheduled_subscription_customer_notification_auto_renewal',
	];

	public function get_time_offset( $subscription ) {
		/**
		 * Offset between a subscription event and related notification.
		 *
		 * @since 8.0.0
		 *
		 * @param int $time_offset
		 */
		return apply_filters( 'woocommerce_subscriptions_customer_notification_time_offset', $this->time_offset, $subscription );
	}

	public function set_time_offset( $time_offset ) {
		$this->time_offset = $time_offset;
	}

	public function __construct() {
		parent::__construct();

		$setting_option    = get_option(
			WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$offset_setting_string,
			[
				'number' => 3,
				'unit'   => 'days',
			]
		);
		$this->time_offset = self::convert_offset_to_seconds( $setting_option );

		add_action( 'woocommerce_before_subscription_object_save', [ $this, 'update_notifications' ], 10, 2 );
	}

	/**
	 * Calculate time offset in seconds from the settings array.
	 *
	 * @param array $offset Format: [ 'number' => 3, 'unit' => 'days' ]
	 *
	 * @return float|int
	 */
	protected static function convert_offset_to_seconds( $offset ) {
		$default_offset = 3 * DAY_IN_SECONDS;

		if ( ! isset( $offset['unit'] ) || ! isset( $offset['number'] ) ) {
			return $default_offset;
		}

		switch ( $offset['unit'] ) {
			case 'days':
				return ( $offset['number'] * DAY_IN_SECONDS );
			case 'weeks':
				return ( $offset['number'] * WEEK_IN_SECONDS );
			case 'months':
				return ( $offset['number'] * MONTH_IN_SECONDS );
			case 'years':
				return ( $offset['number'] * YEAR_IN_SECONDS );
			default:
				return $default_offset;
		}
	}

	protected function schedule_notification( $subscription, $action, $timestamp ) {
		if (
			! (
				$subscription->has_status( 'active' )
				|| $subscription->has_status( 'pending-cancel' ) //TODO: do we want to create notifications when user cancelled the subscription?
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
	 * Subtract time offset from given datetime based on the settings and subscription properties and return resulting timestamp.
	 *
	 * @param string $datetime
	 * @param WC_Subscription $subscription
	 *
	 * @return int
	 */
	protected function sub_time_offset( $datetime, $subscription ) {
		$dt = new DateTime( $datetime, new DateTimeZone( 'UTC' ) );

		return $dt->getTimestamp() - $this->time_offset;
	}

	public function schedule_trial_ending_notification( $subscription ) {
		$trial_end = $subscription->get_date( 'trial_end' );
		$timestamp = $this->sub_time_offset( $trial_end, $subscription );

		$this->schedule_notification(
			$subscription,
			'woocommerce_scheduled_subscription_customer_notification_trial_expiration',
			$timestamp
		);
	}

	public function schedule_expiry_notification( $subscription ) {
		$subscription_end = $subscription->get_date( 'end' );
		$timestamp        = $this->sub_time_offset( $subscription_end, $subscription );

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
		$timestamp    = $this->sub_time_offset( $next_payment, $subscription );

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
	 * Update notifications when subscription gets updated.
	 *
	 * To make batch processing easier, we need to handle the following use case:
	 * 1. Subscription S1 gets updated.
	 * 2. Notification config gets updated, a batch to fix all subscriptions is started and processes all subscriptions
	 *    with update time before the config got updated.
	 * 3. Subscription S1 gets updated before it gets processed by the batch process.
	 *
	 * Thus, we update notifications for all subscriptions that are being updated after notification config change time
	 * and which have their update time before that.
	 *
	 * As this gets called on Subscription save, the modification timestamp should be updated, too, and thus
	 * the currently updated subscription no longer needs to be processed by the batch process.
	 *
	 * @param $subscription
	 * @param $subscription_data_store
	 *
	 * @return void
	 */
	public function update_notifications( $subscription, $subscription_data_store ) {
		if ( ! $subscription->has_status( 'active' ) && ! $subscription->has_status( 'pending-cancel' ) ) {
			return;
		}

		// Here, we need the 'old' update timestamp for comparison, so can't use get_date_modified() method.
		$subscription_update_time_raw = array_key_exists( 'date_modified', $subscription->get_data() ) ? $subscription->get_data()['date_modified'] : $subscription->get_date_created();
		if ( ! $subscription_update_time_raw ) {
			$subscription_update_utc_timestamp = 0;
		} else {
			$subscription_update_time_raw->setTimezone( new DateTimeZone( 'UTC' ) );
			$subscription_update_utc_timestamp = $subscription_update_time_raw->getTimestamp();
		}

		$notification_settings_update_utc_timestamp = get_option( 'wcs_notification_settings_update_time' );

		if ( $subscription_update_utc_timestamp < $notification_settings_update_utc_timestamp ) {
			$this->schedule_all_notifications( $subscription );
		}
	}

	/**
	 * Schedule all notifications for a subscription based on the dates defined on the subscription.
	 *
	 * If there's a trial end, schedule free trial expiry notification.
	 * If there's an end date, schedule expiry notification.
	 * If there's a next payment date defined, schedule automated/manual renewal notification.
	 *
	 * @param $subscription
	 *
	 * @return void
	 */
	protected function schedule_all_notifications( $subscription ) {
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

	public function set_date_types_to_schedule() {
		$date_types_to_schedule = wcs_get_subscription_date_types();
		unset(
			$date_types_to_schedule['start'],
			$date_types_to_schedule['cancelled'] // prevent scheduling end date when reactivating subscription.
		);

		$this->date_types_to_schedule = array_keys( $date_types_to_schedule );
	}

	/**
	 * Schedule notifications if the date has changed.
	 *
	 * @param object $subscription An instance of a WC_Subscription object
	 * @param string $date_type Can be 'trial_end', 'next_payment', 'payment_retry', 'end', 'end_of_prepaid_term' or a custom date type
	 * @param string $datetime A MySQL formatted date/time string in the GMT/UTC timezone.
	 */
	public function update_date( $subscription, $date_type, $datetime ) {
		if ( in_array( $date_type, $this->get_date_types_to_schedule(), true ) ) {
			$this->schedule_all_notifications( $subscription );
		}
	}

	/**
	 * Schedule notifications if the date has been deleted.
	 *
	 * @param object $subscription An instance of a WC_Subscription object
	 * @param string $date_type Can be 'trial_end', 'next_payment', 'end', 'end_of_prepaid_term' or a custom date type
	 */
	public function delete_date( $subscription, $date_type ) {
		$this->update_date( $subscription, $date_type, '0' );
	}

	protected function unschedule_all_notifications( $subscription, $exceptions = [] ) {
		foreach ( $this->notification_actions as $action ) {
			if ( in_array( $action, $exceptions, true ) ) {
				continue;
			}

			$this->unschedule_actions( $action, $this->get_action_args( $subscription ) );
		}
	}

	/**
	 * When a subscription's status is updated, maybe schedule an event
	 *
	 * @param object $subscription An instance of a WC_Subscription object
	 * @param string $new_status New subscription status
	 * @param string $old_status Previous subscription status
	 */
	public function update_status( $subscription, $new_status, $old_status ) {

		switch ( $new_status ) {
			case 'active':
				// Clean up previous notifications (e.g. the expiration might be still pending).
				$this->unschedule_all_notifications( $subscription );
				// Schedule new ones.
				$this->schedule_all_notifications( $subscription );
				break;
			case 'pending-cancel':
				// Unschedule all except expiration notification.
				$this->unschedule_all_notifications( $subscription, [ 'woocommerce_scheduled_subscription_customer_notification_expiration' ] );
				break;
			case 'on-hold':
			case 'cancelled':
			case 'switched':
			case 'expired':
			case 'trash':
				$this->unschedule_all_notifications( $subscription );
				break;
		}
	}

	/**
	 * Get the args to set on the scheduled action.
	 *
	 * @param object $subscription An instance of WC_Subscription to get the hook for
	 *
	 * @return array Array of name => value pairs stored against the scheduled action.
	 */
	protected function get_action_args( $subscription ) {
		$action_args = [ 'subscription_id' => $subscription->get_id() ];

		return $action_args;
	}

	/**
	 * Get the args to set on the scheduled action.
	 *
	 * @param string $action_hook Name of event used as the hook for the scheduled action.
	 * @param array $action_args Array of name => value pairs stored against the scheduled action.
	 */
	protected function unschedule_actions( $action_hook, $action_args ) {
		as_unschedule_all_actions( $action_hook, $action_args );
	}
}
