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
function wcs_get_subscription_ranges_tlc() {

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

	return $subscription_ranges;
}

/**
 * Retaining the API, it makes use of the transient functionality.
 *
 * @param string $period
 * @return bool|mixed
 */
function wcs_get_subscription_ranges( $subscription_period = '' ) {
	if ( ! is_string( $subscription_period ) ) {
		$subscription_period = '';
	}

	$subscription_ranges = WC_Subscriptions::$cache->cache_and_get( 'wcs-sub-ranges', 'wcs_get_subscription_ranges_tlc', array(), 86400 );

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
		// translators: period interval, placeholder is ordinal (eg "$10 every _2nd/3rd/4th_", etc)
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
 * @param string A rounding method, either ceil (default) or floor for anything else
 * @since 2.0
 */
function wcs_estimate_periods_between( $start_timestamp, $end_timestamp, $unit_of_time = 'month', $rounding_method = 'ceil' ) {

	if ( $end_timestamp <= $start_timestamp ) {

		$periods_until = 0;

	} elseif ( 'month' == $unit_of_time ) {

		// Calculate the number of times this day will occur until we'll be in a time after the given timestamp
		$timestamp = $start_timestamp;

		if ( 'ceil' == $rounding_method ) {
			for ( $periods_until = 0; $timestamp < $end_timestamp; $periods_until++ ) {
				$timestamp = wcs_add_months( $timestamp, 1 );
			}
		} else {
			for ( $periods_until = -1; $timestamp <= $end_timestamp; $periods_until++ ) {
				$timestamp = wcs_add_months( $timestamp, 1 );
			}
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

		$periods_until = ( 'ceil' == $rounding_method ) ? ceil( $seconds_until_timestamp / $denominator ) : floor( $seconds_until_timestamp / $denominator );
	}

	return $periods_until;
}

/**
 * Method to try to determine the period of subscriptions if data is missing. It tries the following, in order:
 *
 * - defaults to month
 * - comes up with an array of possible values given the standard time spans (day / week / month / year)
 * - ranks them
 * - discards 0 interval values
 * - discards high deviation values
 * - tries to match with passed in interval
 * - if all else fails, sorts by interval and returns the one having the lowest interval, or the first, if equal (that should
 *   not happen though)
 *
 * @param  string  $last_date   mysql date string
 * @param  string  $second_date mysql date string
 * @param  integer $interval    potential interval
 * @return string               period string
 */
function wcs_estimate_period_between( $last_date, $second_date, $interval = 1 ) {

	if ( ! is_int( $interval ) ) {
		$interval = 1;
	}

	$last_timestamp    = strtotime( $last_date );
	$second_timestamp  = strtotime( $second_date );

	$earlier_timestamp = min( $last_timestamp, $second_timestamp );
	$later_timestamp   = max( $last_timestamp, $second_timestamp );

	$days_in_month     = date( 't', $earlier_timestamp );
	$difference        = absint( $last_timestamp - $second_timestamp );
	$period_in_seconds = round( $difference / $interval );
	$possible_periods  = array();

	// check for months
	$full_months = wcs_find_full_months_between( $earlier_timestamp, $later_timestamp );

	$possible_periods['month'] = array(
		'intervals'         => $full_months['months'],
		'remainder'         => $remainder = $full_months['remainder'],
		'fraction'          => $remainder / ( 30 * DAY_IN_SECONDS ),
		'period'            => 'month',
		'days_in_month'     => $days_in_month,
		'original_interval' => $interval,
	);

	// check for different time spans
	foreach ( array( 'year' => YEAR_IN_SECONDS, 'week' => WEEK_IN_SECONDS, 'day' => DAY_IN_SECONDS ) as $time => $seconds ) {
		$possible_periods[ $time ] = array(
			'intervals'         => floor( $period_in_seconds / $seconds ),
			'remainder'         => $remainder = $period_in_seconds % $seconds,
			'fraction'          => $remainder / $seconds,
			'period'            => $time,
			'days_in_month'     => $days_in_month,
			'original_interval' => $interval,
		);
	}

	// filter out ones that are less than one period
	$possible_periods_zero_filtered = array_filter( $possible_periods, 'wcs_discard_zero_intervals' );
	if ( empty( $possible_periods_zero_filtered ) ) {
		// fall back if the difference is less than a day and return default 'day'
		return 'day';
	} else {
		$possible_periods = $possible_periods_zero_filtered;
	}

	// filter out ones that have too high of a deviation
	$possible_periods_no_hd = array_filter( $possible_periods, 'wcs_discard_high_deviations' );

	if ( count( $possible_periods_no_hd ) == 1 ) {
		// only one matched, let's return that as our best guess
		$possible_periods_no_hd = array_shift( $possible_periods_no_hd );
		return $possible_periods_no_hd['period'];
	} elseif ( count( $possible_periods_no_hd ) > 1 ) {
		$possible_periods = $possible_periods_no_hd;
	}

	// check for interval equality
	$possible_periods_interval_match = array_filter( $possible_periods, 'wcs_match_intervals' );

	if ( count( $possible_periods_interval_match ) == 1 ) {
		foreach ( $possible_periods_interval_match as $period_data ) {
			// only one matched the interval as our best guess
			return $period_data['period'];
		}
	} elseif ( count( $possible_periods_interval_match ) > 1 ) {
		$possible_periods = $possible_periods_interval_match;
	}

	// order by number of intervals and return the lowest

	usort( $possible_periods, 'wcs_sort_by_intervals' );

	$least_interval = array_shift( $possible_periods );

	return $least_interval['period'];
}

/**
 * Finds full months between two dates and the remaining seconds after the end of the last full month. Takes into account
 * leap years and variable number of days in months. Uses wcs_add_months
 *
 * @param  numeric $start_timestamp unix timestamp of a start date
 * @param  numeric $end_timestamp   unix timestamp of an end date
 * @return array                    with keys 'months' (integer) and 'remainder' (seconds, integer)
 */
function wcs_find_full_months_between( $start_timestamp, $end_timestamp ) {
	$number_of_months = 0;
	$remainder = null;
	$previous_remainder = null;

	while ( 0 <= $remainder ) {
		$previous_timestamp = $start_timestamp;
		$start_timestamp = wcs_add_months( $start_timestamp, 1 );
		$previous_remainder = $remainder;
		$remainder = $end_timestamp - $start_timestamp;

		if ( $remainder >= 0 ) {
			$number_of_months++;
		} elseif ( null === $previous_remainder ) {
			$previous_remainder = $end_timestamp - $previous_timestamp;
		}
	}

	$time_difference = array(
		'months' => $number_of_months,
		'remainder' => $previous_remainder,
	);

	return $time_difference;
}

/**
 * Used in an array_filter, removes elements where intervals are less than 0
 *
 * @param  array $array elements of an array
 * @return bool        true if at least 1 interval
 */
function wcs_discard_zero_intervals( $array ) {
	return $array['intervals'] > 0;
}

/**
 * Used in an array_filter, discards high deviation elements.
 * - for days it's 1/24th
 * - for week it's 1/7th
 * - for year it's 1/300th
 * - for month it's 1/($days_in_months-2)
 *
 * @param  array $array elements of the filtered array
 * @return bool        true if value is within deviation limit
 */
function wcs_discard_high_deviations( $array ) {
	switch ( $array['period'] ) {
		case 'year':
			return $array['fraction'] < ( 1 / 300 );
			break;
		case 'month':
			return $array['fraction'] < ( 1 / ( $array['days_in_month'] - 2 ) );
			break;
		case 'week':
			return $array['fraction'] < ( 1 / 7 );
			break;
		case 'day':
			return $array['fraction'] < ( 1 / 24 );
			break;
		default:
			return false;
	}
}

/**
 * Used in an array_filter, tries to match intervals against passed in interval
 * @param  array $array elements of filtered array
 * @return bool        true if intervals match
 */
function wcs_match_intervals( $array ) {
	return $array['intervals'] == $array['original_interval'];
}

/**
 * Used in a usort, responsible for making sure the array is sorted in ascending order by intervals
 *
 * @param  array $a one element of the sorted array
 * @param  array $b different element of the sorted array
 * @return int    0 if equal, -1 if $b is larger, 1 if $a is larger
 */
function wcs_sort_by_intervals( $a, $b ) {
	if ( $a['intervals'] == $b['intervals'] ) {
		return 0;
	}
	return ( $a['intervals'] < $b['intervals'] ) ? -1 : 1;
}

/**
 * Used in a usort, responsible for making sure the array is sorted in descending order by fraction.
 *
 * @param  array $a one element of the sorted array
 * @param  array $b different element of the sorted array
 * @return int    0 if equal, -1 if $b is larger, 1 if $a is larger
 */
function wcs_sort_by_fractions( $a, $b ) {
	if ( $a['fraction'] == $b['fraction'] ) {
		return 0;
	}
	return ( $a['fraction'] > $b['fraction'] ) ? -1 : 1;
}

/**
 * PHP on Windows does not have strptime function. Therefore this is what we're using to check
 * whether the given time is of a specific format.
 *
 * @param  string $time the mysql time string
 * @return boolean      true if it matches our mysql pattern of YYYY-MM-DD HH:MM:SS
 */
function wcs_is_datetime_mysql_format( $time ) {
	if ( ! is_string( $time ) ) {
		return false;
	}

	if ( function_exists( 'strptime' ) ) {
		$valid_time = $match = ( false !== strptime( $time, '%Y-%m-%d %H:%M:%S' ) ) ? true : false;
	} else {
		// parses for the pattern of YYYY-MM-DD HH:MM:SS, but won't check whether it's a valid timedate
		$match = preg_match( '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $time );

		// parses time, returns false for invalid dates
		$valid_time = strtotime( $time );
	}

	// magic number -2209078800 is strtotime( '1900-01-00 00:00:00' ). Needed to achieve parity with strptime
	return ( $match && false !== $valid_time && -2209078800 <= $valid_time ) ? true : false;
}
