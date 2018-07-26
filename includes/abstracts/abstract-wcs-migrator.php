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

abstract class WCS_Migrator {
	/**
	 * @var mixed
	 */
	private $source_store;

	/**
	 * @var mixed
	 */
	private $destination_store;

	public function __construct( $source_store, $destination_store ) {
		$this->source_store      = $source_store;
		$this->destination_store = $destination_store;
	}

	/**
	 * Should this entry be migrated.
	 *
	 * @param int $entry_id
	 *
	 * @return bool
	 */
	abstract public function should_migrate_entry( $entry_id );

	/**
	 * Gets the item from the source store.
	 *
	 * @param int $entry_id
	 *
	 * @return mixed
	 */
	abstract public function get_source_store_entry( $entry_id );

	/**
	 * save the item to the destination store.
	 *
	 * @param int $entry_id
	 *
	 * @return mixed
	 */
	abstract public function save_destination_store_entry( $entry_id );

	/**
	 * deletes the item from the source store.
	 *
	 * @param int $entry_id
	 *
	 * @return mixed
	 */
	abstract public function delete_source_store_entry( $entry_id );

	/**
	 * Migrates our retry.
	 *
	 * @param int $entry_id
	 *
	 * @return mixed
	 */
	public function migrate_entry( $entry_id ) {
		$source_store_item = $this->get_source_store_entry( $entry_id );
		if ( $source_store_item ) {
			$destination_store_item = $this->save_destination_store_entry( $entry_id );
			$this->delete_source_store_entry( $entry_id );

			return $destination_store_item;
		}

		return false;
	}
}
