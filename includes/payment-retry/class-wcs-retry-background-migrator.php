<?php
/**
 * Retry Background Updater.
 *
 * @author      Prospress
 * @category    Class
 * @package     WooCommerce Subscriptions
 * @subpackage  WCS_Retry_Backgound_Migrator
 * @since       2.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WCS_Retry_Background_Migrator.
 *
 * Updates our retries on background.
 * @since 2.4
 */
class WCS_Retry_Background_Migrator extends WCS_Background_Upgrader {
	/**
	 * Where we're saving/migrating our data.
	 *
	 * @var WCS_Retry_Store
	 */
	private $destination_store;

	/**
	 * Where the data comes from.
	 *
	 * @var WCS_Retry_Store
	 */
	private $source_store;

	/**
	 * Our migration class.
	 *
	 * @var WCS_Retry_Migrator
	 */
	private $migrator;

	/**
	 * construct.
	 */
	public function __construct() {
		$this->scheduled_hook = 'wcs_retries_migration_hook';
		$this->time_limit     = 30;

		$this->destination_store = WCS_Retry_Stores::get_database_store();
		$this->source_store      = WCS_Retry_Stores::get_post_store();

		$migrator_class = apply_filters( 'wcs_retry_retry_migrator_class', 'WCS_Retry_Migrator' );
		$this->migrator = new $migrator_class( $this->source_store, $this->destination_store, new WC_Logger() );
	}

	/**
	 * Get the items to be updated, if any.
	 *
	 * @return array An array of items to update, or empty array if there are no items to update.
	 * @since 2.4
	 */
	protected function get_items_to_update() {
		return $this->source_store->get_retries();
	}

	/**
	 * Run the update for a single item.
	 *
	 * @param WCS_Retry $retry The item to update.
	 *
	 * @return int
	 * @since 2.4
	 */
	protected function update_item( $retry ) {
		return $this->migrator->migrate_entry( $retry->get_id() );
	}

	/**
	 * Unscheduled the instance's hook in Action Scheduler
	 */
	protected function unschedule_background_updates() {
		parent::unschedule_background_updates();

		$this->migrator->set_needs_migration();
	}
}
