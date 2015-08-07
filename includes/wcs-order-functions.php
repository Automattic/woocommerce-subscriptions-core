<?php
/**
 * WooCommerce Subscriptions Order Functions
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
 * A wrapper for @see wcs_get_subscriptions() which accepts simply an order ID
 *
 * @param int|WC_Order $order_id The post_id of a shop_order post or an instance of a WC_Order object
 * @param array $args A set of name value pairs to filter the returned value.
 *		'subscriptions_per_page' The number of subscriptions to return. Default set to -1 to return all.
 *		'offset' An optional number of subscription to displace or pass over. Default 0.
 *		'orderby' The field which the subscriptions should be ordered by. Can be 'start_date', 'trial_end_date', 'end_date', 'status' or 'order_id'. Defaults to 'start_date'.
 *		'order' The order of the values returned. Can be 'ASC' or 'DESC'. Defaults to 'DESC'
 *		'customer_id' The user ID of a customer on the site.
 *		'product_id' The post ID of a WC_Product_Subscription, WC_Product_Variable_Subscription or WC_Product_Subscription_Variation object
 *		'order_id' The post ID of a shop_order post/WC_Order object which was used to create the subscription
 *		'subscription_status' Any valid subscription status. Can be 'any', 'active', 'cancelled', 'suspended', 'expired', 'pending' or 'trash'. Defaults to 'any'.
 * @return array Subscription details in post_id => WC_Subscription form.
 * @since  2.0
 */
function wcs_get_subscriptions_for_order( $order_id, $args = array() ) {

	if ( is_object( $order_id ) ) {
		$order_id = $order_id->id;
	}

	$args = wp_parse_args( $args, array(
			'order_id'               => $order_id,
			'subscriptions_per_page' => -1,
		)
	);

	return wcs_get_subscriptions( $args );
}

/**
 * Copy the billing, shipping or all addresses from one order to another (including custom order types, like the
 * WC_Subscription order type).
 *
 * @param WC_Order $to_order The WC_Order object to copy the address to.
 * @param WC_Order $from_order The WC_Order object to copy the address from.
 * @param string $address_type The address type to copy, can be 'shipping', 'billing' or 'all'
 * @return WC_Order The WC_Order object with the new address set.
 * @since  2.0
 */
function wcs_copy_order_address( $from_order, $to_order, $address_type = 'all' ) {

	if ( in_array( $address_type, array( 'shipping', 'all' ) ) ) {
		$to_order->set_address( array(
			'first_name' => $from_order->shipping_first_name,
			'last_name'  => $from_order->shipping_last_name,
			'company'    => $from_order->shipping_company,
			'address_1'  => $from_order->shipping_address_1,
			'address_2'  => $from_order->shipping_address_2,
			'city'       => $from_order->shipping_city,
			'state'      => $from_order->shipping_state,
			'postcode'   => $from_order->shipping_postcode,
			'country'    => $from_order->shipping_country,
		), 'shipping' );
	}

	if ( in_array( $address_type, array( 'billing', 'all' ) ) ) {
		$to_order->set_address( array(
			'first_name' => $from_order->billing_first_name,
			'last_name'  => $from_order->billing_last_name,
			'company'    => $from_order->billing_company,
			'address_1'  => $from_order->billing_address_1,
			'address_2'  => $from_order->billing_address_2,
			'city'       => $from_order->billing_city,
			'state'      => $from_order->billing_state,
			'postcode'   => $from_order->billing_postcode,
			'country'    => $from_order->billing_country,
			'email'      => $from_order->billing_email,
			'phone'      => $from_order->billing_phone,
		), 'billing' );
	}

	return apply_filters( 'woocommerce_subscriptions_copy_order_address', $to_order, $from_order, $address_type );
}

/**
 * Utility function to copy order meta between two orders. Originally intended to copy meta between
 * first order and subscription object, then between subscription and renewal orders.
 *
 * The hooks used here in those cases are
 * - wcs_subscription_meta_query
 * - wcs_subscription_meta
 * - wcs_renewal_order_meta_query
 * - wcs_renewal_order_meta
 *
 * @param  WC_Order $from_order Order to copy meta from
 * @param  WC_Order $to_order   Order to copy meta to
 * @param  string $type type of copy
 */
