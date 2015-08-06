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

		WC()->payment_gateways();

		WCS_Upgrade_Logger::add( sprintf( 'Upgrading batch of %d subscriptions', $batch_size ) );

		$upgraded_subscription_count = 0;

		$execution_time_start = time();

		foreach ( self::get_subscriptions( $batch_size ) as $original_order_item_id => $old_subscription ) {

			try {

				self::maybe_repair_subscription( $old_subscription, $original_order_item_id );

				// don't allow data to be half upgraded on a subscription (but we need the subscription to be the atomic level, not the whole batch, to ensure that resubscribe and switch updates in the same batch have the new subscription available)
				$wpdb->query( 'START TRANSACTION' );

				WCS_Upgrade_Logger::add( sprintf( 'For order %d: beginning subscription upgrade process', $old_subscription['order_id'] ) );

				$original_order = wc_get_order( $old_subscription['order_id'] );

				// If we're still in a prepaid term, the new subscription has the new pending cancellation status
				if ( 'cancelled' == $old_subscription['status'] && false != wc_next_scheduled_action( 'scheduled_subscription_end_of_prepaid_term', array( 'user_id' => $old_subscription['user_id'], 'subscription_key' => $old_subscription['subscription_key'] ) ) ) {
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

					// Set the order to be manual
					if ( isset( $original_order->wcs_requires_manual_renewal ) && 'true' == $original_order->wcs_requires_manual_renewal ) {
						$new_subscription->update_manual( true );
					}

					// Add the line item from the order
					$subscription_item_id = self::add_product( $new_subscription, $original_order_item_id, wcs_get_order_item( $original_order_item_id, $original_order ) );

					// Add the line item from the order
					self::migrate_download_permissions( $new_subscription, $subscription_item_id, $original_order );

					// Set dates on the subscription
					self::migrate_dates( $new_subscription, $old_subscription );

					// Set some meta from order meta
					self::migrate_post_meta( $new_subscription->id, $original_order );

					// Copy over order notes which are now logged on the subscription
					self::migrate_order_notes( $new_subscription->id, $original_order->id );

					// Migrate recurring tax, shipping and coupon line items to be plain line items on the subscription
					self::migrate_order_items( $new_subscription->id, $original_order->id );

					// Update renewal orders to link via post meta key instead of post_parent column
					self::migrate_renewal_orders( $new_subscription->id, $original_order->id );

					// Make sure the resubscribe meta data is migrated to use the new subscription ID + meta key
					self::migrate_resubscribe_orders( $new_subscription->id, $original_order->id );

					// If the order for this subscription contains a switch, make sure the switch meta data is migrated to use the new subscription ID + meta key
					self::migrate_switch_meta( $new_subscription, $original_order, $subscription_item_id );

					// If the subscription was in the trash, now that we've set on the meta on it, we need to trash it
					if ( 'trash' == $old_subscription['status'] ) {
						wp_trash_post( $new_subscription->id );
					}

					WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: upgrade complete', $new_subscription->id ) );

				} else {

					self::deprecate_item_meta( $original_order_item_id );

					self::deprecate_post_meta( $old_subscription['order_id'] );

					WCS_Upgrade_Logger::add( sprintf( '!!! For order %d: unable to create subscription. Error: %s', $old_subscription['order_id'], $new_subscription->get_error_message() ) );

				}

				// If we got here, the batch was upgraded without problems
				$wpdb->query( 'COMMIT' );

				$upgraded_subscription_count++;

			} catch ( Exception $e ) {

				// We can still recover from here.
				if ( 422 == $e->getCode() ) {

					self::deprecate_item_meta( $original_order_item_id );

					self::deprecate_post_meta( $old_subscription['order_id'] );

					WCS_Upgrade_Logger::add( sprintf( '!!! For order %d: unable to create subscription. Error: %s', $old_subscription['order_id'], $e->getMessage() ) );

					$wpdb->query( 'COMMIT' );

					$upgraded_subscription_count++;

				} else {
					// we couldn't upgrade this subscription don't commit the query
					$wpdb->query( 'ROLLBACK' );

					throw $e;
				}
			}

			if ( $upgraded_subscription_count >= $batch_size || ( array_key_exists( 'WPENGINE_ACCOUNT', $_SERVER ) && ( time() - $execution_time_start ) > 50 ) ) {
				break;
			}
		}


		// Double check we actually have no more subscriptions to upgrade as sometimes they can fall through the cracks
		if ( $upgraded_subscription_count < $batch_size && $upgraded_subscription_count > 0 && ! array_key_exists( 'WPENGINE_ACCOUNT', $_SERVER ) ) {
			$upgraded_subscription_count += self::upgrade_subscriptions( $batch_size );
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

		$query = WC_Subscriptions_Upgrader::get_subscription_query( $batch_size );

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

				$subscriptions[ $raw_subscription->order_item_id ]['user_id'] = (int) get_post_meta( $raw_subscription->order_id, '_customer_user', true );
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
		global $wpdb;

		$item_id = wc_add_order_item( $new_subscription->id, array(
			'order_item_name' => $order_item['name'],
			'order_item_type' => 'line_item',
		) );

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: new line item ID %d added', $new_subscription->id, $item_id ) );

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO `{$wpdb->prefix}woocommerce_order_itemmeta` (`order_item_id`, `meta_key`, `meta_value`)
			 VALUES
				(%d, '_qty', %s),
				(%d, '_tax_class', %s),
				(%d, '_product_id', %s),
				(%d, '_variation_id', %s),
				(%d, '_line_subtotal', %s),
				(%d, '_line_total', %s),
				(%d, '_line_subtotal_tax', %s),
				(%d, '_line_tax', %s)",
			// The substitutions
			$item_id, $order_item['qty'],
			$item_id, $order_item['tax_class'],
			$item_id, $order_item['product_id'],
			$item_id, $order_item['variation_id'],
			$item_id, $order_item['recurring_line_subtotal'],
			$item_id, $order_item['recurring_line_total'],
			$item_id, $order_item['recurring_line_subtotal_tax'],
			$item_id, $order_item['recurring_line_tax']
		) );

		// Save tax data array added in WC 2.2 (so it won't exist for all orders/subscriptions)
		self::add_line_tax_data( $item_id, $order_item_id, $order_item );

		if ( isset( $order_item['subscription_trial_length'] ) && $order_item['subscription_trial_length'] > 0 ) {
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
			foreach ( $order_item['item_meta'][ $meta_key ] as $meta_value ) {
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

			foreach ( $tax_data_keys as $tax_data_key ) {
				foreach ( $line_tax_data[ $tax_data_key ] as $tax_index => $tax_value ) {
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
			if ( $line_total > 0 && $recurring_line_total > 0 ) {

				// Make sure we account for any sign-up fees by determining what proportion of the initial amount the recurring total represents
				$recurring_ratio = $recurring_line_total / $line_total;

				$recurring_tax_data = array();
				$tax_data_keys      = array( 'total', 'subtotal' );

				foreach ( $tax_data_keys as $tax_data_key ) {
					foreach ( $line_tax_data[ $tax_data_key ] as $tax_index => $tax_value ) {

						// Use total tax amount for both total and subtotal because we don't want any initial discounts to be applied to recurring amounts
						$total_tax_amount = $line_tax_data['total'][ $tax_index ];

						$recurring_tax_data[ $tax_data_key ][ $tax_index ] = wc_format_decimal( $recurring_ratio * $total_tax_amount );
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
	 * Move download permissions from original order to the new subscription created for the order.
	 *
	 * @param WC_Subscription $subscription A subscription object
	 * @param int $subscription_item_id ID of the product line item on the subscription
	 * @param WC_Order $original_order The original order that was created to purchase the subscription
	 * @since 2.0
	 */
	private static function migrate_download_permissions( $subscription, $subscription_item_id, $order ) {
		global $wpdb;

		$product_id = wcs_get_canonical_product_id( wcs_get_order_item( $subscription_item_id, $subscription ) );

		$rows_affected = $wpdb->update(
			$wpdb->prefix . 'woocommerce_downloadable_product_permissions',
			array(
				'order_id'  => $subscription->id,
				'order_key' => $subscription->order_key,
			),
			array(
				'order_id'   => $order->id,
				'order_key'  => $order->order_key,
				'product_id' => $product_id,
				'user_id'    => absint( $subscription->get_user_id() ),
			),
			array( '%d', '%s' ),
			array( '%d', '%s', '%d', '%d' )
		);

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: migrated %d download permissions for product %d', $subscription->id, $rows_affected, $product_id ) );
	}

	/**
	 * Migrate the trial expiration, next payment and expiration/end dates to a new subscription.
	 *
	 * @since 2.0
	 */
	private static function migrate_dates( $new_subscription, $old_subscription ) {
		global $wpdb;

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
				'old_scheduled_hook'   => 'scheduled_subscription_end_of_prepaid_term',
			),
		);

		$old_hook_args = array(
			'user_id'          => $old_subscription['user_id'],
			'subscription_key' => $old_subscription['subscription_key'],
		);

		foreach ( $date_keys as $new_key => $old_keys ) {

			// First check if there is a date stored on the subscription, and if so, use that
			if ( ! empty( $old_keys['old_subscription_key'] ) && ( isset( $old_subscription[ $old_keys['old_subscription_key'] ] ) && 0 !== $old_subscription[ $old_keys['old_subscription_key'] ] ) ) {

				$dates_to_update[ $new_key ] = $old_subscription[ $old_keys['old_subscription_key'] ];

			} elseif ( ! empty( $old_keys['old_scheduled_hook'] ) ) {

				// Now check if there is a scheduled date, this is for next payment and end of prepaid term dates
				$next_scheduled = wc_next_scheduled_action( $old_keys['old_scheduled_hook'], $old_hook_args );

				if ( $next_scheduled > 0 ) {

					if ( 'end_of_prepaid_term' == $new_key ) {
						wc_schedule_single_action( $next_scheduled, 'woocommerce_scheduled_subscription_end_of_prepaid_term', array( 'subscription_id' => $new_subscription->id ) );
					} else {
						$dates_to_update[ $new_key ] = date( 'Y-m-d H:i:s', $next_scheduled );
					}
				}
			}
		}

		// Trash all the hooks in one go to save write requests
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'trash' ), array( 'post_type' => ActionScheduler_wpPostStore::POST_TYPE, 'post_content' => json_encode( $old_hook_args ) ), array( '%s', '%s' ) );

		$dates_to_update['start'] = $new_subscription->post->post_date_gmt;

		// v2.0 enforces new rules for dates when they are being set, so we need to massage the old data to conform to these new rules
		foreach ( $dates_to_update as $date_type => $date ) {

			if ( 0 == $date ) {
				continue;
			}

			switch ( $date_type ) {
				case 'end' :
					if ( array_key_exists( 'next_payment', $dates_to_update ) && $date <= $dates_to_update['next_payment'] ) {
						$dates_to_update[ $date_type ] = $date;
					}
				case 'next_payment' :
					if ( array_key_exists( 'trial_end', $dates_to_update ) && $date < $dates_to_update['trial_end'] ) {
						$dates_to_update[ $date_type ] = $date;
					}
				case 'trial_end' :
					if ( array_key_exists( 'start', $dates_to_update ) && $date <= $dates_to_update['start'] ) {
						$dates_to_update[ $date_type ] = $date;
					}
			}
		}

		try {

			if ( ! empty( $dates_to_update ) ) {
				$new_subscription->update_dates( $dates_to_update );
			}

			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: updated dates = %s', $new_subscription->id, str_replace( array( '{', '}', '"' ), '', json_encode( $dates_to_update ) ) ) );

		} catch ( Exception $e ) {
			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: unable to update dates, exception "%s"', $new_subscription->id, $e->getMessage() ) );
		}
	}

	/**
	 * Copy an assortment of meta data from the original order's post meta table to the new subscription's post meta table.
	 *
	 * @param int $subscription_id The ID of a 'shop_subscription' post type
	 * @param WC_Order $order The original order used to purchase a subscription
	 * @return null
	 * @since 2.0
	 */
	private static function migrate_post_meta( $subscription_id, $order ) {
		global $wpdb;

		// Form: new meta key => old meta key
		$post_meta_with_new_key = array(
			// Order totals
			'_order_total'                  => '_order_recurring_total',
			'_order_tax'                    => '_order_recurring_tax_total',
			'_order_shipping'               => '_order_recurring_shipping_total',
			'_order_shipping_tax'           => '_order_recurring_shipping_tax_total',
			'_cart_discount'                => '_order_recurring_discount_cart',
			'_cart_discount_tax'            => '_order_recurring_discount_cart_tax',
			'_order_discount'               => '_order_recurring_discount_total', // deprecated since WC 2.3

			// Misc meta data
			'_payment_method'               => '_recurring_payment_method',
			'_payment_method_title'         => '_recurring_payment_method_title',
			'_suspension_count'             => '_subscription_suspension_count',
			'_contains_synced_subscription' => '_order_contains_synced_subscription',
		);

		$order_meta = get_post_meta( $order->id );

		foreach ( $post_meta_with_new_key as $subscription_meta_key => $order_meta_key ) {

			$order_meta_value = get_post_meta( $order->id, $order_meta_key, true );

			if ( isset( $order_meta[ $order_meta_key ] ) && '' !== $order_meta[ $order_meta_key ] ) {
				update_post_meta( $subscription_id, $subscription_meta_key, $order_meta_value );
			}
		}

		// Don't copy any of the data we've already copied or known data which isn't relevant to a subscription
		$meta_keys_to_ignore = array_merge( array_values( $post_meta_with_new_key ), array_keys( $post_meta_with_new_key ), array(
			'_completed_date',
			'_customer_ip_address',
			'_customer_user_agent',
			'_customer_user',
			'_order_currency',
			'_order_key',
			'_paid_date',
			'_recorded_sales',
			'_transaction_id',
			'_transaction_id_original',
			'_switched_subscription_first_payment_timestamp',
			'_switched_subscription_new_order',
			'_switched_subscription_key',
			'_old_recurring_payment_method',
			'_old_recurring_payment_method_title',
			'_wc_points_earned',
			'_wcs_requires_manual_renewal',
		) );

		// Also allow extensions to unset or modify data that will be copied
		$order_meta = apply_filters( 'wcs_upgrade_subscription_meta_to_copy', $order_meta, $subscription_id, $order );

		// Prepare the meta data for a bulk insert
		$query_meta_values  = array();
		$query_placeholders = array();

		foreach ( $order_meta as $meta_key => $meta_value ) {
			if ( ! in_array( $meta_key, $meta_keys_to_ignore ) ) {
				$query_meta_values = array_merge( $query_meta_values, array(
					$subscription_id,
					$meta_key,
					$meta_value[0],
				) );
				$query_placeholders[] = '(%d, %s, %s)';
			}
		}

		// Do a single bulk insert instead of using update_post_meta() to massively reduce query time
		if ( ! empty( $query_meta_values ) ) {
			$rows_affected = $wpdb->query( $wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
				 VALUES " . implode( ', ', $query_placeholders ),
				$query_meta_values
			) );

			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: %d rows of post meta added', $subscription_id, $rows_affected ) );
		}

		// Now that we've copied over the old data, deprecate it
		$rows_affected = self::deprecate_post_meta( $order->id );

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: %d rows of post meta deprecated', $subscription_id, $rows_affected ) );
	}

	/**
	 * Deprecate post meta data stored on the original order that used to make up the subscription by prefixing it with with '_wcs_migrated'
	 *
	 * @param int $subscription_id The ID of a 'shop_subscription' post type
	 * @param WC_Order $order The original order used to purchase a subscription
	 * @return null
	 * @since 2.0
	 */
	private static function deprecate_post_meta( $order_id ) {
		global $wpdb;

		$post_meta_to_deprecate = array(
			// Order totals
			'_order_recurring_total',
			'_order_recurring_tax_total',
			'_order_recurring_shipping_total',
			'_order_recurring_shipping_tax_total',
			'_order_recurring_discount_cart',
			'_order_recurring_discount_cart_tax',
			'_order_recurring_discount_total',
			'_recurring_payment_method',
			'_recurring_payment_method_title',
			'_old_paypal_subscriber_id',
			'_old_payment_method',
			'_paypal_ipn_tracking_ids',
			'_paypal_transaction_ids',
			'_paypal_first_ipn_ignored_for_pdt',
			'_order_contains_synced_subscription',
			'_subscription_suspension_count',
		);

		$post_meta_to_deprecate = implode( "','", esc_sql( $post_meta_to_deprecate ) );

		$rows_affected = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET `meta_key` = concat( '_wcs_migrated', `meta_key` )
			WHERE `post_id` = %d AND `meta_key` IN ('{$post_meta_to_deprecate}')",
			$order_id
		) );

		return $rows_affected;
	}

	/**
	 * Migrate order notes relating to subscription events to the new subscription as these are now logged on the subscription
	 * not the order.
	 *
	 * @param int $subscription_id The ID of a 'shop_subscription' post type
	 * @param WC_Order $order The original order used to purchase a subscription
	 * @return null
	 * @since 2.0
	 */
	private static function migrate_order_notes( $subscription_id, $order_id ) {
		global $wpdb;

		$rows_affected = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->comments} SET `comment_post_ID` = %d
			WHERE `comment_post_id` = %d
			AND (
				`comment_content` LIKE '%%subscription%%'
				OR `comment_content` LIKE '%%Recurring%%'
				OR `comment_content` LIKE '%%Renewal%%'
				OR `comment_content` LIKE '%%Simplify payment error%%'
			)",
			$subscription_id, $order_id
		) );

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: migrated %d order notes', $subscription_id, $rows_affected ) );
	}

	/**
	 * Migrate recurring_tax, recurring_shipping and recurring_coupon line items to be plain tax, shipping and coupon line
	 * items on a subscription.
	 *
	 * @param int $subscription_id The ID of a 'shop_subscription' post type
	 * @param WC_Order $order The original order used to purchase a subscription
	 * @return null
	 * @since 2.0
	 */
	private static function migrate_order_items( $subscription_id, $order_id ) {
		global $wpdb;

		foreach ( array( 'tax', 'shipping', 'coupon' ) as $line_item_type ) {
			$rows_affected = $wpdb->update(
				$wpdb->prefix . 'woocommerce_order_items',
				array(
					'order_item_type' => $line_item_type,
					'order_id'        => $subscription_id,
				),
				array(
					'order_item_type' => 'recurring_' . $line_item_type,
					'order_id'        => $order_id,
				),
				array( '%s', '%d' ),
				array( '%s', '%d' )
			);

			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: migrated %d %s item/s', $subscription_id, $rows_affected, $line_item_type ) );
		}
	}

	/**
	 * The 'post_parent' column is no longer used to relate a renewal order with a subscription/order, instead, we use a
	 * '_subscription_renewal' post meta value, so the 'post_parent' of all renewal orders needs to be changed from the original
	 * order's ID, to 0, and then the new subscription's ID should be set as the '_subscription_renewal' post meta value on
	 * the renewal order.
	 *
	 * @param int $subscription_id The ID of a 'shop_subscription' post type
	 * @param int $order_id The ID of a 'shop_order' which created this susbcription
	 * @return null
	 * @since 2.0
	 */
	private static function migrate_renewal_orders( $subscription_id, $order_id ) {
		global $wpdb;

		// Get the renewal order IDs
		$renewal_order_ids = get_posts( array(
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'post_type'      => 'shop_order',
			'post_parent'    => $order_id,
			'fields'         => 'ids',
		) );

		// Set the post meta
		foreach ( $renewal_order_ids as $renewal_order_id ) {
			update_post_meta( $renewal_order_id, '_subscription_renewal', $subscription_id );
		}

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: migrated data for renewal orders %s', $subscription_id, implode( ', ', $renewal_order_ids ) ) );

		$rows_affected = $wpdb->update(
			$wpdb->posts,
			array(
				'post_parent' => 0,
			),
			array(
				'post_parent' => $order_id,
				'post_type'   => 'shop_order',
			),
			array( '%d' ),
			array( '%d', '%s' )
		);

		WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: %d rows of renewal order post_parent values changed', $subscription_id, count( $renewal_order_ids ) ) );
	}

	/**
	 * The '_original_order' post meta value is no longer used to relate a resubscribe order with a subscription/order, instead, we use
	 * a '_subscription_resubscribe' post meta value, so the '_original_order' of all resubscribe orders needs to be changed from the
	 * original order's ID, to 0, and then the new subscription's ID should be set as the '_subscription_resubscribe' post meta value
	 * on the resubscribe order.
	 *
	 * @param int $subscription_id The ID of a 'shop_subscription' post type
	 * @param int $resubscribe_order_id The ID of a 'shop_order' which created this susbcription
	 * @return null
	 * @since 2.0
	 */
	private static function migrate_resubscribe_orders( $new_subscription_id, $resubscribe_order_id ) {
		global $wpdb;

		// Set the post meta on the new subscription and old order
		foreach ( get_post_meta( $resubscribe_order_id, '_original_order', false ) as $original_order_id ) {

			// Because self::get_subscriptions() orders by order ID, it's safe to use wcs_get_subscriptions_for_order() here because the subscription in the new format will have been created for the original order (because its ID will be < the resubscribe order's ID)
			foreach ( wcs_get_subscriptions_for_order( $original_order_id ) as $old_subscription ) {
				update_post_meta( $resubscribe_order_id, '_subscription_resubscribe', $old_subscription->id, true );
				update_post_meta( $new_subscription_id, '_subscription_resubscribe', $old_subscription->id, true );
			}

			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET `meta_key` = concat( '_wcs_migrated', `meta_key` )
				WHERE `post_id` = %d AND `meta_key` = '_original_order'",
				$resubscribe_order_id
			) );

			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: migrated data for resubscribe order %d', $new_subscription_id, $original_order_id ) );
		}
	}

	/**
	 * The '_switched_subscription_key' and '_switched_subscription_new_order' post meta values are no longer used to relate orders
	 * and switched subscriptions, instead, we need to set a '_subscription_switch' value on the switch order and depreacted the old
	 * meta keys by prefixing them with '_wcs_migrated'.
	 *
	 * Subscriptions also sets a '_switched_subscription_item_id' value on the new line item of for the switched item and a item meta
	 * value of '_switched_subscription_new_item_id' on the old line item on the subscription, but the old switching process didn't
	 * change order items, it just created a new order with the new item, so we won't bother setting this as it is purely for record
	 * keeping.
	 *
	 * @param WC_Subscription $new_subscription A subscription object
	 * @param WC_Order $switch_order The original order used to purchase the subscription
	 * @param int $subscription_item_id The order item ID of the item added to the subscription by self::add_product()
	 * @return null
	 * @since 2.0
	 */
	private static function migrate_switch_meta( $new_subscription, $switch_order, $subscription_item_id ) {
		global $wpdb;

		// If the order doesn't contain a switch, we don't need to do anything
		if ( '' == get_post_meta( $switch_order->id, '_switched_subscription_key', true ) ) {
			return;
		}

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET `meta_key` = concat( '_wcs_migrated', `meta_key` )
			WHERE `post_id` = %d AND `meta_key` IN ('_switched_subscription_first_payment_timestamp','_switched_subscription_key')",
			$switch_order->id
		) );

		// Select the orders which had the items which were switched by this order
		$previous_order_id = get_posts( array(
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key'   => '_switched_subscription_new_order',
					'value' => $switch_order->id,
				),
			),
		) );

		if ( ! empty( $previous_order_id ) ) {

			$previous_order_id = $previous_order_id[0];

			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET `meta_key` = concat( '_wcs_migrated', `meta_key` )
				WHERE `post_id` = %d AND `meta_key` = '_switched_subscription_new_order'",
				$previous_order_id
			) );

			// Because self::get_subscriptions() orders by order ID, it's safe to use wcs_get_subscriptions_for_order() here because the subscription in the new format will have been created for the original order (because its ID will be < the switch order's ID)
			$old_subscriptions = wcs_get_subscriptions_for_order( $previous_order_id );
			$old_subscription  = array_shift( $old_subscriptions ); // there can be only one

			// Link the old subscription's ID to the switch order using the new switch meta key
			update_post_meta( $switch_order->id, '_subscription_switch', $old_subscription->id );

			// Now store the new/old item IDs for record keeping
			foreach ( $old_subscription->get_items() as $item_id => $item ) {
				wc_add_order_item_meta( $item_id, '_switched_subscription_new_item_id', $subscription_item_id, true );
				wc_add_order_item_meta( $subscription_item_id, '_switched_subscription_item_id', $item_id, true );
			}

			WCS_Upgrade_Logger::add( sprintf( 'For subscription %d: migrated switch data for subscription %d purchased in order %d', $new_subscription->id, $old_subscription->id, $previous_order_id ) );
		}
	}

	private static function integrity_check( $subscription ) {

		// if ( ! array_key_exists( 'order_id', $subscription ) || ! is_numeric( $subscription['order_id'] ) ) {
		// 	throw new InvalidArgumentException( __( 'Invalid data. The subscription did not have an order ID associated with it.', 'woocommerce-subscriptions' ), 422 );
		// }

		// $meta = get_post_meta( $subscription['order_id'] );
		// $productmeta = wc_get_order_item_meta( $id, null, false );

		$repairs_needed = array();

		// paid date?
		//
		foreach ( array(
			'order_id',
			'product_id',
			'variation_id',
			'status',
			'period',
			'interval',
			'length',
			'start_date',
			'trial_expiry_date',
			'expiry_date',
			'end_date',
			// 'failed_payments',
			// 'completed_payments',
			// 'suspension_count',
			// 'recurring_amount',
			// 'sign_up_fee',
			'recurring_line_total',
			'recurring_line_tax',
			'recurring_line_subtotal',
			'recurring_line_subtotal_tax' ) as $meta ) {
			if ( ! array_key_exists( $meta, $subscription ) || empty( $subscription[ $meta ] ) ) {
				$repairs_needed[] = $meta;
			}
		}

		return $repairs_needed;
	}


	public static function maybe_repair_subscription( $subscription, $item_id ) {
		global $wpdb;

		$item_meta = get_metadata( 'order_item', $item_id );

		$subscription = WC_Repair_2_0::repair_period( $subscription, $item_id, $item_meta );

		// foreach (self::integrity_check( $subscription ) as $function ) {
		// 	$subscription = call_user_func( 'WC_Repair_2_0::repair_' . $function, $subscription, $item_id, $item_meta );
		// }


		#17 - healthy
		// #20 - missing order_id
		#23 - missing product_id
		// #26 - missing variation_id
		#29 - missing subscription_status
		#32 - missing subscription_period
		#35 - missing subscription_interval
		#38 - missing subscription_length
		#41 - missing start_date
		#44 - missing subscription trial_expiry_date
		#47 - missing subscription_expiry date
		#50 - missing subscription_end_date
		#53 - missing subscription_failed_payments
		#56 - missing subscription_completed_payments
		#59 - missing subscription_suspension_count
		#62 - missing subscription_last_payment_date
		#65 - missing subscription_recurring_amount
		#68 - missing subscription_signup_fee
		#71 - missing recurring_line_total
		#74 - missing recurring_line_tax
		#77 - missing recurring_line_subtotal
		#80 - missing recurring_line_subtotal_tax
	}
}


