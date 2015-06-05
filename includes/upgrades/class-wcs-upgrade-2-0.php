<?php
/**
 * Upgrade subscriptions data to v2.0
 *
 * @author		Prospress
 * @category	Admin
 * @package		WooCommerce Subscriptions/Admin/Upgrades
 * @version		2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Upgrade_2_0 {

	/* Cache of order item meta keys that were used to store subscription data in v1.5 */
	private static $subscription_item_meta_keys = array(
		'_recurring_line_total',
		'_recurring_line_tax',
		'_recurring_line_subtotal',
		'_recurring_line_subtotal_tax',
		'_recurring_line_tax_data',
		'_subscription_suspension_count',
		'_subscription_period',
		'_subscription_interval',
		'_subscription_trial_length',
		'_subscription_trial_period',
		'_subscription_length',
		'_subscription_sign_up_fee',
		'_subscription_failed_payments',
		'_subscription_recurring_amount',
		'_subscription_start_date',
		'_subscription_trial_expiry_date',
		'_subscription_expiry_date',
		'_subscription_end_date',
		'_subscription_status',
		'_subscription_completed_payments',
	);

	/**
	 * Migrate subscriptions out of order item meta and into post/post meta tables for their own post type.
	 *
	 * @since 2.0
	 */
	public static function upgrade_subscriptions( $batch_size ) {
		global $wpdb;

		WCS_Upgrade_Logger::add( sprintf( 'Upgrading batch of %d subscriptions', $batch_size ) );

		$upgraded_subscription_count = 0;

		foreach ( self::get_subscriptions( $batch_size ) as $original_order_item_id => $old_subscription ) {

			try {

				// don't allow data to be half upgraded on a subscription (but we need the subscription to be the atomic level, not the whole batch, to ensure that resubscribe and switch updates in the same batch have the new subscription available)
				$wpdb->query( 'START TRANSACTION' );

				WCS_Upgrade_Logger::add( sprintf( 'For order %d: beginning subscription upgrade process', $old_subscription['order_id'] ) );

				$original_order = wc_get_order( $old_subscription['order_id'] );

				// If we're still in a prepaid term, the new subscription has the new pending cancellation status
				if ( 'cancelled' == $old_subscription['status'] && false != wc_next_scheduled_action( 'subscription_end_of_prepaid_term', array( 'user_id' => $old_subscription['user_id'], 'subscription_key' => $old_subscription['subscription_key'] ) ) ) {
					$subscription_status = 'pending-cancel';
				} elseif ( 'trash' == $old_subscription['status'] ) {
					$subscription_status = 'cancelled'; // we'll trash it properly after migrating it
				} else {
					$subscription_status = $old_subscription['status'];
				}

				// Create a new subscription for this user
				$new_subscription = wcs_create_subscription( array(
					'status'           => $subscription_status,
					'order_id'         => $old_subscription['order_id'],
					'customer_id'      => $old_subscription['user_id'],
					'start_date'       => $old_subscription['start_date'],
					'billing_period'   => $old_subscription['period'],
					'billing_interval' => $old_subscription['interval'],
					'order_version'    => ( ! empty( $original_order->order_version ) ) ? $original_order->order_version : '', // Subscriptions will default to WC_Version if $original_order->order_version is not set, but we want the version set at the time of the order
				) );

				if ( ! is_wp_error( $new_subscription ) ) {

					WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: post created', $new_subscription->id ) );

					// Add the line item from the order
					$subscription_item_id = self::add_product( $new_subscription, $original_order_item_id, wcs_get_order_item( $original_order_item_id, $original_order ) );

					// Set dates on the subscription
					self::migrate_dates( $new_subscription, $old_subscription );

					// If the subscription was in the trash, now that we've set on the meta on it, we need to trash it
					if ( 'trash' == $old_subscription['status'] ) {
						wp_trash_post( $new_subscription->id );
					}

					WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: upgrade complete', $new_subscription->id ) );

				} else {

					self::deprecate_item_meta( $original_order_item_id );

					WCS_Upgrade_Logger::add( sprintf( '!!! For order %d: unable to create subscription. Error: %s', $old_subscription['order_id'], $new_subscription->get_error_message() ) );

				}

				// If we got here, the batch was upgraded without problems
				$wpdb->query( 'COMMIT' );

				$upgraded_subscription_count++;

			} catch ( Exception $e ) {

				// we couldn't upgrade this subscription don't commit the query
				$wpdb->query( 'ROLLBACK' );

				throw $e;
			}

			if ( $upgraded_subscription_count >= $batch_size ) {
				break;
			}
		}

		WCS_Upgrade_Logger::add( sprintf( 'Upgraded batch of %d subscriptions', $upgraded_subscription_count ) );

		return $upgraded_subscription_count;
	}

	/**
	 * Gets an array of subscriptions from the v1.5 database structure and returns them in the in the v1.5 structure of
	 * 'order_item_id' => subscripton details array().
	 *
	 * The subscription will be orders from oldest to newest, which is important because self::migrate_resubscribe_orders()
	 * method expects a subscription to exist in order to migrate the resubscribe meta data correctly.
	 *
	 * @param int $batch_size The number of subscriptions to return.
	 * @return array Subscription details in the v1.5 structure of 'order_item_id' => array()
	 * @since 2.0
	 */
	private static function get_subscriptions( $batch_size ) {
		global $wpdb;

		$query = sprintf(
			"SELECT meta.*, items.* FROM `{$wpdb->prefix}woocommerce_order_itemmeta` AS meta
			LEFT JOIN `{$wpdb->prefix}woocommerce_order_items` AS items USING (order_item_id)
			LEFT JOIN (
				SELECT a.order_item_id FROM `{$wpdb->prefix}woocommerce_order_itemmeta` AS a
				LEFT JOIN (
					SELECT `{$wpdb->prefix}woocommerce_order_itemmeta`.order_item_id FROM `{$wpdb->prefix}woocommerce_order_itemmeta`
					WHERE `{$wpdb->prefix}woocommerce_order_itemmeta`.meta_key = '_subscription_status'
				) AS s
				USING (order_item_id)
				WHERE 1=1
				AND a.order_item_id = s.order_item_id
				AND a.meta_key = '_subscription_start_date'
				ORDER BY CASE WHEN CAST(a.meta_value AS DATETIME) IS NULL THEN 1 ELSE 0 END, CAST(a.meta_value AS DATETIME) ASC
				LIMIT 0, %s
			) AS a3 USING (order_item_id)
			WHERE meta.meta_key REGEXP '_subscription_(.*)|_product_id|_variation_id'
			AND meta.order_item_id = a3.order_item_id", $batch_size );

		$wpdb->query( 'SET SQL_BIG_SELECTS = 1;' );

		$raw_subscriptions = $wpdb->get_results( $query );

		$subscriptions = array();

		// Create a backward compatible structure
		foreach ( $raw_subscriptions as $raw_subscription ) {

			if ( ! isset( $raw_subscription->order_item_id ) ) {
				continue;
			}

			if ( ! array_key_exists( $raw_subscription->order_item_id, $subscriptions ) ) {
				$subscriptions[ $raw_subscription->order_item_id ] = array(
					'order_id' => $raw_subscription->order_id,
					'name'     => $raw_subscription->order_item_name,
				);

				$subscriptions[ $raw_subscription->order_item_id ]['user_id'] = get_post_meta( $raw_subscription->order_id, '_customer_user', true );
			}

			$meta_key = str_replace( '_subscription', '', $raw_subscription->meta_key );
			$meta_key = substr( $meta_key, 0, 1 ) == '_' ? substr( $meta_key, 1 ) : $meta_key;

			if ( 'product_id' === $meta_key ) {
				$subscriptions[ $raw_subscription->order_item_id ]['subscription_key'] = $subscriptions[ $raw_subscription->order_item_id ]['order_id'] . '_' . $raw_subscription->meta_value;
			}

			$subscriptions[ $raw_subscription->order_item_id ][ $meta_key ] = maybe_unserialize( $raw_subscription->meta_value );
		}

		return $subscriptions;
	}

	/**
	 * Add the details of an order item to a subscription as a produdct line item.
	 *
	 * When adding a product to a subscription, we can't use WC_Abstract_Order::add_product() because it requires a product object
	 * and the details of the product may have changed since it was purchased so we can't simply instantiate an instance of the
	 * product based on ID.
	 *
	 * @param WC_Subscription $new_subscription A subscription object
	 * @param int $order_item_id ID of the subscription item on the original order
	 * @param array $order_item An array of order item data in the form returned by WC_Abstract_Order::get_items()
	 * @return int Subscription $item_id The order item id of the new line item added to the subscription.
	 * @since 2.0
	 */
	private static function add_product( $new_subscription, $order_item_id, $order_item ) {

		$item_id = wc_add_order_item( $new_subscription->id, array(
			'order_item_name' => $order_item['name'],
			'order_item_type' => 'line_item'
		) );

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: new line item ID %d added', $new_subscription->id, $item_id ) );

		wc_add_order_item_meta( $item_id, '_qty',          $order_item['qty'] );
		wc_add_order_item_meta( $item_id, '_tax_class',    $order_item['tax_class'] );
		wc_add_order_item_meta( $item_id, '_product_id',   $order_item['product_id'] );
		wc_add_order_item_meta( $item_id, '_variation_id', $order_item['variation_id'] );

		// Set line item totals, either passed in or from the product
		wc_add_order_item_meta( $item_id, '_line_subtotal',     $order_item['recurring_line_subtotal'] );
		wc_add_order_item_meta( $item_id, '_line_total',        $order_item['recurring_line_total'] );
		wc_add_order_item_meta( $item_id, '_line_subtotal_tax', $order_item['recurring_line_subtotal_tax'] );
		wc_add_order_item_meta( $item_id, '_line_tax',          $order_item['recurring_line_tax'] );

		// Save tax data array added in WC 2.2 (so it won't exist for all orders/subscriptions)
		self::add_line_tax_data( $item_id, $order_item_id, $order_item );

		if ( $order_item['subscription_trial_length'] > 0 ) {
			wc_add_order_item_meta( $item_id, '_has_trial', 'true' );
		}

		// Don't copy item meta already copied
		$reserved_item_meta_keys = array(
			'_item_meta',
			'_qty',
			'_tax_class',
			'_product_id',
			'_variation_id',
			'_line_subtotal',
			'_line_total',
			'_line_tax',
			'_line_tax_data',
			'_line_subtotal_tax',
		);

		$meta_keys_to_copy = array_diff( array_keys( $order_item['item_meta'] ), array_merge( $reserved_item_meta_keys, self::$subscription_item_meta_keys ) );

		// Add variation and any other meta
		foreach ( $meta_keys_to_copy as $meta_key ) {
			foreach( $order_item['item_meta'][ $meta_key ] as $meta_value ) {
				wc_add_order_item_meta( $item_id, $meta_key, $meta_value );
			}
		}

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: for item %d added %s', $new_subscription->id, $item_id, implode( ', ', $meta_keys_to_copy ) ) );

		// Now that we've copied over the old data, prefix some the subscription meta keys with _wcs_migrated to deprecate it without deleting it (yet)
		$rows_affected = self::deprecate_item_meta( $order_item_id );

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: %s rows of line item meta deprecated', $new_subscription->id, $rows_affected ) );

		return $item_id;
	}

	/**
	 * Copy or recreate line tax data to the new subscription.
	 *
	 * @param int $new_order_item_id ID of the line item on the new subscription post type
	 * @param int $old_order_item_id ID of the line item on the original order that in v1.5 represented the subscription
	 * @param array $old_order_item The line item on the original order that in v1.5 represented the subscription
	 * @since 2.0
	 */
	private static function add_line_tax_data( $new_order_item_id, $old_order_item_id, $old_order_item ) {

		// If we have _recurring_line_tax_data, use that
		if ( isset( $order_item['item_meta']['_recurring_line_tax_data'] ) ) {

			$line_tax_data      = maybe_unserialize( $order_item['item_meta']['_recurring_line_tax_data'][0] );
			$recurring_tax_data = array();
			$tax_data_keys      = array( 'total', 'subtotal' );

			foreach( $tax_data_keys as $tax_data_key ) {
				foreach( $line_tax_data[ $tax_data_key ] as $tax_index => $tax_value ) {
					$recurring_tax_data[ $tax_data_key ][ $tax_index ] = wc_format_decimal( $tax_value );
				}
			}

			wc_add_order_item_meta( $new_order_item_id, '_line_tax_data', $recurring_tax_data );

		// Otherwise try to calculate the recurring values from _line_tax_data
		} elseif ( isset( $order_item['item_meta']['_line_tax_data'] ) ) {

			// Copy line tax data if the order doesn't have a '_recurring_line_tax_data' (for backward compatibility)
			$line_tax_data        = maybe_unserialize( $order_item['item_meta']['_line_tax_data'][0] );
			$line_total           = maybe_unserialize( $order_item['item_meta']['_line_total'][0] );
			$recurring_line_total = maybe_unserialize( $order_item['item_meta']['_recurring_line_total'][0] );

			// There will only be recurring tax data if the recurring amount is > 0 and we can only retroactively calculate recurring amount from initial amoutn if it is > 0
			if ( $line_total > 0 && $recurring_line_total > 0) {

				// Make sure we account for any sign-up fees by determining what proportion of the initial amount the recurring total represents
				$recurring_ratio = $recurring_line_total / $line_total;

				$recurring_tax_data = array();
				$tax_data_keys      = array( 'total', 'subtotal' );

				foreach( $tax_data_keys as $tax_data_key ) {
					foreach( $line_tax_data[ $tax_data_key ] as $tax_index => $tax_value ) {

						// Use total tax amount for both total and subtotal because we don't want any initial discounts to be applied to recurring amounts
						$total_tax_amount = $line_tax_data['total'][ $tax_index ];

						$recurring_tax_data[ $tax_data_key ][ $tax_index ] = wc_format_decimal( $failed_payment_multiplier * ( $recurring_ratio * $total_tax_amount ) );
					}
				}
			} else {
				$recurring_tax_data = array( 'total' => array(), 'subtotal' => array() );
			}

			wc_add_order_item_meta( $new_order_item_id, '_line_tax_data', $recurring_tax_data );
		}
	}

	/**
	 * Deprecate order item meta data stored on the original order that used to make up the subscription by prefixing it with with '_wcs_migrated'
	 *
	 * @param int $order_item_id ID of the subscription item on the original order
	 * @since 2.0
	 */
	private static function deprecate_item_meta( $order_item_id ) {
		global $wpdb;

		// Now that we've copied over the old data, prefix some the subscription meta keys with _wcs_migrated to deprecate it without deleting it (yet)
		$subscription_item_meta_key_string = implode( "','", esc_sql( self::$subscription_item_meta_keys ) );

		$rows_affected = $wpdb->query( $wpdb->prepare(
			"UPDATE `{$wpdb->prefix}woocommerce_order_itemmeta` SET `meta_key` = concat( '_wcs_migrated', `meta_key` )
			WHERE `order_item_id` = %d AND `meta_key` IN ('{$subscription_item_meta_key_string}')",
			$order_item_id
		) );

		return $rows_affected;
	}

	/**
	 * Migrate the trial expiration, next payment and expiration/end dates to a new subscription.
	 *
	 * @since 2.0
	 */
	private static function migrate_dates( $new_subscription, $old_subscription ) {

		$dates_to_update = array();

		// old hook => new hook
		$date_keys = array(
			'trial_end' => array(
				'old_subscription_key' => 'trial_expiry_date',
				'old_scheduled_hook'   => 'scheduled_subscription_trial_end',
			),
			'end' => array(
				'old_subscription_key' => 'expiry_date',
				'old_scheduled_hook'   => 'scheduled_subscription_expiration',
			),
			'end_date' => array(
				'old_subscription_key' => '_subscription_end_date', // this is the actual end date, not just the date it was scheduled to expire
				'old_scheduled_hook'   => '',
			),
			'next_payment' => array(
				'old_subscription_key' => '',
				'old_scheduled_hook'   => 'scheduled_subscription_payment',
			),
			'end_of_prepaid_term' => array(
				'old_subscription_key' => '',
				'old_scheduled_hook'   => 'subscription_end_of_prepaid_term',
			),
		);

		$old_hook_args = array(
			'user_id'          => $old_subscription['user_id'],
			'subscription_key' => $old_subscription['subscription_key']
		);

		foreach ( $date_keys as $new_key => $old_keys ) {

			// First check if there is a date stored on the subscription, and if so, use that
			if ( ! empty( $old_keys['old_subscription_key'] ) && ( isset( $old_subscription[ $old_keys['old_subscription_key'] ] ) && 0 !== $old_subscription[ $old_keys['old_subscription_key'] ] ) ) {

				$dates_to_update[ $new_key ] = $old_subscription[ $old_keys['old_subscription_key'] ];

			} elseif ( ! empty( $old_keys['old_scheduled_hook'] ) ) {

				// Now check if there is a scheduled date, this is for next payment and end of prepaid term dates
				$next_scheduled = wc_next_scheduled_action( $old_keys['old_scheduled_hook'], $old_hook_args );

				if ( $next_scheduled > 0 ) {

					if ( $new_key == '' ) {
						wc_schedule_single_action( $next_scheduled, 'woocommerce_scheduled_subscription_end_of_prepaid_term', array( 'subscription_id' => $new_subscription->id ) );
					} else {
						$dates_to_update[ $new_key ] = date( 'Y-m-d H:i:s', $next_scheduled );
					}

					wc_unschedule_action( $old_keys['old_scheduled_hook'], $old_hook_args );
				}
			}
		}

		if ( ! empty( $dates_to_update ) ) {
			$new_subscription->update_dates( $dates_to_update );
		}

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: updated dates = %s', $new_subscription->id, str_replace( array( '{', '}', '"' ), '', json_encode( $dates_to_update ) ) ) );
	}
}