function wcs_copy_order_meta( $from_order, $to_order, $type = 'subscription' ) {
	global $wpdb;

	if ( ! is_a( $from_order, 'WC_Abstract_Order' ) || ! is_a( $to_order, 'WC_Abstract_Order' ) ) {
		throw new InvalidArgumentException( __( 'Invalid data. Orders expected aren\'t orders.', 'woocommerce-subscriptions' ) );
	}

	if ( ! is_string( $type ) ) {
		throw new InvalidArgumentException( __( 'Invalid data. Type of copy is not a string.', 'woocommerce-subscriptions' ) );
	}

	if ( ! in_array( $type, array( 'subscription', 'renewal_order' ) ) ) {
		$type = 'copy_order';
	}

	$meta_query = $wpdb->prepare(
		"SELECT `meta_key`, `meta_value`
		 FROM {$wpdb->postmeta}
		 WHERE `post_id` = %d
		 AND `meta_key` NOT LIKE '_schedule_%%'
		 AND `meta_key` NOT IN (
			 '_paid_date',
			 '_completed_date',
			 '_order_key',
			 '_edit_lock',
			 '_wc_points_earned',
			 '_transaction_id',
			 '_billing_interval',
			 '_billing_period',
			 '_subscription_resubscribe',
			 '_subscription_renewal',
			 '_subscription_switch',
			 '_payment_method',
			 '_payment_method_title'
		 )",
		$from_order->id
	);

	// Allow extensions to add/remove order meta
	$meta_query = apply_filters( 'wcs_' . $type . '_meta_query', $meta_query, $to_order, $from_order );
	$meta       = $wpdb->get_results( $meta_query, 'ARRAY_A' );
	$meta       = apply_filters( 'wcs_' . $type . '_meta', $meta, $to_order, $from_order );

	foreach ( $meta as $meta_item ) {
		update_post_meta( $to_order->id, $meta_item['meta_key'], maybe_unserialize( $meta_item['meta_value'] ) );
	}
}

/**
 * Wrapper function to get the address from an order / subscription in array format
 * @param  WC_Order $order The order / subscription we want to get the order from
 * @param  string $address_type shipping|billing. Default is shipping
 * @return array
 */
function wcs_get_order_address( $order, $address_type = 'shipping' ) {
	if ( ! is_object( $order ) ) {
		return array();
	}

	if ( 'billing' == $address_type ) {
		$address = array(
			'first_name' => $order->billing_first_name,
			'last_name'  => $order->billing_last_name,
			'company'    => $order->billing_company,
			'address_1'  => $order->billing_address_1,
			'address_2'  => $order->billing_address_2,
			'city'       => $order->billing_city,
			'state'      => $order->billing_state,
			'postcode'   => $order->billing_postcode,
			'country'    => $order->billing_country,
			'email'      => $order->billing_email,
			'phone'      => $order->billing_phone,
		);
	} else {
		$address = array(
			'first_name' => $order->shipping_first_name,
			'last_name'  => $order->shipping_last_name,
			'company'    => $order->shipping_company,
			'address_1'  => $order->shipping_address_1,
			'address_2'  => $order->shipping_address_2,
			'city'       => $order->shipping_city,
			'state'      => $order->shipping_state,
			'postcode'   => $order->shipping_postcode,
			'country'    => $order->shipping_country,
		);
	}

	return apply_filters( 'wcs_get_order_address', $address, $address_type, $order );
}

/**
 * Checks an order to see if it contains a subscription.
 *
 * @param mixed $order A WC_Order object or the ID of the order which the subscription was purchased in.
 * @return bool True if the order contains a subscription, otherwise false.
 * @since 2.0
 */
function wcs_order_contains_subscription( $order ) {

	if ( ! is_object( $order ) ) {
		$order = new WC_Order( $order );
	}

	if ( count( wcs_get_subscriptions_for_order( $order->id ) ) > 0 ) {
		$contains_subscription = true;
	} else {
		$contains_subscription = false;
	}

	return $contains_subscription;
}


/**
 * Save the download permissions on the individual subscriptions as well as the order. Hooked into
 * 'woocommerce_grant_product_download_permissions', which is strictly after the order received all the info
 * it needed, so we don't need to play with priorities.
 *
 * @param integer $order_id the ID of the order. At this point it is guaranteed that it has files in it and that it hasn't been granted permissions before
 */
function wcs_save_downloadable_product_permissions( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( wcs_order_contains_subscription( $order ) ) {
		$subscriptions = wcs_get_subscriptions_for_order( $order );
	} elseif ( wcs_order_contains_renewal( $order ) ) {
		$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
	} else {
		return;
	}

	foreach ( $subscriptions as $subscription ) {
		if ( sizeof( $subscription->get_items() ) > 0 ) {
			foreach ( $subscription->get_items() as $item ) {
				$_product = $subscription->get_product_from_item( $item );

				if ( $_product && $_product->exists() && $_product->is_downloadable() ) {
					$downloads = $_product->get_files();

					foreach ( array_keys( $downloads ) as $download_id ) {
						$product_id = wcs_get_canonical_product_id( $item );

						wc_downloadable_file_permission( $download_id, $product_id, $subscription, $item['qty'] );
						wcs_revoke_downloadable_file_permission( $product_id, $order_id, $order->user_id );
					}
				}
			}
		}
		update_post_meta( $subscription->id, '_download_permissions_granted', 1 );
	}
}
add_action( 'woocommerce_grant_product_download_permissions', 'wcs_save_downloadable_product_permissions' );


