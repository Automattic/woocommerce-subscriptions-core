<?php
/**
 * Repair subscriptions data corrupted with the v2.0.0 upgrade process
 *
 * @author		Prospress
 * @category	Admin
 * @package		WooCommerce Subscriptions/Admin/Upgrades
 * @version		2.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Repair_2_0_2 {

	/**
	 * Get a batch of subscriptions and update any that need to be repaired.
	 *
	 * @return array The counts of repaired and unrepaired subscriptions
	 */
	public static function maybe_repair_subscriptions( $batch_size ) {
		global $wpdb;

		// don't allow data to be half upgraded on a subscription in case of a script timeout or other non-recoverable error
		$wpdb->query( 'START TRANSACTION' );

		// Get any subscriptions that haven't already been checked for repair
		$subscription_ids_to_repair = get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => 'any',
			'posts_per_page' => $batch_size,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => '_wcs_repaired_2_0_2',
					'compare' => 'NOT EXISTS',
				),
			),
		) );

		$repaired_count = $unrepaired_count = 0;

		foreach ( $subscription_ids_to_repair as $subscription_id ) {

			$subscription = wcs_get_subscription( $subscription_id );

			if ( false !== $subscription && self::maybe_repair_subscription( $subscription ) ) {
				WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: repair completed', $subscription->id ) );
				$repaired_count++;
			} else {
				WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: no repair needed', $subscription->id ) );
				$unrepaired_count++;
			}

			update_post_meta( $subscription_id, '_wcs_repaired_2_0_2', 'true' );
		}

		$wpdb->query( 'COMMIT' );

		return array(
			'repaired_count'   => $repaired_count,
			'unrepaired_count' => $unrepaired_count,
		);

	}

	/**
	 * Check if a subscription was created prior to 2.0.0 and has some dates that need to be updated
	 * because the meta was borked during the 2.0.0 upgrade process. If it does, then update the dates
	 * to the new values.
	 *
	 * @return bool true if the subscription was repaired, otherwise false
	 */
	protected static function maybe_repair_subscription( $subscription ) {

		// if the subscription doesn't have an order, it must have been created in 2.0, so we can ignore it
		if ( false === $subscription->order ) {
			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: no need to repair: it has no order.', $subscription->id ) );
			return false;
		}

		// if the subscription has been cancelled, we don't need to repair it
		if ( $subscription->has_status( array( 'pending-cancel', 'cancelled' ) ) ) {
			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: no need to repair: it has cancelled status.', $subscription->id ) );
			return false;
		}

		$subscription_line_items = $subscription->get_items();

		// if the subscription has more than one line item, it must have been created in 2.0, so we can ignore it
		if ( count( $subscription_line_items ) > 1 ) {
			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: no need to repair: it has more than one line item.', $subscription->id ) );
			return false;
		}

		$subscription_line_item = array_shift( $subscription_line_items );

		// Get old order item's meta
		foreach ( $subscription->order->get_items() as $line_item_id => $line_item ) {
			if ( wcs_get_canonical_product_id( $line_item ) == wcs_get_canonical_product_id( $subscription_line_item ) ) {
				$matching_line_item = $line_item;
				break;
			}
		}

		// we couldn't find a matching line item so we can't repair it
		if ( ! isset( $matching_line_item ) ) {
			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: can not repair: it has no matching line item.', $subscription->id ) );
			return false;
		}

		$matching_line_item_meta = $matching_line_item['item_meta'];

		// if the order item doesn't have migrated subscription data, the subscription wasn't migrated from 1.5
		if ( ! isset( $matching_line_item_meta['_wcs_migrated_subscription_status'] ) && ! isset( $matching_line_item_meta['_wcs_migrated_subscription_start_date'] )  ) {
			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: no need to repair: matching line item has no migrated meta data.', $subscription->id ) );
			return false;
		}

		$dates_to_update = array();

		if ( false !== ( $repair_date = self::check_trial_end_date( $subscription, $matching_line_item_meta ) ) ) {
			$dates_to_update['trial_end'] = $repair_date;
		}

		if ( false !== ( $repair_date = self::check_next_payment_date( $subscription ) ) ) {
			$dates_to_update['next_payment'] = $repair_date;
		}

		if ( false !== ( $repair_date = self::check_end_date( $subscription, $matching_line_item_meta ) ) ) {
			$dates_to_update['end'] = $repair_date;
		}

		if ( ! empty( $dates_to_update ) ) {

			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: repairing dates = %s', $subscription->id, str_replace( array( '{', '}', '"' ), '', json_encode( $dates_to_update ) ) ) );

			try {
				$subscription->update_dates( $dates_to_update );
				WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: repaired dates = %s', $subscription->id, str_replace( array( '{', '}', '"' ), '', json_encode( $dates_to_update ) ) ) );
			} catch ( Exception $e ) {
				WCS_Upgrade_Logger::add( sprintf( '!! For subscription %d: unable to repair dates (%s), exception "%s"', $subscription->id, str_replace( array( '{', '}', '"' ), '', json_encode( $dates_to_update ) ), $e->getMessage() ) );
			}

			try {
				self::maybe_repair_status( $subscription, $matching_line_item_meta );
			} catch ( Exception $e ) {
				WCS_Upgrade_Logger::add( sprintf( '!! For subscription %d: unable to repair status. Exception: "%s"', $subscription->id, str_replace( array( '{', '}', '"' ), '', json_encode( $dates_to_update ) ), $e->getMessage() ) );
			}

			$repaired_subscription = true;

		} else {
			$repaired_subscription = false;
		}

		return $repaired_subscription;
	}

	/**
	 * If we have a trial end date and that value is not the same as the old end date prior to upgrade, it was most likely
	 * corrupted, so we will reset it to the value in meta.
	 *
	 * @param  WC_Subscription $subscription the subscription to check
	 * @param  array $former_order_item_meta the order item meta data for the line item on the original order that formerly represented the subscription
	 * @return string|bool false if the date does not need to be repaired or the new date if it should be repaired
	 */
	protected static function check_trial_end_date( $subscription, $former_order_item_meta ) {

		$new_trial_end_time = $subscription->get_time( 'trial_end' );

		if ( $new_trial_end_time > 0 ) {

			$old_trial_end_date = isset( $former_order_item_meta['_wcs_migrated_subscription_trial_expiry_date'][0] ) ? $former_order_item_meta['_wcs_migrated_subscription_trial_expiry_date'][0] : 0;

			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: old trial end date = %s.', $subscription->id, var_export( $old_trial_end_date, true ) ) );

			if ( $old_trial_end_date > 0 && $new_trial_end_time != strtotime( $old_trial_end_date ) ) {
				// the subscription had a trial end time that was different to the current value, so let's restore it
				$repair_date = $old_trial_end_date;
			} elseif ( 0 == $old_trial_end_date ) {
				// the subscription has a trial end time whereas previously it didn't, so we need it to be deleted
				$repair_date = 0;
			} else {
				$repair_date = false;
			}
		} else {
			$repair_date = false;
		}

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: repair trial end date = %s.', $subscription->id, var_export( $repair_date, true ) ) );

		return $repair_date;
	}

	/**
	 * Because the upgrader may have attempted to set an invalid end date on the subscription, it could
	 * lead to the entire date update process failing, which would mean that a next payment date would
	 * not be set even when one existed.
	 *
	 * This method checks if a given subscription has no next payment date, and if it doesn't, it checks
	 * if one was previously scheduled for the old subscription. If one was, and that date is in the future,
	 * it will pass that date back for being set on the subscription. If a date was scheduled but that is now
	 * in the past, it will recalculate it.
	 *
	 * @param  WC_Subscription $subscription the subscription to check
	 * @return string|bool false if the date does not need to be repaired or the new date if it should be repaired
	 */
	protected static function check_next_payment_date( $subscription ) {
		global $wpdb;

		// the subscription doesn't have a next payment date set, let's see if it should
		if ( 0 == $subscription->get_time( 'next_payment' ) ) {

			$old_hook_args = array(
				'user_id'          => (int) $subscription->get_user_id(),
				'subscription_key' => wcs_get_old_subscription_key( $subscription ),
			);

			// get the latest scheduled subscription payment in v1.5
			$old_next_payment_date = $wpdb->get_var( $wpdb->prepare( 
				"SELECT post_date_gmt FROM $wpdb->posts
				 WHERE post_type = %s
				 AND post_content = %s
				 AND post_title = 'scheduled_subscription_payment'
				 ORDER BY post_date_gmt DESC",
				ActionScheduler_wpPostStore::POST_TYPE,
				json_encode( $old_hook_args )
			) );

			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: old next payment date = %s.', $subscription->id, var_export( $old_next_payment_date, true ) ) );

			// we have a date, let's make sure 
			if ( null !== $old_next_payment_date ) {
				if ( strtotime( $old_next_payment_date ) <= gmdate( 'U' ) ) {
					$repair_date = $subscription->calculate_date( 'next_payment' );
					if ( 0 == $repair_date ) {
						$repair_date = false;
					}
					WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: old next payment date is in the past, setting it to %s.', $subscription->id, var_export( $repair_date, true ) ) );
				} else {
					$repair_date = $old_next_payment_date;
				}
			} else {

				// let's just double check we shouldn't have a date set by recalculating it
				$repair_date = $subscription->calculate_date( 'next_payment' );

				WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: no old next payment date, setting it to %s.', $subscription->id, var_export( $repair_date, true ) ) );
			}
		} else {
			$repair_date = false;
		}

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: repair next payment date = %s.', $subscription->id, var_export( $repair_date, true ) ) );

		return $repair_date;
	}

	/**
	 * Check if the old subscription meta had an end date recorded and make sure that end date is now being used for the new subscription.
	 *
	 * In Subscriptions prior to 2.0 a subscription could have both an end date and an expiration date. The end date represented a date in the past
	 * on which the subscription expired or was cancelled. The expiration date represented a date on which the subscription was set to expire (this
	 * could be in the past or future and could be the same as the end date or different). Because the end date is a definitive even, in this function
	 * we first check if it exists before falling back to the expiration date to check against.
	 *
	 * @param  WC_Subscription $subscription the subscription to check
	 * @param  array $former_order_item_meta the order item meta data for the line item on the original order that formerly represented the subscription
	 * @return string|bool false if the date does not need to be repaired or the new date if it should be repaired
	 */
	protected static function check_end_date( $subscription, $former_order_item_meta ) {

		$new_end_time = $subscription->get_time( 'end' );

		if ( $new_end_time > 0 ) {

			$old_end_date = isset( $former_order_item_meta['_wcs_migrated_subscription_end_date'][0] ) ? $former_order_item_meta['_wcs_migrated_subscription_end_date'][0] : 0;

			// if the subscription hadn't expired or been cancelled yet, it wouldn't have an end date, but it may still have had an expiry date, so use that instead
			if ( 0 == $old_end_date ) {
				$old_end_date = isset( $former_order_item_meta['_wcs_migrated_subscription_expiry_date'][0] ) ? $former_order_item_meta['_wcs_migrated_subscription_expiry_date'][0] : 0;
			}

			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: old end date = %s.', $subscription->id, var_export( $old_end_date, true ) ) );

			// if the subscription has an end time whereas previously it didn't, we need it to be deleted so set it 0
			if ( 0 == $old_end_date ) {
				$repair_date = 0;
			} else {
				$repair_date = false;
			}
		} else {
			$repair_date = false;
		}

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: repair end date = %s.', $subscription->id, var_export( $repair_date, true ) ) );

		return $repair_date;
	}

	/**
	 * If the subscription has expired since upgrading and the end date is not the original expiration date,
	 * we need to unexpire it, which in the case of a previously active subscription means activate it, and
	 * in any other case, leave it as on-hold (a cancelled subscription wouldn't have been expired, so the
	 * status must be on-hold or active).
	 *
	 * @param  WC_Subscription $subscription data about the subscription
	 * @return bool true if the trial date was repaired, otherwise false
	 */
	protected static function maybe_repair_status( $subscription, $former_order_item_meta ) {

		if ( $subscription->has_status( 'expired' ) && 'expired' != $former_order_item_meta['_wcs_migrated_subscription_status'][0] && $subscription->get_date( 'end' ) != $former_order_item_meta['_wcs_migrated_subscription_expiry_date'][0] ) {

			if ( $subscription->payment_method_supports( 'subscription_date_changes' ) ) {

				// we need to bypass the update_status() method here because normally an expired subscription can't have it's status changed, we also don't want normal status change hooks to be fired
				wp_update_post( array( 'ID' => $subscription->id, 'post_status' => 'wc-on-hold' ) );

				if ( 'active' == $former_order_item_meta['_wcs_migrated_subscription_status'][0] ) {
					$subscription->update_status( 'active' );
				}
				WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: repaired status. Status was "expired", it is now "%s".', $subscription->id, $subscription->get_status() ) );
				$repair_status = true;
			} else {
				WCS_Upgrade_Logger::add( sprintf( '!!! For subscription %d: unable to repair status, payment method does not support "subscription_date_changes".', $subscription->id ) );
				$repair_status = false;
			}
		} else {
			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: no need to repair status, current status: %s; former status: %s.', $subscription->id, $subscription->get_status(), $former_order_item_meta['_wcs_migrated_subscription_status'][0] ) );
			$repair_status = false;
		}
		return $repair_status;
	}
}