class WC_Repair_2_0 {

	public static function repair_order_id( $subscription ) {
		// 'order_id': a subscription can exist without an original order in v2.0, so technically the order ID is no longer required. However, if some or all order item meta data that constitutes a subscription exists without a corresponding parent order, we can deem the issue to be that the subscription meta data was not deleted, not that the subscription should exist. Meta data could be orphaned in v1.n if the order row in the wp_posts table was deleted directly in the database, or the subscription/order were for a customer that was deleted in WordPress administration interface prior to Subscriptions v1.3.8. In both cases, the subscription, including meta data, should have been permanently deleted. However, deleting data is not a good idea during an upgrade. So I propose instead that we create a subscription without a parent order, but move it to the trash.
		// Additional idea was to check whether the given order_id exists, but since that's another database read, it would slow down a lot of things.
		WCS_Upgrade_Logger::add( sprintf( 'Repairing order_id for subscription %d: Status changed to trash', $subscription['order_id'] ) );

		$subscription['status'] = 'trash';

		return $subscription;
	}

	public static function repair_product_id( $subscription, $item_id, $item_meta ) {
		// '_product_id': the only way to derive a order item's product ID would be to match the order item's name to a product name/title. This is quite hacky, so we may be better copying the empty product ID to the new subscription. A subscription to a deleted produced should be able to exist.
		WCS_Upgrade_Logger::add( sprintf( 'Repairing product_id for subscription %d.', $subscription['order_id'] ) );

		if ( array_key_exists( 'product_id', $item_meta ) && ! empty ( $item_meta['product_id'] ) ) {

			WCS_Upgrade_Logger::add( '-- Copying product_id from item_meta' );
			$subscription['product_id'] = $item_meta['product_id'][0];
		} elseif ( ! array_key_exists( 'product_id', $subscription ) ) {
			WCS_Upgrade_Logger::add( '-- Setting an empty product_id on old subscription, item meta was not helpful.' );
			$subscription['product_id'] = '';
		}

		return $subscription;
	}

