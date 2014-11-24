<?php
/**
 * WooCommerce Time Functions
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
			'year'  => sprintf( _n( 'year', '%s years', $number, 'woocommerce-subscriptions' ), $number )
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
			'year'  => sprintf( _n( '%s year', 'a %s-year', $number, 'woocommerce-subscriptions' ), $number )
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
			__( 'all time', 'woocommerce-subscriptions' ),
		);

		switch( $period ) {
			case 'day':
				$subscription_lengths[] = __( '1 day', 'woocommerce-subscriptions' );
				break;
			case 'week':
				$subscription_lengths[] = __( '1 week', 'woocommerce-subscriptions' );
				break;
			case 'month':
				$subscription_lengths[] = __( '1 month', 'woocommerce-subscriptions' );
				break;
			case 'year':
				$subscription_lengths[] = __( '1 year', 'woocommerce-subscriptions' );
				break;
		}

		switch( $period ) {
			case 'day':
				$subscription_range = range( 2, 90 );
				break;
			case 'week':
				$subscription_range = range( 2, 52 );
				break;
			case 'month':
				$subscription_range = range( 2, 24 );
				break;
			case 'year':
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

