<?php
/**
 * WooCommerce Subscriptions Temporal Functions
 *
 * Functions for time values and ranges
 *
 * @author 		Prospress
 * @category 	Core
 * @package 	WooCommerce Subscriptions/Functions
 * @version     2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Return an i18n'ified associative array of all possible subscription periods.
 *
 * @param int (optional) An interval in the range 1-6
 * @param string (optional) One of day, week, month or year. If empty, all subscription ranges are returned.
 * @since 2.0
 */
function wcs_get_subscription_period_strings( $number = 1, $period = '' ) {

	$translated_periods = apply_filters( 'woocommerce_subscription_periods',
		array(
			'day'   => sprintf( _n( 'day', '%s days', $number, 'woocommerce-subscriptions' ), $number ),
			'week'  => sprintf( _n( 'week', '%s weeks', $number, 'woocommerce-subscriptions' ), $number ),
			'month' => sprintf( _n( 'month', '%s months', $number, 'woocommerce-subscriptions' ), $number ),
			'year'  => sprintf( _n( 'year', '%s years', $number, 'woocommerce-subscriptions' ), $number ),
		)
	);

	return ( ! empty( $period ) ) ? $translated_periods[ $period ] : $translated_periods;
}

/**
 * Return an i18n'ified associative array of all possible subscription trial periods.
 *
 * @param int (optional) An interval in the range 1-6
 * @param string (optional) One of day, week, month or year. If empty, all subscription ranges are returned.
 * @since 2.0
 */
function wcs_get_subscription_trial_period_strings( $number = 1, $period = '' ) {

	$translated_periods = apply_filters( 'woocommerce_subscription_trial_periods',
		array(
			'day'   => sprintf( _n( '%s day', 'a %s-day', $number, 'woocommerce-subscriptions' ), $number ),
			'week'  => sprintf( _n( '%s week', 'a %s-week', $number, 'woocommerce-subscriptions' ), $number ),
			'month' => sprintf( _n( '%s month', 'a %s-month', $number, 'woocommerce-subscriptions' ), $number ),
			'year'  => sprintf( _n( '%s year', 'a %s-year', $number, 'woocommerce-subscriptions' ), $number ),
		)
	);

	return ( ! empty( $period ) ) ? $translated_periods[ $period ] : $translated_periods;
}

/**
 * Returns an array of subscription lengths.
 *
 * PayPal Standard Allowable Ranges
 * D – for days; allowable range is 1 to 90
 * W – for weeks; allowable range is 1 to 52
 * M – for months; allowable range is 1 to 24
 * Y – for years; allowable range is 1 to 5
 *
 * @param string (optional) One of day, week, month or year. If empty, all subscription ranges are returned.
 * @since 2.0
 */
function wcs_get_subscription_ranges( $subscription_period = '' ) {

	$subscription_periods = wcs_get_subscription_period_strings();

	foreach ( array( 'day', 'week', 'month', 'year' ) as $period ) {

		$subscription_lengths = array(
			_x( 'all time', 'Subscription length (eg "$10 per month for _all time_")', 'woocommerce-subscriptions' ),
		);

		switch ( $period ) {
			case 'day':
				$subscription_lengths[] = __( '1 day', 'woocommerce-subscriptions' );
				$subscription_range = range( 2, 90 );
				break;
			case 'week':
				$subscription_lengths[] = __( '1 week', 'woocommerce-subscriptions' );
				$subscription_range = range( 2, 52 );
				break;
			case 'month':
				$subscription_lengths[] = __( '1 month', 'woocommerce-subscriptions' );
				$subscription_range = range( 2, 24 );
				break;
			case 'year':
				$subscription_lengths[] = __( '1 year', 'woocommerce-subscriptions' );
				$subscription_range = range( 2, 5 );
				break;
		}

		foreach ( $subscription_range as $number ) {
			$subscription_range[ $number ] = wcs_get_subscription_period_strings( $number, $period );
		}

		// Add the possible range to all time range
		$subscription_lengths += $subscription_range;

		$subscription_ranges[ $period ] = $subscription_lengths;
	}

	$subscription_ranges = apply_filters( 'woocommerce_subscription_lengths', $subscription_ranges, $subscription_period );

	if ( ! empty( $subscription_period ) ) {
		return $subscription_ranges[ $subscription_period ];
	} else {
		return $subscription_ranges;
	}
}

/**
 * Return an i18n'ified associative array of all possible subscription periods.
 *
 * @param int (optional) An interval in the range 1-6
 * @since 2.0
 */
function wcs_get_subscription_period_interval_strings( $interval = '' ) {

	$intervals = array( 1 => _x( 'every', 'period interval (eg "$10 _every_ 2 weeks")', 'woocommerce-subscriptions' ) );

	foreach ( range( 2, 6 ) as $i ) {
		/* translators: period interval, placeholder is numeral (eg "$10 _every 2nd/3rd/4th_", etc) */
		$intervals[ $i ] = sprintf( __( 'every %s', 'woocommerce-subscriptions' ), WC_Subscriptions::append_numeral_suffix( $i ) );
	}

	$intervals = apply_filters( 'woocommerce_subscription_period_interval_strings', $intervals );

	if ( empty( $interval ) ) {
		return $intervals;
	} else {
		return $intervals[ $interval ];
	}
}