	public static function repair_variation_id( $subscription, $item_id, $item_meta ) {
		// '_variation_id': the only way to derive a order item's product ID would be to match the order item's name to a product name/title. This is quite hacky, so we may be better copying the empty product ID to the new subscription. A subscription to a deleted produced should be able to exist.
		WCS_Upgrade_Logger::add( sprintf( 'Repairing variation_id for subscription %d.', $subscription['order_id'] ) );

		if ( array_key_exists( 'variation_id', $item_meta ) && ! empty ( $item_meta['variation_id'] ) ) {
			WCS_Upgrade_Logger::add( '-- Copying variation_id from item_meta' );
			$subscription['variation_id'] = $item_meta['variation_id'][0];
		} elseif ( ! array_key_exists( 'variation_id', $item_meta ) ) {
			WCS_Upgrade_Logger::add( '-- Setting an empty variation_id on old subscription, item meta was not helpful.' );
			$subscription['variation_id'] = '';
		}

		return $subscription;
	}

	public static function repair_status( $subscription, $item_id, $item_meta ) {
		// '_subscription_status': we could default to cancelled (and then potentially trash) if no status exists because the cancelled status is irreversible. But we can also take this a step further. If the subscription has a '_subscription_expiry_date' value and a '_subscription_end_date' value, and they are within a few minutes of each other, we can assume the subscription's status should be expired. If there is a '_subscription_end_date' value that is different to the '_subscription_expiry_date' value (either because the expiration value is 0 or some other date), then we can assume the status should be cancelled). If there is no end date value, we're a bit lost as technically the subscription hasn't ended, but we should make sure it is not active, so cancelled is still the best default.
		WCS_Upgrade_Logger::add( sprintf( 'Repairing status for subscription %d.', $subscription['order_id'] ) );

		// only reset this if we didn't repair the order_id
		if ( ! array_key_exists( 'order_id', $subscription ) || empty( $subscription[ 'order_id' ] ) ) {
			WCS_Upgrade_Logger::add( '-- Previously set it to trash with order_id missing, bailing.' );
			return $subscription;
		}

		// default to cancelled
		WCS_Upgrade_Logger::add( '-- Setting the default to "cancelled".' );
		$subscription['status'] = 'cancelled';

		// if expiry_date and end_date are within 4 minutes (arbitrary), let it be expired
		if ( array_key_exists( 'expiry_date' ) && ! empty( $subscription['expiry_date'] ) && array_key_exists( 'end_date', $subscription ) && ! empty( $subscription['end_date'] ) && ( 4 * MINUTE_IN_SECONDS ) > self::time_diff( $subscription['expiry_date'], $subscription['end_date'] ) ) {
			WCS_Upgrade_Logger::add( '-- There are end dates and expiry dates, they are close to each other, setting status to "expired" and returning.' );

			$subscription['status'] = 'expired';

			return $subscription;
		}

		// we already have cancelled, so if there's no end value, or if end date and expiry date are further apart, then we're still good
		WCS_Upgrade_Logger::add( '-- Returning the default "cancelled" status.' );
		return $subscription;
	}