/**
 * Revokes download permissions from permissions table if a file has permissions on a subscription. If a product has
 * multiple files, all permissions will be revoked from the original order.
 *
 * @param int $product_id the ID for the product (the downloadable file)
 * @param int $order_id the ID for the original order
 * @param int $user_id the user we're removing the permissions from
 * @return boolean true on success, false on error
 */
function wcs_revoke_downloadable_file_permission( $product_id, $order_id, $user_id ) {
	global $wpdb;

	$table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';

	$where = array(
		'product_id' => $product_id,
		'order_id' => $order_id,
		'user_id' => $user_id,
	);

	$format = array( '%d', '%d', '%d' );

	return $wpdb->delete( $table, $where, $format );
}


/**
 * WooCommerce's function receives the original order ID, the item and the list of files. This does not work for
 * download permissions stored on the subscription rather than the original order as the URL would have the wrong order
 * key. This function takes the same parameters, but queries the database again for download ids belonging to all the
 * subscriptions that were in the original order. Then for all subscriptions, it checks all items, and if the item
 * passed in here is in that subscription, it creates the correct download link to be passed to the email.
 *
 * @param array $files List of files already included in the list
 * @param array $item An item (you get it by doing $order->get_items())
 * @param WC_Order $order The original order
 * @return array List of files with correct download urls
 */
