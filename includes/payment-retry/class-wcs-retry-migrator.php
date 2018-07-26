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

class WCS_Retry_Migrator extends WCS_Migrator {
	/**
	 * @var WCS_Retry_Store
	 */
	private $source_store;

	/**
	 * @var WCS_Retry_Store
	 */
	private $destination_store;

	/**
	 * Should this retry be migrated.
	 *
	 * @param int $retry_id
	 *
	 * @return bool
	 */
	public function should_migrate_entry( $retry_id ) {
		return ! $this->destination_store->get_retry( $retry_id );
	}

	/**
	 * Gets the item from the source store.
	 *
	 * @param int $entry_id
	 *
	 * @return WCS_Retry
	 */
	public function get_source_store_entry( $entry_id ) {
		return $this->source_store->get_retry( $entry_id );
	}

	/**
	 * save the item to the destination store.
	 *
	 * @param int $entry_id
	 *
	 * @return mixed
	 */
	public function save_destination_store_entry( $entry_id ) {
		$source_retry = $this->get_source_store_entry( $entry_id );

		return $this->destination_store->save( $source_retry );
	}

	/**
	 * deletes the item from the source store.
	 *
	 * @param int $entry_id
	 *
	 * @return mixed
	 */
	public function delete_source_store_entry( $entry_id ) {
		return wp_delete_post( $entry_id );
	}
}