/**
 * Return an i18n'ified associative array of all time periods allowed for subscriptions.
 *
 * @param string (Optional) Either 'singular' for singular trial periods or 'plural'.
 * @since 2.0
 */
function wcs_get_available_time_periods( $form = 'singular' ) {

	$number = ( 'singular' === $form ) ? 1 : 2;

	$translated_periods = apply_filters( 'woocommerce_subscription_available_time_periods',
		array(
			'day'   => _n( 'day', 'days', $number, 'woocommerce-subscriptions' ),
			'week'  => _n( 'week', 'weeks', $number, 'woocommerce-subscriptions' ),
			'month' => _n( 'month', 'months', $number, 'woocommerce-subscriptions' ),
			'year'  => _n( 'year', 'years', $number, 'woocommerce-subscriptions' ),
		)
	);

	return $translated_periods;
}

/**
 * Returns an array of allowed trial period lengths.
 *
 * @param string (optional) One of day, week, month or year. If empty, all subscription trial period lengths are returned.
 * @since 2.0
 */
function wcs_get_subscription_trial_lengths( $subscription_period = '' ) {

	$all_trial_periods = wcs_get_subscription_ranges();

	foreach ( $all_trial_periods as $period => $trial_periods ) {
		$all_trial_periods[ $period ][0] = _x( 'no', 'no trial period', 'woocommerce-subscriptions' );
	}

	if ( ! empty( $subscription_period ) ) {
		return $all_trial_periods[ $subscription_period ];
	} else {
		return $all_trial_periods;
	}
}

/**
 * Convenience wrapper for adding "{n} {periods}" to a timestamp (e.g. 2 months or 5 days).
 *
 * @param int The number of periods to add to the timestamp
 * @param string One of day, week, month or year.
 * @param int A Unix timestamp to add the time too.
 * @since 2.0
 */
function wcs_add_time( $number_of_periods, $period, $from_timestamp ) {

	if ( 'month' == $period ) {
		$next_timestamp = wcs_add_months( $from_timestamp, $number_of_periods );
	} else {
		$next_timestamp = strtotime( "+ {$number_of_periods} {$period}", $from_timestamp );
	}

	return $next_timestamp;
}

/**
 * Workaround the last day of month quirk in PHP's strtotime function.
 *
 * Adding +1 month to the last day of the month can yield unexpected results with strtotime().
 * For example:
 * - 30 Jan 2013 + 1 month = 3rd March 2013
 * - 28 Feb 2013 + 1 month = 28th March 2013
 *
 * What humans usually want is for the date to continue on the last day of the month.
 *
 * @param int A Unix timestamp to add the months too.
 * @param int The number of months to add to the timestamp.
 * @since 2.0
 */
function wcs_add_months( $from_timestamp, $months_to_add ) {

	$first_day_of_month = date( 'Y-m', $from_timestamp ) . '-1';
	$days_in_next_month = date( 't', strtotime( "+ {$months_to_add} month", strtotime( $first_day_of_month ) ) );

	// Payment is on the last day of the month OR number of days in next billing month is less than the the day of this month (i.e. current billing date is 30th January, next billing date can't be 30th February)
	if ( date( 'd m Y', $from_timestamp ) === date( 't m Y', $from_timestamp ) || date( 'd', $from_timestamp ) > $days_in_next_month ) {
		for ( $i = 1; $i <= $months_to_add; $i++ ) {
			$next_month = strtotime( '+ 3 days', $from_timestamp ); // Add 3 days to make sure we get to the next month, even when it's the 29th day of a month with 31 days
			$next_timestamp = $from_timestamp = strtotime( date( 'Y-m-t H:i:s', $next_month ) ); // NB the "t" to get last day of next month
		}
	} else { // Safe to just add a month
		$next_timestamp = strtotime( "+ {$months_to_add} month", $from_timestamp );
	}

	return $next_timestamp;
}

/**
 * Estimate how many days, weeks, months or years there are between now and a given
 * date in the future. Estimates the minimum total of periods.
 *
 * @param int A Unix timestamp at some time in the future.
 * @param string A unit of time, either day, week month or year.
 * @since 2.0
 */
function wcs_estimate_periods_between( $start_timestamp, $end_timestamp, $unit_of_time = 'month' ) {

	if ( $end_timestamp <= $start_timestamp ) {

		$periods_until = 0;

	} elseif ( 'month' == $unit_of_time ) {

		// Calculate the number of times this day will occur until we'll be in a time after the given timestamp
		$timestamp = $start_timestamp;

		for ( $periods_until = 0; $timestamp < $end_timestamp; $periods_until++ ) {
			$timestamp = wcs_add_months( $timestamp, 1 );
		}
	} else {

		$seconds_until_timestamp = $end_timestamp - $start_timestamp;

		switch ( $unit_of_time ) {

			case 'day' :
				$denominator = DAY_IN_SECONDS;
				break;

			case 'week' :
				$denominator = WEEK_IN_SECONDS;
				break;

			case 'year' :
				$denominator = YEAR_IN_SECONDS;
				break;
		}

		$periods_until = floor( $seconds_until_timestamp / $denominator ); // use floor() because we want the total number of complete periods between now and the given timestamp

	}

	return $periods_until;
}