	public static function repair_period( $subscription, $item_id, $item_meta ) {
		// '_subscription_period': we can attempt to derive this from the time between renewal orders. For example, if there are two renewal orders found 3 months apart, the billing period would be month. If there are not two or more renewal orders (we can't use a single renewal order because that would account for the free trial) and a _product_id value , if the product still exists, we can use the current value set on that product. It won't always be correct, but it's the closest we can get to an accurate estimate.
		WCS_Upgrade_Logger::add( sprintf( 'Repairing period for subscription %d.', $subscription['order_id'] ) );

		// Get info from the product
		if ( array_key_exists( '_subscription_period', $item_meta ) && ! empty( $item_meta['_subscription_period'] ) ) {
			WCS_Upgrade_Logger::add( '-- Getting info from item meta and returning.' );

			$subscription['period'] = $item_meta['_subscription_period'][0];
			return $subscription;
		}

		// let's get the renewal orders
		$renewal_orders = self::get_renewal_orders( $subscription );

		if ( count( $renewal_orders ) < 2 ) {
			// default to month
			$subscription['period'] = 'month';
			// return $subscription;
		}


		// let's get the last 2 renewal orders
		$last_renewal_order = array_shift( $renewal_orders );
		$last_renewal_date = $last_renewal_order->order_date;
		$last_renewal_ts = strtotime( $last_renewal_date );

		$second_renewal_order = array_shift( $renewal_orders );
		$second_renewal_date = $second_renewal_order->order_date;
		$second_renewal_ts = strtotime( $second_renewal_date );

		$interval = 1;

		// if we have an interval, let's pass this along too, because then it's a known variable
		if ( array_key_exists( 'interval', $subscription ) && ! empty( $subscription['interval'] ) ) {
			$interval = $subscription['interval'];
		}

		WCS_Upgrade_Logger::add( '-- Passing info to maybe_get_period...' );
		$period = self::maybe_get_period( $last_renewal_date, $second_renewal_date, $interval );

		// if we have 3 renewal orders, do a double check
		if ( ! empty( $renewal_orders ) ) {
			WCS_Upgrade_Logger::add( '-- We have 3 renewal orders, trying to make sure we are right.' );

			$third_renewal_order = array_shift( $renewal_orders );
			$third_renewal_date = $third_renewal_order->order_date;

			$period2 = self::maybe_get_period( $second_renewal_date, $third_renewal_date, $interval );

			if ( $period == $period2 ) {

				WCS_Upgrade_Logger::add( sprintf( '-- Second check confirmed, we are very confident period is %s', $period ) );
				$subscription['period'] = $period;
				// log add that we're really sure
			}
		}

		$subscription['period'] = $period;

		return $subscription;
	}

