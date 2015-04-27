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
 * @param int|WC_Order $order_id The post_id of a shop_order post or an intsance of a WC_Order object
 * @return array Subscription details in post_id => WC_Subscription form.
 * @since  2.0
 */
function wcs_get_subscriptions_for_order( $order_id ) {

	if ( is_object( $order_id ) ) {
		$order_id = $order_id->id;
	}

	return wcs_get_subscriptions( array( 'order_id' => $order_id ) );
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
			'country'    => $from_order->shipping_country
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
		), 'billing' );
	}

	return apply_filters( 'woocommerce_subscriptions_copy_order_address', $to_order, $from_order, $address_type );
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
 * @param  integer 			$order_id 		the ID of the order. At this point it is guaranteed that it has files in it
 *                                     		and that it hasn't been granted permissions before
 */
function wcs_save_downloadable_product_permissions( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! wcs_order_contains_subscription( $order ) ) {
		return;
	}

	$subscriptions = wcs_get_subscriptions_for_order( $order );

	foreach ($subscriptions as $subscription) {
		if ( sizeof( $subscription->get_items() ) > 0 ) {
			foreach ( $subscription->get_items() as $item ) {
				$_product = $subscription->get_product_from_item( $item );

				if ( $_product && $_product->exists() && $_product->is_downloadable() ) {
					$downloads = $_product->get_files();

					foreach ( array_keys( $downloads ) as $download_id ) {
						wc_downloadable_file_permission( $download_id, wcs_get_canonical_product_id( $item ), $subscription, $item['qty'] );
						wcs_revoke_downloadable_file_permission( $item_id, $order_id, $order->user_id );
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
 * @param  integer 			$product_id 	the ID for the product (the downloadable file)
 * @param  integer 			$order_id		the ID for the original order
 * @param  integer 			$user_id		the user we're removing the permissions from
 * @return boolean 							true on success, false on error
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
 * passed in here is in that subscription, it creates the correct download link to be passsed to the email.
 *
 * @param  array 			$files 			List of files already included in the list
 * @param  array 			$item 			An item (you get it by doing $order->get_items())
 * @param  WC_Order			$order 			The original order
 * @return array 							List of files with correct download urls
 */
function wcs_subscription_email_download_links( $files, $item, $order ) {
	if ( ! wcs_order_contains_subscription( $order ) ) {
		return $files;
	}

	global $wpdb;

	$subscriptions = wcs_get_subscriptions_for_order( $order );

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
	", $order->billing_email, implode( "', '", $subs_keys ), $product_id ) );

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
 * @param  integer 			$post_id 		The ID of the subscription
 */
function wcs_repair_permission_data( $post_id ) {
	if ( $post_id !== absint( $post_id ) ) {
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
