<?php
/**
 * Retry migration class.
 *
 * @author      Prospress
 * @category    Class
 * @package     WooCommerce Subscriptions
 * @subpackage  WCS_Retry_Migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Retry_Migrator {

	/**
	 * Should this retry be migrated.
	 *
	 * @param int $retry_id
	 *
	 * @return bool
	 */
	public static function should_migrate_retry( $retry_id ) {
		if ( ! ! WCS_Retry_Stores::get_post_store()->get_retry( $retry_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Migrates our retry.
	 *
	 * @param int $retry_id
	 *
	 * @return bool|int
	 */
	public static function migrate_retry( $retry_id ) {
		$source_store_retry = WCS_Retry_Stores::get_post_store()->get_retry( $retry_id );
		if ( $source_store_retry ) {
			$destination_store_retry = WCS_Retry_Stores::get_database_store()->save( new WCS_Retry( array(
				'order_id' => $source_store_retry->get_order_id(),
				'status'   => $source_store_retry->get_status(),
				'date_gmt' => $source_store_retry->get_date_gmt(),
				'rule_raw' => $source_store_retry->get_rule()->get_raw_data(),
			) ) );

			wp_delete_post( $retry_id );

			return $destination_store_retry;
		}

		return false;
	}
}