	public static function repair_interval( $subscription, $item_id, $item_meta ) {
		// '_subscription_interval': we can attempt to derive this from the time between renewal orders. For example, if there are two renewal orders found 3 months apart, the billing period would be month. If there are not two or more renewal orders (we can't use a single renewal order because that would account for the free trial) and a _product_id value , if the product still exists, we can use the current value set on that product. It won't always be correct, but it's the closest we can get to an accurate estimate.

		// let's see if we can have info from the product
		// Get info from the product
		if ( array_key_exists( '_subscription_interval', $item_meta ) && ! empty( $item_meta['_subscription_interval'] ) ) {
			WCS_Upgrade_Logger::add( '-- Getting info from item meta and returning.' );

			$subscription['interval'] = $item_meta['_subscription_interval'][0];
			return $subscription;
		}

		// by this time we already have a period on our hand
		// let's get the renewal orders
		$renewal_orders = self::get_renewal_orders( $subscription );

		if ( count( $renewal_orders ) < 2 ) {
			// default to month
			$subscription['interval'] = 1;
			// return $subscription;
		}


		// let's get the last 2 renewal orders
		$last_renewal_order = array_shift( $renewal_orders );
		$last_renewal_date = $last_renewal_order->order_date;
		$last_renewal_ts = strtotime( $last_renewal_date );

		$second_renewal_order = array_shift( $renewal_orders );
		$second_renewal_date = $second_renewal_order->order_date;
		$second_renewal_ts = strtotime( $second_renewal_date );

		$subscription['interval'] = wcs_estimate_periods_between( $second_renewal_ts, $last_renewal_ts, $subscription['period'] );

		return $subscription;
	}

