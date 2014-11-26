<?php
/**
 * Scheduler for subscription events that uses the Action Scheduler
 *
 * @class     WCS_Action_Scheduler
 * @version   2.0.0
 * @package   WooCommerce Subscriptions/Classes
 * @category  Class
 * @author    Prospress
 */
class WCS_Action_Scheduler extends WCS_Scheduler {

	/*@protected Array of $action_hook => $date_type values */
	protected $action_hooks = array(
		'scheduled_subscription_trial_end'  => 'trial_end',
		'scheduled_subscription_payment'    => 'next_payment',
		'scheduled_subscription_expiration' => 'end',

	);

	/**
	 * Maybe set a schedule action if the new date is in the future 
	 *
	 * @param int $subscription_id The ID for a WC_Subscription object
	 * @param string $date_type Can be 'start', 'trial_end', 'next_payment', 'last_payment', 'end', 'end_of_prepaid_term' or a custom date type
	 * @param string $datetime A MySQL formated date/time string in the GMT/UTC timezone.
	 */
	public function update_date( $subscription_id, $date_type, $datetime ) {

		if ( in_array( $date_type, $this->date_types_to_schedule ) ) {

			$action_hook = $this->get_scheduled_action_hook( $subscription_id, $date_type );

			if ( ! empty( $hook ) ) {
				$action_args = array( 'subscription_id' => $subscription_id );

				wc_unschedule_action( $action_hook, $action_args );

				// Only reschedule if it's in the future
				if ( strtotime( $datetime ) > current_time( 'timestamp', true ) ) {
					wc_schedule_single_action( $datetime, $action_hook, $action_args );
				}
			}
		}
	}

	/**
	 * Delete a date from the action scheduler queue
	 *
	 * @param int $subscription_id The ID for a WC_Subscription object
	 * @param string $date_type Can be 'start', 'trial_end', 'next_payment', 'last_payment', 'end', 'end_of_prepaid_term' or a custom date type
	 */
	public function delete_date( $subscription_id, $date_type ) {
		$this->update_date( $subscription_id, $date_type, 0 );
	}

	/**
	 * When a subscription's status is updated, maybe schedule an event
	 *
	 * @param int $subscription_id The ID for a WC_Subscription object
	 * @param string $date_type Can be 'start', 'trial_end', 'next_payment', 'last_payment', 'end', 'end_of_prepaid_term' or a custom date type
	 * @param string $datetime A MySQL formated date/time string in the GMT/UTC timezone.
	 */
	public function update_status( $subscription_id, $old_status, $new_status ) {

		$action_args = array( 'subscription_id' => $subscription_id );

		switch ( $new_status ) {
			case 'active' :
				$subscription = wcs_get_subscription( $subscription_id );

				foreach( $this->action_hooks as $action_hook => $date_type ) {

					$next_scheduled = wc_next_scheduled_action( $action_hook, $action_args );
					$event_time     = $subscription->get_time( $date_type );

					// Maybe clear the existing schedule for this hook
					if ( false !== $next_scheduled ) {
						wc_unschedule_action( $action_hook, $hook_args );
					}

					if ( $event_time != 0 && $event_time > current_time( 'timestamp', true ) && $next_scheduled != $event_time ) {
						wc_schedule_single_action( $event_time, $action_hook, $action_args );
					}
				}
				break;
			case 'pending-cancellation' :

				$subscription = wcs_get_subscription( $subscription_id );
				//$next_payment = $subscription->get_time( 'next_payment' );
				$end_time     = $subscription->get_time( 'end' );

				// Now that we have the current times, clear the scheduled hooks
				foreach( $this->action_hooks as $action_hook => $date_type ) {
					wc_unschedule_action( $action_hook, $action_args );
				}

				// If there was a future payment, the customer has paid up until that payment date
				//if ( $next_payment > current_time( 'timestamp', true ) ) {

					//wc_schedule_single_action( $next_payment, 'scheduled_subscription_end_of_prepaid_term', $action_args );

				// If there was an expiration and no future payment, the customer has paid up until that date
				//} else
				// WC_Subscription::update_status() will set the end time to the next payment date (if there is one) so we can use this to schedule the end of prepaid term hook
				if ( $end_time > current_time( 'timestamp', true ) ) {

					wc_schedule_single_action( $end_time, 'scheduled_subscription_end_of_prepaid_term', $action_args );

				}
				break;
			case 'on-hold' :
			case 'cancelled' :
			case 'switched' :
			case 'expired' :
			case 'trash' :
				foreach( $this->action_hooks as $action_hook => $date_type ) {
					wc_unschedule_action( $action_hook, $action_args );
				}
				wc_unschedule_action( 'scheduled_subscription_end_of_prepaid_term', $action_args );
				break;
		}
	}

	/**
	 * Get the hook to use in the action scheduler for the date type
	 *
	 * @param string $date_type Can be 'start', 'trial_end', 'next_payment', 'last_payment', 'expiration', 'end_of_prepaid_term' or a custom date type
	 * @param object $subscription An instance of WC_Subscription to get the hook for
	 */
	protected function get_scheduled_action_hook( $subscription_id, $date_type ) {

		$hook = '';
		$subscription = wcs_get_subscription( $subscription_id );

		switch ( $date_type ) {
			case 'next_payment' :
				$hook = 'scheduled_subscription_payment';
				break;
			case 'trial_end' :
				$hook = 'scheduled_subscription_trial_end';
				break;
			case 'end' :
				// End dates may need either an expiration or end of prepaid term hook, depending on the status
				if ( $subscription->has_status( 'cancelled' ) ) {
					$hook = 'scheduled_subscription_end_of_prepaid_term';
				} elseif ( $subscription->has_status( 'active' ) ) {
					$hook = 'scheduled_subscription_expiration';
				}
				break;
		}

		return apply_filters( 'woocommerce_subscriptions_scheduled_action_hook', $hook, $date_type );
	}
}
