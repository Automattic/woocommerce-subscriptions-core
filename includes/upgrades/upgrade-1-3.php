<?php
/**
 * Update Subscriptions to 1.3.0
 *
 * @author		Prospress
 * @category	Admin
 * @package		WooCommerce Subscriptions/Admin/Upgrades
 * @version		1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $wpdb;

// Change transient timeout entries to be a vanilla option
$wpdb->query( " UPDATE $wpdb->options
				SET option_name = TRIM(LEADING '_transient_timeout_' FROM option_name)
				WHERE option_name LIKE '_transient_timeout_wcs_blocker_%'" );

// Change transient keys from the < 1.1.5 format to new format
$wpdb->query( " UPDATE $wpdb->options
				SET option_name = CONCAT('wcs_blocker_', TRIM(LEADING '_transient_timeout_block_scheduled_subscription_payments_' FROM option_name))
				WHERE option_name LIKE '_transient_timeout_block_scheduled_subscription_payments_%'" );

// Delete old transient values
$wpdb->query( " DELETE FROM $wpdb->options
				WHERE option_name LIKE '_transient_wcs_blocker_%'
				OR option_name LIKE '_transient_block_scheduled_subscription_payments_%'" );