	public static function repair_length( $subscription, $item_id, $item_meta ) {
		// '_subscription_length': if there is are '_subscription_expiry_date' and '_subscription_start_date' values, we can use those to determine how many billing periods fall between them, and therefore, the length of the subscription. This data is low value however as it is no longer stored in v2.0 and mainly used to determine the expiration date.

		// Set a default
		$subscription['length'] = 0;

		// Let's see if the item meta has that
		if ( array_key_exists( '_subscription_length', $item_meta ) && ! empty ( $item_meta['_subscription_length'] ) ) {
			WCS_Upgrade_Logger::add( '-- Copying subscription_length from item_meta' );
			$subscription['length'] = $item_meta['_subscription_length'][0];
			return $subscription;
		}

		// If we can calculate it from start date and expiry date
		if ( $subscription['status'] == 'expired' && array_key_exists( 'expiry_date', $susbcription ) && ! empty( $subscription['expiry_date'] ) && array_key_exists( 'start_date', $subscription ) && ! empty( $subscription['start_date'] ) && array_key_exists( 'period', $subscription ) && ! empty( $subscription['period'] ) && array_key_exists( 'interval', $subscription ) && ! empty( $subscription['interval'] ) ) {
			$intervals = wcs_estimate_periods_between( strtotime( $subscription['start_date'] ), strtotime( $subscription['expiry_date'] ), $subscription['period'], 'floor' );

			$intervals = floor( $intervals / $subscription['interval'] );

			$subscription['length'] = $intervals;
		}

		return $subscription;
	}


