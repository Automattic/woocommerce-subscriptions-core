<?php
/**
 * WooCommerce Subscriptions Download Handling
 *
 * Functions for download related things within the Subscription Extension
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
 * When adding new downloadable content to a subscription product, we don't
 * want to automatically add the new downloadable files to the subscription or initial and 
 * renewal orders.
 *
 * @param bool $grant_access
 * @param string $download_id
 * @param int $product_id
 * @param WC_Order $order
 * @return bool
 * @since 2.0
 */
function wcs_revoke_immediate_access_to_new_files( $grant_access, $download_id, $product_id, $order ) {

	if ( wcs_is_subscription( $order->id ) || wcs_order_contains_subscription( $order ) || wcs_order_contains_renewal( $order ) || wcs_order_contains_switch( $order ) ) {
		$grant_access = false;
	}
	return $grant_access;
}
add_filter( 'woocommerce_process_product_file_download_paths_grant_access_to_new_file', 'wcs_revoke_immediate_access_to_new_files', 10, 4 );