function wcs_subscription_email_download_links( $files, $item, $order ) {
	global $wpdb;

	if ( wcs_order_contains_subscription( $order ) ) {
		$subscriptions = wcs_get_subscriptions_for_order( $order );
	} elseif ( wcs_order_contains_renewal( $order ) ) {
		$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
	} else {
		return $files;
	}

	// This is needed because downloads are keyed to the subscriptions, not the original orders
	$subs_keys = wp_list_pluck( $subscriptions, 'order_key' );

	$product_id = wcs_get_canonical_product_id( $item );

	$download_ids = $wpdb->get_col( $wpdb->prepare("
		SELECT download_id
		FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions
		WHERE user_email = %s
		AND order_key IN ('%s')
		AND product_id = %s
		ORDER BY permission_id
	", $order->billing_email, implode( "', '", esc_sql( $subs_keys ) ), $product_id ) );

	foreach ( $subscriptions as $subscription ) {
		$sub_products = $subscription->get_items();

		foreach ( $sub_products as $sub_product ) {
			$sub_product_id = wcs_get_canonical_product_id( $sub_product );

			if ( $sub_product_id === $product_id ) {
				$product = wc_get_product( $product_id );

				foreach ( $download_ids as $download_id ) {

					if ( $product->has_file( $download_id ) ) {
						$files[ $download_id ]                 = $product->get_file( $download_id );
						$files[ $download_id ]['download_url'] = $subscription->get_download_url( $product_id, $download_id );
					}
				}
			}
		}
	}

	return $files;
}
add_filter( 'woocommerce_get_item_downloads', 'wcs_subscription_email_download_links', 10, 3 );


/**
 * Repairs a glitch in WordPress's save function. You cannot save a null value on update, see
 * https://github.com/woothemes/woocommerce/issues/7861 for more info on this.
 *
 * @param integer $post_id The ID of the subscription
 */
function wcs_repair_permission_data( $post_id ) {
	if ( absint( $post_id ) !== $post_id ) {
		return;
	}

	if ( 'shop_subscription' !== get_post_type( $post_id ) ) {
		return;
	}

	global $wpdb;

	$wpdb->query( $wpdb->prepare( "
		UPDATE {$wpdb->prefix}woocommerce_downloadable_product_permissions
		SET access_expires = null
		WHERE order_id = %d
		AND access_expires = %s
	", $post_id, '0000-00-00 00:00:00' ) );
}
add_action( 'woocommerce_process_shop_order_meta', 'wcs_repair_permission_data', 60, 1 );


/**
 * A wrapper for getting a specific item from a subscription.
 *
 * WooCommerce has a wc_add_order_item() function, wc_update_order_item() function and wc_delete_order_item() function,
 * but no `wc_get_order_item()` function, so we need to add our own (for now).
 *
 * @param int $item_id The ID of an order item
 * @return WC_Subscription Subscription details in post_id => WC_Subscription form.
 * @since 2.0
 */
function wcs_get_order_item( $item_id, $order ) {

	$item = array();

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		throw new InvalidArgumentException( __( 'Invalid data. No valid subscription / order was passed in.', 'woocommerce-subscriptions' ), 422 );
	}

	if ( ! absint( $item_id ) ) {
		throw new InvalidArgumentException( __( 'Invalid data. No valid item id was passed in.', 'woocommerce-subscriptions' ), 422 );
	}

	foreach ( $order->get_items() as $line_item_id => $line_item ) {
		if ( $item_id == $line_item_id ) {
			$item = $line_item;
			break;
		}
	}

	return $item;
}

/**
 * Get an instance of WC_Order_Item_Meta for an order item
 *
 * @param array
 * @return WC_Order_Item_Meta
 * @since 2.0
 */
function wcs_get_order_item_meta( $item, $product = null ) {

	if ( WC_Subscriptions::is_woocommerce_pre( '2.4' ) ) {
		$item_meta = new WC_Order_Item_Meta( $item['item_meta'], $product );
	} else {
		$item_meta = new WC_Order_Item_Meta( $item, $product );
	}

	return $item_meta;
}

/**
 * Create a string representing an order item's name and optionally include attributes.
 *
 * @param array $order_item An order item.
 * @since 2.0
 */
function wcs_get_order_item_name( $order_item, $include = array() ) {

	$include = wp_parse_args( $include, array(
		'attributes' => false,
	) );

	$order_item_name = $order_item['name'];

	if ( $include['attributes'] && ! empty( $order_item['item_meta'] ) ) {

		$attribute_strings = array();

		foreach ( $order_item['item_meta'] as $meta_key => $meta_value ) {

			$meta_value = $meta_value[0];

			// Skip hidden core fields
			if ( in_array( $meta_key, apply_filters( 'woocommerce_hidden_order_itemmeta', array(
				'_qty',
				'_tax_class',
				'_product_id',
				'_variation_id',
				'_line_subtotal',
				'_line_subtotal_tax',
				'_line_total',
				'_line_tax',
				'_switched_subscription_item_id',
			) ) ) ) {
				continue;
			}

			// Skip serialised meta
			if ( is_serialized( $meta_value ) ) {
				continue;
			}

			// Get attribute data
			if ( taxonomy_exists( wc_sanitize_taxonomy_name( $meta_key ) ) ) {
				$term       = get_term_by( 'slug', $meta_value, wc_sanitize_taxonomy_name( $meta_key ) );
				$meta_key   = wc_attribute_label( wc_sanitize_taxonomy_name( $meta_key ) );
				$meta_value = isset( $term->name ) ? $term->name : $meta_value;
			} else {
				$meta_key   = apply_filters( 'woocommerce_attribute_label', wc_attribute_label( $meta_key ), $meta_key );
			}

			$attribute_strings[] = sprintf( '%s: %s', wp_kses_post( rawurldecode( $meta_key ) ), wp_kses_post( rawurldecode( $meta_value ) ) );
		}

		$order_item_name = sprintf( '%s (%s)', $order_item_name, implode( ', ', $attribute_strings ) );
	}

	return apply_filters( 'wcs_get_order_item_name', $order_item_name, $order_item, $include );
}

/**
 * Get the full name for a order/subscription line item, including the items non hidden meta
 * (i.e. attributes), as a flat string.
 *
 * @param array
 * @return string
 */
function wcs_get_line_item_name( $line_item ) {

	$item_meta_strings = array();

	foreach ( $line_item['item_meta'] as $meta_key => $meta_value ) {

		$meta_value = $meta_value[0];

		// Skip hidden core fields
		if ( in_array( $meta_key, apply_filters( 'woocommerce_hidden_order_itemmeta', array(
			'_qty',
			'_tax_class',
			'_product_id',
			'_variation_id',
			'_line_subtotal',
			'_line_subtotal_tax',
			'_line_total',
			'_line_tax',
			'_line_tax_data',
		) ) ) ) {
			continue;
		}

		// Skip serialised meta
		if ( is_serialized( $meta_value ) ) {
			continue;
		}

		// Get attribute data
		if ( taxonomy_exists( wc_sanitize_taxonomy_name( $meta_key ) ) ) {
			$term       = get_term_by( 'slug', $meta_value, wc_sanitize_taxonomy_name( $meta_key ) );
			$meta_key   = wc_attribute_label( wc_sanitize_taxonomy_name( $meta_key ) );
			$meta_value = isset( $term->name ) ? $term->name : $meta_value;
		} else {
			$meta_key   = apply_filters( 'woocommerce_attribute_label', wc_attribute_label( $meta_key ), $meta_key );
		}

		$item_meta_strings[] = sprintf( '%s: %s', rawurldecode( $meta_key ), rawurldecode( $meta_value ) );
	}

	if ( ! empty( $item_meta_strings ) ) {
		$line_item_name = sprintf( '%s (%s)', $line_item['name'], implode( ', ', $item_meta_strings ) );
	} else {
		$line_item_name = $line_item['name'];
	}

	return apply_filters( 'wcs_line_item_name', $line_item_name, $line_item );
}
