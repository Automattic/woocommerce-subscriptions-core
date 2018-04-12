<?php
/**
 * Subscriptions Debug Tools
 *
 * Add tools for debugging and managing Subscriptions to the
 * WooCommerce > System Status > Tools administration screen.
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin
 * @version  2.3
 * @since    2.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * WCS_Debug_Tool_Background_Updater Class
 *
 * Provide APIs for a debug tool to update data in the background using Action Scheduler.
 */
abstract class WCS_Debug_Tool_Background_Updater extends WCS_Debug_Tool {

	/**
	 * @var int Amount of second to give each batch.
	 */
	protected $time_limit = 60;

	/**
	 * @var string The hook used to schedule background updates.
	 */
	protected $scheduled_hook = null;

	/**
	 * Attach callbacks to hooks and validate required properties are assigned values.
	 */
	public function init() {

		parent::init();

		// Make sure child classes have defined a scheduled hook, otherwise we can't do background updates.
		if ( is_null( $this->scheduled_hook ) ) {
			throw new RuntimeException( __CLASS__ . ' must assign a hook to $this->scheduled_hook' );
		}

		// Allow for each class's time limit to be customised by 3rd party code
		$this->time_limit = apply_filters( 'wcs_debug_tools_time_limit', $this->time_limit, $this );

		// Action scheduled in Action Scheduler for updating data in the background
		add_action( $this->scheduled_hook, array( $this, 'run_update' ) );
	}

	/**
	 * Get the items to be updated, if any.
	 *
	 * @return array An array of items to update, or empty array if there are no items to update.
	 */
	abstract protected function get_items_to_update();

	/**
	 * Run the update for a single item.
	 *
	 * @param mixed $item The item to update.
	 */
	abstract protected function update_item( $item );

	/**
	 * Update a set of items in the background.
	 *
	 * This method will loop over until there are no more items to update, or the process has been running for the
	 * time limit set on the class @see $this->time_limit, which is 60 seconds by default (wall clock time, not
	 * execution time).
	 *
	 * The $scheduler_hook is rescheduled before updating any items something goes wrong when processing a batch - it's
	 * scheduled for $this->time_limit in future, so there's little chance of duplicate processes running at the same
	 * time with WP Cron, but importantly, there is some chance so it should not be used for critical data, like
	 * payments. Instead, it is intended for use for things like cache updates. It's also a good idea to use an atomic
	 * update methods to avoid updating something that has already been updated in a separate request.
	 *
	 * Importantly, the overlap between the next scheduled update and the current batch is also useful for running
	 * Action Scheduler via WP CLI, because it will allow for continuous execution of updates (i.e. updating a new
	 * batch as soon as one batch has execeeded the time limit rather than having to run Action Scheduler via WP CLI
	 * again later).
	 */
	public function run_update() {

		$start_time = gmdate( 'U' );

		$this->schedule_background_update();

		do {

			$items = $this->get_items_to_update();

			foreach ( $items as $item ) {

				$this->update_item( $item );

				$time_elapsed = ( gmdate( 'U' ) - $start_time );

				if ( $time_elapsed >= $this->time_limit ) {
					break 2;
				}
			}
		} while ( ! empty( $items ) );

		// If we stopped processing the batch because we ran out of items to process, not because we ran out of time, we don't need to run any other batches
		if ( empty( $items ) ) {
			$this->unschedule_background_updates();
		}
	}

	/**
	 * Schedule the instance's hook to run in $this->time_limit seconds, if it's not already scheduled.
	 */
	protected function schedule_background_update() {
		if ( false === wc_next_scheduled_action( $this->scheduled_hook ) ) {
			wc_schedule_single_action( gmdate( 'U' ) + $this->time_limit, $this->scheduled_hook );
		}
	}

	/**
	 * Unschedule the instance's hook in Action Scheduler
	 */
	protected function unschedule_background_updates() {
		wc_unschedule_action( $this->scheduled_hook );
	}
}