	public static function repair_start_date( $subscription, $item_id, $item_meta ) {
		global $wpdb;
		// '_subscription_start_date': the original order's '_paid_date' value (stored in post meta) can be used as the subscription's start date. If no '_paid_date' exists, because the order used a payment method that doesn't call $order->payment_complete(), like BACs or Cheque, then we can use the post_date_gmt column in the wp_posts table of the original order.
		$start_date = get_post_meta( $subscription['order_id'], '_paid_date', true );

		if ( empty( $start_date ) ) {
			$start_date = $wpdb->get_var( $wpdb->prepare( "SELECT post_date_gmt FROM {$wpdb->posts} WHERE ID = %d", $subscription['order_id'] ) );
		}

		$subscription['start_date'] = $start_date;
		return $subscription;
	}

	public static function repair_trial_expiry_date( $subscription, $item_id, $item_meta ) {
		// '_subscription_trial_expiry_date': if the subscription has at least one renewal order, we can set the trial expiration date to the date of the first renewal order. However, this is generally safe to default to 0 if it is not set. Especially if the subscription is inactive and/or has 1 or more renewals (because its no longer used and is simply for record keeping).
		$subscription['trial_expiry_date'] = 0;
		return $subscription;
	}

	public static function repair_expiry_date( $subscription, $item_id, $item_meta ) {
		// '_subscription_expiry_date': if the subscription has a '_subscription_length' value, that can be used to calculate the expiration date (from the '_subscription_start_date' or '_subscription_trial_expiry_date' if one is set). If no length is set, but the subscription has an expired status, the '_subscription_end_date' can be used. In most other cases, this is generally safe to default to 0 if the subscription is cancelled because its no longer used and is simply for record keeping.
		$subscription['expiry_date'] = 0;
		return $subscription;
	}

	public static function repair_end_date( $subscription, $item_id, $item_meta ) {
		// '_subscription_end_date': if the subscription has a '_subscription_length' value and status of expired, the length can be used to calculate the end date as it will be the same as the expiration date. If no length is set, or the subscription has a cancelled status, some time within 24 hours after the last renewal order's date can be used to provide a rough estimate.
		if ( array_key_exists( '_subscription_end_date', $item_meta ) ) {
			WCS_Upgrade_Logger::add( '-- Copying end date from item_meta' );
			$subscription['end_date'] = $item_meta['_subscription_end_date'][0];
			return $subscription;
		}

		if ( 'expired' == $subscription['status'] && array_key_exists( 'expiry_date', $subscription ) && ! empty( $subscription['expiry_date'] ) ) {
			$subscription['end_date'] = $subscription['expiry_date'];
			return $subscription;
		}

		if ( 'cancelled' == $subscription['status'] || ! array_key_exists( 'length', $subscription ) || empty( $subscription['length'] ) ) {
			// get renewal orders
			$renewal_orders = self::get_renewal_orders( $subscription );
			$last_order = array_shift( $renewal_orders );

			$subscription['end_date'] = wcs_add_time( 5, 'hours', strtotime( $last_order->order_date ) );
			return $subscription;
		}

		// if everything failed, let's have an empty one
		$subscription['end_date'] = 0;

		return $subscription;
	}

	public static function repair_recurring_line_total( $subscription, $item_id, $item_meta ) {
		// _recurring_line_total': if the subscription has at least one renewal order, this value can be derived from the '_line_total' value of that order. If no renewal orders exist, it can be derived roughly by deducting the '_subscription_sign_up_fee' value from the original order's total if there is no trial expiration date.
		// I'm not using line_total on subscription because it might contain non-sub bits

		if ( array_key_exists( '_line_total', $item_meta ) ) {
			WCS_Upgrade_Logger::add( '-- Copying end date from item_meta' );
			$subscription['recurring_line_total'] = $item_meta['_line_total'][0];
			return $subscription;
		}

		$subscription['recurring_line_total'] = 0;

		return $subscription;
	}

	public static function repair_recurring_line_tax( $subscription, $item_id, $item_meta ) {
		// _recurring_line_total': if the subscription has at least one renewal order, this value can be derived from the '_line_total' value of that order. If no renewal orders exist, it can be derived roughly by deducting the '_subscription_sign_up_fee' value from the original order's total if there is no trial expiration date.

		if ( array_key_exists( '_line_tax', $item_meta ) ) {
			WCS_Upgrade_Logger::add( '-- Copying end date from item_meta' );
			$subscription['recurring_line_tax'] = $item_meta['_line_tax'][0];
			return $subscription;
		}

		$subscription['recurring_line_tax'] = 0;

		return $subscription;
	}

