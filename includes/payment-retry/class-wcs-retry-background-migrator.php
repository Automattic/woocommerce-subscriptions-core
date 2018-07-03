<?php
/**
 * Retry Background Updater.
 *
 * @author      Prospress
 * @category    Class
 * @package     WooCommerce Subscriptions
 * @subpackage  WCS_Retry_Backgound_Migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WCS_Retry_Background_Migrator.
 *
 * Updates our retries on background.
 */
class WCS_Retry_Background_Migrator extends WCS_Background_Updater {
	/**
	 * Where we're saving/migrating our data.
	 *
	 * @var WCS_Retry_Store
	 */
	private $database_store;

	/**
	 * Where the data comes from.
	 *
	 * @var WCS_Retry_Store
	 */
	private $post_store;

	/**
	 * Our migration class.
	 *
	 * @var WCS_Retry_Migrator
	 */
	private $migrator;

	/**
	 * @var string The hook used to schedule retries migration.
	 */
	protected $schedule_hook = 'wcs_retries_migration_hook';


	/**
	 * @var int Amount of second to give each run.
	 */
	protected $time_limit = 30;

	/**
	 * construct.
	 */
	public function __construct() {
		$this->database_store = WCS_Retry_Stores::get_database_store();
		$this->post_store     = WCS_Retry_Stores::get_post_store();
		$this->migrator       = WCS_Retry_Migrator::instance();
	}

	/**
	 * Get the items to be updated, if any.
	 *
	 * @return array An array of items to update, or empty array if there are no items to update.
	 */
	protected function get_items_to_update() {
		return $this->post_store->get_retries( array() );
	}

	/**
	 * Run the update for a single item.
	 *
	 * @param WCS_Retry $retry The item to update.
	 *
	 * @return int
	 */
	protected function update_item( $retry ) {
		return $this->migrator->migrate_retry( $retry->get_id() );
	}
}
