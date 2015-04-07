<?php
/**
 * Subscriptions Remove Item
 *
 *
 * @author   Prospress
 * @since    2.0
 */
class WCS_Remove_Item {

	/**
	 * Initialise class hooks & filters when the file is loaded
	 *
	 * @since 2.0
	 */
	public static function init() {

		// Check if a user is requesting to remove or re-add an item to their subscription
		add_action( 'init', __CLASS__ . '::maybe_remove_or_add_item_to_subscription', 100 );
	}

}
WCS_Remove_Item::init();