	public static function repair_recurring_line_subtotal( $subscription, $item_id, $item_meta ) {
		// _recurring_line_total': if the subscription has at least one renewal order, this value can be derived from the '_line_total' value of that order. If no renewal orders exist, it can be derived roughly by deducting the '_subscription_sign_up_fee' value from the original order's total if there is no trial expiration date.

		if ( array_key_exists( '_line_subtotal', $item_meta ) ) {
			WCS_Upgrade_Logger::add( '-- Copying end date from item_meta' );
			$subscription['recurring_line_subtotal'] = $item_meta['_line_subtotal'][0];
			return $subscription;
		}

		$subscription['recurring_line_subtotal'] = 0;

		return $subscription;
	}

	public static function repair_recurring_line_subtotal_tax( $subscription, $item_id, $item_meta ) {
		// _recurring_line_total': if the subscription has at least one renewal order, this value can be derived from the '_line_total' value of that order. If no renewal orders exist, it can be derived roughly by deducting the '_subscription_sign_up_fee' value from the original order's total if there is no trial expiration date.
	}

	/**
	 * Utility function to calculate the seconds between two timestamps. Order is not important, it's just the difference.
	 *
	 * @param  string $to   mysql timestamp
	 * @param  string $from mysql timestamp
	 * @return integer       number of seconds between the two
	 */
	private static function time_diff( $to, $from ) {

		$to = strtotime( $to );
		$from = strtotime( $from );

		return abs( $to - $from );
	}


	private static function get_renewal_orders( $subscription ) {

		$related_orders = array();

		$related_post_ids = get_posts( array(
			'posts_per_page' => -1,
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_parent'    => $subscription['order_id']
		) );

		foreach ( $related_post_ids as $post_id ) {
			$related_orders[ $post_id ] = wc_get_order( $post_id );
		}


		return $related_orders;
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
	 * @param  string  $last_date   mysql date string
	 * @param  string  $second_date mysql date string
	 * @param  integer $interval    potential interval
	 * @return string               period string
	 */
	private static function maybe_get_period( $last_date, $second_date, $interval = 1 ) {

		if ( ! is_int( $interval ) ) {
			$interval = 1;
		}

		$last_ts = strtotime( $last_date );
		$second_ts = strtotime( $second_date );

		$earlier_ts = min( $last_ts, $second_ts );
		$days_in_month = date( 't', $earlier_ts );

		$difference = absint( $last_ts - $second_ts );

		$period_in_seconds = round( $difference / $interval );

		$possible_periods = array();

		// check for different time spans
		foreach ( array( 'year' => YEAR_IN_SECONDS, 'month' => $days_in_month * DAY_IN_SECONDS, 'week' => WEEK_IN_SECONDS, 'day' => DAY_IN_SECONDS ) as $time => $seconds ) {
			$possible_periods[$time] = array(
				'intervals' => floor( $period_in_seconds / $seconds ),
				'remainder' => $remainder = $period_in_seconds % $seconds,
				'fraction' => $remainder / $seconds,
				'period' => $time,
				'days_in_month' => $days_in_month,
				'original_interval' => $interval,
			);
		}

		// filter out ones that are less than one period
		$possible_periods = array_filter( $possible_periods, 'self::discard_zero_intervals' );

		// filter out ones that have too high of a deviation
		$possible_periods_no_hd = array_filter( $possible_periods, 'self::discard_high_deviations' );

		if ( count( $possible_periods_no_hd ) == 1 ) {
			WCS_Upgrade_Logger::add( sprintf( '---- There is only one period left with no high deviation, returning with %s.', $possible_periods_no_hd['period'] ) );
			// only one matched, let's return that as our best guess
			return $possible_periods_no_hd['period'];
		} elseif ( count( $possible_periods_no_hd > 1 ) ) {
			WCS_Upgrade_Logger::add( '---- More than 1 periods with high deviation left.' );
			$possible_periods = $possible_periods_no_hd;
		}

		// check for interval equality
		$possible_periods_interval_match = array_filter( $possible_periods, 'self::match_intervals' );

		if ( count( $possible_periods_interval_match ) == 1 ) {
			foreach ( $possible_periods_interval_match as $period_data ) {
				WCS_Upgrade_Logger::add( sprintf( '---- Checking for interval matching, only one found, returning with %s.', $period_data['period'] ) );

				// only one matched the interval as our best guess
				return $period_data['period'];
			}
		} elseif( count( $possible_periods_interval_match ) > 1 ) {
			WCS_Upgrade_Logger::add( '---- More than 1 periods with matching intervals left.' );
			$possible_periods = $possible_periods_interval_match;
		}

		// order by number of intervals and return the lowest

		usort( $possible_periods, 'self::sort_by_intervals' );

		$least_interval = array_shift( $possible_periods );

		WCS_Upgrade_Logger::add( sprintf( '---- Sorting by intervals and returning the first member of the array: %s', $least_interval['period'] ) );

		return $least_interval['period'];
	}

	/**
	 * Used in an array_filter, removes elements where intervals are less than 0
	 * @param  array $array elements of an array
	 * @return bool        true if at least 1 interval
	 */
	private static function discard_zero_intervals( $array ) {
		return $array['intervals'] > 0;
	}

	/**
	 * Used in an array_filter, discards high deviation elements.
	 * - for days it's 1/24th
	 * - for week it's 1/7th
	 * - for year it's 1/300th
	 * - for month it's 1/($days_in_months-2)
	 * @param  array $array elements of the filtered array
	 * @return bool        true if value is within deviation limit
	 */
	private static function discard_high_deviations( $array ) {
		switch ( $array['period'] ) {
			case 'year':
				return $array['fraction'] < ( 1/300 );
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
	private static function match_intervals( $array ) {
		return $array['intervals'] == $array['original_interval'];
	}

	/**
	 * Used in a usort, responsible for making sure the array is sorted in ascending order by intervals
	 * @param  array $a one element of the sorted array
	 * @param  array $b different element of the sorted array
	 * @return int    0 if equal, -1 if $b is larger, 1 if $a is larger
	 */
	private static function sort_by_intervals( $a, $b ) {
		if ( $a['intervals'] == $b['intervals'] ) {
			return 0;
		}
		// return ( $a['intervals'] > $b['intervals'] ) ? -1 : 1;
		return ( $a['intervals'] < $b['intervals'] ) ? -1 : 1;
	}
}