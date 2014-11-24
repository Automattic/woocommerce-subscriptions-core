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

